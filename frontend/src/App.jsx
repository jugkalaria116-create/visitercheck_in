
import { lazy, Suspense } from 'react';
import { Navigate, Outlet, Route, Routes } from 'react-router-dom';

import { AuthProvider } from './context/AuthContext.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import AdminLayout from './layouts/AdminLayout.jsx';

import Landing from './pages/Landing.jsx';
import Register from './pages/Register.jsx';
import Display from './pages/Display.jsx';

import { Spinner } from './components/ui.jsx';

const Login = lazy(() => import('./pages/admin/Login.jsx'));
const Dashboard = lazy(() => import('./pages/admin/Dashboard.jsx'));
const Visitors = lazy(() => import('./pages/admin/Visitors.jsx'));
const Gallery = lazy(() => import('./pages/admin/Gallery.jsx'));
const Displays = lazy(() => import('./pages/admin/Displays.jsx'));
const Settings = lazy(() => import('./pages/admin/Settings.jsx'));
const QrCode = lazy(() => import('./pages/admin/QrCode.jsx'));

const Fallback = () => (
  <div className="flex min-h-screen items-center justify-center">
    <Spinner className="h-6 w-6 text-brand" />
  </div>
);

function AdminProvider() {
  return (
    <AuthProvider>
      <Outlet />
    </AuthProvider>
  );
}

export default function App() {
  return (
    <Suspense fallback={<Fallback />}>
      <Routes>
        {/* Public Routes */}
        <Route path="/" element={<Landing />} />
        <Route path="/register" element={<Register />} />
        <Route path="/display" element={<Display />} />

        {/* Admin Routes */}
        <Route path="/admin" element={<AdminProvider />}>
          <Route path="login" element={<Login />} />

          <Route
            element={
              <ProtectedRoute>
                <AdminLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<Dashboard />} />
            <Route path="visitors" element={<Visitors />} />
            <Route path="gallery" element={<Gallery />} />
            <Route path="displays" element={<Displays />} />
            <Route path="settings" element={<Settings />} />
            <Route path="qr" element={<QrCode />} />
          </Route>
        </Route>

        {/* 404 */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  );
}
