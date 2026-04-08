/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useEffect, useState } from 'react';
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
            <Sidebar onLogout={() => setIsAuthenticated(false)} />
            <main className="flex-1 overflow-y-auto">
              <div className="max-w-7xl mx-auto p-8">
                <Routes>
                  <Route path="/" element={<Dashboard />} />
                  <Route path="/orders" element={<OrderList />} />
                  <Route path="/packing/:orderId?" element={<PackingMode />} />
                  <Route path="*" element={<Navigate to="/" />} />
                </Routes>
              </div>
            </main>
          </div>
        )}
      </div>
    </Router>
  );
}
