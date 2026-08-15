import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown, Download, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/Button';

const steps = [
  ['Check payment status', 'Your purchase becomes available after the transaction is successfully verified. If payment is pending, avoid creating repeated payments for the same order.'],
  ['Use the correct account', 'If you purchased while signed in, use the same account. If you purchased as a guest, use the same email entered during checkout.'],
  ['Open My Library', 'Signed-in customers can open the library to find purchased products and available download options.'],
  ['Check browser downloads', 'Check download history, your Downloads folder, and any blocked download indicators in the browser toolbar.'],
  ['Try another browser/device', 'Try a current version of Chrome, Edge, Firefox, or Safari. You can also try another device or network if needed.'],
  ['Contact support', 'Provide the purchase email, order reference, product name, and what happens when you try to download. Never send passwords, OTPs, or full payment credentials.'],
];

const issues = [
  ['Download button does not respond', 'Refresh the page, check popup/download indicators, and try again from your library. If it continues, contact support with your browser and order details.'],
  ['File downloaded but will not open', 'Check that the file finished downloading completely and that your device has software that supports the file type.'],
  ['Purchase does not appear in library', 'Confirm you are signed into the same account used at checkout or use the guest purchase email flow where available.'],
  ['Guest purchase access problem', 'Use the same email entered during checkout. If the access link is missing or expired, contact support with the purchase email and order reference.'],
];

export default function DownloadHelp() {
  const [open, setOpen] = useState<number | null>(0);

  return (
    <main>
      <section className="border-b border-ink-100 bg-gradient-to-b from-white to-brand-50/40">
        <div className="container-page py-12 lg:py-16">
          <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">Download Help</p>
          <h1 className="mt-3 font-display text-4xl font-bold text-ink-900 lg:text-5xl">Download & access help</h1>
          <p className="mt-4 max-w-2xl text-base leading-7 text-ink-600">Having trouble opening or downloading your purchase? Start here.</p>
        </div>
      </section>

      <section className="container-page py-12 lg:py-16">
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {steps.map(([title, text], index) => (
            <article key={title} className="rounded-2xl border border-ink-100 bg-white p-6 shadow-soft">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-lg font-bold text-white">{index + 1}</div>
              <h2 className="mt-5 text-lg font-bold text-ink-900">{title}</h2>
              <p className="mt-2 text-sm leading-6 text-ink-600">{text}</p>
              {index === 2 && <Link to="/account/library" className="mt-5 inline-flex"><Button size="sm">Open My Library</Button></Link>}
            </article>
          ))}
        </div>

        <section className="mt-12">
          <h2 className="font-display text-2xl font-bold text-ink-900">Common download issues</h2>
          <div className="mt-5 space-y-3">
            {issues.map(([title, text], index) => (
              <div key={title} className="rounded-2xl border border-ink-100 bg-white shadow-soft">
                <button type="button" aria-expanded={open === index} onClick={() => setOpen(open === index ? null : index)} className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left font-bold text-ink-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                  {title}
                  <ChevronDown className={`h-5 w-5 text-ink-400 transition-transform ${open === index ? 'rotate-180' : ''}`} />
                </button>
                {open === index && <p className="border-t border-ink-100 px-5 py-4 text-sm leading-6 text-ink-600">{text}</p>}
              </div>
            ))}
          </div>
        </section>

        <section className="mt-10 rounded-2xl border border-success-200 bg-success-50 p-6">
          <div className="flex gap-4">
            <ShieldCheck className="mt-1 h-5 w-5 shrink-0 text-success-700" />
            <div>
              <h2 className="text-lg font-bold text-ink-900">Keep your account secure</h2>
              <p className="mt-2 text-sm leading-6 text-ink-600">Learn by Bluxor support will never need your password, one-time password, or complete payment credentials to help with a download issue.</p>
            </div>
          </div>
        </section>

        <section className="mt-10 rounded-2xl border border-ink-100 bg-ink-900 p-8 text-white">
          <Download className="h-6 w-6 text-brand-300" />
          <h2 className="mt-4 font-display text-2xl font-bold">Still unavailable?</h2>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-ink-200">Contact support with your purchase email, order reference, product name, and what happens when you try to download.</p>
          <Link to="/contact" className="mt-5 inline-flex"><Button>Contact Support</Button></Link>
        </section>
      </section>
    </main>
  );
}
