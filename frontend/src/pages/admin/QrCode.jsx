import { useState } from 'react';
import { Download, Printer } from 'lucide-react';
import { useApiData } from '../../hooks/useApiData';
import { api, ApiError } from '../../services/api';
import { useToast } from '../../context/ToastContext';
import { Field, Logo, Skeleton, Spinner } from '../../components/ui.jsx';

export default function QrCode() {
  const toast = useToast();
  const { data, loading, reload } = useApiData('/admin/settings');
  const [url, setUrl] = useState('');
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState('');
  const [bust, setBust] = useState(0);

  const current = data?.registration_url ?? '';
  const save = async (e) => {
    e.preventDefault();
    setSaving(true); setErr('');
    try {
      await api.patch('/admin/settings', { registration_url: url.trim() });
      toast.success('Registration URL updated. The QR code now points to it.');
      setUrl(''); setBust((b) => b + 1); reload();
    } catch (ex) { setErr(ex instanceof ApiError ? (ex.fields?.registration_url || ex.message) : 'Save failed.'); } finally { setSaving(false); }
  };

  const src = `/api/admin/qr-code?format=svg&b=${bust}`;
  const isLocal = /localhost|127\.0\.0\.1/.test(current);

  return (
    <div className="space-y-6">
      <div><h1 className="text-2xl font-semibold">Entrance QR code</h1><p className="text-sm text-neutral-500">One permanent code. It only contains the public registration URL — never visitor data.</p></div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,420px)_1fr]">
        {/* The poster preview is also what prints */}
        <div className="print-area card flex flex-col items-center bg-white px-8 py-10 text-center">
          <Logo size={48} inner={16} />
          <h2 className="mt-6 text-2xl font-semibold uppercase tracking-[0.15em]">Scan to Register Your Visit</h2>
          {loading ? <Skeleton className="mt-6 h-72 w-72" /> : <img src={src} alt={`QR code linking to ${current}`} className="mt-6 h-72 w-72" />}
          <p className="mt-5 max-w-xs text-sm text-neutral-600">Point your phone camera at the code, enter your name, add a photo, and you’ll appear on our lobby screens.</p>
          <p className="mt-3 break-all text-xs text-neutral-400">{current}</p>
        </div>

        <div className="space-y-5">
          <div className="card space-y-4 p-5">
            <h2 className="text-base font-semibold">Download or print</h2>
            <div className="flex flex-wrap gap-2">
              <a className="btn-primary" href="/api/admin/qr-code?format=png&download=1"><Download className="h-4 w-4" />Download PNG</a>
              <a className="btn-ghost" href="/api/admin/qr-code?format=svg&download=1"><Download className="h-4 w-4" />Download SVG</a>
              <button className="btn-dark" onClick={() => window.print()}><Printer className="h-4 w-4" />Print poster</button>
            </div>
            <p className="text-xs text-neutral-500">Printing uses the poster on the left (A4). Keep the white border around the code so phones can scan it.</p>
          </div>

          <form onSubmit={save} className="card space-y-4 p-5">
            <h2 className="text-base font-semibold">Registration URL</h2>
            <p className="break-all rounded-xl bg-neutral-100 px-3 py-2 text-sm">{current || '—'}</p>
            {isLocal && <p className="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">This address only works on this computer. Phones can’t open “localhost” — set your real public or office-network address before printing.</p>}
            <Field label="Change URL" htmlFor="u" error={err}>
              <input id="u" type="url" className={`input ${err ? 'input-error' : ''}`} placeholder="https://visitors.example.com/register" value={url} onChange={(e) => setUrl(e.target.value)} />
            </Field>
            <button className="btn-primary" disabled={!url || saving}>{saving && <Spinner />}Save URL</button>
          </form>
        </div>
      </div>
    </div>
  );
}
