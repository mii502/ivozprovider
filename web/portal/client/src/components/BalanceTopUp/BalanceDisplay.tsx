/**
 * Balance Display - Dashboard Widget
 * Shows current balance with top-up button for prepaid/pseudoprepaid accounts.
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/BalanceTopUp/BalanceDisplay.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-15
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import AccountBalanceWalletIcon from '@mui/icons-material/AccountBalanceWallet';
import { Box, Paper, Skeleton, styled, Typography } from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { useStoreActions } from 'store';

import TopUpButton from './TopUpButton';
import TopUpModal from './TopUpModal';
import { BalanceData } from './types';

interface BalanceDisplayProps {
  className?: string;
  onTopUpSuccess?: () => void;
}

const BalanceDisplay = (props: BalanceDisplayProps): JSX.Element | null => {
  const { className, onTopUpSuccess } = props;

  const [data, setData] = useState<BalanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);

  const apiGet = useStoreActions((store) => store.api.get);
  const [, cancelToken] = useCancelToken();

  const loadBalance = useCallback(() => {
    setLoading(true);
    apiGet({
      path: '/balance',
      params: {},
      cancelToken: cancelToken,
      successCallback: async (response) => {
        setData(response as BalanceData);
        setLoading(false);
      },
    }).catch(() => {
      setLoading(false);
    });
  }, [apiGet, cancelToken]);

  useEffect(() => {
    loadBalance();
  }, [loadBalance]);

  const handleOpenModal = () => {
    setModalOpen(true);
  };

  const handleCloseModal = () => {
    setModalOpen(false);
  };

  const handleTopUpSuccess = () => {
    loadBalance();
    if (onTopUpSuccess) {
      onTopUpSuccess();
    }
  };

  // Don't render for postpaid or if showTopUp is false
  if (!loading && data && !data.showTopUp) {
    return null;
  }

  // Don't render if no data and finished loading
  if (!loading && !data) {
    return null;
  }

  return (
    <Paper className={className}>
      <Box className="balance-header">
        <AccountBalanceWalletIcon className="balance-icon" />
        <Typography variant="h6" className="balance-title">
          {_('Account Balance')}
        </Typography>
      </Box>

      <Box className="balance-content">
        {loading ? (
          <>
            <Skeleton variant="text" width={120} height={48} />
            <Skeleton variant="text" width={80} height={24} />
          </>
        ) : data ? (
          <>
            <Typography variant="h3" className="balance-amount">
              {data.currency === 'EUR' ? '€' : data.currency}
              {data.balance.toFixed(2)}
            </Typography>
            <Typography variant="body2" className="billing-method">
              {data.billingMethod === 'prepaid'
                ? _('Prepaid Account')
                : _('Pseudoprepaid Account')}
            </Typography>
          </>
        ) : null}
      </Box>

      {data?.showTopUp && (
        <Box className="balance-actions">
          <TopUpButton onClick={handleOpenModal} />
        </Box>
      )}

      {data && (
        <TopUpModal
          open={modalOpen}
          onClose={handleCloseModal}
          onSuccess={handleTopUpSuccess}
          currentBalance={data.balance}
          currency={data.currency}
          minAmount={data.minAmount}
          maxAmount={data.maxAmount}
        />
      )}
    </Paper>
  );
};

const StyledBalanceDisplay = styled(BalanceDisplay)(({ theme }) => ({
  padding: 'var(--spacing-xl)',
  display: 'flex',
  flexDirection: 'column',
  gap: 'var(--spacing-lg)',

  '& .balance-header': {
    display: 'flex',
    alignItems: 'center',
    gap: 'var(--spacing-sm)',
  },

  '& .balance-icon': {
    color: 'var(--color-primary)',
    fontSize: '28px',
  },

  '& .balance-title': {
    fontWeight: 500,
    color: 'var(--color-text)',
  },

  '& .balance-content': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    padding: 'var(--spacing-lg) 0',
  },

  '& .balance-amount': {
    fontWeight: 700,
    fontSize: '2.5rem',
    color: 'var(--color-primary)',
    lineHeight: 1.2,
  },

  '& .billing-method': {
    color: 'var(--color-text-secondary)',
    marginTop: 'var(--spacing-xs)',
  },

  '& .balance-actions': {
    display: 'flex',
    justifyContent: 'center',
    paddingTop: 'var(--spacing-md)',
    borderTop: '1px solid var(--color-border)',
  },

  [theme.breakpoints.down('sm')]: {
    '& .balance-amount': {
      fontSize: '2rem',
    },
  },
}));

export default StyledBalanceDisplay;
