/**
 * Purchase Modal - DID purchase confirmation with balance check
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidPurchase/PurchaseModal.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import ErrorMessageComponent from '@irontec/ivoz-ui/components/ErrorMessageComponent';
import Modal from '@irontec/ivoz-ui/components/shared/Modal/Modal';
import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import {
  Alert,
  Box,
  CircularProgress,
  styled,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { useStoreActions } from 'store';

import InsufficientBalance from './InsufficientBalance';
import PriceBreakdown from './PriceBreakdown';
import {
  DdiDetails,
  PurchasePreviewResponse,
  PurchaseResponse,
} from './types';

interface PurchaseModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  ddi: DdiDetails;
  className?: string;
}

type ModalState = 'loading' | 'preview' | 'insufficient' | 'purchasing' | 'success' | 'error';

const PurchaseModal = (props: PurchaseModalProps): JSX.Element => {
  const { open, onClose, onSuccess, ddi, className } = props;

  const [state, setState] = useState<ModalState>('loading');
  const [preview, setPreview] = useState<PurchasePreviewResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [purchaseResult, setPurchaseResult] = useState<PurchaseResponse | null>(null);

  const apiPost = useStoreActions((store) => store.api.post);
  const [, cancelToken] = useCancelToken();

  // Fetch purchase preview when modal opens
  useEffect(() => {
    if (!open) {
      // Reset state when modal closes
      setState('loading');
      setPreview(null);
      setError(null);
      setPurchaseResult(null);
      return;
    }

    const fetchPreview = async () => {
      setState('loading');
      setError(null);

      try {
        const response = await apiPost({
          path: '/dids/purchase/preview',
          values: { ddiId: ddi.id },
          contentType: 'application/json',
          cancelToken: cancelToken,
          silenceErrors: true,
        });

        const previewData = response.data as PurchasePreviewResponse;
        setPreview(previewData);

        if (previewData.canPurchase) {
          setState('preview');
        } else {
          setState('insufficient');
        }
      } catch (err: unknown) {
        const errorData = err as { data?: { detail?: string; message?: string } };
        setError(
          errorData?.data?.detail ||
            errorData?.data?.message ||
            _('Failed to fetch purchase details')
        );
        setState('error');
      }
    };

    fetchPreview();
  }, [open, ddi.id, apiPost, cancelToken]);

  // Handle purchase confirmation
  const handlePurchase = useCallback(async () => {
    setState('purchasing');
    setError(null);

    try {
      const response = await apiPost({
        path: '/dids/purchase',
        values: { ddiId: ddi.id },
        contentType: 'application/json',
        cancelToken: cancelToken,
        silenceErrors: true,
      });

      const result = response.data as PurchaseResponse;
      setPurchaseResult(result);

      if (result.success) {
        setState('success');
      } else {
        setError(result.errorMessage);
        setState('error');
      }
    } catch (err: unknown) {
      const errorData = err as { data?: { detail?: string; errorMessage?: string } };
      setError(
        errorData?.data?.detail ||
          errorData?.data?.errorMessage ||
          _('Purchase failed. Please try again.')
      );
      setState('error');
    }
  }, [apiPost, cancelToken, ddi.id]);

  const handleClose = () => {
    if (state === 'purchasing') return;
    onClose();
    if (state === 'success') {
      onSuccess();
    }
  };

  // Determine modal buttons based on state
  const getModalButtons = () => {
    switch (state) {
      case 'loading':
      case 'purchasing':
        return [];
      case 'preview':
        return [
          {
            label: _('Cancel'),
            onClick: handleClose,
            variant: 'outlined' as const,
          },
          {
            label: _('Confirm Purchase'),
            onClick: handlePurchase,
            variant: 'solid' as const,
            autoFocus: true,
          },
        ];
      case 'insufficient':
      case 'error':
      case 'success':
        return [
          {
            label: _('Close'),
            onClick: handleClose,
            variant: 'outlined' as const,
            autoFocus: true,
          },
        ];
      default:
        return [];
    }
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title={_('Purchase DID')}
      buttons={getModalButtons()}
      keepMounted={false}
    >
      <Box className={className}>
        {/* DID Header */}
        <Box className="ddi-header">
          <Typography variant="h5" className="ddi-number">
            {ddi.ddiE164}
          </Typography>
          <Typography variant="body2" className="ddi-country">
            {ddi.countryName || ddi.country}
          </Typography>
        </Box>

        {/* Loading State */}
        {state === 'loading' && (
          <Box className="loading-container">
            <CircularProgress size={40} />
            <Typography variant="body1" className="loading-text">
              {_('Loading purchase details...')}
            </Typography>
          </Box>
        )}

        {/* Preview State - Can Purchase */}
        {state === 'preview' && preview && (
          <>
            <PriceBreakdown
              breakdown={preview.breakdown}
              totalDueNow={preview.totalDueNow}
              currentBalance={preview.currentBalance}
              balanceAfterPurchase={preview.balanceAfterPurchase}
            />
            <Box className="renewal-info">
              <Typography variant="body2">
                {_('Next renewal: {{date}} ({{amount}})', {
                  date: new Date(preview.nextRenewalDate).toLocaleDateString(),
                  amount: `€${preview.nextRenewalAmount.toFixed(2)}/mo`,
                })}
              </Typography>
            </Box>
          </>
        )}

        {/* Insufficient Balance State */}
        {state === 'insufficient' && preview && (
          <InsufficientBalance
            currentBalance={preview.currentBalance}
            requiredAmount={preview.totalDueNow}
          />
        )}

        {/* Purchasing State */}
        {state === 'purchasing' && (
          <Box className="loading-container">
            <CircularProgress size={40} />
            <Typography variant="body1" className="loading-text">
              {_('Processing purchase...')}
            </Typography>
          </Box>
        )}

        {/* Success State */}
        {state === 'success' && purchaseResult && purchaseResult.success && (
          <Box className="success-container">
            <CheckCircleIcon className="success-icon" color="success" />
            <Alert severity="success" className="success-alert">
              {_('DID purchased successfully!')}
            </Alert>
            <Box className="success-details">
              <Typography variant="body2">
                {_('Invoice')}: {purchaseResult.invoiceNumber}
              </Typography>
              <Typography variant="body2">
                {_('Amount charged')}: €{purchaseResult.totalCharged.toFixed(2)}
              </Typography>
              <Typography variant="body2">
                {_('Remaining balance')}: €{purchaseResult.currentBalance.toFixed(2)}
              </Typography>
            </Box>
            <Typography variant="body2" className="next-steps">
              {_('You can now configure this number in "My Phone Numbers".')}
            </Typography>
          </Box>
        )}

        {/* Error State */}
        {state === 'error' && error && (
          <Alert severity="error" className="error-alert">
            {error}
          </Alert>
        )}
      </Box>
    </Modal>
  );
};

