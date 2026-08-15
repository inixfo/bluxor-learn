import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Link, useLocation } from 'react-router-dom';
import {
  ArrowRight,
  BookOpen,
  Bot,
  Briefcase,
  CheckCircle2,
  CreditCard,
  Download,
  FileQuestion,
  HelpCircle,
  LockKeyhole,
  Mail,
  PackageCheck,
  RefreshCcw,
  ShieldCheck,
  UserCircle,
} from 'lucide-react';
import { Breadcrumb } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input, Textarea } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { getContentPage, submitContact, type ContentPage } from '@/services/api/content';

type Block =
  | { type: 'paragraph'; text: string }
  | { type: 'list'; items: string[] }
  | { type: 'cta'; label: string; to: string };

type Section = {
  title: string;
  blocks: Block[];
  subsections: { title: string; blocks: Block[] }[];
};

type ParsedPage = {
  eyebrow?: string;
  h1?: string;
  intro: Block[];
  sections: Section[];
};

const pageCategories: Record<string, string> = {
  about: 'Company',
  contact: 'Company',
  help: 'Support',
  faq: 'Support',
  'download-help': 'Support',
  terms: 'Legal',
  privacy: 'Legal',
  'refund-policy': 'Legal',
};

const cardIcons = [BookOpen, Bot, Briefcase, PackageCheck, CheckCircle2, ShieldCheck];
const supportIcons = [PackageCheck, Download, UserCircle, CreditCard, RefreshCcw, FileQuestion];

