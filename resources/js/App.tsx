/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useEffect, useState } from 'react';
import { Menu, Package } from 'lucide-react';
import { BrowserRouter as Router, Navigate, Route, Routes } from 'react-router-dom';
import api from './api';
import Login from './components/Login';
import Dashboard from './components/Dashboard';
import OrderList from './components/OrderList';
import PackingMode from './components/PackingMode';
import Sidebar from './components/Sidebar';
import { routerBasename } from './config';

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);
  const [sidebarOpen, setSidebarOpen] = useState(false);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const res = await api.get('/auth/check');
      setIsAuthenticated(res.data.authenticated);
    } catch {
      setIsAuthenticated(false);
    }
  };

  if (isAuthenticated === null) {
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
        {!isAuthenticated ? (
          <Routes>
            <Route path="/login" element={<Login onLogin={() => setIsAuthenticated(true)} />} />
            <Route path="*" element={<Navigate to="/login" />} />
          </Routes>
        ) : (
          <div className="flex h-screen overflow-hidden">
            <Sidebar
              isOpen={sidebarOpen}
              onClose={() => setSidebarOpen(false)}
              onLogout={() => setIsAuthenticated(false)}
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
                    <Route path="*" element={<Navigate to="/" />} />
                  </Routes>
                </div>
              </main>
            </div>
          </div>
        )}
      </div>
    </Router>
  );
}
