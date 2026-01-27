/**
 * BYON API Hook - API interactions for BYON verification
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/useByonApi.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import { useCallback, useState } from 'react';
import { useStoreActions } from 'store';

import { ByonStatus, InitiateResponse, VerifyResponse } from './types';

interface UseByonApiReturn {
  status: ByonStatus | null;
  loading: boolean;
  error: string | null;
  fetchStatus: () => Promise<ByonStatus | null>;
  initiate: (phoneNumber: string) => Promise<InitiateResponse>;
  verify: (phoneNumber: string, code: string) => Promise<VerifyResponse>;
}

export const useByonApi = (): UseByonApiReturn => {
  const [status, setStatus] = useState<ByonStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const apiGet = useStoreActions((store) => store.api.get);
  const apiPost = useStoreActions((store) => store.api.post);
  const [, cancelToken] = useCancelToken();

  const fetchStatus = useCallback(async (): Promise<ByonStatus | null> => {
    setLoading(true);
    setError(null);

    try {
      let result: ByonStatus | null = null;

      await apiGet({
        path: '/byon/status',
        params: {},
        cancelToken,
        successCallback: (response: unknown) => {
          result = response as ByonStatus;
          setStatus(result);
        },
      });

      setLoading(false);
      return result;
    } catch (err) {
      setError('Failed to fetch BYON status');
      setLoading(false);
      return null;
    }
  }, [apiGet, cancelToken]);

  const initiate = useCallback(async (phoneNumber: string): Promise<InitiateResponse> => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiPost({
        path: '/byon/initiate',
        values: { phoneNumber },
        contentType: 'application/json',
        cancelToken,
        silenceErrors: true,
      });

      // apiPost returns the raw response - could be wrapped in data property or direct
      const result = (response?.data || response) as InitiateResponse;
      setLoading(false);

      // Update status after successful initiate
      if (result.success) {
        setStatus((prev) => prev ? {
          ...prev,
          dailyAttemptsRemaining: result.dailyAttemptsRemaining ?? prev.dailyAttemptsRemaining,
          byonCount: result.byonCount ?? prev.byonCount,
        } : prev);
      }

      return result;
    } catch (err: unknown) {
      const errorData = err as { data?: { errorCode?: string; message?: string; detail?: string } };
      setLoading(false);
      return {
        success: false,
        errorCode: errorData?.data?.errorCode || 'UNKNOWN_ERROR',
        message: errorData?.data?.message || errorData?.data?.detail || 'An error occurred',
      };
    }
  }, [apiPost, cancelToken]);

  const verify = useCallback(async (phoneNumber: string, code: string): Promise<VerifyResponse> => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiPost({
        path: '/byon/verify',
        values: { phoneNumber, code },
        contentType: 'application/json',
        cancelToken,
        silenceErrors: true,
      });

      const result = (response?.data || response) as VerifyResponse;
      setLoading(false);

      // Update status after successful verify
      if (result.success) {
        setStatus((prev) => prev ? {
          ...prev,
          byonCount: prev.byonCount + 1,
          canAddByon: prev.byonCount + 1 < prev.byonLimit,
        } : prev);
      }

      return result;
    } catch (err: unknown) {
      const errorData = err as { data?: { errorCode?: string; message?: string; detail?: string } };
      setLoading(false);
      return {
        success: false,
        errorCode: errorData?.data?.errorCode || 'UNKNOWN_ERROR',
        message: errorData?.data?.message || errorData?.data?.detail || 'Verification failed',
      };
    }
  }, [apiPost, cancelToken]);

  return {
    status,
    loading,
    error,
    fetchStatus,
    initiate,
    verify,
  };
};

export default useByonApi;