const StyledPurchaseModal = styled(PurchaseModal)(({ theme }) => ({
  minWidth: '360px',
  maxWidth: '480px',

  '& .ddi-header': {
    textAlign: 'center',
    marginBottom: 'var(--spacing-lg)',
    paddingBottom: 'var(--spacing-md)',
    borderBottom: '1px solid var(--color-border)',
  },

  '& .ddi-number': {
    fontFamily: 'monospace',
    fontWeight: 700,
    color: 'var(--color-primary)',
  },

  '& .ddi-country': {
    color: 'var(--color-text-secondary)',
    marginTop: 'var(--spacing-xs)',
  },

  '& .loading-container': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    padding: 'var(--spacing-xl)',
    gap: 'var(--spacing-md)',
  },

  '& .loading-text': {
    color: 'var(--color-text-secondary)',
  },

  '& .renewal-info': {
    marginTop: 'var(--spacing-md)',
    padding: 'var(--spacing-sm)',
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '4px',
    textAlign: 'center',

    '& .MuiTypography-root': {
      color: 'var(--color-text-secondary)',
      fontSize: '0.875rem',
    },
  },

  '& .success-container': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: 'var(--spacing-md)',
  },

  '& .success-icon': {
    fontSize: '48px',
  },

  '& .success-alert': {
    width: '100%',
  },

  '& .success-details': {
    width: '100%',
    padding: 'var(--spacing-md)',
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '8px',

    '& .MuiTypography-root': {
      marginBottom: 'var(--spacing-xs)',
      '&:last-child': {
        marginBottom: 0,
      },
    },
  },

  '& .next-steps': {
    color: 'var(--color-text-secondary)',
    fontStyle: 'italic',
    textAlign: 'center',
  },

  '& .error-alert': {
    marginTop: 'var(--spacing-md)',
  },

  [theme.breakpoints.down('sm')]: {
    minWidth: '280px',
  },
}));

export default StyledPurchaseModal;
