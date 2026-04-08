import { useEffect, useState } from 'react';
import { Search, ChevronRight, CheckCircle2, Circle } from 'lucide-react';
import { motion } from 'motion/react';
import { useNavigate } from 'react-router-dom';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import api from '../api';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export default function OrderList() {
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('processing');
  const [search, setSearch] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    fetchOrders();
  }, [filter]);

  const fetchOrders = async () => {
    setLoading(true);

    try {
      const res = await api.get('/orders', { params: { status: filter } });
      setOrders(res.data);
    } catch {
      console.error('Failed to fetch orders');
    } finally {
      setLoading(false);
    }
  };

  const togglePacked = async (id: number, current: boolean) => {
    try {
      await api.post(`/orders/${id}/pack`, { packed: !current });
      setOrders(orders.map((order) => (order.id === id ? { ...order, is_packed: !current } : order)));
    } catch {
      console.error('Failed to update packing status');
    }
  };

  const filteredOrders = orders.filter((order) =>
    order.id.toString().includes(search)
      || (order.billing?.first_name || '').toLowerCase().includes(search.toLowerCase())
      || (order.billing?.last_name || '').toLowerCase().includes(search.toLowerCase()),
  );

  return (
    <div className="space-y-8">
      <header className="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Pedidos</h1>
          <p className="text-slate-500 mt-1">Gerencie e monitore todos os pedidos</p>
        </div>

        <div className="flex flex-wrap items-center gap-4">
          <div className="relative group">
            <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors" />
            <input
              type="text"
              placeholder="Buscar por ID ou nome..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all w-72 text-sm shadow-sm"
            />
          </div>
          <div className="flex items-center gap-2 bg-white border border-slate-200 p-1 rounded-xl shadow-sm">
            {['processing', 'on-hold', 'completed', 'any'].map((status) => (
              <button
                key={status}
                onClick={() => setFilter(status)}
                className={cn(
                  'px-4 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all',
                  filter === status
                    ? 'bg-primary text-white shadow-md shadow-primary/20'
                    : 'text-slate-500 hover:bg-slate-50',
                )}
              >
                {status === 'any' ? 'Todos' : status === 'processing' ? 'Processando' : status === 'on-hold' ? 'Aguardando' : 'Concluidos'}
              </button>
            ))}
          </div>
        </div>
      </header>

      <div className="card-modern">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50/50 border-b border-slate-100">
                <th className="p-5 text-xs font-bold text-slate-500 uppercase tracking-widest">ID</th>
                <th className="p-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Cliente</th>
                <th className="p-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Data</th>
                <th className="p-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Total</th>
                <th className="p-5 text-xs font-bold text-slate-500 uppercase tracking-widest">Status</th>
                <th className="p-5 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Embalado</th>
                <th className="p-5"></th>
              </tr>
            </thead>
            <tbody className="text-sm">
              {loading ? (
                <tr>
                  <td colSpan={7} className="p-20 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
                      <span className="text-slate-400 font-medium">Sincronizando pedidos...</span>
                    </div>
                  </td>
                </tr>
              ) : filteredOrders.length === 0 ? (
                <tr>
                  <td colSpan={7} className="p-20 text-center text-slate-400 italic">
                    Nenhum pedido encontrado nesta categoria.
                  </td>
                </tr>
              ) : (
                filteredOrders.map((order) => (
                  <motion.tr
                    key={order.id}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    onClick={() => navigate(`/packing/${order.id}`)}
                    className="border-b border-slate-50 hover:bg-slate-50/50 transition-colors group cursor-pointer"
                  >
                    <td className="p-5 font-bold text-slate-900">#{order.id}</td>
                    <td className="p-5">
                      <div className="font-semibold text-slate-900">{order.billing?.first_name} {order.billing?.last_name}</div>
                      <div className="text-xs text-slate-400">{order.billing?.email}</div>
                    </td>
                    <td className="p-5 text-slate-500">{new Date(order.date_created).toLocaleDateString('pt-BR')}</td>
                    <td className="p-5 font-bold text-slate-900">R$ {parseFloat(order.total).toFixed(2)}</td>
                    <td className="p-5">
                      <span
                        className={cn(
                          'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                          order.status === 'processing' ? 'bg-amber-50 text-amber-600 border-amber-100'
                            : order.status === 'completed' ? 'bg-emerald-50 text-emerald-600 border-emerald-100'
                              : 'bg-slate-50 text-slate-500 border-slate-100',
                        )}
                      >
                        {order.status}
                      </span>
                    </td>
                    <td className="p-5 text-center">
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          togglePacked(order.id, order.is_packed);
                        }}
                        className={cn(
                          'inline-flex items-center justify-center w-10 h-10 rounded-xl transition-all',
                          order.is_packed
                            ? 'bg-emerald-50 text-emerald-600 shadow-sm shadow-emerald-100'
                            : 'bg-slate-50 text-slate-300 hover:text-slate-400',
                        )}
                      >
                        {order.is_packed ? <CheckCircle2 size={22} /> : <Circle size={22} />}
                      </button>
                    </td>
                    <td className="p-5 text-right">
                      <button className="p-2 text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all">
                        <ChevronRight size={20} />
                      </button>
                    </td>
                  </motion.tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
