import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { LayoutDashboard, Images, LogOut, Menu, QrCode, Settings, Tv, Users, X } from 'lucide-react';
import { Logo } from '../components/ui.jsx';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';

const LINKS = [
  { to: '/admin', label: 'Overview', icon: LayoutDashboard, end: true },
  { to: '/admin/visitors', label: 'Visitors', icon: Users },
  { to: '/admin/gallery', label: 'Gallery', icon: Images },
  { to: '/admin/displays', label: 'TV displays', icon: Tv },
  { to: '/admin/settings', label: 'Settings', icon: Settings },
  { to: '/admin/qr', label: 'QR code', icon: QrCode },
];

export default function AdminLayout() {
  const { admin, logout } = useAuth();
  const toast = useToast();
  const nav = useNavigate();
  const [open, setOpen] = useState(false);

  const signOut = async () => {
    try { await logout(); nav('/admin/login', { replace: true }); } catch { toast.error('Sign out failed. Please try again.'); }
  };

  const Sidebar = (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-3 px-5 py-5">
        <Logo /><span className="text-sm font-semibold uppercase tracking-[0.18em]">Visitor Admin</span>
      </div>
      <nav className="flex-1 space-y-1 px-3" aria-label="Admin">
        {LINKS.map(({ to, label, icon: Icon, end }) => (
          <NavLink key={to} to={to} end={end} onClick={() => setOpen(false)}
            className={({ isActive }) => `flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${isActive ? 'bg-brand text-white' : 'text-neutral-700 hover:bg-neutral-100'}`}>
            <Icon className="h-[18px] w-[18px]" />{label}
          </NavLink>
        ))}
      </nav>
      <div className="border-t border-neutral-200 p-3">
        <button onClick={signOut} className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-neutral-700 hover:bg-neutral-100"><LogOut className="h-[18px] w-[18px]" />Sign out</button>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-neutral-50">
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 border-r border-neutral-200 bg-white lg:block">{Sidebar}</aside>
      {open && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={() => setOpen(false)} />
          <aside className="absolute inset-y-0 left-0 w-64 bg-white shadow-xl">{Sidebar}</aside>
        </div>
      )}
      <div className="lg:pl-60">
        <header className="sticky top-0 z-20 flex items-center justify-between border-b border-neutral-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-6">
          <button className="btn-ghost !p-2 lg:hidden" onClick={() => setOpen((o) => !o)} aria-label="Toggle menu">{open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}</button>
          <span className="hidden text-sm text-neutral-500 lg:block">Signed in as <strong className="text-neutral-900">{admin?.username}</strong></span>
          <a href="/display" target="_blank" rel="noreferrer" className="btn-ghost !py-2"><Tv className="h-4 w-4" />Open TV display</a>
        </header>
        <main className="mx-auto max-w-6xl p-4 sm:p-6"><Outlet /></main>
      </div>
    </div>
  );
}
