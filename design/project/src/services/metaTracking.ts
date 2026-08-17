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
    visitor_id: persistentId('lbx_vid', 'v'),
    session_id: activeSessionId(),
    first_touch: localJson('lbx_first_touch'),
    last_touch: localJson('lbx_last_touch'),
  };
}

export function rememberLandingSource(): void {
  try {
    if (location.pathname.startsWith('/go/')) {
      sessionStorage.setItem('lbx_landing_page_url', location.href);
    }
    const touch = attributionTouch();
    if (!localJson('lbx_first_touch')) {
      localStorage.setItem('lbx_first_touch', JSON.stringify(touch));
    }
    const lastTouch = localJson('lbx_last_touch');
    if (!lastTouch || sourceOf(touch).toLowerCase() !== 'direct') {
      localStorage.setItem('lbx_last_touch', JSON.stringify(touch));
    }
  } catch {
    // Attribution storage is best-effort and must not block checkout or Meta tracking.
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

function persistentId(key: string, prefix: string): string | undefined {
  try {
    let value = localStorage.getItem(key);
    if (!value) {
      value = `${prefix}_${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`;
      localStorage.setItem(key, value);
    }
    return value;
  } catch {
    return undefined;
  }
}

function activeSessionId(): string | undefined {
  try {
    const nowMs = Date.now();
    const key = 'lbx_sid';
    const seenKey = 'lbx_session_seen_at';
    const lastSeen = Number(sessionStorage.getItem(seenKey) || localStorage.getItem(seenKey) || 0);
    let value = localStorage.getItem(key);
    if (!value || !lastSeen || nowMs - lastSeen > 30 * 60 * 1000) {
      value = `s_${Math.random().toString(36).slice(2)}${nowMs.toString(36)}`;
      localStorage.setItem(key, value);
    }
    sessionStorage.setItem(seenKey, String(nowMs));
    localStorage.setItem(seenKey, String(nowMs));
    return value;
  } catch {
    return undefined;
  }
}

function localJson(key: string): Record<string, unknown> | undefined {
  try {
    const value = localStorage.getItem(key);
    if (!value) return undefined;
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed as Record<string, unknown> : undefined;
  } catch {
    return undefined;
  }
}

function attributionTouch(): Record<string, unknown> {
  const params = new URLSearchParams(location.search);
  return {
    current_url: location.href,
    landing_url: location.href,
    path: location.pathname,
    referrer: document.referrer || undefined,
    referrer_host: hostFrom(document.referrer) || undefined,
    utm_source: params.get('utm_source') || undefined,
    utm_medium: params.get('utm_medium') || undefined,
    utm_campaign: params.get('utm_campaign') || undefined,
    utm_content: params.get('utm_content') || undefined,
    utm_term: params.get('utm_term') || undefined,
    fbclid: params.get('fbclid') || undefined,
    gclid: params.get('gclid') || undefined,
    msclkid: params.get('msclkid') || undefined,
    ttclid: params.get('ttclid') || undefined,
    occurred_at: new Date().toISOString(),
  };
}

function sourceOf(touch: Record<string, unknown>): string {
  if (typeof touch.utm_source === 'string' && touch.utm_source) return touch.utm_source;
  if (typeof touch.fbclid === 'string' && touch.fbclid) return 'Facebook';
  if (typeof touch.gclid === 'string' && touch.gclid) return 'Google';
  if (typeof touch.msclkid === 'string' && touch.msclkid) return 'Microsoft';
  if (typeof touch.ttclid === 'string' && touch.ttclid) return 'TikTok';
  if (typeof touch.referrer_host === 'string' && touch.referrer_host) return touch.referrer_host;
  return 'Direct';
}

function hostFrom(value: string): string | undefined {
  try {
    return value ? new URL(value).hostname : undefined;
  } catch {
    return undefined;
  }
}