export default function StaticPage() {
  const location = useLocation();
  const toast = useToast();
  const slug = location.pathname.replace(/^\//, '') || 'help';
  const [page, setPage] = useState<ContentPage | null>(null);
  const [missing, setMissing] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' });
  const [sending, setSending] = useState(false);

  useEffect(() => {
    setPage(null);
    setMissing(false);
    getContentPage(slug).then(setPage).catch(() => setMissing(true));
  }, [slug]);

  useEffect(() => {
    if (!page) return;
    document.title = page.meta_title || `${page.title} | Learn by Bluxor`;
    let meta = document.querySelector<HTMLMetaElement>('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.name = 'description';
      document.head.appendChild(meta);
    }
    meta.content = page.meta_description || '';
  }, [page]);

  const parsed = useMemo(() => parseContent(page?.content || ''), [page?.content]);

  const send = async (event: React.FormEvent) => {
    event.preventDefault();
    setSending(true);
    try {
      const message = await submitContact(form);
      toast({ type: 'success', title: 'Message received', message });
      setForm({ name: '', email: '', subject: '', message: '' });
    } catch {
      toast({ type: 'error', title: 'Message failed', message: 'Check the form and try again.' });
    } finally {
      setSending(false);
    }
  };

  if (missing) {
    return (
      <div className="container-page py-16">
        <div className="mx-auto max-w-2xl rounded-2xl border border-ink-100 bg-white p-8 text-center shadow-soft">
          <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">Page unavailable</p>
          <h1 className="mt-3 font-display text-3xl font-bold text-ink-900">This page is not published.</h1>
          <p className="mt-3 text-sm leading-6 text-ink-500">The requested content page is unavailable or still in draft.</p>
          <Link to="/help" className="mt-6 inline-flex">
            <Button>Visit Help Center</Button>
          </Link>
        </div>
      </div>
    );
  }

  if (!page) {
    return <div className="container-page py-16 text-sm text-ink-500">Loading page...</div>;
  }

  if (slug === 'contact') {
    return <ContactPage page={page} parsed={parsed} form={form} sending={sending} setForm={setForm} send={send} />;
  }

  if (slug === 'about') return <AboutPage page={page} parsed={parsed} />;
  if (slug === 'help') return <HelpPage page={page} parsed={parsed} />;
  if (slug === 'faq') return <FaqPage page={page} parsed={parsed} />;
  if (slug === 'download-help') return <DownloadHelpPage page={page} parsed={parsed} />;
  if (slug === 'terms' || slug === 'privacy') return <LegalPage page={page} parsed={parsed} />;
  if (slug === 'refund-policy') return <RefundPage page={page} parsed={parsed} />;

  return (
    <PageShell page={page} parsed={parsed}>
      <Article sections={parsed.sections} />
    </PageShell>
  );
}

function AboutPage({ page, parsed }: { page: ContentPage; parsed: ParsedPage }) {
  const [belief, findSection, build, principles, cta] = [
    parsed.sections[0],
    parsed.sections.find((section) => section.title === "What you'll find here"),
    parsed.sections.find((section) => section.title === 'Made to be useful, not just readable.'),
    parsed.sections.find((section) => section.title === 'Our principles'),
    parsed.sections.find((section) => section.title === 'Ready to learn something useful?'),
  ];

  return (
    <PageShell page={page} parsed={parsed}>
      {belief && <Article sections={[belief]} />}
      {findSection && <SubsectionCards section={findSection} icons={cardIcons} />}
      {build && <Article sections={[build]} />}
      {principles && <SubsectionCards section={principles} compact icons={cardIcons.slice(2)} />}
      <CtaBand title={cta?.title || 'Ready to learn something useful?'} text={firstParagraph(cta)} label="Explore Products" to="/products" />
    </PageShell>
  );
}

function ContactPage({
  page,
  parsed,
  form,
  sending,
  setForm,
  send,
}: {
  page: ContentPage;
  parsed: ParsedPage;
  form: { name: string; email: string; subject: string; message: string };
  sending: boolean;
  setForm: React.Dispatch<React.SetStateAction<{ name: string; email: string; subject: string; message: string }>>;
  send: (event: React.FormEvent) => Promise<void>;
}) {
  const topics = parsed.sections.find((section) => section.title === 'Support topics');
  const before = parsed.sections.find((section) => section.title === 'Before contacting us');

  return (
    <PageShell page={page} parsed={parsed} wide>
      <div className="grid gap-8 lg:grid-cols-[1fr_420px]">
        <div className="space-y-8">
          {topics && <SubsectionCards section={topics} icons={[PackageCheck, Download, UserCircle, HelpCircle]} compact />}
          {before && (
            <section className="rounded-2xl border border-brand-100 bg-brand-50/60 p-6">
              <h2 className="text-lg font-bold text-ink-900">{before.title}</h2>
              <MarkdownBlocks blocks={before.blocks} className="mt-3" />
              <div className="mt-5 flex flex-wrap gap-3">
                <TextLinkButton to="/help">Visit Help</TextLinkButton>
                <TextLinkButton to="/faq" variant="outline">View FAQ</TextLinkButton>
              </div>
            </section>
          )}
        </div>

        <form onSubmit={send} className="rounded-2xl border border-ink-100 bg-white p-6 shadow-soft">
          <div className="mb-5 flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-white">
              <Mail className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-ink-900">Send a message</h2>
              <p className="text-sm text-ink-500">Use the form below for support and product questions.</p>
            </div>
          </div>
          <div className="space-y-4">
            <Input label="Name" name="name" value={form.name} onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))} required />
            <Input label="Email" name="email" type="email" value={form.email} onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))} required />
            <Input label="Subject" name="subject" value={form.subject} onChange={(event) => setForm((prev) => ({ ...prev, subject: event.target.value }))} required />
            <Textarea label="Message" name="message" rows={7} value={form.message} onChange={(event) => setForm((prev) => ({ ...prev, message: event.target.value }))} required />
            <Button type="submit" loading={sending} className="w-full">Send Message</Button>
          </div>
        </form>
      </div>
    </PageShell>
  );
}

function HelpPage({ page, parsed }: { page: ContentPage; parsed: ParsedPage }) {
  const supportSections = parsed.sections.filter((section) => section.title !== 'Still need help?');
  const cta = parsed.sections.find((section) => section.title === 'Still need help?');

  return (
    <PageShell page={page} parsed={parsed} wide>
      <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        {supportSections.map((section, index) => {
          const Icon = supportIcons[index % supportIcons.length];
          const ctaBlock = section.blocks.find((block): block is Extract<Block, { type: 'cta' }> => block.type === 'cta');
          return (
            <section key={section.title} className="rounded-2xl border border-ink-100 bg-white p-6 shadow-soft">
              <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                <Icon className="h-5 w-5" />
              </div>
              <h2 className="text-base font-bold text-ink-900">{section.title}</h2>
              <MarkdownBlocks blocks={section.blocks.filter((block) => block.type !== 'cta')} className="mt-3" />
              {ctaBlock && (
                <Link to={ctaBlock.to} className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700">
                  {ctaBlock.label} <ArrowRight className="h-4 w-4" />
                </Link>
              )}
            </section>
          );
        })}
      </div>
      <CtaBand title={cta?.title || 'Still need help?'} text={firstParagraph(cta)} label="Contact Support" to="/contact" />
    </PageShell>
  );
}

