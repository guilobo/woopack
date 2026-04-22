export interface LineItemMeta {
  id?: number;
  key?: string;
  value?: unknown;
  display_key?: string;
  display_value?: unknown;
}

export interface LineItemImage {
  id?: string | number;
  src?: string;
}

export interface LineItem {
  id: number;
  name: string;
  product_id?: number | string;
  variation_id?: number | string;
  quantity: number;
  total: string;
  sku?: string;
  image?: LineItemImage | null;
  meta_data?: LineItemMeta[];
}

export interface GroupedLineItemChild extends LineItem {
  wooco_parent_id: string;
}

export interface GroupedLineItemSingleBlock {
  type: 'single';
  key: string;
  item: LineItem;
}

export interface GroupedLineItemGroupBlock {
  type: 'group';
  key: string;
  parent: LineItem;
  children: GroupedLineItemChild[];
}

export type GroupedLineItemBlock = GroupedLineItemSingleBlock | GroupedLineItemGroupBlock;
