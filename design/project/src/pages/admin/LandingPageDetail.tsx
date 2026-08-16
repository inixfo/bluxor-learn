import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowDown, ArrowLeft, ArrowUp, BarChart3, CheckCircle2, Download, Eye, FileCode2, Globe, History, Plus, Settings as SettingsIcon, Trash2, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { ApiError } from '@/services/api/client';
import { assignLandingOffers, displayMinor, displaySize, getLandingAnalytics, getLandingPage, getPreviewUrl, publishLandingVersion, searchOfferItems, updateLandingProduct, type LandingAnalytics, type LandingOfferPayload, type LandingPageAdmin, type OfferItem } from '@/services/api/landing';
import { useToast } from '@/components/ui/Toast';

const tabs = [
  { id: 'overview', label: 'Overview', icon: FileCode2 },
  { id: 'analytics', label: 'Analytics', icon: BarChart3 },
  { id: 'versions', label: 'Versions', icon: History },
  { id: 'settings', label: 'Settings', icon: SettingsIcon },
];

export default function AdminLandingPageDetail() {
  const { id = '' } = useParams();
  const toast = useToast();
  const [activeTab, setActiveTab] = useState('overview');
  const [page, setPage] = useState<LandingPageAdmin | null>(null);
  const [analytics, setAnalytics] = useState<LandingAnalytics | null>(null);
  const [offerItems, setOfferItems] = useState<{ product: OfferItem[]; bundle: OfferItem[] }>({ product: [], bundle: [] });
  const [offerDrafts, setOfferDrafts] = useState<LandingOfferPayload[]>([]);
  const [associatedProductId, setAssociatedProductId] = useState('');

  const applyPage = (next: LandingPageAdmin) => {
    setPage(next);
    setAssociatedProductId(next.primary_product_id ? String(next.primary_product_id) : '');
    setOfferDrafts((next.offers || []).map((offer) => ({
      offer_key: offer.offer_key,
      offer_type: offer.offer_type as 'product' | 'bundle',
      product_id: offer.product_id || null,
      bundle_id: offer.bundle_id || null,
      is_primary: offer.is_primary,
    })));
  };

  const load = () => {
    getLandingPage(id).then(applyPage);
    getLandingAnalytics(Number(id)).then(setAnalytics);
  };

  useEffect(load, [id]);
  useEffect(() => {
    searchOfferItems('product').then((items) => setOfferItems((prev) => ({ ...prev, product: items })));
    searchOfferItems('bundle').then((items) => setOfferItems((prev) => ({ ...prev, bundle: items })));
  }, []);

  const preview = async (versionId?: number) => {
    if (!versionId) return;
    window.open(await getPreviewUrl(versionId), '_blank', 'noopener,noreferrer');
  };

  const publish = async (versionId?: number) => {
    if (!page || !versionId) return;
    await publishLandingVersion(page.id, versionId);
    toast({ type: 'success', title: 'Version published' });
    load();
  };

  const saveOffers = async () => {
    if (!page) return;
    const validation = validateOfferDrafts(offerDrafts);
    if (validation) {
      toast({ type: 'error', title: 'Unable to save offers', message: validation });
      return;
    }

    try {
      const next = await assignLandingOffers(page.id, {
        primary_product_id: page.primary_product_id || Number(associatedProductId) || offerDrafts.find((offer) => offer.offer_type === 'product' && offer.is_primary)?.product_id || null,
        offers: offerDrafts.map((offer) => ({
          ...offer,
          offer_key: offer.offer_key.trim(),
          is_primary: !!offer.is_primary,
        })),
      });
      applyPage(next);
      toast({ type: 'success', title: 'Offers saved' });
      load();
    } catch (error) {
      toast({ type: 'error', title: 'Unable to save offers', message: apiErrorMessage(error, 'Add at least one valid offer.') });
    }
  };

  const saveAssociatedProduct = async () => {
    if (!page || !associatedProductId) return;
    try {
      const next = await updateLandingProduct(page.id, Number(associatedProductId));
      applyPage(next);
      toast({ type: 'success', title: 'Associated product updated.' });
      load();
    } catch (error) {
      toast({ type: 'error', title: 'Unable to update associated product', message: apiErrorMessage(error, 'Choose a published product and try again.') });
    }
  };

  const updateOffer = (index: number, patch: Partial<LandingOfferPayload>) => {
    setOfferDrafts((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...patch } : row));
  };

  const addOffer = () => {
    setOfferDrafts((rows) => {
      const first = rows.length === 0;
      const associatedId = Number(page?.primary_product_id || associatedProductId || 0) || null;
      return [
        ...rows,
        {
          offer_key: first ? 'single' : `offer-${rows.length + 1}`,
          offer_type: 'product',
          product_id: first ? associatedId : offerItems.product[0]?.id || null,
          bundle_id: null,
          is_primary: first,
        },
      ];
    });
  };

  if (!page) {
    return <div className="text-sm text-ink-500">Loading landing page...</div>;
  }

  return (
    <div>
      <div className="mb-6">
        <Link to="/admin/landing-pages" className="mb-2 flex items-center gap-1.5 text-sm text-ink-400 hover:text-brand-600">
          <ArrowLeft className="h-4 w-4" /> Landing Pages
        </Link>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="font-display text-2xl font-bold text-ink-900">{page.name}</h1>
            <p className="mt-1 text-sm text-ink-500">Assigned to: {page.product || 'Unassigned'}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" leftIcon={<Eye className="h-4 w-4" />} onClick={() => preview(page.published_version_id || page.versions?.[0]?.id)}>Preview</Button>
            <Link to="/admin/landing-pages/upload"><Button variant="outline" size="sm" leftIcon={<Upload className="h-4 w-4" />}>New Version</Button></Link>
            {page.published_version_id && <a href={`/api/v1/admin/landing-page-versions/${page.published_version_id}/download`}><Button variant="outline" size="sm" leftIcon={<Download className="h-4 w-4" />}>Source</Button></a>}
          </div>
        </div>
      </div>

      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Card className="p-4"><p className="text-xs text-ink-400">Public URL</p><p className="mt-1 flex items-center gap-1.5 truncate text-sm font-medium text-brand-600"><Globe className="h-3.5 w-3.5 shrink-0" /> /go/{page.slug}</p></Card>
        <Card className="p-4"><p className="text-xs text-ink-400">Status</p><div className="mt-1"><Badge tone={page.status === 'published' ? 'success' : 'warning'}>{page.status}</Badge></div></Card>
        <Card className="p-4"><p className="text-xs text-ink-400">Current Version</p><p className="mt-1 text-sm font-bold text-ink-900">{page.version || 'none'}</p></Card>
        <Card className="p-4"><p className="text-xs text-ink-400">Updated</p><p className="mt-1 text-sm font-bold text-ink-900">{new Date(page.updated_at).toLocaleDateString()}</p></Card>
      </div>

      <div className="mb-6 flex gap-1 overflow-x-auto border-b border-ink-100 no-scrollbar">
        {tabs.map((tab) => (
          <button key={tab.id} onClick={() => setActiveTab(tab.id)} className={`flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${activeTab === tab.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-800'}`}>
            <tab.icon className="h-4 w-4" /> {tab.label}
          </button>
        ))}
      </div>

      {activeTab === 'overview' && (
        <div className="space-y-5">
          <Card className="p-6">
            <h2 className="mb-4 text-base font-bold text-ink-900">Page Details</h2>
            <dl className="grid gap-4 sm:grid-cols-2">
              <div><dt className="text-xs text-ink-400">Name</dt><dd className="mt-0.5 text-sm font-medium text-ink-900">{page.name}</dd></div>
              <div><dt className="text-xs text-ink-400">Slug</dt><dd className="mt-0.5 font-mono text-sm text-ink-900">/go/{page.slug}</dd></div>
              <div><dt className="text-xs text-ink-400">Assigned Product</dt><dd className="mt-0.5 text-sm font-medium text-ink-900">{page.product || 'Unassigned'}</dd></div>
              <div><dt className="text-xs text-ink-400">Offers</dt><dd className="mt-0.5 text-sm font-medium text-ink-900">{page.offers?.map((offer) => offer.offer_key).join(', ') || 'None'}</dd></div>
            </dl>
          </Card>

          <Card className="p-5">
            <div className="mb-5">
              <h2 className="mb-2 text-sm font-bold text-ink-900">Associated Product</h2>
              <p className="mb-3 text-xs leading-5 text-ink-500">The main product sold by this landing page.</p>
              <div className="flex gap-2">
                <select value={associatedProductId} onChange={(event) => setAssociatedProductId(event.target.value)} className="min-w-0 flex-1 rounded-lg border border-ink-200 px-3 py-2 text-sm">
                  <option value="">Select product</option>
                  {offerItems.product.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                <Button size="sm" onClick={saveAssociatedProduct}>Save</Button>
              </div>
              <p className="mt-1 text-xs text-ink-400">Saving creates or updates the default primary offer when needed.</p>
            </div>
            <div className="mb-4 flex items-center justify-between">
              <div>
                <h2 className="text-sm font-bold text-ink-900">Offer Assignment</h2>
                <p className="mt-1 text-xs leading-5 text-ink-500">Optional advanced offers for bundles, alternate products, or multiple CTA options.</p>
              </div>
              <Button size="sm" variant="outline" leftIcon={<Plus className="h-4 w-4" />} onClick={addOffer}>Add</Button>
            </div>
            <div className="space-y-3">
              {offerDrafts.length === 0 && (
                <div className="rounded-lg border border-dashed border-ink-200 bg-ink-50/70 px-4 py-5 text-sm text-ink-500">
                  No advanced offers are configured yet. Use Associated Product for the default product, or add an offer for alternate CTA options.
                </div>
              )}
              {offerDrafts.map((offer, index) => {
                const items = offerItems[offer.offer_type];
                const selectedId = offer.offer_type === 'bundle' ? offer.bundle_id : offer.product_id;
                return (
                  <div key={`${offer.offer_key}-${index}`} className="grid gap-3 rounded-lg border border-ink-100 p-3 lg:grid-cols-[140px_130px_1fr_140px_120px]">
                    <input value={offer.offer_key} onChange={(event) => updateOffer(index, { offer_key: event.target.value })} className="rounded-lg border border-ink-200 px-3 py-2 text-sm" />
                    <select value={offer.offer_type} onChange={(event) => updateOffer(index, { offer_type: event.target.value as 'product' | 'bundle', product_id: null, bundle_id: null })} className="rounded-lg border border-ink-200 px-3 py-2 text-sm">
                      <option value="product">Product</option>
                      <option value="bundle">Bundle</option>
                    </select>
                    <select value={selectedId || ''} onChange={(event) => {
                      const nextId = Number(event.target.value) || null;
                      updateOffer(index, offer.offer_type === 'bundle' ? { bundle_id: nextId, product_id: null } : { product_id: nextId, bundle_id: null });
                    }} className="rounded-lg border border-ink-200 px-3 py-2 text-sm">
                      <option value="">Select item</option>
                      {items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                    </select>
                    <label className="flex items-center gap-2 text-sm text-ink-600">
                      <input type="checkbox" checked={!!offer.is_primary} onChange={() => setOfferDrafts((rows) => rows.map((row, rowIndex) => ({ ...row, is_primary: rowIndex === index })))} />
                      Featured
                    </label>
                    <div className="flex justify-end gap-1">
                      <Button size="icon" variant="ghost" disabled={index === 0} onClick={() => setOfferDrafts((rows) => rows.map((row, rowIndex) => rowIndex === index - 1 ? rows[index] : rowIndex === index ? rows[index - 1] : row))}><ArrowUp className="h-4 w-4" /></Button>
                      <Button size="icon" variant="ghost" disabled={index === offerDrafts.length - 1} onClick={() => setOfferDrafts((rows) => rows.map((row, rowIndex) => rowIndex === index + 1 ? rows[index] : rowIndex === index ? rows[index + 1] : row))}><ArrowDown className="h-4 w-4" /></Button>
                      <Button size="icon" variant="ghost" onClick={() => setOfferDrafts((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 className="h-4 w-4" /></Button>
                    </div>
                  </div>
                );
              })}
            </div>
            <div className="mt-4 flex justify-end">
              <Button onClick={saveOffers} disabled={offerDrafts.length === 0}>Save Offers</Button>
            </div>
          </Card>
        </div>
      )}

      {activeTab === 'analytics' && analytics && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card className="p-4"><p className="text-xs text-ink-400">Visitors</p><p className="mt-1 text-2xl font-bold text-ink-900">{analytics.visitors.toLocaleString()}</p></Card>
          <Card className="p-4"><p className="text-xs text-ink-400">Conversion Rate</p><p className="mt-1 text-2xl font-bold text-ink-900">{analytics.conversion_rate}%</p></Card>
          <Card className="p-4"><p className="text-xs text-ink-400">Revenue</p><p className="mt-1 text-2xl font-bold text-ink-900">{formatBDT(displayMinor(analytics.revenue_minor))}</p></Card>
          <Card className="p-4"><p className="text-xs text-ink-400">AOV</p><p className="mt-1 text-2xl font-bold text-ink-900">{formatBDT(displayMinor(analytics.aov_minor))}</p></Card>
        </div>
      )}

      {activeTab === 'versions' && (
        <Card className="p-5">
          <h2 className="mb-4 text-sm font-bold text-ink-900">Version History</h2>
          <div className="space-y-3">
            {(page.versions || []).map((version) => {
              const current = page.published_version_id === version.id;
              return (
                <div key={version.id} className="flex items-center gap-4 rounded-xl border border-ink-100 p-4">
                  <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${current ? 'bg-success-100 text-success-600' : 'bg-ink-100 text-ink-500'}`}>
                    {current ? <CheckCircle2 className="h-5 w-5" /> : <History className="h-5 w-5" />}
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-bold text-ink-900">v{version.version_number}</p>
                      {current && <Badge tone="success">Current</Badge>}
                      <Badge tone={version.status === 'validated' || version.status === 'published' ? 'success' : 'warning'}>{version.status}</Badge>
                    </div>
                    <p className="text-xs text-ink-400">{new Date(version.created_at).toLocaleDateString()} / SDK {version.sdk_version} / {displaySize(version.package_size_bytes)}</p>
                  </div>
                  <div className="flex gap-1">
                    <Button size="sm" variant="ghost" onClick={() => preview(version.id)}><Eye className="h-4 w-4" /></Button>
                    {!current && <Button size="sm" variant="ghost" onClick={() => publish(version.id)}>Restore</Button>}
                    <a href={`/api/v1/admin/landing-page-versions/${version.id}/download`}><Button size="sm" variant="ghost"><Download className="h-4 w-4" /></Button></a>
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      )}

      {activeTab === 'settings' && (
        <Card className="p-6">
          <h2 className="mb-4 text-base font-bold text-ink-900">Landing Page Settings</h2>
          <div className="flex items-center justify-between rounded-xl border border-ink-100 p-4">
            <div><p className="text-sm font-semibold text-ink-900">Publish status</p><p className="text-xs text-ink-400">Publishing is version-based and preserves rollback history.</p></div>
            <Badge tone={page.status === 'published' ? 'success' : 'warning'}>{page.status}</Badge>
          </div>
        </Card>
      )}
    </div>
  );
}

function validateOfferDrafts(offers: LandingOfferPayload[]): string | null {
  if (offers.length === 0) return 'Add at least one offer before saving.';

  const keys = offers.map((offer) => offer.offer_key.trim());
  if (keys.some((key) => key === '')) return 'Offer key is required for every offer.';
  if (new Set(keys).size !== keys.length) return 'Offer keys must be unique for this landing page.';

  for (const offer of offers) {
    if (offer.offer_type === 'product' && !offer.product_id) return `Offer ${offer.offer_key || '(untitled)'} needs a product.`;
    if (offer.offer_type === 'bundle' && !offer.bundle_id) return `Offer ${offer.offer_key || '(untitled)'} needs a bundle.`;
  }

  if (offers.filter((offer) => offer.is_primary).length !== 1) return 'Select exactly one featured offer.';

  return null;
}

function apiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) return error.message || fallback;
  if (error instanceof Error) return error.message || fallback;
  return fallback;
}
