/**
 * Top-Up Modal - Amount input with validation and WHMCS redirect
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/BalanceTopUp/TopUpModal.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-15
 */

import ErrorMessageComponent from '@irontec/ivoz-ui/components/ErrorMessageComponent';
import Modal from '@irontec/ivoz-ui/components/shared/Modal/Modal';
import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import { StyledTextField } from '@irontec/ivoz-ui/services/form/Field/TextField/TextField.styles';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import {
  Alert,
  Box,
  Button,
  ButtonGroup,
  CircularProgress,
  InputAdornment,
  styled,
  Typography,
} from '@mui/material';
import { useCallback, useState } from 'react';
import { useStoreActions } from 'store';

import { TopUpResponse } from './types';

interface TopUpModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  currentBalance: number;
  currency: string;
  minAmount: number;
  maxAmount: number;
  className?: string;
}

const QUICK_AMOUNTS = [25, 50, 100];

const TopUpModal = (props: TopUpModalProps): JSX.Element => {
  const {
    open,
    onClose,
    onSuccess,
    currentBalance,
    currency,
    minAmount,
    maxAmount,
    className,
  } = props;

  const [amount, setAmount] = useState<string>('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<TopUpResponse | null>(null);

  const apiPost = useStoreActions((store) => store.api.post);
  const [, cancelToken] = useCancelToken();

  const currencySymbol = currency === 'EUR' ? '€' : currency;

  const validateAmount = useCallback(
    (value: string): string | null => {
      const numValue = parseFloat(value);

      if (isNaN(numValue) || value.trim() === '') {
        return _('Please enter a valid amount');
      }

      if (numValue < minAmount) {
        return _('Minimum amount is {{min}}', { min: `${currencySymbol}${minAmount.toFixed(2)}` });
      }

      if (numValue > maxAmount) {
        return _('Maximum amount is {{max}}', { max: `${currencySymbol}${maxAmount.toFixed(2)}` });
      }

      // Check for more than 2 decimal places
      const decimalPart = value.split('.')[1];
      if (decimalPart && decimalPart.length > 2) {
        return _('Amount cannot have more than 2 decimal places');
      }

      return null;
    },
    [minAmount, maxAmount, currencySymbol]
  );

  const handleAmountChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const value = event.target.value;
    // Only allow numbers and one decimal point
    if (/^[0-9]*\.?[0-9]*$/.test(value) || value === '') {
      setAmount(value);
      setError(null);
    }
  };

  const handleQuickAmount = (quickAmount: number) => {
    setAmount(quickAmount.toString());
    setError(null);
  };

  const handleSubmit = async () => {
    const validationError = validateAmount(amount);
    if (validationError) {
      setError(validationError);
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const response = await apiPost({
        path: '/balance/topup',
        values: {
          amount: parseFloat(amount),
        },
        contentType: 'application/json',
        cancelToken: cancelToken,
        silenceErrors: true,
      });

      const result = response.data as TopUpResponse;

      if (result.success && result.whmcsRedirectUrl) {
        setSuccess(result);
        // Redirect to WHMCS payment page after short delay
        setTimeout(() => {
          window.location.href = result.whmcsRedirectUrl!;
        }, 1500);
      } else {
        setError(result.error || _('An error occurred'));
      }
    } catch (err: unknown) {
      const errorData = err as { data?: { detail?: string; error?: string } };
      setError(
        errorData?.data?.detail ||
          errorData?.data?.error ||
          _('Failed to process top-up request')
      );
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    if (submitting) return;
    setAmount('');
    setError(null);
    setSuccess(null);
    onClose();
    if (success) {
      onSuccess();
    }
  };

  const numericAmount = parseFloat(amount) || 0;
  const balanceAfter = currentBalance + numericAmount;
  const isValidAmount = !validateAmount(amount);

  const modalButtons = success
    ? [
        {
          label: _('Close'),
          onClick: handleClose,
          variant: 'outlined' as const,
          autoFocus: true,
        },
      ]
    : [
        {
          label: _('Cancel'),
          onClick: handleClose,
          variant: 'outlined' as const,
          autoFocus: false,
          disabled: submitting,
        },
        {
          label: submitting ? _('Processing...') : _('Continue to Payment'),
          onClick: handleSubmit,
          variant: 'solid' as const,
          autoFocus: true,
          disabled: !isValidAmount || submitting || !amount,
        },
      ];

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title={_('Top Up Balance')}
      buttons={modalButtons}
      keepMounted={false}
    >
      <Box className={className}>
        {success ? (
          <Box className="success-content">
            <Alert severity="success" className="success-alert">
              {_('Top-up invoice created successfully!')}
            </Alert>
            <Typography variant="body1" className="redirect-message">
              {_('Redirecting to payment page...')}
            </Typography>
            <CircularProgress size={24} className="redirect-spinner" />
          </Box>
        ) : (
          <>
            {/* Amount Input */}
            <Box className="amount-input-section">
              <StyledTextField
                type="text"
                label={_('Amount')}
                placeholder="0.00"
                value={amount}
                onChange={handleAmountChange}
                hasChanged={false}
                error={!!error}
                helperText={error || _('Enter amount between {{min}} and {{max}}', {
                  min: `${currencySymbol}${minAmount}`,
                  max: `${currencySymbol}${maxAmount.toLocaleString()}`,
                })}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">{currencySymbol}</InputAdornment>
                  ),
                }}
                inputProps={{
                  inputMode: 'decimal',
                  pattern: '[0-9]*\\.?[0-9]*',
                }}
                disabled={submitting}
                fullWidth
              />
            </Box>

            {/* Quick Amount Buttons */}
            <Box className="quick-amounts-section">
              <Typography variant="body2" className="quick-amounts-label">
                {_('Quick amounts')}:
              </Typography>
              <ButtonGroup
                variant="outlined"
                size="small"
                className="quick-amounts-group"
                disabled={submitting}
              >
                {QUICK_AMOUNTS.map((quickAmount) => (
                  <Button
                    key={quickAmount}
                    onClick={() => handleQuickAmount(quickAmount)}
                    className={parseFloat(amount) === quickAmount ? 'selected' : ''}
                  >
                    {currencySymbol}{quickAmount}
                  </Button>
                ))}
              </ButtonGroup>
            </Box>

            {/* Balance Preview */}
            {numericAmount > 0 && isValidAmount && (
              <Box className="balance-preview">
                <Typography variant="body2" className="preview-label">
                  {_('Balance after top-up')}:
                </Typography>
                <Typography variant="h6" className="preview-amount">
                  {currencySymbol}{balanceAfter.toFixed(2)}
                </Typography>
              </Box>
            )}
          </>
        )}
      </Box>
    </Modal>
  );
};

