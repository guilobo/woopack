import type { GroupedLineItemBlock, GroupedLineItemChild, LineItem, LineItemMeta } from '../types/orders';

function normalizeMetaString(value: unknown): string | null {
  if (typeof value === 'number') {
    return String(value);
  }

  if (typeof value !== 'string') {
    return null;
  }

  const normalized = value.trim();

  return normalized.length > 0 ? normalized : null;
}

function getMetaValue(metaData: LineItemMeta[] | undefined, key: string): string | null {
  if (!Array.isArray(metaData)) {
    return null;
  }

  const entry = metaData.find((meta) => meta.key === key);

  return normalizeMetaString(entry?.value);
}

function getProductId(item: LineItem): string | null {
  return normalizeMetaString(item.product_id);
}

function getWoocoParentId(item: LineItem): string | null {
  return getMetaValue(item.meta_data, 'wooco_parent_id');
}

export function groupLineItems(lineItems: LineItem[]): GroupedLineItemBlock[] {
  const childrenByParentId = new Map<string, GroupedLineItemChild[]>();
  const parentProductIds = new Set<string>();

  lineItems.forEach((item) => {
    const parentId = getWoocoParentId(item);
    const productId = getProductId(item);

    if (!parentId && productId) {
      parentProductIds.add(productId);
    }

    if (!parentId) {
      return;
    }

    const child: GroupedLineItemChild = {
      ...item,
      wooco_parent_id: parentId,
    };

    const current = childrenByParentId.get(parentId) ?? [];
    current.push(child);
    childrenByParentId.set(parentId, current);
  });

  const consumedParentIds = new Set<string>();
  const blocks: GroupedLineItemBlock[] = [];

  lineItems.forEach((item) => {
    const itemKey = `line-item-${item.id}`;
    const parentId = getWoocoParentId(item);

    if (parentId) {
      if (parentProductIds.has(parentId)) {
        return;
      }

      blocks.push({
        type: 'single',
        key: itemKey,
        item,
      });
      return;
    }

    const productId = getProductId(item);

    if (!productId) {
      blocks.push({
        type: 'single',
        key: itemKey,
        item,
      });
      return;
    }

    const children = childrenByParentId.get(productId) ?? [];

    if (children.length === 0 || consumedParentIds.has(productId)) {
      blocks.push({
        type: 'single',
        key: itemKey,
        item,
      });
      return;
    }

    consumedParentIds.add(productId);
    blocks.push({
      type: 'group',
      key: `line-group-${item.id}`,
      parent: item,
      children,
    });
  });

  return blocks;
}