function FaqPage({ page, parsed }: { page: ContentPage; parsed: ParsedPage }) {
  return (
    <PageShell page={page} parsed={parsed}>
      <div className="space-y-8">
        {parsed.sections.map((section) => (
          <section key={section.title}>
            <h2 className="mb-4 text-xl font-bold text-ink-900">{section.title}</h2>
            <div className="space-y-3">
              {section.subsections.map((item) => (
                <details key={item.title} className="group rounded-2xl border border-ink-100 bg-white p-5 shadow-soft">
                  <summary className="cursor-pointer list-none text-base font-semibold text-ink-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <span className="flex items-center justify-between gap-4">
                      {item.title}
                      <ArrowRight className="h-4 w-4 shrink-0 text-ink-400 transition-transform group-open:rotate-90" />
                    </span>
                  </summary>
                  <MarkdownBlocks blocks={item.blocks} className="mt-4" />
                </details>
              ))}
            </div>
          </section>
        ))}
      </div>
    </PageShell>
  );
}

function DownloadHelpPage({ page, parsed }: { page: ContentPage; parsed: ParsedPage }) {
  const security = parsed.sections.find((section) => section.title === 'Keep your account secure');
  const steps = parsed.sections.filter((section) => section.title !== 'Keep your account secure');

  return (
    <PageShell page={page} parsed={parsed}>
      <div className="space-y-5">
        {steps.map((section, index) => (
          <section key={section.title} className="grid gap-4 rounded-2xl border border-ink-100 bg-white p-5 shadow-soft sm:grid-cols-[72px_1fr]">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-lg font-bold text-white">
              {index + 1}
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-brand-600">{section.title}</p>
              {section.subsections[0] && <h2 className="mt-1 text-xl font-bold text-ink-900">{section.subsections[0].title}</h2>}
              <MarkdownBlocks blocks={[...section.blocks, ...(section.subsections[0]?.blocks || [])]} className="mt-3" />
            </div>
          </section>
        ))}
      </div>
      {security && (
        <section className="mt-8 rounded-2xl border border-success-200 bg-success-50 p-6">
          <div className="flex gap-4">
            <LockKeyhole className="mt-1 h-5 w-5 shrink-0 text-success-700" />
            <div>
              <h2 className="text-lg font-bold text-ink-900">{security.title}</h2>
              <MarkdownBlocks blocks={security.blocks} className="mt-2" />
            </div>
          </div>
        </section>
      )}
      <CtaBand title="Still need download help?" text="Send support the purchase email, order reference, product name, and what went wrong." label="Contact Support" to="/contact" />
    </PageShell>
  );
}

function LegalPage({ page, parsed }: { page: ContentPage; parsed: ParsedPage }) {
  return (
    <PageShell page={page} parsed={parsed} legal>
      <LegalLayout page={page} parsed={parsed} />
    </PageShell>
  );
}

function RefundPage({ page, parsed }: { page: ContentPage; parsed: ParsedPage }) {
  const summary = parsed.sections.find((section) => section.title === 'Digital purchases');
  const rest = parsed.sections.filter((section) => section.title !== 'Digital purchases');

  return (
    <PageShell page={page} parsed={parsed} legal>
      {summary && (
        <section className="mb-8 rounded-2xl border border-brand-200 bg-brand-50 p-6">
          <h2 className="text-lg font-bold text-ink-900">{summary.title}</h2>
          <MarkdownBlocks blocks={summary.blocks} className="mt-3" />
        </section>
      )}
      <LegalLayout page={page} parsed={{ ...parsed, sections: rest }} showUpdated={false} />
      <CtaBand title="Questions" text="If you're unsure whether your situation qualifies, contact us and explain the issue." label="Contact Support" to="/contact" />
    </PageShell>
  );
}

