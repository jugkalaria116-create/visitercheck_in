import { useState } from 'react';
import { Check, Copy, Trash2, Tv } from 'lucide-react';
import { useApiData } from '../../hooks/useApiData';
import { api, ApiError } from '../../services/api';
import { useToast } from '../../context/ToastContext';
import { ConfirmDialog, EmptyState, Skeleton } from '../../components/ui.jsx';
import { fmtAgo, fmtDateTime } from '../../utils/format';

const STATUS = {
  online: ['Online', 'bg-emerald-100 text-emerald-800', 'bg-emerald-500'],
  recently_disconnected: ['Recently disconnected', 'bg-amber-100 text-amber-800', 'bg-amber-500'],
  offline: ['Offline', 'bg-neutral-200 text-neutral-700', 'bg-neutral-400'],
};

export default function Displays() {
  const toast = useToast();
  const { data, loading, error, reload } = useApiData('/admin/displays', { poll: 5000 });
  const [del, setDel] = useState(null);
  const [copied, setCopied] = useState(false);
  const url = `${window.location.origin}/display?name=Lobby`;

  const copy = async () => {
    try { await navigator.clipboard.writeText(url); setCopied(true); setTimeout(() => setCopied(false), 1500); } catch { toast.error('Copy failed — select the address and copy it manually.'); }
  };
  const remove = async () => {
    try { await api.del(`/admin/displays/${del.id}`); toast.success('Display removed from the list.'); setDel(null); reload(); }
    catch (e) { toast.error(e instanceof ApiError ? e.message : 'Remove failed.'); }
  };

  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">TV displays</h1><p className="text-sm text-neutral-500">Status comes from heartbeats (every 10 s), not from who opened the page before.</p></div>

      <div className="card p-5">
        <h2 className="text-sm font-semibold">Connect a TV</h2>
        <p className="mt-1 text-sm text-neutral-600">Open this address in the TV’s browser and press <kbd className="rounded border px-1 text-xs">F</kbd> (or double-click) for full screen. Change <code>name=</code> to label each TV.</p>
        <div className="mt-3 flex items-center gap-2">
          <code className="min-w-0 flex-1 truncate rounded-xl bg-neutral-100 px-3 py-2.5 text-sm">{url}</code>
          <button className="btn-ghost" onClick={copy}>{copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}{copied ? 'Copied' : 'Copy'}</button>
        </div>
      </div>

      <div className="card overflow-hidden">
        {loading && !data ? <div className="space-y-3 p-4">{Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-12" />)}</div>
          : error && !data ? <EmptyState title="Couldn’t load displays">{error.message}</EmptyState>
          : data.length === 0 ? <EmptyState icon={Tv} title="No TVs have connected yet">Open the display address above on a TV or any browser. It appears here within seconds.</EmptyState>
          : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-left text-sm">
                <thead className="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500"><tr><th className="px-4 py-3">Display</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Last seen</th><th className="px-4 py-3">Showing</th><th className="px-4 py-3" /></tr></thead>
                <tbody className="divide-y divide-neutral-100">
                  {data.map((c) => {
                    const [label, cls, dot] = STATUS[c.status];
                    return (
                      <tr key={c.id}>
                        <td className="px-4 py-3 font-medium">{c.display_name}</td>
                        <td className="px-4 py-3"><span className={`badge ${cls}`}><span className={`h-1.5 w-1.5 rounded-full ${dot}`} />{label}</span></td>
                        <td className="px-4 py-3 text-neutral-600" title={fmtDateTime(c.last_seen_at)}>{fmtAgo(c.seconds_since_seen)}</td>
                        <td className="px-4 py-3 text-neutral-600">{c.current_mode ? `${c.current_mode.replace('_', ' ')} · v${c.current_version}` : '—'}</td>
                        <td className="px-4 py-3 text-right"><button className="btn-ghost !p-2 text-red-600" aria-label={`Remove ${c.display_name}`} onClick={() => setDel(c)}><Trash2 className="h-4 w-4" /></button></td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
      </div>
      <ConfirmDialog open={!!del} title="Remove this display?" confirmLabel="Remove" message="It will reappear automatically if that TV is still open and sending heartbeats." onConfirm={remove} onCancel={() => setDel(null)} />
    </div>
  );
}
