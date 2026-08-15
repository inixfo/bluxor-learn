import { Link } from 'react-router-dom';
import { ShieldCheck } from 'lucide-react';

const sections = [
  {
    title: 'Company',
    links: [
      { label: 'About Bluxor', to: '/about' },
      { label: 'Contact', to: '/contact' },
    ],
  },
  {
    title: 'Support',
    links: [
      { label: 'Help', to: '/help' },
      { label: 'FAQ', to: '/faq' },
      { label: 'Download Help', to: '/download-help' },
    ],
  },
  {
    title: 'Legal',
    links: [
      { label: 'Terms', to: '/terms' },
      { label: 'Privacy', to: '/privacy' },
      { label: 'Refund Policy', to: '/refund-policy' },
    ],
  },
];

export function Footer() {
  return (
    <footer className="border-t border-ink-100 bg-white">
      <div className="container-page py-12">
        <div className="grid grid-cols-2 gap-8 md:grid-cols-6">
          <div className="col-span-2 md:col-span-3">
            <Link to="/" className="flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-700 text-white">
                <ShieldCheck className="h-5 w-5" />
              </div>
              <span className="text-base font-bold text-ink-900">Learn by Bluxor</span>
            </Link>
            <p className="mt-3 max-w-xs text-sm text-ink-500">
              Practical ebooks, guides, and resources to help you learn modern skills and actually use them.
            </p>
          </div>

          {sections.map((section) => (
            <div key={section.title}>
              <h4 className="text-sm font-semibold text-ink-900">{section.title}</h4>
              <ul className="mt-3 space-y-2">
                {section.links.map((link) => (
                  <li key={link.label}>
                    <Link to={link.to} className="text-sm text-ink-500 transition-colors hover:text-brand-600">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-10 flex flex-col items-center justify-between gap-3 border-t border-ink-100 pt-6 sm:flex-row">
          <p className="text-xs text-ink-400">&copy; {new Date().getFullYear()} Bluxor. All rights reserved.</p>
          <p className="text-xs text-ink-400">learn.bluxor.com</p>
        </div>
      </div>
    </footer>
  );
}
