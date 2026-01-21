/**
 * DID Release Types
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidRelease/types.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

export interface ReleaseResponse {
  success: boolean;
  message: string;
  ddiNumber?: string;
  newDdiId?: number;
  errorCode?: string;
}

export interface MyDdiDetails {
  id: number;
  ddiE164: string;
  countryName: string;
  monthlyPrice: string;
  assignedAt: string;
  nextRenewalAt: string;
}

export type ReleaseModalState = 'confirm' | 'releasing' | 'success' | 'error';