const StyledTopUpModal = styled(TopUpModal)(({ theme }) => ({
  minWidth: '320px',

  '& .amount-input-section': {
    marginBottom: 'var(--spacing-lg)',
  },

  '& .quick-amounts-section': {
    display: 'flex',
    alignItems: 'center',
    gap: 'var(--spacing-md)',
    marginBottom: 'var(--spacing-lg)',
    flexWrap: 'wrap',
  },

  '& .quick-amounts-label': {
    color: 'var(--color-text-secondary)',
  },

  '& .quick-amounts-group': {
    '& .MuiButton-root': {
      textTransform: 'none',
      minWidth: '60px',
    },
    '& .selected': {
      backgroundColor: 'var(--color-primary)',
      color: 'white',
      '&:hover': {
        backgroundColor: 'var(--color-primary)',
        filter: 'brightness(1.1)',
      },
    },
  },

  '& .balance-preview': {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 'var(--spacing-md)',
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '8px',
    marginBottom: 'var(--spacing-md)',
  },

  '& .preview-label': {
    color: 'var(--color-text-secondary)',
  },

  '& .preview-amount': {
    color: 'var(--color-primary)',
    fontWeight: 600,
  },

  '& .success-content': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: 'var(--spacing-md)',
    padding: 'var(--spacing-lg)',
  },

  '& .success-alert': {
    width: '100%',
  },

  '& .redirect-message': {
    color: 'var(--color-text-secondary)',
    marginTop: 'var(--spacing-sm)',
  },

  '& .redirect-spinner': {
    marginTop: 'var(--spacing-sm)',
  },

  [theme.breakpoints.down('sm')]: {
    minWidth: '280px',

    '& .quick-amounts-section': {
      flexDirection: 'column',
      alignItems: 'flex-start',
    },
  },
}));

export default StyledTopUpModal;
