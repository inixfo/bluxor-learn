import type { LucideIcon } from 'lucide-react';

export type ProductType = 'ebook' | 'guide' | 'template' | 'bundle' | 'toolkit';
export type ProductStatus = 'draft' | 'published';

export interface Product {
  id: string;
  slug: string;
  title: string;
  titleBn: string;
  category: string;
  type: ProductType;
  shortDescription: string;
  description: string;
  whatsIncluded: string[];
  regularPrice: number;
  salePrice?: number;
  rating: number;
  reviewCount: number;
  sales: number;
  revenue: number;
  cover: string;
  badges: string[];
  status: ProductStatus;
  updatedAt: string;
  tags: string[];
  fileSize: string;
  format: string;
  updatedAtLabel: string;
}

export interface Category {
  id: string;
  name: string;
  nameBn: string;
  icon: LucideIcon;
  count: number;
  color: 'brand' | 'danger' | 'success' | 'violet' | 'warning';
}

export interface Bundle {
  id: string;
  slug: string;
  title: string;
  titleBn: string;
  description: string;
  productIds: string[];
  regularTotal: number;
  bundlePrice: number;
  cover: string;
  savings: number;
  sales: number;
}

export const formatBDT = (n: number) =>
  `BDT ${n.toLocaleString('en-BD', { maximumFractionDigits: 0 })}`;
