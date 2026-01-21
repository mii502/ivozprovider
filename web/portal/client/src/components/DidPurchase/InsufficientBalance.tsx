/**
 * Insufficient Balance - Warning message with top-up link
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidPurchase/InsufficientBalance.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import { Alert, Box, Button, styled, Typography } from '@mui/material';
import { useNavigate } from 'react-router-dom';

interface InsufficientBalanceProps {
  currentBalance: number;
  requiredAmount: number;
  currency?: string;
  className?: string;
}

const InsufficientBalance = (props: InsufficientBalanceProps): JSX.Element => {
  const { currentBalance, requiredAmount, currency = 'EUR', className } = props;
  const navigate = useNavigate();

  const currencySymbol = currency === 'EUR' ? '€' : currency;
  const shortfall = requiredAmount - currentBalance;

  const formatAmount = (amount: number): string => {
    return `${currencySymbol}${amount.toFixed(2)}`;
  };

  const handleTopUp = () => {
    // Navigate to dashboard where Top Up Balance button is available
    // Use process.env.BASE_URL to ensure correct path with /client/ prefix
    const basePath = process.env.BASE_URL || '/client/';
    navigate(basePath);
  };

  return (
    <Box className={className}>
      <Alert
        severity="warning"
        icon={<WarningAmberIcon />}
        className="warning-alert"
      >
        <Typography variant="subtitle2" className="warning-title">
          {_('Insufficient Balance')}
        </Typography>
        <Typography variant="body2" className="warning-message">
          {_('You need {{shortfall}} more to complete this purchase.', {
            shortfall: formatAmount(shortfall),
          })}
        </Typography>
      </Alert>

      <Box className="balance-details">
        <Box className="detail-row">
          <Typography variant="body2" className="detail-label">
            {_('Current Balance')}
          </Typography>
          <Typography variant="body2" className="detail-value">
            {formatAmount(currentBalance)}
          </Typography>
        </Box>
        <Box className="detail-row">
          <Typography variant="body2" className="detail-label">
            {_('Amount Required')}
          </Typography>
          <Typography variant="body2" className="detail-value required">
            {formatAmount(requiredAmount)}
          </Typography>
        </Box>
        <Box className="detail-row">
          <Typography variant="body2" className="detail-label">
            {_('Shortfall')}
          </Typography>
          <Typography variant="body2" className="detail-value shortfall">
            {formatAmount(shortfall)}
          </Typography>
        </Box>
      </Box>

      <Button
        variant="contained"
        color="primary"
        onClick={handleTopUp}
        className="topup-button"
        fullWidth
      >
        {_('Top Up Balance')}
      </Button>
    </Box>
  );
};

const StyledInsufficientBalance = styled(InsufficientBalance)(({ theme }) => ({
  '& .warning-alert': {
    marginBottom: 'var(--spacing-lg)',
  },

  '& .warning-title': {
    fontWeight: 600,
    marginBottom: 'var(--spacing-xs)',
  },

  '& .warning-message': {
    color: 'inherit',
  },

  '& .balance-details': {
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '8px',
    padding: 'var(--spacing-md)',
    marginBottom: 'var(--spacing-lg)',
  },

  '& .detail-row': {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 'var(--spacing-sm)',

    '&:last-child': {
      marginBottom: 0,
    },
  },

  '& .detail-label': {
    color: 'var(--color-text-secondary)',
  },

  '& .detail-value': {
    fontWeight: 500,
    fontFamily: 'monospace',

    '&.required': {
      color: theme.palette.warning.main,
    },

    '&.shortfall': {
      color: theme.palette.error.main,
      fontWeight: 700,
    },
  },

  '& .topup-button': {
    textTransform: 'none',
    fontWeight: 600,
  },
}));

export default StyledInsufficientBalance;
