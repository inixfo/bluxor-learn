import { Bot, Briefcase, Code2, Laptop, Megaphone, ShieldCheck, type LucideIcon } from 'lucide-react';
import { apiRequest, minorToDisplay } from './client';
import type { Category, Product, ProductType } from '@/data/store';

export interface ApiProduct extends Product {
  backendId: number;
  files?: unknown[];
  currency: string;
}

export interface ApiBundle {
  id: string;
  backendId: number;
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
  currency: string;
  products?: ApiProduct[];
}

type CatalogProductResponse = {
  id: number;
  slug: string;
  title: string;
  title_bn?: string | null;
  category?: string | null;
  category_slug?: string | null;
  type: string;
  short_description?: string | null;
  description?: string | null;
  regular_price_minor: number;
  sale_price_minor?: number | null;
  currency: string;
  cover?: string | null;
  featured?: boolean;
  tags?: string[];
  files?: unknown[];
  metadata?: Record<string, unknown> | null;
};

type CatalogBundleResponse = {
  id: number;
  slug: string;
  title: string;
  title_bn?: string | null;
  description?: string | null;
  regular_value_minor: number;
  bundle_price_minor: number;
  sale_price_minor?: number | null;
  currency: string;
  cover?: string | null;
  products?: CatalogProductResponse[] | number[];
};

type CategoryResponse = {
  id: number;
  name: string;
  name_bn?: string | null;
  slug: string;
  products_count?: number;
};

const categoryIcons: Record<string, LucideIcon> = {
  ai: Bot,
  security: ShieldCheck,
  freelance: Briefcase,
  web: Code2,
  business: Laptop,
  marketing: Megaphone,
};

const categoryColors: Record<string, Category['color']> = {
  ai: 'brand',
  security: 'danger',
  freelance: 'success',
  web: 'violet',
  business: 'warning',
  marketing: 'brand',
};

export async function getProducts(params: URLSearchParams): Promise<ApiProduct[]> {
  const response = await apiRequest<{ data: CatalogProductResponse[] }>(`/products?${params.toString()}`);
  return response.data.map(toUiProduct);
}

export async function getHomeCatalog(): Promise<{ featured: ApiProduct[]; newArrivals: ApiProduct[]; categories: Category[]; bundles: ApiBundle[] }> {
  const response = await apiRequest<{ data: { featured_products: CatalogProductResponse[]; new_arrivals: CatalogProductResponse[]; categories: CategoryResponse[]; bundles: CatalogBundleResponse[] } }>('/home');
  return {
    featured: response.data.featured_products.map(toUiProduct),
    newArrivals: response.data.new_arrivals.map(toUiProduct),
    categories: response.data.categories.map(toUiCategory),
    bundles: response.data.bundles.map(toUiBundle),
  };
}

export async function searchProducts(term: string): Promise<ApiProduct[]> {
  const params = new URLSearchParams();
  if (term.trim()) params.set('q', term.trim());
  const response = await apiRequest<{ data: CatalogProductResponse[] }>(`/search/products?${params.toString()}`);
  return response.data.map(toUiProduct);
}

export async function getCategories(): Promise<Category[]> {
  const response = await apiRequest<{ data: CategoryResponse[] }>('/categories');
  return response.data.map(toUiCategory);
}

function toUiCategory(category: CategoryResponse): Category {
  return {
    id: category.slug,
    name: category.name,
    nameBn: category.name_bn || category.name,
    icon: categoryIcons[category.slug] || Laptop,
    count: category.products_count || 0,
    color: categoryColors[category.slug] || 'brand',
  };
}

export async function getCatalogItem(slug: string): Promise<{ kind: 'product'; item: ApiProduct } | { kind: 'bundle'; item: ApiBundle }> {
  const response = await apiRequest<{ data: ({ kind: 'product' } & CatalogProductResponse) | ({ kind: 'bundle' } & CatalogBundleResponse) }>(`/catalog/${slug}`);

  if (response.data.kind === 'bundle') {
    return { kind: 'bundle', item: toUiBundle(response.data) };
  }

  return { kind: 'product', item: toUiProduct(response.data) };
}

export function toUiProduct(product: CatalogProductResponse): ApiProduct {
  const salePrice = minorToDisplay(product.sale_price_minor);
  const regularPrice = minorToDisplay(product.regular_price_minor) || 0;

  return {
    backendId: product.id,
    id: String(product.id),
    slug: product.slug,
    title: product.title,
    titleBn: product.title_bn || product.title,
    category: product.category || 'Digital Product',
    type: (product.type as ProductType) || 'ebook',
    shortDescription: product.short_description || '',
    description: product.description || product.short_description || '',
    whatsIncluded: Array.isArray(product.files) && product.files.length > 0 ? product.files.map((_, i) => `Protected resource ${i + 1}`) : ['Protected digital download', 'Lifetime access'],
    regularPrice,
    salePrice,
    rating: 4.8,
    reviewCount: 0,
    sales: 0,
    revenue: 0,
    cover: product.cover || '',
    badges: product.featured ? ['Best Seller'] : [],
    status: 'published',
    updatedAt: new Date().toISOString(),
    tags: product.tags || [],
    fileSize: 'Protected',
    format: 'Digital download',
    updatedAtLabel: 'Recently',
    files: product.files,
    currency: product.currency,
  };
}

export function toUiBundle(bundle: CatalogBundleResponse): ApiBundle {
  const regularTotal = minorToDisplay(bundle.regular_value_minor) || 0;
  const bundlePrice = minorToDisplay(bundle.sale_price_minor ?? bundle.bundle_price_minor) || 0;
  const products = Array.isArray(bundle.products) && typeof bundle.products[0] === 'object'
    ? (bundle.products as CatalogProductResponse[]).map(toUiProduct)
    : undefined;

  return {
    backendId: bundle.id,
    id: String(bundle.id),
    slug: bundle.slug,
    title: bundle.title,
    titleBn: bundle.title_bn || bundle.title,
    description: bundle.description || '',
    productIds: products?.map((product) => product.id) || (bundle.products as number[] | undefined)?.map(String) || [],
    regularTotal,
    bundlePrice,
    cover: bundle.cover || '',
    savings: Math.max(0, regularTotal - bundlePrice),
    sales: 0,
    currency: bundle.currency,
    products,
  };
}
