import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { Camera, CheckCircle2, ImagePlus, RefreshCw, ShieldCheck, Upload } from 'lucide-react';
import { api, ApiError } from '../services/api';
import { Field, Logo, Skeleton, Spinner } from '../components/ui.jsx';
import CameraCapture from '../components/CameraCapture.jsx';
import { prepareImage } from '../utils/image';

const NAME_RE = /^[\p{L}\p{M}][\p{L}\p{M}\s.'’-]{1,79}$/u;
const newId = () => crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}-xxxxxxxx`;

export default function Register() {
  const [cfg, setCfg] = useState(null);
  const [cfgError, setCfgError] = useState(false);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [consent, setConsent] = useState(false);
  const [photo, setPhoto] = useState(null); // { file, url }
  const [camera, setCamera] = useState(false);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState('');
  const [done, setDone] = useState(null);
  const submissionId = useRef(newId());
  const fileInput = useRef(null);

  useEffect(() => { api.get('/public/config').then((r) => setCfg(r.data)).catch(() => setCfgError(true)); }, []);
  useEffect(() => () => { if (photo) URL.revokeObjectURL(photo.url); }, [photo]);

  const setFile = (file) => { setPhoto({ file, url: URL.createObjectURL(file) }); setErrors((e) => ({ ...e, photo: undefined })); };

  const onPick = async (e) => {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { setErrors((x) => ({ ...x, photo: 'Choose a JPEG, PNG or WebP image.' })); return; }
    setFile(await prepareImage(f));
  };

  const validate = () => {
    const e = {};
    if (!NAME_RE.test(name.trim().replace(/\s+/g, ' '))) e.name = 'Enter your full name (letters only).';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 7 || digits.length > 15) e.phone = 'Enter a valid phone number.';
    if (!photo) e.photo = 'Please add a photo.';
    else if (cfg && photo.file.size > cfg.max_upload_bytes) e.photo = 'This photo is too large. Try another one.';
    if (!consent) e.consent = 'Please agree to continue.';
    return e;
  };

  const submit = async (ev) => {
    ev.preventDefault();
    if (submitting) return;
    setFormError('');
    const e = validate();
    setErrors(e);
    if (Object.keys(e).length) return;
    setSubmitting(true);
    try {
      const fd = new FormData();
      fd.append('name', name.trim());
      fd.append('phone', phone.trim());
      fd.append('consent', '1');
      fd.append('submission_id', submissionId.current);
      fd.append('photo', photo.file, photo.file.name || 'photo.jpg');
      const { data } = await api.upload('/visitors', fd);
      setDone(data);
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) setErrors(err.fields);
      setFormError(err instanceof ApiError ? err.message : 'We could not reach the server. Check your connection and try again.');
      if (err instanceof ApiError && err.code === 'registration_closed') setCfg((c) => ({ ...c, registration_enabled: false }));
    } finally {
      setSubmitting(false);
    }
  };

  const reset = () => {
    setName(''); setPhone(''); setConsent(false); setPhoto(null); setErrors({}); setFormError(''); setDone(null);
    submissionId.current = newId();
  };

  const minutes = done ? Math.max(1, Math.round(done.duration_seconds / 60)) : 5;

  return (
    <div className="min-h-screen bg-gradient-to-b from-violet-50 to-white">
      <header className="mx-auto flex max-w-md items-center justify-between px-5 py-5">
        <Link to="/" className="flex items-center gap-2.5" aria-label="Home"><Logo /><span className="text-xs font-semibold uppercase tracking-[0.2em]">Visitor Check-in</span></Link>
      </header>

      <main className="mx-auto max-w-md px-5 pb-16">
        <AnimatePresence mode="wait">
          {done ? (
            <motion.div key="done" initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} className="card p-7 text-center">
              <CheckCircle2 className="mx-auto h-14 w-14 text-emerald-600" />
              <h1 className="mt-4 text-2xl font-semibold">Welcome, {done.name}!</h1>
              <p className="mt-2 text-sm text-neutral-600">You’re checked in. Look up at the lobby screens — your photo and name are showing for about {minutes} {minutes === 1 ? 'minute' : 'minutes'}.</p>
              <button className="btn-ghost mt-6" onClick={reset}>Register another visitor</button>
            </motion.div>
          ) : cfgError ? (
            <motion.div key="err" className="card p-6 text-center">
              <p className="text-sm text-neutral-700">We couldn’t load the check-in form. Check your connection and try again.</p>
              <button className="btn-primary mt-4" onClick={() => location.reload()}><RefreshCw className="h-4 w-4" />Reload</button>
            </motion.div>
          ) : !cfg ? (
            <div key="load" className="space-y-4"><Skeleton className="h-10" /><Skeleton className="h-10" /><Skeleton className="h-52" /></div>
          ) : !cfg.registration_enabled ? (
            <motion.div key="closed" className="card p-7 text-center">
              <h1 className="text-xl font-semibold">Check-in is closed</h1>
              <p className="mt-2 text-sm text-neutral-600">Visitor registration isn’t available right now. Please ask the front desk for help.</p>
            </motion.div>
          ) : (
            <motion.form key="form" onSubmit={submit} noValidate initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} className="card space-y-5 p-6">
              <div>
                <h1 className="text-2xl font-semibold">Welcome</h1>
                <p className="mt-1 text-sm text-neutral-600">Check in to appear on the lobby screens.</p>
              </div>

              <Field label="Full name" htmlFor="name" error={errors.name}>
                <input id="name" className={`input ${errors.name ? 'input-error' : ''}`} value={name} onChange={(e) => setName(e.target.value)} autoComplete="name" maxLength={80} placeholder="Jane Doe" />
              </Field>
              <Field label="Phone number" htmlFor="phone" error={errors.phone} hint={cfg.phone_country_name ? `Number for ${cfg.phone_country_name}. It is never shown on screens.` : undefined}>
                <input id="phone" type="tel" inputMode="tel" className={`input ${errors.phone ? 'input-error' : ''}`} value={phone} onChange={(e) => setPhone(e.target.value)} autoComplete="tel" maxLength={25} placeholder="98765 43210" />
              </Field>

              <Field label="Your photo" error={errors.photo}>
                {photo ? (
                  <div className="flex items-center gap-4 rounded-xl border border-neutral-200 p-3">
                    <img src={photo.url} alt="Your selected photo" className="h-24 w-24 rounded-lg object-cover" />
                    <div className="flex flex-col gap-2">
                      <button type="button" className="btn-ghost !py-2" onClick={() => setCamera(true)}><Camera className="h-4 w-4" />Retake</button>
                      <button type="button" className="btn-ghost !py-2" onClick={() => fileInput.current?.click()}><ImagePlus className="h-4 w-4" />Replace</button>
                    </div>
                  </div>
                ) : (
                  <div className="grid grid-cols-2 gap-3">
                    <button type="button" onClick={() => setCamera(true)} className="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-brand/40 bg-brand-soft/60 px-3 py-6 text-sm font-semibold text-brand hover:bg-brand-soft">
                      <Camera className="h-7 w-7" />Take a photo
                    </button>
                    <button type="button" onClick={() => fileInput.current?.click()} className="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-neutral-300 px-3 py-6 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                      <Upload className="h-7 w-7" />Upload a photo
                    </button>
                  </div>
                )}
                <input ref={fileInput} type="file" accept="image/*" className="sr-only" onChange={onPick} tabIndex={-1} aria-label="Upload a photo" />
              </Field>

              <div>
                <label className="flex items-start gap-3 rounded-xl bg-neutral-50 p-3.5 text-sm text-neutral-700">
                  <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 h-5 w-5 shrink-0 accent-[#5E0ED7]" />
                  <span><ShieldCheck className="-mt-0.5 mr-1 inline h-4 w-4 text-brand" />I agree that my <strong>name and photo will be displayed on the office TVs</strong> for a few minutes. My phone number is kept private and removed after the retention period.</span>
                </label>
                {errors.consent && <p className="mt-1 text-xs font-medium text-red-600" role="alert">{errors.consent}</p>}
              </div>

              {formError && <p className="rounded-xl bg-red-50 p-3 text-sm text-red-700" role="alert">{formError}</p>}

              <button type="submit" className="btn-primary w-full !py-3.5 text-base" disabled={submitting}>
                {submitting ? <><Spinner />Checking you in…</> : 'Check in'}
              </button>
            </motion.form>
          )}
        </AnimatePresence>
      </main>

      {camera && <CameraCapture onCancel={() => setCamera(false)} onConfirm={(f) => { setCamera(false); prepareImage(f).then(setFile); }} />}
    </div>
  );
}
