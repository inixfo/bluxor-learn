import { useMemo } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Button } from '@/components/ui/Button';

const pages: Record<string, { title: string; body: string[] }> = {
  '/about': {
    title: 'About Learn by Bluxor',
    body: ['Learn by Bluxor publishes practical digital learning resources for learners who want usable skills, not passive content.'],
  },
  '/contact': {
    title: 'Contact',
    body: ['For support, order access, payment, or download questions, contact the Learn by Bluxor support team through the official support channel configured for production.'],
  },
  '/help': {
    title: 'Help',
    body: ['After a verified payment, purchases appear in your account library. Guest buyers receive secure access by email and may create an account later using the same email.'],
  },
  '/faq': {
    title: 'FAQ',
    body: ['Products are delivered as protected digital files. Prices and discounts are calculated by the backend at checkout.'],
  },
  '/terms': {
    title: 'Terms',
    body: ['Final legal terms should be reviewed before launch. This page exists so production navigation never points to a dead route.'],
  },
  '/privacy': {
    title: 'Privacy Policy',
    body: ['Final privacy copy should be reviewed before launch. Do not enter sensitive secrets or payment credentials into public forms.'],
  },
  '/refund-policy': {
    title: 'Refund Policy',
    body: ['Digital-product refunds are reviewed case by case and completed only after backend/payment-provider confirmation.'],
  },
};

export default function StaticPage() {
  const location = useLocation();
  const page = useMemo(() => pages[location.pathname] || pages['/help'], [location.pathname]);

  return (
    <div className="container-page py-16">
      <div className="mx-auto max-w-2xl">
        <h1 className="font-display text-3xl font-bold text-ink-900">{page.title}</h1>
        <div className="mt-5 space-y-4 text-sm leading-6 text-ink-600">
          {page.body.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
        </div>
        <Link to="/products" className="mt-8 inline-block">
          <Button>Browse Products</Button>
        </Link>
      </div>
    </div>
  );
}
