import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, ChevronDown, Search } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { getFaq, type FaqCategory, type FaqItem } from '@/services/api/support';

export default function Faq() {
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('');
  const [categories, setCategories] = useState<FaqCategory[]>([]);
  const [navCategories, setNavCategories] = useState<FaqCategory[]>([]);
  const [open, setOpen] = useState<number | null>(null);

  useEffect(() => {
    document.title = 'FAQ | Learn by Bluxor';
    getFaq().then(setNavCategories).catch(() => undefined);
  }, []);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      getFaq({ q: query, category }).then((next) => {
        setCategories(next);
        setOpen(null);
      }).catch(() => undefined);
    }, 200);
    return () => window.clearTimeout(timeout);
  }, [category, query]);

  const allItems = useMemo(() => categories.flatMap((group) => group.items.map((item) => ({ ...item, category: group }))), [categories]);

  return (
    <main>
      <section className="border-b border-ink-100 bg-gradient-to-b from-white to-ink-50/60">
        <div className="container-page py-12 text-center lg:py-16">
          <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">FAQ</p>
          <h1 className="mt-3 font-display text-4xl font-bold text-ink-900 lg:text-5xl">Frequently asked questions</h1>
          <p className="mx-auto mt-4 max-w-2xl text-base leading-7 text-ink-600">
            Quick answers about Learn by Bluxor products, purchases, downloads, accounts, payments, and refunds.
          </p>
          <div className="mx-auto mt-8 max-w-xl">
            <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search questions..." leftIcon={<Search className="h-5 w-5" />} />
          </div>
        </div>
      </section>

      <section className="container-page max-w-5xl py-12 lg:py-16">
        <div className="mb-6 flex gap-2 overflow-x-auto pb-1">
          <button onClick={() => setCategory('')} className={`shrink-0 rounded-full px-4 py-2 text-sm font-semibold ${!category ? 'bg-brand-600 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-200'}`}>All</button>
          {navCategories.map((group) => (
            <button key={group.id} onClick={() => setCategory(group.slug)} className={`shrink-0 rounded-full px-4 py-2 text-sm font-semibold ${category === group.slug ? 'bg-brand-600 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-200'}`}>
              {group.name}
            </button>
          ))}
        </div>

        <div className="space-y-3">
          {allItems.map((item) => <FaqRow key={item.id} item={item} open={open === item.id} onToggle={() => setOpen(open === item.id ? null : item.id)} />)}
          {allItems.length === 0 && <p className="rounded-2xl border border-ink-100 bg-white p-6 text-sm text-ink-500">No FAQ entries matched your search.</p>}
        </div>

        <section className="mt-10 rounded-2xl border border-ink-100 bg-ink-900 p-8 text-white">
          <h2 className="font-display text-2xl font-bold">Didn't find your answer?</h2>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-ink-200">Browse the Help Center for detailed guides or contact our support team.</p>
          <div className="mt-5 flex flex-wrap gap-3">
            <Link to="/help"><Button>Browse Help Center</Button></Link>
            <Link to="/contact"><Button variant="outline">Contact Support</Button></Link>
          </div>
        </section>
      </section>
    </main>
  );
}

function FaqRow({ item, open, onToggle }: { item: FaqItem & { category: FaqCategory }; open: boolean; onToggle: () => void }) {
  return (
    <div className="rounded-2xl border border-ink-100 bg-white shadow-soft">
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
      >
        <span>
          <span className="text-xs font-semibold uppercase tracking-wide text-brand-600">{item.category.name}</span>
          <span className="mt-1 block font-bold text-ink-900">{item.question}</span>
        </span>
        <ChevronDown className={`h-5 w-5 shrink-0 text-ink-400 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>
      {open && (
        <div className="border-t border-ink-100 px-5 py-4 text-sm leading-6 text-ink-600">
          {item.answer.split(/(\/[a-z0-9-]+)/gi).map((part, index) => part.startsWith('/') ? (
            <Link key={`${part}-${index}`} to={part} className="font-semibold text-brand-600 hover:text-brand-700">{part}</Link>
          ) : part)}
          <ArrowRight className="ml-1 inline h-3 w-3 text-transparent" />
        </div>
      )}
    </div>
  );
}
