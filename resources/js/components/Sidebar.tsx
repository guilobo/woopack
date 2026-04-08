import { NavLink, useNavigate } from 'react-router-dom';
import { LayoutDashboard, ListOrdered, Package, LogOut } from 'lucide-react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import api from '../api';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

interface SidebarProps {
  onLogout: () => void;
}

export default function Sidebar({ onLogout }: SidebarProps) {
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await api.post('/logout');
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
  ];

  return (
    <aside className="w-72 bg-white border-r border-slate-200 flex flex-col">
      <div className="p-8">
        <div className="flex items-center gap-3 mb-2">
          <div className="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/20">
            <Package size={24} />
          </div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">WooPack</h1>
        </div>
        <p className="text-slate-400 text-[11px] font-medium uppercase tracking-wider ml-1">Logistica Inteligente</p>
      </div>

      <nav className="flex-1 px-4 py-4 space-y-1">
        {navItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
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

      <div className="p-6 border-t border-slate-100">
        <button
          onClick={handleLogout}
          className="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600 transition-all group"
        >
          <LogOut size={20} className="group-hover:text-red-600" />
          Sair do Sistema
        </button>
      </div>
    </aside>
  );
}
