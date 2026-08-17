import { API_BASE_URL } from './api/client';

type MetaConfig = {
  enabled: boolean;
  pixel_id: string;
  require_marketing_consent?: boolean;
};

export type MetaEventPayload = {
  contentIds?: string[];
  contentName?: string;
  contentType?: string;
  value?: number;
  currency?: string;
  numItems?: number;
  eventId?: string;
};

type Fbq = {
  (...args: unknown[]): void;
  callMethod?: (...args: unknown[]) => void;
  queue?: unknown[];
  loaded?: boolean;
  version?: string;
};

declare global {
  interface Window {
    fbq?: Fbq;
    _fbq?: Fbq;
  }
}

let configPromise: Promise<MetaConfig> | null = null;
let initializedPixelId = '';
const trackedKeys = new Set<string>();

export async function initMetaPixel(): Promise<boolean> {
  const config = await getMetaConfig();
  if (!canTrack(config)) return false;
  if (initializedPixelId === config.pixel_id && window.fbq?.loaded) return true;

  installFbqStub();
  if (!document.querySelector('script[data-lbx-meta-pixel="true"]')) {
    const script = document.createElement('script');
    script.async = true;
    script.defer = true;
    script.src = 'https://connect.facebook.net/en_US/fbevents.js';
    script.dataset.lbxMetaPixel = 'true';
    script.onerror = () => undefined;
    document.head.appendChild(script);
  }

  window.fbq?.('init', config.pixel_id);
  initializedPixelId = config.pixel_id;
  return true;
}

export async function trackMetaPageView(path: string): Promise<void> {
  if (!shouldTrackPageView(path)) return;
  if (!await initMetaPixel()) return;
  once(`PageView:${path}`, () => window.fbq?.('track', 'PageView'));
}

export async function trackMetaViewContent(payload: MetaEventPayload): Promise<void> {
  if (!await initMetaPixel()) return;
  once(`ViewContent:${payload.contentIds?.join(',') || payload.contentName || location.pathname}`, () => {
    window.fbq?.('track', 'ViewContent', toPixelPayload(payload));
  });
}

export async function trackMetaInitiateCheckout(payload: MetaEventPayload): Promise<void> {
  if (!await initMetaPixel()) return;
  once(`InitiateCheckout:${payload.contentIds?.join(',')}:${payload.value}:${payload.currency}`, () => {
    window.fbq?.('track', 'InitiateCheckout', toPixelPayload(payload), payload.eventId ? { eventID: payload.eventId } : undefined);
  });
}

export async function trackMetaPurchase(payload: MetaEventPayload & { eventId: string }): Promise<void> {
  const marker = `meta_purchase_sent:${payload.eventId}`;
  if (localStorage.getItem(marker)) return;
  if (!await initMetaPixel()) return;

  window.fbq?.('track', 'Purchase', toPixelPayload(payload), { eventID: payload.eventId });
  localStorage.setItem(marker, '1');
}

export function metaTrackingContext(marketingConsent?: boolean): Record<string, unknown> {
  return {
    fbp: cookie('_fbp'),
    fbc: cookie('_fbc'),
    event_source_url: window.location.href,
    landing_page_url: sessionStorage.getItem('lbx_landing_page_url') || undefined,
    referrer: document.referrer || undefined,
    marketing_consent: marketingConsent,
  };
}

export function rememberLandingSource(): void {
  if (location.pathname.startsWith('/go/')) {
    sessionStorage.setItem('lbx_landing_page_url', location.href);
  }
}

async function getMetaConfig(): Promise<MetaConfig> {
  if (!configPromise) {
    configPromise = fetch(`${API_BASE_URL}/tracking/config`, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then((response) => response.json())
      .then((payload) => payload.data?.meta || { enabled: false, pixel_id: '' })
      .catch(() => ({ enabled: false, pixel_id: '' }));
  }

  return configPromise;
}

function canTrack(config: MetaConfig): boolean {
  if (!config.enabled || !config.pixel_id) return false;
  if (config.require_marketing_consent && localStorage.getItem('lbx_marketing_consent') !== 'true') return false;
  return true;
}

function installFbqStub(): void {
  if (window.fbq) return;
  const fbq = function (...args: unknown[]) {
    if (fbq.callMethod) fbq.callMethod(...args);
    else (fbq.queue ||= []).push(args);
  } as Fbq;
  fbq.queue = [];
  fbq.loaded = true;
  fbq.version = '2.0';
  window.fbq = fbq;
  window._fbq = fbq;
}

function shouldTrackPageView(path: string): boolean {
  if (path.startsWith('/admin') || path.startsWith('/account')) return false;
  return path === '/'
    || path.startsWith('/products')
    || path.startsWith('/categories')
    || path.startsWith('/p/')
    || path.startsWith('/checkout')
    || path.startsWith('/go/');
}

function toPixelPayload(payload: MetaEventPayload): Record<string, unknown> {
  return {
    content_ids: payload.contentIds,
    content_name: payload.contentName,
    content_type: payload.contentType,
    value: payload.value,
    currency: payload.currency,
    num_items: payload.numItems,
  };
}

function once(key: string, callback: () => void): void {
  if (trackedKeys.has(key)) return;
  trackedKeys.add(key);
  try {
    callback();
  } catch {
    trackedKeys.delete(key);
  }
}

function cookie(name: string): string | undefined {
  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));
  return match ? decodeURIComponent(match[1]) : undefined;
}
