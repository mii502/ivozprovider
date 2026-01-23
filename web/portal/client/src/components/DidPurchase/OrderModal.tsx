/**
 * Order Modal - DID order request for postpaid customers
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidPurchase/OrderModal.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import Modal from '@irontec/ivoz-ui/components/shared/Modal/Modal';
import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ScheduleIcon from '@mui/icons-material/Schedule';
import {
  Alert,
  Box,
  CircularProgress,
  styled,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { useStoreActions } from 'store';

import {
  DdiDetails,
  OrderResponse,
} from './types';

interface OrderModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  ddi: DdiDetails;
  className?: string;
}

type ModalState = 'preview' | 'creating' | 'success' | 'error';

const OrderModal = (props: OrderModalProps): JSX.Element => {
  const { open, onClose, onSuccess, ddi, className } = props;

  const [state, setState] = useState<ModalState>('preview');
  const [error, setError] = useState<string | null>(null);
  const [orderResult, setOrderResult] = useState<OrderResponse | null>(null);

  const apiPost = useStoreActions((store) => store.api.post);
  const [, cancelToken] = useCancelToken();

  // Reset state when modal opens
  useEffect(() => {
    if (open) {
      setState('preview');
      setError(null);
      setOrderResult(null);
    }
  }, [open]);

  // Handle order creation
  const handleCreateOrder = useCallback(async () => {
    setState('creating');
    setError(null);

    try {
      const response = await apiPost({
        path: '/did-orders',
        values: { ddiId: ddi.id },
        contentType: 'application/json',
        cancelToken: cancelToken,
        silenceErrors: true,
      });

      const result = response.data as OrderResponse;
      setOrderResult(result);

      if (result.success) {
        setState('success');
      } else {
        setError(result.message);
        setState('error');
      }
    } catch (err: unknown) {
      const errorData = err as { data?: { message?: string; detail?: string } };
      setError(
        errorData?.data?.message ||
          errorData?.data?.detail ||
          _('Failed to create order. Please try again.')
      );
      setState('error');
    }
  }, [apiPost, cancelToken, ddi.id]);

  const handleClose = () => {
    if (state === 'creating') return;
    onClose();
    if (state === 'success') {
      onSuccess();
    }
  };

  // Format price for display
  const formatPrice = (price: string | number): string => {
    const numPrice = typeof price === 'string' ? parseFloat(price) : price;
    return `€${numPrice.toFixed(2)}`;
  };

  // Determine modal buttons based on state
  const getModalButtons = () => {
    switch (state) {
      case 'creating':
        return [];
      case 'preview':
        return [
          {
            label: _('Cancel'),
            onClick: handleClose,
            variant: 'outlined' as const,
          },
          {
            label: _('Request DID'),
            onClick: handleCreateOrder,
            variant: 'solid' as const,
            autoFocus: true,
          },
        ];
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
      title={_('Request DID')}
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

        {/* Preview State - Show order details */}
        {state === 'preview' && (
          <>
            <Alert severity="info" className="order-info-alert">
              <Typography variant="body2">
                {_('As a postpaid customer, your DID request will be sent for admin approval. The DID will be reserved for 24 hours while pending approval.')}
              </Typography>
            </Alert>

            <Box className="price-section">
              <Box className="price-row">
                <Typography variant="body1">{_('Setup Fee')}</Typography>
                <Typography variant="body1" className="price-value">
                  {formatPrice(ddi.setupPrice)}
                </Typography>
              </Box>
              <Box className="price-row">
                <Typography variant="body1">{_('Monthly Fee')}</Typography>
                <Typography variant="body1" className="price-value">
                  {formatPrice(ddi.monthlyPrice)}
                </Typography>
              </Box>
            </Box>

            <Box className="approval-notice">
              <ScheduleIcon className="schedule-icon" />
              <Typography variant="body2">
                {_('After approval, an invoice will be created and the DID will be provisioned to your account.')}
              </Typography>
            </Box>
          </>
        )}

        {/* Creating State */}
        {state === 'creating' && (
          <Box className="loading-container">
            <CircularProgress size={40} />
            <Typography variant="body1" className="loading-text">
              {_('Creating order...')}
            </Typography>
          </Box>
        )}

        {/* Success State */}
        {state === 'success' && orderResult && orderResult.success && (
          <Box className="success-container">
            <CheckCircleIcon className="success-icon" color="success" />
            <Alert severity="success" className="success-alert">
              {_('DID order submitted successfully!')}
            </Alert>
            <Box className="success-details">
              <Typography variant="body2">
                {_('Order ID')}: #{orderResult.orderId}
              </Typography>
              <Typography variant="body2">
                {_('Status')}: {_('Pending Approval')}
              </Typography>
            </Box>
            <Typography variant="body2" className="next-steps">
              {_('You can track your order status in "My DID Orders".')}
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

const StyledOrderModal = styled(OrderModal)(({ theme }) => ({
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

  '& .order-info-alert': {
    marginBottom: 'var(--spacing-lg)',
  },

  '& .price-section': {
    padding: 'var(--spacing-md)',
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '8px',
    marginBottom: 'var(--spacing-md)',
  },

  '& .price-row': {
    display: 'flex',
    justifyContent: 'space-between',
    marginBottom: 'var(--spacing-xs)',
    '&:last-child': {
      marginBottom: 0,
    },
  },

  '& .price-value': {
    fontWeight: 600,
  },

  '& .approval-notice': {
    display: 'flex',
    alignItems: 'flex-start',
    gap: 'var(--spacing-sm)',
    padding: 'var(--spacing-md)',
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '8px',

    '& .schedule-icon': {
      color: 'var(--color-text-secondary)',
      fontSize: '20px',
      marginTop: '2px',
    },

    '& .MuiTypography-root': {
      color: 'var(--color-text-secondary)',
      fontSize: '0.875rem',
    },
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

export default StyledOrderModal;
