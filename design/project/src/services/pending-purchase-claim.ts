import { claimPurchase, type PurchaseClaimResult } from '@/services/api/account';

const STORAGE_KEY = 'learn_bluxor.pending_purchase_claim';

export type PendingPurchaseClaim = {
  order_number: string;
  guest_access_token: string;
};

export function storePendingPurchaseClaim(claim: PendingPurchaseClaim): void {
  if (!claim.order_number || !claim.guest_access_token) return;
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(claim));
}

export function getPendingPurchaseClaim(): PendingPurchaseClaim | null {
  const raw = sessionStorage.getItem(STORAGE_KEY);
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw) as Partial<PendingPurchaseClaim>;
    if (typeof parsed.order_number === 'string' && typeof parsed.guest_access_token === 'string' && parsed.order_number && parsed.guest_access_token) {
      return { order_number: parsed.order_number, guest_access_token: parsed.guest_access_token };
    }
  } catch {
    clearPendingPurchaseClaim();
  }

  return null;
}

export function clearPendingPurchaseClaim(): void {
  sessionStorage.removeItem(STORAGE_KEY);
}

export async function claimPendingPurchase(): Promise<PurchaseClaimResult | null> {
  const pending = getPendingPurchaseClaim();
  if (!pending) return null;

  const result = await claimPurchase(pending);
  clearPendingPurchaseClaim();

  return result;
}
