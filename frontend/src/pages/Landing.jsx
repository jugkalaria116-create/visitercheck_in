import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ArrowUpRight, X } from 'lucide-react';
import { Logo } from '../components/ui.jsx';

const ACCENT = '#5E0ED7';
const EASE = [0.22, 1, 0.36, 1];
const VIDEO = 'https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260517_222138_3e3205be-3364-417b-a64a-bfe087acbec4.mp4';

// Design-oriented hero copy supplied with the brief. These are decorative, NOT live system metrics.
const HERO_STATS = [
  { value: '300', label: ['CRAFTED', 'BRANDS'] },
  { value: '200', label: ['DIGITAL', 'PRODUCTS'] },
  { value: '100', label: ['VENTURES', 'FUNDED'] },
];
const NAV = [
  { label: 'Register', to: '/register' },
  { label: 'How It Works', href: '#how-it-works' },
  { label: 'Lobby Screen', to: '/display' },
  { label: 'Staff Login', to: '/admin' },
];
const HEADING = ['Fearless', 'Vision', 'Delivered'];
const STEPS = [
  ['Scan the code', 'Point your phone camera at the QR code at the entrance. No app and no account needed.'],
  ['Add your details', 'Enter your name and phone number, then take a photo or choose one from your phone.'],
  ['See yourself on screen', 'Your name and photo appear on the lobby displays right away, for a few minutes.'],
];

function NavItem({ item, className, onClick }) {
  const props = { className, onClick };
  return item.to ? <Link to={item.to} {...props}>{item.label}</Link> : <a href={item.href} {...props}>{item.label}</a>;
}

