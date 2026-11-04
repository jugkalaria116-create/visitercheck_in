import { useEffect, useMemo, useRef, useState } from 'react';
import { api } from '../services/api';

const TOKEN_KEY = 'vms_display_token';

function clientToken() {
  const make = () => (crypto.randomUUID?.().replace(/-/g, '') ?? Array.from({ length: 4 }, () => Math.random().toString(36).slice(2)).join('')).slice(0, 48).padEnd(24, 'x');
  try {
    let t = localStorage.getItem(TOKEN_KEY);
    if (!t || !/^[A-Za-z0-9_-]{20,128}$/.test(t)) { t = make(); localStorage.setItem(TOKEN_KEY, t); }
    return t;
  } catch { return make(); }
}

/**
 * Polls the authoritative display state (default every 2 s) and sends a heartbeat every 10 s.
 * - Updates React state only when the server `version` changes.
 * - Backs off exponentially while the network is down and keeps the last known display.
 * - While offline, never shows a visitor past their expiry (estimated with the server/client clock offset).
 */
export function useDisplay(name) {
  const [state, setState] = useState(null);
  const [offline, setOffline] = useState(false);
  const [, setTick] = useState(0);
  const offset = useRef(0);
  const version = useRef(null);
  const latest = useRef(null);
  const token = useRef(clientToken());

  useEffect(() => {
    let stopped = false;
    let timer;
    let fails = 0;
    let interval = 2000;
    const ctrl = new AbortController();

    const poll = async () => {
      try {
        const { data } = await api.get('/display/current', { timeout: 6000, signal: ctrl.signal });
        offset.current = Date.parse(data.server_time) - Date.now();
        interval = Math.max(1, data.polling_interval_seconds || 2) * 1000;
        fails = 0;
        setOffline(false);
        if (version.current !== data.version) {
          version.current = data.version;
          latest.current = data;
          setState(data);
        }
      } catch {
        if (stopped) return;
        fails += 1;
        setOffline(true);
      }
      if (!stopped) {
        const delay = fails ? Math.min(30000, interval * 2 ** Math.min(fails, 5)) + Math.random() * 500 : interval;
        timer = setTimeout(poll, delay);
      }
    };
    poll();
    return () => { stopped = true; clearTimeout(timer); ctrl.abort(); };
  }, []);

  useEffect(() => {
    const beat = () => {
      api.post('/display/heartbeat', {
        client_token: token.current, name: name || undefined,
        version: latest.current?.version ?? 0, mode: latest.current?.mode ?? '',
      }, { timeout: 5000 }).catch(() => {});
    };
    beat();
    const id = setInterval(beat, 10000);
    return () => clearInterval(id);
  }, [name]);

  useEffect(() => {
    if (!offline) return undefined;
    const id = setInterval(() => setTick((t) => t + 1), 1000);
    return () => clearInterval(id);
  }, [offline]);

  const masked = offline && state?.mode === 'visitor' && !!state.expires_at
    && Date.now() + offset.current >= Date.parse(state.expires_at);
  const view = useMemo(
    () => (masked ? { ...state, mode: 'branded', visitor_name: null, image_url: null, version: `${state.version}-offline-expired` } : state),
    [masked, state],
  );
  return { view, offline };
}
