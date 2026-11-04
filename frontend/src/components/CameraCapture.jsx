import { useCallback, useEffect, useRef, useState } from 'react';
import { Camera, Check, RefreshCw, SwitchCamera, X } from 'lucide-react';
import { Spinner } from './ui.jsx';

const stop = (s) => s?.getTracks().forEach((t) => t.stop());

function explain(e) {
  if (!window.isSecureContext) return 'The camera needs a secure (HTTPS) connection. Please upload a photo instead.';
  if (e?.name === 'NotAllowedError' || e?.name === 'SecurityError') return 'Camera access was blocked. Allow the camera in your browser settings, or upload a photo instead.';
  if (e?.name === 'NotFoundError' || e?.name === 'OverconstrainedError') return 'No camera was found on this device. Please upload a photo instead.';
  return 'The camera could not be started. Please upload a photo instead.';
}

/** Live camera: request permission → preview → capture → retake/confirm. Tracks are always stopped. */
export default function CameraCapture({ onConfirm, onCancel }) {
  const videoRef = useRef(null);
  const streamRef = useRef(null);
  const [facing, setFacing] = useState('user');
  const [ready, setReady] = useState(false);
  const [error, setError] = useState('');
  const [shot, setShot] = useState(null); // { blob, url }
  const [attempt, setAttempt] = useState(0);

  const release = useCallback(() => { stop(streamRef.current); streamRef.current = null; }, []);

  useEffect(() => {
    if (shot) return undefined;
    let cancelled = false;
    setReady(false);
    setError('');
    (async () => {
      if (!navigator.mediaDevices?.getUserMedia) { setError(explain()); return; }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 1280 } } });
        if (cancelled) { stop(stream); return; }
        streamRef.current = stream;
        const v = videoRef.current;
        if (v) { v.srcObject = stream; await v.play().catch(() => {}); setReady(true); }
      } catch (e) { if (!cancelled) setError(explain(e)); }
    })();
    return () => { cancelled = true; release(); };
  }, [facing, attempt, shot, release]);

  useEffect(() => () => { release(); }, [release]);
  useEffect(() => () => { if (shot) URL.revokeObjectURL(shot.url); }, [shot]);

  const capture = () => {
    const v = videoRef.current;
    if (!v || !v.videoWidth) return;
    const c = document.createElement('canvas');
    c.width = v.videoWidth;
    c.height = v.videoHeight;
    c.getContext('2d').drawImage(v, 0, 0);
    c.toBlob((blob) => {
      if (!blob) { setError('Could not capture the photo. Please try again.'); return; }
      release();
      setShot({ blob, url: URL.createObjectURL(blob) });
    }, 'image/jpeg', 0.92);
  };
  const cancel = () => { release(); onCancel(); };

  return (
    <div className="fixed inset-0 z-[80] flex flex-col bg-black" role="dialog" aria-modal="true" aria-label="Take a photo">
      <div className="flex items-center justify-between p-4 text-white">
        <span className="text-sm font-semibold">Take your photo</span>
        <button onClick={cancel} aria-label="Close camera" className="rounded-full bg-white/15 p-2"><X className="h-5 w-5" /></button>
      </div>
      <div className="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden">
        {error ? (
          <div className="mx-6 max-w-sm rounded-2xl bg-white p-5 text-center">
            <p className="text-sm text-neutral-700">{error}</p>
            <button className="btn-primary mt-4" onClick={cancel}>Upload a photo instead</button>
          </div>
        ) : shot ? (
          <img src={shot.url} alt="Captured preview" className="max-h-full max-w-full object-contain" />
        ) : (
          <>
            <video ref={videoRef} playsInline muted className={`max-h-full max-w-full object-contain ${facing === 'user' ? '-scale-x-100' : ''}`} />
            {!ready && <div className="absolute inset-0 flex items-center justify-center text-white"><Spinner className="h-7 w-7" /></div>}
          </>
        )}
      </div>
      {!error && (
        <div className="flex items-center justify-center gap-6 p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
          {shot ? (
            <>
              <button className="btn bg-white/15 text-white hover:bg-white/25" onClick={() => { setShot(null); setAttempt((a) => a + 1); }}><RefreshCw className="h-4 w-4" />Retake</button>
              <button className="btn bg-brand text-white hover:bg-brand-dark" onClick={() => onConfirm(new File([shot.blob], 'camera.jpg', { type: 'image/jpeg' }))}><Check className="h-4 w-4" />Use this photo</button>
            </>
          ) : (
            <>
              <span className="w-10" />
              <button onClick={capture} disabled={!ready} aria-label="Capture photo" className="flex h-[72px] w-[72px] items-center justify-center rounded-full border-4 border-white bg-white/20 disabled:opacity-40">
                <Camera className="h-7 w-7 text-white" />
              </button>
              <button onClick={() => setFacing((f) => (f === 'user' ? 'environment' : 'user'))} aria-label="Switch camera" className="rounded-full bg-white/15 p-2.5 text-white"><SwitchCamera className="h-5 w-5" /></button>
            </>
          )}
        </div>
      )}
    </div>
  );
}