function PageShell({
  page,
  parsed,
  children,
  wide = false,
  legal = false,
}: {
  page: ContentPage;
  parsed: ParsedPage;
  children: ReactNode;
  wide?: boolean;
  legal?: boolean;
}) {
  const title = parsed.h1 || page.title;
  const category = pageCategories[page.slug] || 'Page';

  return (
    <main>
      <section className="border-b border-ink-100 bg-gradient-to-b from-white to-ink-50/60">
        <div className={`container-page py-10 sm:py-14 ${wide ? '' : 'max-w-5xl'}`}>
          <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: category }, { label: page.title }]} />
          <div className="mt-8 max-w-3xl">
            {parsed.eyebrow && <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">{parsed.eyebrow}</p>}
            {legal && <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">Last updated: {formatDate(page.updated_at)}</p>}
            <h1 className="mt-3 font-display text-3xl font-bold leading-tight text-ink-900 sm:text-4xl lg:text-5xl">{title}</h1>
            <MarkdownBlocks blocks={parsed.intro} className="mt-5 max-w-2xl text-base leading-7 text-ink-600" />
          </div>
        </div>
      </section>
      <div className={`container-page py-12 sm:py-16 ${wide ? '' : 'max-w-5xl'}`}>{children}</div>
    </main>
  );
}