export default function Landing() {
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [videoFailed, setVideoFailed] = useState(false);

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('keydown', onKey);
    return () => { document.body.style.overflow = ''; document.removeEventListener('keydown', onKey); };
  }, [open]);

  const down = (i) => ({
    initial: reduce ? false : { opacity: 0, y: -20 },
    animate: { opacity: 1, y: 0 },
    transition: reduce ? { duration: 0 } : { duration: 0.5, delay: i * 0.1, ease: EASE },
  });
  const up = (i) => ({
    initial: reduce ? false : { opacity: 0, y: 32 },
    animate: { opacity: 1, y: 0 },
    transition: reduce ? { duration: 0 } : { duration: 0.6, delay: i * 0.12, ease: EASE },
  });

  const linkCls = 'text-xs font-semibold uppercase tracking-[0.2em] text-black transition-colors hover:text-[#5E0ED7]';

  return (
    <>
      <section className="relative flex min-h-screen w-full flex-col overflow-hidden bg-gradient-to-br from-white via-violet-50 to-violet-100 font-sans text-black">
        {!reduce && !videoFailed && (
          <video className="absolute inset-0 h-full w-full object-cover" src={VIDEO} autoPlay loop muted playsInline preload="auto" aria-hidden="true" onError={() => setVideoFailed(true)} />
        )}
        <div className="absolute inset-0 bg-white/30" aria-hidden="true" />

        {/* Navigation */}
        <nav className="relative z-20 flex items-center justify-between px-5 py-5 md:px-10" aria-label="Primary">
          <motion.div {...down(0)}><Link to="/" aria-label="Home"><Logo /></Link></motion.div>
          <div className="hidden items-center gap-10 md:flex">
            {NAV.map((item, i) => (
              <motion.div key={item.label} {...down(i + 1)}><NavItem item={item} className={linkCls} /></motion.div>
            ))}
          </div>
          <motion.button {...down(5)} onClick={() => setOpen(true)} aria-label="Open menu" aria-expanded={open}
            className="flex h-9 w-9 flex-col items-center justify-center gap-[5px] rounded-full bg-black focus:outline-none focus-visible:ring-2 focus-visible:ring-[#5E0ED7] focus-visible:ring-offset-2">
            <span className="h-[2px] w-4 bg-white" /><span className="h-[2px] w-4 bg-white" /><span className="h-[2px] w-4 bg-white" />
          </motion.button>
        </nav>

        {/* Stats */}
        <div className="relative z-10 mt-4 flex justify-end gap-6 px-5 sm:gap-10 md:gap-14 md:px-10">
          {HERO_STATS.map((s, i) => (
            <motion.div key={s.value} {...up(i)} className="text-right">
              <div className="font-semibold leading-none" style={{ fontSize: 'clamp(1.5rem, 5vw, 3.5rem)' }}>
                <span style={{ color: ACCENT }}>+</span>{s.value}
              </div>
              <div className="mt-2 text-[10px] font-semibold uppercase leading-snug tracking-[0.2em] sm:text-xs">
                {s.label[0]}<br />{s.label[1]}
              </div>
            </motion.div>
          ))}
        </div>

        <div className="flex-1" />

        {/* Bottom content */}
        <div className="relative z-10 px-5 pb-8 md:px-10 md:pb-10">
          <div className="mb-6 flex flex-col gap-6 md:mb-8 md:flex-row md:items-end md:justify-between">
            <div className="space-y-5">
              <motion.p {...up(3)} className="text-sm font-semibold uppercase leading-relaxed tracking-[0.2em] md:text-base">
                Shaping Bold<br />Visions Into Power<br />For Your Tribe
              </motion.p>
              <motion.div {...up(4)}>
                <Link to="/register" className="group inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.2em]" style={{ color: ACCENT }}>
                  Register as a Visitor
                  <ArrowUpRight className="h-5 w-5 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" style={{ color: ACCENT }} />
                </Link>
              </motion.div>
            </div>
            <motion.p {...up(5)} className="max-w-xs text-xs font-semibold uppercase leading-relaxed tracking-[0.2em] md:max-w-sm md:text-right md:text-sm">
              Creative Studios Built Around Elevating Your Vision Into Striking Reality
            </motion.p>
          </div>
          <h1 className="font-semibold uppercase leading-[0.88] tracking-tight" style={{ fontSize: 'clamp(3rem, 13vw, 11rem)' }}>
            {HEADING.map((w, i) => (
              <span key={w} className="block overflow-hidden pb-[0.06em]">
                <motion.span className="block" initial={reduce ? false : { y: '110%' }} animate={{ y: 0 }}
                  transition={reduce ? { duration: 0 } : { duration: 0.7, delay: 0.4 + i * 0.14, ease: EASE }}>{w}</motion.span>
              </span>
            ))}
          </h1>
        </div>

        {/* Mobile overlay */}
        <AnimatePresence>
          {open && (
            <motion.div className="fixed inset-0 z-50 flex flex-col bg-white px-5 py-5" role="dialog" aria-modal="true" aria-label="Menu"
              initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: reduce ? 0 : 0.25 }}>
              <div className="flex items-center justify-between">
                <Logo />
                <button onClick={() => setOpen(false)} aria-label="Close menu" className="flex h-9 w-9 items-center justify-center rounded-full bg-black text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#5E0ED7] focus-visible:ring-offset-2">
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="flex flex-1 flex-col justify-center gap-8">
                {NAV.map((item) => (
                  <NavItem key={item.label} item={item} onClick={() => setOpen(false)} className="text-3xl font-semibold uppercase tracking-widest text-black" />
                ))}
              </div>
              <Link to="/register" className="inline-flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.2em]" style={{ color: ACCENT }}>
                Register as a Visitor <ArrowUpRight className="h-5 w-5" />
              </Link>
            </motion.div>
          )}
        </AnimatePresence>
      </section>

      <section id="how-it-works" className="bg-white px-5 py-20 md:px-10">
        <div className="mx-auto max-w-5xl">
          <h2 className="text-2xl font-semibold uppercase tracking-[0.15em] md:text-3xl">How it works</h2>
          <ol className="mt-10 grid gap-8 md:grid-cols-3">
            {STEPS.map(([t, d], i) => (
              <li key={t} className="border-t-2 pt-5" style={{ borderColor: ACCENT }}>
                <span className="text-sm font-semibold" style={{ color: ACCENT }}>Step {i + 1}</span>
                <h3 className="mt-2 text-lg font-semibold">{t}</h3>
                <p className="mt-2 max-w-sm text-sm leading-relaxed text-neutral-600">{d}</p>
              </li>
            ))}
          </ol>
          <Link to="/register" className="mt-12 inline-flex items-center gap-2 rounded-full bg-black px-6 py-3 text-sm font-semibold uppercase tracking-[0.15em] text-white hover:bg-neutral-800">
            Enter Visitor Portal <ArrowUpRight className="h-4 w-4" style={{ color: '#fff' }} />
          </Link>
        </div>
      </section>
    </>
  );
}
