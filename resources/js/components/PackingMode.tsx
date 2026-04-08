import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight, Package, CheckSquare, ArrowLeft, CheckCircle2, Clock } from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../api';

export default function PackingMode() {
  const [orders, setOrders] = useState<any[]>([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const { orderId } = useParams();

  useEffect(() => {
    fetchPendingOrders();
  }, []);

  const fetchPendingOrders = async () => {
    try {
      const res = await api.get('/orders', { params: { status: 'processing' } });
      let pending = res.data.filter((order: any) => !order.is_packed);

      if (orderId) {
        const index = pending.findIndex((order: any) => order.id.toString() === orderId);

        if (index !== -1) {
          setCurrentIndex(index);
        } else {
          try {
            const orderRes = await api.get(`/orders/${orderId}`);
            pending = [orderRes.data, ...pending];
            setCurrentIndex(0);
          } catch {
            console.error('Requested order not found');
          }
        }
      }

      setOrders(pending);
    } catch {
      console.error('Failed to fetch pending orders');
    } finally {
      setLoading(false);
    }
  };

  const togglePacked = async (id: number) => {
    try {
      await api.post(`/orders/${id}/pack`, { packed: true });
      const newOrders = orders.filter((order) => order.id !== id);
      setOrders(newOrders);

      if (currentIndex >= newOrders.length && newOrders.length > 0) {
        setCurrentIndex(newOrders.length - 1);
      }
    } catch {
      console.error('Failed to update packing status');
    }
  };

  const nextOrder = () => {
    if (currentIndex < orders.length - 1) {
      setCurrentIndex(currentIndex + 1);
    }
  };

  const prevOrder = () => {
    if (currentIndex > 0) {
      setCurrentIndex(currentIndex - 1);
    }
  };

  if (loading) return (
    <div className="flex items-center justify-center h-full">
      <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (orders.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center h-full space-y-8">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="p-12 bg-white rounded-3xl shadow-xl shadow-slate-200/50 text-center max-w-md border border-slate-100"
        >
          <div className="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-6">
            <CheckCircle2 size={48} />
          </div>
          <h2 className="text-2xl font-bold text-slate-900 mb-2">Tudo Pronto!</h2>
          <p className="text-slate-500">Nao ha mais pedidos pendentes para embalagem no momento.</p>
          <button onClick={() => navigate('/')} className="mt-8 btn-primary w-full">
            Voltar ao Dashboard
          </button>
        </motion.div>
      </div>
    );
  }

  const currentOrder = orders[currentIndex];

  return (
    <div className="h-full flex flex-col space-y-6 sm:space-y-8">
      <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <button
          onClick={() => navigate('/orders')}
          className="flex items-center gap-2 self-start rounded-xl px-4 py-2 text-sm font-medium text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-900"
        >
          <ArrowLeft size={18} /> Sair do Modo
        </button>

        <div className="flex items-center justify-between gap-4 sm:justify-center sm:gap-6">
          <button
            onClick={prevOrder}
            disabled={currentIndex === 0}
            className="w-10 h-10 flex items-center justify-center bg-slate-50 text-slate-600 rounded-xl hover:bg-slate-100 disabled:opacity-30 transition-all"
          >
            <ChevronLeft size={24} />
          </button>

          <div className="text-center min-w-[120px]">
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Fila de Embalagem</div>
            <div className="text-lg font-bold text-slate-900 tabular-nums">
              {currentIndex + 1} <span className="text-slate-300 font-medium mx-1">/</span> {orders.length}
            </div>
          </div>

          <button
            onClick={nextOrder}
            disabled={currentIndex === orders.length - 1}
            className="w-10 h-10 flex items-center justify-center bg-slate-50 text-slate-600 rounded-xl hover:bg-slate-100 disabled:opacity-30 transition-all"
          >
            <ChevronRight size={24} />
          </button>
        </div>

        <div className="flex items-center gap-3 px-4 py-2 bg-amber-50 text-amber-600 rounded-xl border border-amber-100">
          <Clock size={16} />
          <span className="text-xs font-bold uppercase tracking-wider">Pendente</span>
        </div>
      </div>

      <AnimatePresence mode="wait">
        <motion.div
          key={currentOrder.id}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -20 }}
          className="flex-1 grid grid-cols-1 gap-6 overflow-hidden pb-4 lg:grid-cols-3 lg:gap-8"
        >
          <div className="space-y-6 overflow-y-auto custom-scrollbar lg:col-span-2 lg:pr-2">
            <div className="card-modern p-6 sm:p-8 lg:p-10">
              <div className="mb-8 flex flex-col gap-6 sm:mb-10 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <div className="text-primary font-bold text-sm mb-1">PEDIDO</div>
                  <h2 className="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">#{currentOrder.id}</h2>
                  <p className="text-slate-400 text-sm mt-2 font-medium">
                    {new Date(currentOrder.date_created).toLocaleString('pt-BR')}
                  </p>
                </div>
                <div className="text-right">
                  <div className="text-slate-400 text-xs font-bold uppercase tracking-widest mb-2">Cliente</div>
                  <div className="text-xl font-bold text-slate-900">{currentOrder.billing?.first_name} {currentOrder.billing?.last_name}</div>
                  <div className="text-slate-500 text-sm">{currentOrder.billing?.phone}</div>
                </div>
              </div>

              <div className="space-y-6">
                <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
                  <Package size={20} className="text-primary" />
                  <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Itens para Conferencia</h3>
                </div>
                <div className="grid gap-4">
                  {currentOrder.line_items.map((item: any) => (
                    <div key={item.id} className="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:border-primary/20 sm:flex-row sm:items-center sm:justify-between sm:p-5 group">
                      <div className="flex items-center gap-4 sm:gap-5">
                        <div className="relative">
                          <div className="w-16 h-16 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex items-center justify-center">
                            {item.image?.src ? (
                              <img
                                src={item.image.src}
                                alt={item.name}
                                className="w-full h-full object-cover"
                                referrerPolicy="no-referrer"
                              />
                            ) : (
                              <Package size={24} className="text-slate-300" />
                            )}
                          </div>
                          <div className="absolute -top-2 -right-2 w-7 h-7 bg-primary text-white rounded-full flex items-center justify-center text-xs font-bold shadow-md border-2 border-white">
                            {item.quantity}
                          </div>
                        </div>
                        <div>
                          <div className="font-bold text-slate-900 group-hover:text-primary transition-colors">{item.name}</div>
                          <div className="text-xs text-slate-400 font-medium mt-0.5">SKU: {item.sku || 'N/A'}</div>
                        </div>
                      </div>
                      <div className="text-right sm:min-w-[96px]">
                        <div className="font-bold text-slate-900">R$ {parseFloat(item.total).toFixed(2)}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-6 flex flex-col">
            <div className="card-modern p-6 sm:p-8">
              <h3 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-6">Endereco de Entrega</h3>
              <div className="text-slate-700 space-y-2">
                <p className="font-bold text-slate-900 text-lg mb-4">{currentOrder.shipping?.first_name} {currentOrder.shipping?.last_name}</p>
                <div className="space-y-1 text-sm font-medium leading-relaxed">
                  <p>{currentOrder.shipping?.address_1}</p>
                  {currentOrder.shipping?.address_2 && <p>{currentOrder.shipping?.address_2}</p>}
                  <p>{currentOrder.shipping?.city}, {currentOrder.shipping?.state}</p>
                  <p className="text-slate-400">{currentOrder.shipping?.postcode}</p>
                </div>
              </div>
            </div>

            <div className="card-modern flex-1 p-6 sm:p-8">
              <h3 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Notas do Cliente</h3>
              <div className="p-4 bg-amber-50 rounded-xl border border-amber-100 text-amber-800 text-sm italic leading-relaxed">
                {currentOrder.customer_note || 'Nenhuma observacao especial para este pedido.'}
              </div>
            </div>

            <button
              onClick={() => togglePacked(currentOrder.id)}
              className="flex w-full flex-col items-center justify-center gap-4 rounded-3xl bg-primary p-6 text-white shadow-xl shadow-primary/20 transition-all hover:-translate-y-1 hover:shadow-2xl hover:shadow-primary/30 sm:p-8 group"
            >
              <div className="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <CheckSquare size={32} />
              </div>
              <div className="text-xl font-bold tracking-tight">Finalizar Embalagem</div>
              <p className="text-white/60 text-xs font-medium uppercase tracking-widest">Confirmar conferencia</p>
            </button>
          </div>
        </motion.div>
      </AnimatePresence>
    </div>
  );
}
