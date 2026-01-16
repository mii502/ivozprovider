/**
 * Balance Top-Up - Type Definitions
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/BalanceTopUp/types.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-15
 */

export interface BalanceData {
  balance: number;
  currency: string;
  billingMethod: 'prepaid' | 'pseudoprepaid' | 'postpaid';
  showTopUp: boolean;
  minAmount: number;
  maxAmount: number;
}

export interface TopUpResponse {
  success: boolean;
  invoiceId?: number;
  amount?: number;
  whmcsRedirectUrl?: string;
  error?: string;
}

export interface Transaction {
  id: number;
  type?: string;  // Optional - determined from amount if not present
  amount: number;
  balance_after: number;
  created_at: string;
  reference?: string | null;
}

export interface TransactionHistoryResponse {
  transactions: Transaction[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
  };
}
