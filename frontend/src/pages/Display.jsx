import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Logo } from '../components/ui.jsx';
import { useDisplay } from '../hooks/useDisplay';

function Backdrop({ src }) {
  return src ? <img src={src} alt="" aria-hidden="true" className="absolute inset-0 h-full w-full scale-110 object-cover opacity-40 blur-3xl" /> : null;
}

function BrandScene() {
  return (
    <div className="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-[#1b0746] via-[#3a0a93] to-[#5E0ED7] text-white">
      <Logo size={96} inner={32} className="!border-white [&>span]:!bg-white" />
      <p className="mt-10 text-[clamp(2rem,5vw,5rem)] font-semibold uppercase tracking-[0.3em]">Welcome</p>
    </div>
  );
}

function VisitorScene({ view, failed, onFail }) {
  const initial = view.visitor_name?.trim()?.[0]?.toUpperCase() ?? '';
  return (
    <div className="absolute inset-0 flex flex-col items-center justify-center bg-[#0c0618] px-[4vw] text-white">
      {!failed && <Backdrop src={view.image_url} />}
      <div className="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/60" />
      <div className="relative z-10 flex h-full w-full flex-col items-center justify-center gap-[3vh]">
        <p className="text-[clamp(1rem,2vw,2rem)] font-semibold uppercase tracking-[0.4em] text-violet-300">Welcome</p>
        {failed || !view.image_url ? (
          <div className="flex h-[52vh] w-[52vh] items-center justify-center rounded-full bg-[#5E0ED7] text-[20vh] font-semibold">{initial}</div>
        ) : (
          <img src={view.image_url} alt={view.visitor_name} onError={onFail} className="max-h-[62vh] max-w-[84vw] rounded-3xl object-contain shadow-2xl shadow-black/60 ring-1 ring-white/20" />
        )}
        <h1 className="max-w-[92vw] truncate text-center text-[clamp(2.25rem,6vw,6.5rem)] font-semibold uppercase leading-none tracking-[0.1em]">{view.visitor_name}</h1>
      </div>
    </div>
  );
}

function ImageScene({ view, onFail }) {
  return (
    <div className="absolute inset-0 bg-black">
      <Backdrop src={view.image_url} />
      <img src={view.image_url} alt="" onError={onFail} className="relative z-10 h-full w-full object-contain" />
    </div>
  );
}

export default function Display() {
  const [params] = useSearchParams();
  const reduce = useReducedMotion();
  const { view, offline } = useDisplay(params.get('name'));
  const [shown, setShown] = useState(null);
  const [failed, setFailed] = useState(false);

  // Preload the next image so TVs never flash a broken or half-loaded picture.
  useEffect(() => {
    if (!view) return undefined;
    setFailed(false);
    if (!view.image_url) { setShown(view); return undefined; }
    let cancelled = false;
    const done = (ok) => { if (!cancelled) setShown(ok ? view : { ...view, image_url: null, mode: view.mode === 'visitor' ? 'visitor' : 'branded' }); };
    const img = new Image();
    img.onload = () => done(true);
    img.onerror = () => done(false);
    img.src = view.image_url;
    const t = setTimeout(() => done(true), 10000);
    return () => { cancelled = true; clearTimeout(t); img.onload = null; img.onerror = null; };
  }, [view]);

  useEffect(() => {
    const toggle = () => (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.().catch(() => {}));
    const onKey = (e) => e.key.toLowerCase() === 'f' && toggle();
    window.addEventListener('keydown', onKey);
    window.addEventListener('dblclick', toggle);
    return () => { window.removeEventListener('keydown', onKey); window.removeEventListener('dblclick', toggle); };
  }, []);

  const scene = shown ?? { mode: 'branded', version: 'boot' };
  const t = reduce ? { duration: 0 } : { duration: 0.9, ease: 'easeInOut' };

  return (
    <div className="fixed inset-0 cursor-none overflow-hidden bg-black font-sans">
      <AnimatePresence initial={false}>
        <motion.div key={scene.version} className="absolute inset-0" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={t}>
          {scene.mode === 'visitor' ? <VisitorScene view={scene} failed={failed} onFail={() => setFailed(true)} />
            : scene.mode === 'random_image' && scene.image_url ? <ImageScene view={scene} onFail={() => setShown({ ...scene, mode: 'branded', image_url: null })} />
            : <BrandScene />}
        </motion.div>
      </AnimatePresence>
      {offline && <span className="absolute bottom-3 right-3 z-50 h-2.5 w-2.5 rounded-full bg-amber-400/80" title="Reconnecting…" />}
    </div>
  );
}
