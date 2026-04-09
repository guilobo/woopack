import { NavLink, useNavigate } from 'react-router-dom';
import { LayoutDashboard, ListOrdered, Package, LogOut, Settings, X } from 'lucide-react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import api from '../api';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

interface SidebarProps {
  isAdmin: boolean;
  isOpen: boolean;
  userName: string;
  onClose: () => void;
  onLogout: () => void;
}

export default function Sidebar({ isAdmin, isOpen, userName, onClose, onLogout }: SidebarProps) {
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await api.post('/logout');
      onClose();
      onLogout();
      navigate('/login');
    } catch {
      console.error('Logout failed');
    }
  };

  const navItems = [
    { icon: LayoutDashboard, label: 'Dashboard', path: '/' },
    { icon: ListOrdered, label: 'Pedidos', path: '/orders' },
    { icon: Package, label: 'Modo Embalagem', path: '/packing' },
    { icon: Settings, label: isAdmin ? 'Integracao e Convites' : 'Integracao', path: '/settings/integration' },
  ];

  return (
    <>
      <button
        type="button"
        aria-label="Fechar menu"
        onClick={onClose}
        className={cn(
          'fixed inset-0 z-40 bg-slate-950/30 backdrop-blur-sm transition-opacity lg:hidden',
          isOpen ? 'opacity-100' : 'pointer-events-none opacity-0',
        )}
      />

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-[280px] max-w-[85vw] flex-col border-r border-slate-200 bg-white shadow-2xl shadow-slate-900/10 transition-transform duration-300 lg:static lg:z-auto lg:w-72 lg:max-w-none lg:translate-x-0 lg:shadow-none',
          isOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
      <div className="p-6 lg:p-8">
        <div className="mb-2 flex items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/20">
              <Package size={24} />
            </div>
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">WooPack</h1>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-900 lg:hidden"
            aria-label="Fechar menu"
          >
            <X size={18} />
          </button>
        </div>
        <p className="text-slate-400 text-[11px] font-medium uppercase tracking-wider ml-1">Logistica Inteligente</p>
      </div>

      <nav className="flex-1 px-4 py-4 space-y-1">
        {navItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            onClick={onClose}
            className={({ isActive }) => cn(
              'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all group',
              isActive
                ? 'bg-primary/10 text-primary'
                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900',
            )}
          >
            <item.icon
              size={20}
              className={cn(
                'transition-colors',
                'group-hover:text-primary',
              )}
            />
            {item.label}
          </NavLink>
        ))}
      </nav>

      <div className="border-t border-slate-100 p-6">
        <div className="mb-4 rounded-2xl bg-slate-50 px-4 py-3">
          <div className="text-xs font-bold uppercase tracking-widest text-slate-400">Conta ativa</div>
          <div className="mt-1 text-sm font-semibold text-slate-900">{userName}</div>
          <div className="text-xs text-slate-500">{isAdmin ? 'Administrador' : 'Operador'}</div>
        </div>
        <button
          onClick={handleLogout}
          className="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600 transition-all group"
        >
          <LogOut size={20} className="group-hover:text-red-600" />
          Sair do Sistema
        </button>
      </div>
      </aside>
    </>
  );
}
