import { useRef, useState } from 'react';
import { ImagePlus, Trash2, UploadCloud } from 'lucide-react';
import { useApiData } from '../../hooks/useApiData';
import { api, ApiError } from '../../services/api';
import { useToast } from '../../context/ToastContext';
import { ConfirmDialog, EmptyState, Skeleton, Spinner, Switch } from '../../components/ui.jsx';
import { fmtDateTime } from '../../utils/format';

function Card({ img, onChange, onDelete }) {
  const toast = useToast();
  const [title, setTitle] = useState(img.title);
  const patch = async (body) => {
    try { const { data } = await api.patch(`/admin/gallery/${img.id}`, body); onChange(data); }
    catch (e) { toast.error(e instanceof ApiError ? e.message : 'Update failed.'); setTitle(img.title); }
  };
  return (
    <div className="card overflow-hidden">
      <div className="relative aspect-video bg-neutral-100">
        <img src={img.url} alt={img.title} loading="lazy" className={`h-full w-full object-cover transition ${img.enabled ? '' : 'opacity-40 grayscale'}`} />
        {!img.enabled && <span className="badge absolute left-2 top-2 bg-neutral-800 text-white">Disabled</span>}
      </div>
      <div className="space-y-3 p-3.5">
        <input className="input !py-2" value={title} maxLength={100} aria-label="Image title" onChange={(e) => setTitle(e.target.value)}
          onBlur={() => title.trim() && title !== img.title && patch({ title })} onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()} />
        <div className="flex items-center justify-between">
          <label className="flex items-center gap-2 text-xs font-medium text-neutral-600"><Switch checked={img.enabled} onChange={(v) => patch({ enabled: v })} label={`Show ${img.title} on TVs`} />{img.enabled ? 'Shown on TVs' : 'Hidden'}</label>
          <button className="btn-ghost !p-2 text-red-600" aria-label={`Delete ${img.title}`} onClick={onDelete}><Trash2 className="h-4 w-4" /></button>
        </div>
        <p className="text-xs text-neutral-400">Uploaded {fmtDateTime(img.created_at)}</p>
      </div>
    </div>
  );
}

export default function Gallery() {
  const toast = useToast();
  const input = useRef(null);
  const { data, loading, error, setData, reload } = useApiData('/admin/gallery');
  const settings = useApiData('/admin/settings');
  const [uploading, setUploading] = useState(false);
  const [drag, setDrag] = useState(false);
  const [del, setDel] = useState(null);
  const [busy, setBusy] = useState(false);
  const [rotation, setRotation] = useState('');

  const upload = async (files) => {
    const list = Array.from(files).filter((f) => f.type.startsWith('image/'));
    if (!list.length) { toast.error('Choose JPEG, PNG or WebP images.'); return; }
    setUploading(true);
    try {
      const fd = new FormData();
      list.forEach((f) => fd.append('images[]', f));
      const { data: res } = await api.upload('/admin/gallery', fd);
      toast.success(`${res.uploaded.length} image${res.uploaded.length === 1 ? '' : 's'} uploaded.`);
      res.failed.forEach((f) => toast.error(`${f.name}: ${f.error}`));
      reload();
    } catch (e) {
      const failed = e instanceof ApiError ? e.message : 'Upload failed.';
      toast.error(failed);
    } finally { setUploading(false); }
  };

  const remove = async () => {
    setBusy(true);
    try { await api.del(`/admin/gallery/${del.id}`); setData((l) => l.filter((i) => i.id !== del.id)); toast.success('Image deleted.'); setDel(null); }
    catch (e) { toast.error(e instanceof ApiError ? e.message : 'Delete failed.'); } finally { setBusy(false); }
  };

  const saveRotation = async () => {
    try { await api.patch('/admin/settings', { gallery_rotation_seconds: Number(rotation) }); toast.success('Rotation interval saved.'); settings.reload(); setRotation(''); }
    catch (e) { toast.error(e instanceof ApiError ? (Object.values(e.fields)[0] || e.message) : 'Save failed.'); }
  };
  const currentRotation = settings.data?.gallery_rotation_seconds;
  const enabledCount = data?.filter((i) => i.enabled).length ?? 0;

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 className="text-2xl font-semibold">Gallery</h1><p className="text-sm text-neutral-500">Enabled images rotate on the TVs when no visitor is on screen. {data && `${enabledCount} enabled.`}</p></div>
        <div className="flex items-end gap-2">
          <div><label htmlFor="rot" className="label !mb-1 !text-xs">Rotate every (seconds)</label><input id="rot" type="number" min="5" max="3600" className="input !w-32 !py-2" placeholder={currentRotation ?? ''} value={rotation} onChange={(e) => setRotation(e.target.value)} /></div>
          <button className="btn-ghost !py-2.5" disabled={!rotation} onClick={saveRotation}>Save</button>
        </div>
      </div>

      <div onDragOver={(e) => { e.preventDefault(); setDrag(true); }} onDragLeave={() => setDrag(false)} onDrop={(e) => { e.preventDefault(); setDrag(false); upload(e.dataTransfer.files); }}
        className={`card flex flex-col items-center gap-3 border-2 border-dashed px-6 py-8 text-center transition-colors ${drag ? 'border-brand bg-brand-soft' : 'border-neutral-300'}`}>
        {uploading ? <Spinner className="h-7 w-7 text-brand" /> : <UploadCloud className="h-8 w-8 text-brand" />}
        <p className="text-sm text-neutral-600">{uploading ? 'Uploading and checking images…' : 'Drag images here, or choose files. JPEG, PNG or WebP.'}</p>
        <button className="btn-primary" disabled={uploading} onClick={() => input.current?.click()}><ImagePlus className="h-4 w-4" />Choose images</button>
        <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" tabIndex={-1} aria-label="Choose images" onChange={(e) => { upload(e.target.files); e.target.value = ''; }} />
      </div>

      {loading && !data ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-64" />)}</div>
      ) : error && !data ? (
        <div className="card"><EmptyState title="Couldn’t load the gallery">{error.message}</EmptyState></div>
      ) : data.length === 0 ? (
        <div className="card"><EmptyState icon={ImagePlus} title="No gallery images yet">Upload a few images. They’ll rotate on the TVs between visitors. Until then the TVs show the branded welcome screen.</EmptyState></div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((img) => <Card key={img.id} img={img} onChange={(n) => setData((l) => l.map((i) => (i.id === n.id ? n : i)))} onDelete={() => setDel(img)} />)}
        </div>
      )}

      <ConfirmDialog open={!!del} busy={busy} title="Delete this image?" confirmLabel="Delete image" message={del ? `“${del.title}” will be permanently deleted from the gallery and the server.` : ''} onConfirm={remove} onCancel={() => setDel(null)} />
    </div>
  );
}
