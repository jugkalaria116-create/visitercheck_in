import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../services/api';

/** Fetch + optional polling. `deps` re-fetch when they change. Keeps previous data while refreshing. */
export function useApiData(path, { query, poll = 0 } = {}) {
  const [data, setData] = useState(null);
  const [meta, setMeta] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);
  const key = JSON.stringify([path, query]);
  const alive = useRef(true);

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const res = await api.get(path, { query });
      if (!alive.current) return;
      setData(res.data);
      setMeta(res.meta ?? null);
      setError(null);
    } catch (e) {
      if (alive.current) setError(e);
    } finally {
      if (alive.current && !silent) setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  useEffect(() => {
    alive.current = true;
    load();
    let id;
    if (poll) id = setInterval(() => load(true), poll);
    return () => { alive.current = false; clearInterval(id); };
  }, [load, poll]);

  return { data, meta, error, loading, reload: () => load(true), setData };
}
