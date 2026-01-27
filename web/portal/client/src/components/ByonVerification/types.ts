/**
 * BYON Verification Types
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/types.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

export type ByonStep = 'phone' | 'verifying' | 'otp' | 'checking' | 'success' | 'error';

export interface ByonStatus {
  byonCount: number;
  byonLimit: number;
  dailyAttemptsRemaining: number;
  dailyAttemptsLimit: number;
  canAddByon: boolean;
  disabledReason: 'BYON_LIMIT_REACHED' | 'DAILY_LIMIT_REACHED' | null;
  dailyResetAt: string | null; // ISO 8601 timestamp
}

export interface InitiateResponse {
  success: boolean;
  message?: string;
  errorCode?: string;
  expiresIn?: number;
  dailyAttemptsRemaining?: number;
  byonCount?: number;
  byonLimit?: number;
}

export interface VerifyResponse {
  success: boolean;
  message?: string;
  errorCode?: string;
  ddi?: {
    id: number;
    number: string;
    isByon: boolean;
  };
}

export interface ValidateResponse {
  valid: boolean;
  error?: string;
  errorCode?: string;
  country?: {
    id: number;
    name: string;
    code: string;
  };
  nationalNumber?: string;
  e164Number?: string;
}

export interface ByonError {
  code: string;
  message: string;
}
