import { useEffect, useState } from 'react';
import { Eye, Search, Trash2, Users } from 'lucide-react';
import { useApiData } from '../../hooks/useApiData';
import { api, ApiError } from '../../services/api';
import { useToast } from '../../context/ToastContext';
import { ConfirmDialog, EmptyState, Modal, Pagination, Skeleton } from '../../components/ui.jsx';
import { fmtDateTime } from '../../utils/format';

const StatusBadge = ({ v }) => (
  <span className={`badge ${v.display_status === 'on_display' ? 'bg-violet-100 text-violet-800' : 'bg-neutral-100 text-neutral-700'}`}>
    {v.display_status === 'on_display' ? 'On display' : 'Shown'}
  </span>
);

export default function Visitors() {
  const toast = useToast();
  const [q, setQ] = useState('');
  const [dq, setDq] = useState('');
  const [status, setStatus] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);
  const [detail, setDetail] = useState(null);
  const [del, setDel] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => { const t = setTimeout(() => { setDq(q); setPage(1); }, 300); return () => clearTimeout(t); }, [q]);
  const { data, meta, loading, error, reload } = useApiData('/admin/visitors', { query: { q: dq, status, from, to, page, per_page: 10 } });

  const remove = async () => {
    setBusy(true);
    try {
      await api.del(`/admin/visitors/${del.id}`);
      toast.success('Visitor and photo deleted.');
      setDel(null);
      if (data.length === 1 && page > 1) setPage(page - 1); else reload();
    } catch (e) { toast.error(e instanceof ApiError ? e.message : 'Delete failed.'); } finally { setBusy(false); }
  };

  const filtered = q || status || from || to;
  return (
    <div className="space-y-5">
      <div><h1 className="text-2xl font-semibold">Visitors</h1><p className="text-sm text-neutral-500">Browsing history never puts a visitor back on the TVs.</p></div>

      <div className="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_160px_160px_160px]">
        <div className="relative"><Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" /><input className="input pl-9" placeholder="Search name or phone" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search visitors" /></div>
        <select className="input" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} aria-label="Status"><option value="">All statuses</option><option value="active">On display</option><option value="completed">Shown</option></select>
        <input type="date" className="input" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1); }} aria-label="From date" />
        <input type="date" className="input" value={to} onChange={(e) => { setTo(e.target.value); setPage(1); }} aria-label="To date" />
      </div>

      <div className="card overflow-hidden">
        {loading && !data ? (
          <div className="space-y-3 p-4">{Array.from({ length: 5 }, (_, i) => <Skeleton key={i} className="h-14" />)}</div>
        ) : error && !data ? (
          <EmptyState title="Couldn’t load visitors">{error.message}</EmptyState>
        ) : data.length === 0 ? (
          <EmptyState icon={Users} title={filtered ? 'No visitors match these filters' : 'No visitors yet'}>{filtered ? 'Clear the search or date range to see everyone.' : 'Registrations appear here as soon as someone scans the QR code.'}</EmptyState>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead className="border-b border-neutral-200 bg-neutral-50 text-xs font-semibold text-neutral-500"><tr><th className="px-4 py-3">Visitor</th><th className="px-4 py-3">Phone</th><th className="px-4 py-3">Registered</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Actions</th></tr></thead>
              <tbody className="divide-y divide-neutral-100">
                {data.map((v) => (
                  <tr key={v.id} className="hover:bg-neutral-50/70">
                    <td className="px-4 py-3"><div className="flex items-center gap-3"><img src={v.photo_url} alt="" loading="lazy" className="h-10 w-10 rounded-full bg-neutral-100 object-cover" /><span className="font-medium">{v.name}</span></div></td>
                    <td className="px-4 py-3 tabular-nums text-neutral-700">{v.phone}</td>
                    <td className="px-4 py-3 text-neutral-600">{fmtDateTime(v.created_at)}</td>
                    <td className="px-4 py-3"><StatusBadge v={v} /></td>
                    <td className="px-4 py-3"><div className="flex justify-end gap-1.5">
                      <button className="btn-ghost !p-2" aria-label={`View ${v.name}`} onClick={() => setDetail(v)}><Eye className="h-4 w-4" /></button>
                      <button className="btn-ghost !p-2 text-red-600" aria-label={`Delete ${v.name}`} onClick={() => setDel(v)}><Trash2 className="h-4 w-4" /></button>
                    </div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={meta} onPage={setPage} />
      </div>

      <Modal open={!!detail} onClose={() => setDetail(null)} title="Visitor details">
        {detail && (
          <div className="space-y-4">
            <img src={detail.photo_url} alt={detail.name} className="max-h-80 w-full rounded-xl bg-neutral-100 object-contain" />
            <dl className="grid grid-cols-[130px_1fr] gap-y-2 text-sm">
              <dt className="text-neutral-500">Name</dt><dd className="font-medium">{detail.name}</dd>
              <dt className="text-neutral-500">Phone</dt><dd className="tabular-nums">{detail.phone}</dd>
              <dt className="text-neutral-500">Registered</dt><dd>{fmtDateTime(detail.created_at)}</dd>
              <dt className="text-neutral-500">Consent given</dt><dd>{fmtDateTime(detail.consent_at)}</dd>
              <dt className="text-neutral-500">Display ended</dt><dd>{fmtDateTime(detail.expires_at)}</dd>
              <dt className="text-neutral-500">Auto-delete on</dt><dd>{detail.retention_expires_at ? fmtDateTime(detail.retention_expires_at) : 'Never (retention off)'}</dd>
              <dt className="text-neutral-500">Status</dt><dd><StatusBadge v={detail} /></dd>
            </dl>
            <div className="flex justify-end"><button className="btn-ghost" onClick={() => setDetail(null)}>Close</button></div>
          </div>
        )}
      </Modal>

      <ConfirmDialog open={!!del} busy={busy} title="Delete this visitor?" confirmLabel="Delete visitor"
        message={del ? `${del.name}’s record and photo will be permanently deleted${del.display_status === 'on_display' ? ' and removed from the TVs now' : ''}.` : ''}
        onConfirm={remove} onCancel={() => setDel(null)} />
    </div>
  );
}
