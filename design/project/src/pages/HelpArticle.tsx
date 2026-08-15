import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import { Breadcrumb } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { getHelpArticle, type HelpArticle as HelpArticleType } from '@/services/api/support';

export default function HelpArticle() {
  const { categorySlug = '', articleSlug = '' } = useParams();
  const [article, setArticle] = useState<HelpArticleType | null>(null);
  const [related, setRelated] = useState<HelpArticleType[]>([]);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    setMissing(false);
    getHelpArticle(categorySlug, articleSlug)
      .then((data) => {
        setArticle(data.article);
        setRelated(data.related);
        document.title = `${data.article.title} | Learn by Bluxor Help`;
      })
      .catch(() => setMissing(true));
  }, [articleSlug, categorySlug]);

  if (missing) return <div className="container-page py-16 text-sm text-ink-500">This help article is unavailable.</div>;
  if (!article) return <div className="container-page py-16 text-sm text-ink-500">Loading article...</div>;

  return (
    <main className="container-page py-10 lg:py-14">
      <div className="mx-auto max-w-4xl">
        <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Help Center', to: '/help' }, { label: article.category?.name || 'Article' }, { label: article.title }]} />
        <article className="mt-8 max-w-3xl">
          <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">{article.category?.name}</p>
          <h1 className="mt-3 font-display text-4xl font-bold leading-tight text-ink-900">{article.title}</h1>
          {article.summary && <p className="mt-4 text-base leading-7 text-ink-600">{article.summary}</p>}
          <div className="mt-8 space-y-4 text-sm leading-7 text-ink-700">
            {article.content.split(/\n\n+/).map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
          </div>
        </article>

        {related.length > 0 && (
          <section className="mt-12 border-t border-ink-100 pt-8">
            <h2 className="text-xl font-bold text-ink-900">Related articles</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              {related.map((item) => (
                <Link key={item.id} to={`/help/${item.category?.slug}/${item.slug}`} className="rounded-2xl border border-ink-100 bg-white p-5 shadow-soft hover:border-brand-200">
                  <h3 className="font-bold text-ink-900">{item.title}</h3>
                  <p className="mt-2 text-sm leading-6 text-ink-600">{item.summary}</p>
                </Link>
              ))}
            </div>
          </section>
        )}

        <section className="mt-10 rounded-2xl border border-ink-100 bg-ink-900 p-6 text-white">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="text-xl font-bold">Still need help?</h2>
              <p className="mt-1 text-sm text-ink-200">Send support the details of what you tried and where you got stuck.</p>
            </div>
            <Link to="/contact"><Button rightIcon={<ArrowRight className="h-4 w-4" />}>Contact Support</Button></Link>
          </div>
        </section>
      </div>
    </main>
  );
}
