import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Images, Timer, Tv, UserPlus, Users } from 'lucide-react';
import { useApiData } from '../../hooks/useApiData';
import { EmptyState, Skeleton } from '../../components/ui.jsx';
import { fmtDateTime, fmtDuration } from '../../utils/format';

function useCountdown(expiresAt, serverTime) {
  const [left, setLeft] = useState(null);
  useEffect(() => {
    if (!expiresAt) { setLeft(null); return undefined; }
    const offset = serverTime ? Date.parse(serverTime) - Date.now() : 0;
    const tick = () => setLeft(Math.max(0, Math.round((Date.parse(expiresAt) - (Date.now() + offset)) / 1000)));
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [expiresAt, serverTime]);
  return left;
}

const Stat = ({ icon: Icon, label, value, sub }) => (
  <div className="card p-5">
    <div className="flex items-center justify-between"><span className="text-sm font-medium text-neutral-500">{label}</span><span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-soft text-brand"><Icon className="h-[18px] w-[18px]" /></span></div>
    <div className="mt-3 text-3xl font-semibold tracking-tight">{value}</div>
    {sub && <div className="mt-1 text-xs text-neutral-500">{sub}</div>}
  </div>
);

export default function Dashboard() {
  const { data: s, loading, error } = useApiData('/admin/stats', { poll: 5000 });
  const left = useCountdown(s?.display.expires_at, s?.server_time);

  if (loading && !s) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{Array.from({ length: 6 }, (_, i) => <Skeleton key={i} className="h-28" />)}</div></div>;
  }
  if (error && !s) return <div className="card"><EmptyState title="Couldn’t load the overview">{error.message}</EmptyState></div>;

  const d = s.display;
  const tvs = s.tvs;
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Overview</h1>
        <p className="text-sm text-neutral-500">Live figures from MongoDB. Refreshes every few seconds.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Stat icon={Users} label="Total registrations" value={s.total_visitors} />
        <Stat icon={UserPlus} label="Registered today" value={s.today_visitors} />
        <Stat icon={Images} label="Enabled gallery images" value={s.enabled_gallery_images} sub={`${s.total_gallery_images} uploaded`} />
        <Stat icon={Tv} label="TVs online" value={tvs.online} sub={`${tvs.recently_disconnected} recently disconnected · ${tvs.total} seen`} />
        <Stat icon={Timer} label="Visitor display time" value={fmtDuration(s.display_duration_seconds)} />
        <div className="card p-5">
          <span className="text-sm font-medium text-neutral-500">Registration</span>
          <div className="mt-3"><span className={`badge ${s.registration_enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-neutral-200 text-neutral-700'}`}>{s.registration_enabled ? 'Open' : 'Closed'}</span></div>
          <Link to="/admin/settings" className="mt-3 inline-block text-xs font-semibold text-brand hover:underline">Change in settings</Link>
        </div>
      </div>

      <div className="card p-5">
        <h2 className="text-base font-semibold">On the TVs right now</h2>
        {d.mode === 'visitor' && d.visitor ? (
          <div className="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center">
            <img src={d.visitor.photo_url} alt={d.visitor.name} className="h-40 w-40 rounded-2xl bg-neutral-100 object-cover" />
            <div>
              <div className="text-2xl font-semibold">{d.visitor.name}</div>
              <div className="mt-1 text-sm text-neutral-500">Registered {fmtDateTime(d.visitor.registered_at)}</div>
              <div className="mt-3 text-sm">Expires at <strong>{fmtDateTime(d.expires_at)}</strong></div>
              {left !== null && <div className="mt-1 text-sm text-neutral-600">Time left: <strong className="tabular-nums">{Math.floor(left / 60)}:{String(left % 60).padStart(2, '0')}</strong></div>}
            </div>
          </div>
        ) : (
          <p className="mt-3 text-sm text-neutral-600">
            {d.has_image ? <>Gallery image <strong>{d.image_title || 'Untitled'}</strong>{d.expires_at ? <> · next image at {fmtDateTime(d.expires_at)}</> : ' (only one enabled image, so it stays on screen)'}.</> : 'Branded welcome screen — upload and enable gallery images to show them here.'}
          </p>
        )}
        <p className="mt-4 text-xs text-neutral-400">Database: {s.database} · display version {d.version}</p>
      </div>
    </div>
  );
}
