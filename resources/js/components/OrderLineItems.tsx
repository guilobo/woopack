import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, Layers, Package } from 'lucide-react';
import type { GroupedLineItemBlock, LineItem } from '../types/orders';
import { groupLineItems } from '../utils/groupLineItems';

function formatCurrency(value: string): string {
  const parsed = Number.parseFloat(value);

  if (Number.isNaN(parsed)) {
    return '0.00';
  }

  return parsed.toFixed(2);
}

function LineItemCard({
  item,
  isChild = false,
  groupLabel,
}: {
  item: LineItem;
  isChild?: boolean;
  groupLabel?: string;
}) {
  return (
    <div
      className={[
        'flex flex-col gap-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-all sm:flex-row sm:items-center sm:justify-between sm:p-5 group',
        isChild ? 'border-dashed border-slate-200 bg-white/70' : 'hover:border-primary/20',
      ].join(' ')}
    >
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
          {groupLabel && (
            <div className="mb-1 inline-flex items-center gap-1 rounded-full border border-primary/15 bg-primary/5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">
              <Layers size={12} />
              {groupLabel}
            </div>
          )}
          <div className="font-bold text-slate-900 group-hover:text-primary transition-colors">{item.name}</div>
          <div className="text-xs text-slate-400 font-medium mt-0.5">SKU: {item.sku || 'N/A'}</div>
        </div>
      </div>
      <div className="text-right sm:min-w-[96px]">
        <div className="font-bold text-slate-900">R$ {formatCurrency(item.total)}</div>
      </div>
    </div>
  );
}

export default function OrderLineItems({ lineItems }: { lineItems: LineItem[] }) {
  const blocks = useMemo<GroupedLineItemBlock[]>(() => groupLineItems(lineItems), [lineItems]);
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setOpenGroups({});
  }, [lineItems]);

  const toggleGroup = (groupKey: string) => {
    setOpenGroups((prev) => ({
      ...prev,
      [groupKey]: !prev[groupKey],
    }));
  };

  return (
    <div className="grid gap-4">
      {blocks.map((block) => {
        if (block.type === 'single') {
          return <LineItemCard key={block.key} item={block.item} />;
        }

        const isOpen = Boolean(openGroups[block.key]);

        return (
          <div key={block.key} className="rounded-2xl border border-slate-100 bg-slate-50/40 p-2 sm:p-3">
            <button
              type="button"
              onClick={() => toggleGroup(block.key)}
              className="w-full rounded-xl text-left transition-colors hover:bg-white/70 focus:outline-none focus:ring-2 focus:ring-primary/20"
              aria-expanded={isOpen}
            >
              <div className="flex items-center justify-between gap-2 px-1 pb-2 sm:px-2">
                <div className="text-xs font-bold uppercase tracking-wider text-primary">
                  Produto de Grupo
                </div>
                <div className="flex items-center gap-2 text-xs font-semibold text-slate-500">
                  <span>{block.children.length} item(ns)</span>
                  {isOpen ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
                </div>
              </div>
              <LineItemCard item={block.parent} groupLabel="Grupo" />
            </button>

            {isOpen && (
              <div className="mt-3 border-l-2 border-slate-200 pl-2 sm:pl-4 space-y-3">
                {block.children.map((child) => (
                  <LineItemCard key={`group-child-${child.id}`} item={child} isChild groupLabel="Item do Grupo" />
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
