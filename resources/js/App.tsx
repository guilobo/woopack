/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useEffect, useState } from 'react';
import { Menu, Package } from 'lucide-react';
import {
  BrowserRouter as Router,
  Navigate,
  Route,
  Routes,
  useLocation,
} from 'react-router-dom';
import api from './api';
import Login from './components/Login';
import Dashboard from './components/Dashboard';
import OrderList from './components/OrderList';
import PackingMode from './components/PackingMode';
import Sidebar from './components/Sidebar';
import IntegrationSettings from './components/IntegrationSettings';
import AcceptInvitation from './components/AcceptInvitation';
import { routerBasename } from './config';

export interface AuthState {
  authenticated: boolean;
  user: {
    id: number;
    name: string;
    email: string;
  } | null;
  has_integration: boolean;
  has_whatsapp: boolean;
  is_admin: boolean;
}

const guestState: AuthState = {
  authenticated: false,
  user: null,
  has_integration: false,
  has_whatsapp: false,
  is_admin: false,
};

function normalizeAuthState(payload: Partial<AuthState> | null | undefined): AuthState {
  return {
    authenticated: Boolean(payload?.authenticated),
    user: payload?.user ?? null,
    has_integration: Boolean(payload?.has_integration),
    has_whatsapp: Boolean(payload?.has_whatsapp),
    is_admin: Boolean(payload?.is_admin),
  };
}

function AuthenticatedApp({
  authState,
  onAuthChange,
}: {
  authState: AuthState;
  onAuthChange: (nextState: Partial<AuthState>) => void;
}) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    setSidebarOpen(false);
  }, [location.pathname]);

  const integrationPath = '/settings/integration';

  if (! authState.has_integration && location.pathname !== integrationPath) {
    return <Navigate to={integrationPath} replace />;
  }

  return (
    <div className="flex h-screen overflow-hidden">
      <Sidebar
        isAdmin={authState.is_admin}
        isOpen={sidebarOpen}
        userName={authState.user?.name ?? 'WooPack'}
        onClose={() => setSidebarOpen(false)}
        onLogout={() => onAuthChange(guestState)}
      />

      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
          <button
            type="button"
            onClick={() => setSidebarOpen(true)}
            className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900"
            aria-label="Abrir menu"
          >
            <Menu size={20} />
          </button>

          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary text-white shadow-lg shadow-primary/20">
              <Package size={20} />
            </div>
            <div className="text-right">
              <div className="text-base font-bold tracking-tight text-slate-900">WooPack</div>
              <div className="text-[10px] font-medium uppercase tracking-[0.28em] text-slate-400">Logistica</div>
            </div>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto">
          <div className="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
            <Routes>
              <Route path="/" element={<Dashboard />} />
              <Route path="/orders" element={<OrderList />} />
              <Route path="/packing/:orderId?" element={<PackingMode />} />
              <Route
                path="/settings/integration"
                element={<IntegrationSettings authState={authState} onUpdated={onAuthChange} />}
              />
              <Route path="*" element={<Navigate to="/" />} />
            </Routes>
          </div>
        </main>
      </div>
    </div>
  );
}

export default function App() {
  const [authState, setAuthState] = useState<AuthState | null>(null);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const res = await api.get('/auth/check');
      setAuthState(normalizeAuthState(res.data));
    } catch {
      setAuthState(guestState);
    }
  };

  const handleAuthChange = (nextState: Partial<AuthState>) => {
    setAuthState(normalizeAuthState(nextState));
  };

  if (authState === null) {
    return (
      <div className="flex items-center justify-center h-screen bg-slate-50">
        <div className="flex flex-col items-center gap-4">
          <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
          <div className="font-medium text-slate-500 animate-pulse">Iniciando sistema...</div>
        </div>
      </div>
    );
  }

  return (
    <Router basename={routerBasename}>
      <div className="min-h-screen bg-bg-main text-slate-900 font-sans">
        {!authState.authenticated ? (
          <Routes>
            <Route path="/login" element={<Login onLogin={handleAuthChange} />} />
            <Route path="/invite/:token" element={<AcceptInvitation onAccepted={handleAuthChange} />} />
            <Route path="*" element={<Navigate to="/login" />} />
          </Routes>
        ) : (
          <AuthenticatedApp authState={authState} onAuthChange={handleAuthChange} />
        )}
      </div>
    </Router>
  );
}
