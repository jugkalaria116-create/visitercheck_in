import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Spinner } from './ui.jsx';

export default function ProtectedRoute({ children }) {
  const { admin, loading } = useAuth();
  const loc = useLocation();
  if (loading) return <div className="flex min-h-screen items-center justify-center"><Spinner className="h-6 w-6 text-brand" /></div>;
  if (!admin) return <Navigate to="/admin/login" replace state={{ from: loc.pathname }} />;
  return children;
}