function LegalLayout({ page, parsed, showUpdated = true }: { page: ContentPage; parsed: ParsedPage; showUpdated?: boolean }) {
  return (
    <div className="grid gap-8 lg:grid-cols-[220px_1fr]">
      <aside className="hidden lg:block">
        <nav className="sticky top-24 space-y-2 text-sm" aria-label={`${page.title} sections`}>
          {showUpdated && <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-ink-400">Last updated {formatDate(page.updated_at)}</p>}
          {parsed.sections.map((section) => (
            <a key={section.title} href={`#${sectionId(section.title)}`} className="block rounded-lg px-3 py-2 text-ink-500 hover:bg-ink-50 hover:text-brand-600">
              {section.title}
            </a>
          ))}
        </nav>
      </aside>
      <Article sections={parsed.sections} />
    </div>
  );
}

function Article({ sections }: { sections: Section[] }) {
  return (
    <article className="space-y-10">
      {sections.map((section) => (
        <section key={section.title} id={sectionId(section.title)} className="scroll-mt-24">
          <h2 className="font-display text-2xl font-bold text-ink-900">{section.title}</h2>
          <MarkdownBlocks blocks={section.blocks} className="mt-4" />
          {section.subsections.length > 0 && (
            <div className="mt-5 space-y-5">
              {section.subsections.map((subsection) => (
                <div key={subsection.title}>
                  <h3 className="text-base font-bold text-ink-900">{subsection.title}</h3>
                  <MarkdownBlocks blocks={subsection.blocks} className="mt-2" />
                </div>
              ))}
            </div>
          )}
        </section>
      ))}
    </article>
  );
}

function SubsectionCards({ section, icons, compact = false }: { section: Section; icons: typeof cardIcons; compact?: boolean }) {
  return (
    <section>
      <h2 className="font-display text-2xl font-bold text-ink-900">{section.title}</h2>
      <div className={`mt-5 grid gap-4 ${compact ? 'sm:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-2'}`}>
        {section.subsections.map((item, index) => {
          const Icon = icons[index % icons.length];
          return (
            <article key={item.title} className="rounded-2xl border border-ink-100 bg-white p-5 shadow-soft">
              <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                <Icon className="h-5 w-5" />
              </div>
              <h3 className="font-bold text-ink-900">{item.title}</h3>
              <MarkdownBlocks blocks={item.blocks} className="mt-2" />
            </article>
          );
        })}
      </div>
    </section>
  );
}

function CtaBand({ title, text, label, to }: { title: string; text?: string; label: string; to: string }) {
  return (
    <section className="mt-10 rounded-2xl border border-ink-100 bg-ink-900 p-6 text-white shadow-soft sm:p-8">
      <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="font-display text-2xl font-bold">{title}</h2>
          {text && <p className="mt-2 max-w-2xl text-sm leading-6 text-ink-200">{text}</p>}
        </div>
        <Link to={to} className="shrink-0">
          <Button variant="primary" rightIcon={<ArrowRight className="h-4 w-4" />}>{label}</Button>
        </Link>
      </div>
    </section>
  );
}

function TextLinkButton({ to, children, variant = 'primary' }: { to: string; children: ReactNode; variant?: 'primary' | 'outline' }) {
  return (
    <Link to={to}>
      <Button variant={variant}>{children}</Button>
    </Link>
  );
}

function MarkdownBlocks({ blocks, className = '' }: { blocks: Block[]; className?: string }) {
  if (!blocks.length) return null;

  return (
    <div className={`space-y-4 text-sm leading-6 text-ink-600 ${className}`}>
      {blocks.map((block, index) => {
        if (block.type === 'list') {
          return (
            <ul key={index} className="list-disc space-y-2 pl-5">
              {block.items.map((item) => <li key={item}>{renderInline(item)}</li>)}
            </ul>
          );
        }
        if (block.type === 'cta') {
          return (
            <Link key={index} to={block.to} className="inline-flex items-center gap-1.5 font-semibold text-brand-600 hover:text-brand-700">
              {block.label} <ArrowRight className="h-4 w-4" />
            </Link>
          );
        }
        return <p key={index}>{renderInline(block.text)}</p>;
      })}
    </div>
  );
}

function parseContent(content: string): ParsedPage {
  const parsed: ParsedPage = { intro: [], sections: [] };
  const lines = content.replace(/\r\n/g, '\n').split('\n');
  let section: Section | null = null;
  let subsection: Section['subsections'][number] | null = null;
  let paragraph: string[] = [];
  let list: string[] = [];

  const targetBlocks = () => (subsection ? subsection.blocks : section ? section.blocks : parsed.intro);
  const flushParagraph = () => {
    if (paragraph.length) {
      targetBlocks().push({ type: 'paragraph', text: paragraph.join(' ') });
      paragraph = [];
    }
  };
  const flushList = () => {
    if (list.length) {
      targetBlocks().push({ type: 'list', items: list });
      list = [];
    }
  };
  const flushAll = () => {
    flushParagraph();
    flushList();
  };

  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (!line) {
      flushAll();
      continue;
    }

    if (line.startsWith('Eyebrow:')) {
      flushAll();
      parsed.eyebrow = line.replace('Eyebrow:', '').trim();
      continue;
    }

    if (line.startsWith('# ')) {
      flushAll();
      parsed.h1 = line.slice(2).trim();
      continue;
    }

    if (line.startsWith('## ')) {
      flushAll();
      section = { title: line.slice(3).trim(), blocks: [], subsections: [] };
      parsed.sections.push(section);
      subsection = null;
      continue;
    }

    if (line.startsWith('### ')) {
      flushAll();
      if (!section) {
        section = { title: 'More information', blocks: [], subsections: [] };
        parsed.sections.push(section);
      }
      subsection = { title: line.slice(4).trim(), blocks: [] };
      section.subsections.push(subsection);
      continue;
    }

    if (line.startsWith('* ') || line.startsWith('- ')) {
      flushParagraph();
      list.push(line.slice(2).trim());
      continue;
    }

    if (line.startsWith('CTA:')) {
      flushAll();
      const [label, to = '#'] = line.replace('CTA:', '').split('->').map((part) => part.trim());
      targetBlocks().push({ type: 'cta', label, to });
      continue;
    }

    paragraph.push(line);
  }

  flushAll();
  return parsed;
}

function renderInline(text: string): ReactNode {
  if (text === 'My Library') {
    return <Link to="/account/library" className="font-semibold text-brand-600 hover:text-brand-700">My Library</Link>;
  }

  const parts = text.split(/(\/[a-z0-9-]+(?:\/[a-z0-9-]+)?)/gi);
  return parts.map((part, index) => (
    part.startsWith('/') ? (
      <Link key={`${part}-${index}`} to={part} className="font-semibold text-brand-600 hover:text-brand-700">
        {part}
      </Link>
    ) : part
  ));
}

function firstParagraph(section?: Section): string | undefined {
  return section?.blocks.find((block): block is Extract<Block, { type: 'paragraph' }> => block.type === 'paragraph')?.text;
}

function sectionId(title: string): string {
  return title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function formatDate(value?: string | null): string {
  if (!value) return 'Not published';
  return new Intl.DateTimeFormat('en', { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(value));
}
