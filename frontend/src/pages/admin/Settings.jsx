import { useEffect, useState } from 'react';
import { useApiData } from '../../hooks/useApiData';
import { api, ApiError } from '../../services/api';
import { useToast } from '../../context/ToastContext';
import { Field, Skeleton, Spinner, Switch } from '../../components/ui.jsx';
import { fmtDateTime, fmtDuration } from '../../utils/format';

export default function Settings() {
  const toast = useToast();
  const { data, meta, loading, error, reload } = useApiData('/admin/settings');
  const stats = useApiData('/admin/stats', { poll: 10000 });
  const [f, setF] = useState(null);
  const [applyNow, setApplyNow] = useState(false);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);

  useEffect(() => { if (data) setF({ ...data }); }, [data]);
  if (loading || !f) return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-96" /></div>;
  if (error) return <div className="card p-6 text-sm text-red-700">{error.message}</div>;

  const set = (k) => (e) => setF((x) => ({ ...x, [k]: e.target ? e.target.value : e }));
  const durationChanged = Number(f.display_duration_seconds) !== data.display_duration_seconds;

  const save = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await api.patch('/admin/settings', {
        display_duration_seconds: Number(f.display_duration_seconds),
        gallery_rotation_seconds: Number(f.gallery_rotation_seconds),
        polling_interval_seconds: Number(f.polling_interval_seconds),
        visitor_retention_days: Number(f.visitor_retention_days),
        fallback_mode: f.fallback_mode,
        registration_enabled: f.registration_enabled,
        registration_url: f.registration_url,
        phone_country: f.phone_country,
        apply_to_current: durationChanged && applyNow,
      });
      toast.success('Settings saved.');
      setApplyNow(false);
      reload();
    } catch (err) {
      if (err instanceof ApiError) { setErrors(err.fields || {}); toast.error(err.message); } else toast.error('Could not save settings.');
    } finally { setSaving(false); }
  };

  const s = stats.data;
  const err = (k) => errors[k];
  return (
    <div className="space-y-6">
      <div><h1 className="text-2xl font-semibold">Display settings</h1><p className="text-sm text-neutral-500">Last saved {fmtDateTime(data.updated_at)}</p></div>

      <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
        <form onSubmit={save} className="card space-y-6 p-6">
          <section className="space-y-4">
            <h2 className="text-base font-semibold">Visitor display</h2>
            <Field label="Show each visitor for (seconds)" htmlFor="dur" error={err('display_duration_seconds')} hint={`= ${fmtDuration(Number(f.display_duration_seconds) || 0)}. Applies to visitors who register after you save.`}>
              <input id="dur" type="number" min="10" max="86400" className={`input ${err('display_duration_seconds') ? 'input-error' : ''}`} value={f.display_duration_seconds} onChange={set('display_duration_seconds')} />
            </Field>
            {durationChanged && (
              <label className="flex items-start gap-2.5 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                <input type="checkbox" className="mt-0.5 h-4 w-4 accent-[#5E0ED7]" checked={applyNow} onChange={(e) => setApplyNow(e.target.checked)} />
                <span>Also re-time the visitor on screen right now (their new end time = their start + new duration).</span>
              </label>
            )}
            <Field label="Rotate gallery images every (seconds)" htmlFor="rot" error={err('gallery_rotation_seconds')}>
              <input id="rot" type="number" min="5" max="3600" className={`input ${err('gallery_rotation_seconds') ? 'input-error' : ''}`} value={f.gallery_rotation_seconds} onChange={set('gallery_rotation_seconds')} />
            </Field>
            <Field label="When no visitor is on screen" htmlFor="fb" error={err('fallback_mode')}>
              <select id="fb" className="input" value={f.fallback_mode} onChange={set('fallback_mode')}>
                <option value="random_image">Show random gallery images</option>
                <option value="branded">Show the branded welcome screen</option>
              </select>
            </Field>
            <Field label="TV polling interval (seconds)" htmlFor="poll" error={err('polling_interval_seconds')} hint="TVs pick the new value up on their next poll.">
              <input id="poll" type="number" min="1" max="30" className="input" value={f.polling_interval_seconds} onChange={set('polling_interval_seconds')} />
            </Field>
          </section>

          <section className="space-y-4 border-t border-neutral-200 pt-6">
            <h2 className="text-base font-semibold">Registration</h2>
            <div className="flex items-center justify-between rounded-xl border border-neutral-200 p-3.5">
              <div><div className="text-sm font-medium">Accept new registrations</div><div className="text-xs text-neutral-500">When off, the QR page shows “check-in is closed”.</div></div>
              <Switch checked={f.registration_enabled} onChange={(v) => setF((x) => ({ ...x, registration_enabled: v }))} label="Accept new registrations" />
            </div>
            <Field label="Public registration URL" htmlFor="url" error={err('registration_url')} hint="Encoded in the QR code. Use your real public address, not localhost.">
              <input id="url" type="url" className={`input ${err('registration_url') ? 'input-error' : ''}`} value={f.registration_url} onChange={set('registration_url')} />
            </Field>
            <Field label="Phone number rules" htmlFor="cc" error={err('phone_country')}>
              <select id="cc" className="input" value={f.phone_country} onChange={set('phone_country')}>
                {Object.entries(meta?.countries ?? {}).map(([k, n]) => <option key={k} value={k}>{n}</option>)}
              </select>
            </Field>
            <Field label="Delete visitor records after (days)" htmlFor="ret" error={err('visitor_retention_days')} hint="Records and photos are deleted automatically once this period ends. 0 keeps them forever.">
              <input id="ret" type="number" min="0" max="3650" className="input" value={f.visitor_retention_days} onChange={set('visitor_retention_days')} />
            </Field>
          </section>

          <div className="flex justify-end border-t border-neutral-200 pt-5"><button className="btn-primary" disabled={saving}>{saving && <Spinner />}Save settings</button></div>
        </form>

        <aside className="card h-fit space-y-3 p-5 text-sm">
          <h2 className="text-base font-semibold">Display health</h2>
          {!s ? <Skeleton className="h-28" /> : (
            <dl className="space-y-2.5">
              <div className="flex justify-between"><dt className="text-neutral-500">Database</dt><dd className={s.database === 'up' ? 'font-medium text-emerald-700' : 'font-medium text-red-700'}>{s.database}</dd></div>
              <div className="flex justify-between"><dt className="text-neutral-500">Showing</dt><dd className="font-medium">{s.display.mode === 'visitor' ? 'Visitor' : s.display.has_image ? 'Gallery image' : 'Branded screen'}</dd></div>
              <div className="flex justify-between"><dt className="text-neutral-500">TVs online</dt><dd className="font-medium">{s.tvs.online} / {s.tvs.total}</dd></div>
              <div className="flex justify-between"><dt className="text-neutral-500">Enabled images</dt><dd className="font-medium">{s.enabled_gallery_images}</dd></div>
              <div className="flex justify-between"><dt className="text-neutral-500">State version</dt><dd className="font-medium tabular-nums">{s.display.version}</dd></div>
            </dl>
          )}
        </aside>
      </div>
    </div>
  );
}
