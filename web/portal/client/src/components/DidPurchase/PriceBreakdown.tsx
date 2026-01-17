/**
 * Price Breakdown - Display purchase cost breakdown
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidPurchase/PriceBreakdown.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import { Box, Divider, styled, Typography } from '@mui/material';

import { PriceBreakdownItem } from './types';

interface PriceBreakdownProps {
  breakdown: PriceBreakdownItem[];
  totalDueNow: number;
  currentBalance: number;
  balanceAfterPurchase: number;
  currency?: string;
  className?: string;
}

const PriceBreakdown = (props: PriceBreakdownProps): JSX.Element => {
  const {
    breakdown,
    totalDueNow,
    currentBalance,
    balanceAfterPurchase,
    currency = 'EUR',
    className,
  } = props;

  const currencySymbol = currency === 'EUR' ? '€' : currency;

  const formatAmount = (amount: number): string => {
    return `${currencySymbol}${amount.toFixed(2)}`;
  };

  return (
    <Box className={className}>
      {/* Cost breakdown */}
      <Box className="breakdown-section">
        {breakdown.map((item, index) => (
          <Box key={index} className="breakdown-row">
            <Typography variant="body2" className="breakdown-label">
              {item.description}
            </Typography>
            <Typography variant="body2" className="breakdown-amount">
              {formatAmount(item.amount)}
            </Typography>
          </Box>
        ))}
      </Box>

      <Divider className="breakdown-divider" />

      {/* Total due now */}
      <Box className="total-row">
        <Typography variant="subtitle1" className="total-label">
          {_('Total Due Now')}
        </Typography>
        <Typography variant="h6" className="total-amount">
          {formatAmount(totalDueNow)}
        </Typography>
      </Box>

      <Divider className="breakdown-divider" />

      {/* Balance preview */}
      <Box className="balance-section">
        <Box className="balance-row">
          <Typography variant="body2" className="balance-label">
            {_('Current Balance')}
          </Typography>
          <Typography variant="body2" className="balance-value">
            {formatAmount(currentBalance)}
          </Typography>
        </Box>
        <Box className="balance-row">
          <Typography variant="body2" className="balance-label">
            {_('After Purchase')}
          </Typography>
          <Typography
            variant="body2"
            className={`balance-value ${balanceAfterPurchase < 0 ? 'negative' : ''}`}
          >
            {formatAmount(balanceAfterPurchase)}
          </Typography>
        </Box>
      </Box>
    </Box>
  );
};

const StyledPriceBreakdown = styled(PriceBreakdown)(({ theme }) => ({
  '& .breakdown-section': {
    marginBottom: 'var(--spacing-md)',
  },

  '& .breakdown-row': {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 'var(--spacing-sm)',
  },

  '& .breakdown-label': {
    color: 'var(--color-text-secondary)',
  },

  '& .breakdown-amount': {
    fontWeight: 500,
    fontFamily: 'monospace',
  },

  '& .breakdown-divider': {
    margin: 'var(--spacing-md) 0',
  },

  '& .total-row': {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },

  '& .total-label': {
    fontWeight: 600,
  },

  '& .total-amount': {
    color: 'var(--color-primary)',
    fontWeight: 700,
    fontFamily: 'monospace',
  },

  '& .balance-section': {
    backgroundColor: 'var(--color-background-elevated)',
    borderRadius: '8px',
    padding: 'var(--spacing-md)',
    marginTop: 'var(--spacing-md)',
  },

  '& .balance-row': {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 'var(--spacing-xs)',

    '&:last-child': {
      marginBottom: 0,
    },
  },

  '& .balance-label': {
    color: 'var(--color-text-secondary)',
  },

  '& .balance-value': {
    fontWeight: 500,
    fontFamily: 'monospace',

    '&.negative': {
      color: theme.palette.error.main,
    },
  },
}));

export default StyledPriceBreakdown;
