import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import { getCategories } from '@/services/api/catalog';
import type { Category } from '@/data/store';

export default function Categories() {
  const [categories, setCategories] = useState<Category[]>([]);
  useEffect(() => { getCategories().then(setCategories); }, []);

  return (
    <div className="container-page py-12">
      <div className="mb-8">
        <h1 className="font-display text-3xl font-bold text-ink-900">All Categories</h1>
        <p className="mt-2 text-sm text-ink-500">Browse active Learn by Bluxor categories.</p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {categories.map((category) => (
          <Link key={category.id} to={`/categories/${category.id}`} className="group rounded-xl border border-ink-100 bg-white p-5 transition hover:border-brand-200 hover:shadow-card">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="font-semibold text-ink-900">{category.name}</p>
                <p className="mt-1 text-sm text-ink-500">{category.count} products</p>
              </div>
              <ArrowRight className="h-5 w-5 text-ink-300 transition group-hover:text-brand-600" />
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
