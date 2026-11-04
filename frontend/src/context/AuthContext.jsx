import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, setCsrf } from '../services/api';

const Ctx = createContext(null);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }) {
  const [admin, setAdmin] = useState(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get('/admin/me');
      setCsrf(data.csrf_token);
      setAdmin(data.authenticated ? data.admin : null);
    } catch {
      setAdmin(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
    const expired = () => setAdmin(null);
    window.addEventListener('auth:expired', expired);
    return () => window.removeEventListener('auth:expired', expired);
  }, [refresh]);

  const login = useCallback(async (username, password) => {
    const { data } = await api.post('/admin/login', { username, password });
    setCsrf(data.csrf_token);
    setAdmin(data.admin);
  }, []);

  const logout = useCallback(async () => {
    try { await api.post('/admin/logout', {}); } finally {
      setAdmin(null);
      await refresh();
    }
  }, [refresh]);

  const value = useMemo(() => ({ admin, loading, login, logout }), [admin, loading, login, logout]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
