/**
 * DID Purchase - Type Definitions
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidPurchase/types.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

export interface PriceBreakdownItem {
  description: string;
  amount: number;
}

export interface PurchasePreviewResponse {
  ddi: string;
  ddiId: number;
  country: string;
  setupPrice: number;
  monthlyPrice: number;
  proratedFirstMonth: number;
  totalDueNow: number;
  nextRenewalDate: string;
  nextRenewalAmount: number;
  currentBalance: number;
  balanceAfterPurchase: number;
  canPurchase: boolean;
  breakdown: PriceBreakdownItem[];
}

export interface PurchaseSuccessResponse {
  success: true;
  ddiId: number;
  ddi: string;
  invoiceId: number;
  invoiceNumber: string;
  totalCharged: number;
  currentBalance: number;
}

export interface PurchaseErrorResponse {
  success: false;
  errorCode: string;
  errorMessage: string;
  currentBalance?: number;
  requiredAmount?: number;
}

export type PurchaseResponse = PurchaseSuccessResponse | PurchaseErrorResponse;

export interface DdiDetails {
  id: number;
  ddi: string;
  ddiE164: string;
  country?: string;
  countryName?: string;
  setupPrice: string | number;
  monthlyPrice: string | number;
  inventoryStatus: string;
}

// Order types for postpaid customers
export interface OrderPreviewResponse {
  ddi: string;
  ddiId: number;
  country: string;
  setupFee: number;
  monthlyFee: number;
  canOrder: boolean;
  reservationDuration: string;
}

export interface OrderSuccessResponse {
  success: true;
  orderId: number;
  status: string;
  ddiId: number;
  ddi: string;
  message: string;
}

export interface OrderErrorResponse {
  success: false;
  error: string;
  message: string;
}

export type OrderResponse = OrderSuccessResponse | OrderErrorResponse;
