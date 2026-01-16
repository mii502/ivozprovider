/**
 * Transaction History - Table of past balance movements
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/BalanceTopUp/TransactionHistory.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-15
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import HistoryIcon from '@mui/icons-material/History';
import RefreshIcon from '@mui/icons-material/Refresh';
import {
  Box,
  Chip,
  IconButton,
  Pagination,
  Paper,
  Skeleton,
  styled,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { useStoreActions } from 'store';

import { Transaction, TransactionHistoryResponse } from './types';

interface TransactionHistoryProps {
  className?: string;
  refreshTrigger?: number;
}

const ITEMS_PER_PAGE = 10;

const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getTransactionTypeLabel = (type: string | undefined | null): string => {
  if (!type) {
    return _('Balance Movement');
  }
  const typeMap: Record<string, string> = {
    whmcs_topup: _('WHMCS Top-Up'),
    manual_topup: _('Manual Top-Up'),
    admin_topup: _('Admin Top-Up'),
    admin_deduct: _('Admin Deduction'),
    call_deduct: _('Call Charge'),
    sms_deduct: _('SMS Charge'),
    monthly_fee: _('Monthly Fee'),
    refund: _('Refund'),
  };
  return typeMap[type] || type;
};

const getTransactionTypeColor = (
  type: string | undefined | null
): 'success' | 'error' | 'info' | 'warning' | 'default' => {
  if (!type) {
    return 'default';
  }
  if (type.includes('topup') || type === 'refund') {
    return 'success';
  }
  if (type.includes('deduct') || type === 'monthly_fee') {
    return 'error';
  }
  return 'default';
};

const TransactionHistory = (props: TransactionHistoryProps): JSX.Element => {
  const { className, refreshTrigger } = props;

  const [data, setData] = useState<TransactionHistoryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);

  const apiGet = useStoreActions((store) => store.api.get);
  const [, cancelToken] = useCancelToken();

  const loadHistory = useCallback(() => {
    setLoading(true);
    apiGet({
      path: '/balance/history',
      params: {
        page: page,
        per_page: ITEMS_PER_PAGE,
      },
      cancelToken: cancelToken,
      successCallback: async (response) => {
        setData(response as TransactionHistoryResponse);
        setLoading(false);
      },
    }).catch(() => {
      setLoading(false);
    });
  }, [apiGet, cancelToken, page]);

  useEffect(() => {
    loadHistory();
  }, [loadHistory, refreshTrigger]);

  const handlePageChange = (
    _event: React.ChangeEvent<unknown>,
    newPage: number
  ) => {
    setPage(newPage);
  };

  const handleRefresh = () => {
    loadHistory();
  };

  const totalPages = data
    ? Math.ceil(data.pagination.total / data.pagination.per_page)
    : 0;

  const renderSkeletonRows = () => {
    return Array(5)
      .fill(null)
      .map((_, index) => (
        <TableRow key={index}>
          <TableCell>
            <Skeleton variant="text" width={120} />
          </TableCell>
          <TableCell>
            <Skeleton variant="rounded" width={80} height={24} />
          </TableCell>
          <TableCell>
            <Skeleton variant="text" width={60} />
          </TableCell>
          <TableCell>
            <Skeleton variant="text" width={60} />
          </TableCell>
          <TableCell>
            <Skeleton variant="text" width={80} />
          </TableCell>
        </TableRow>
      ));
  };

  const renderTransactionRow = (transaction: Transaction) => {
    const isPositive = transaction.amount > 0;

    return (
      <TableRow key={transaction.id}>
        <TableCell className="date-cell">
          {formatDate(transaction.created_at)}
        </TableCell>
        <TableCell>
          <Chip
            size="small"
            label={getTransactionTypeLabel(transaction.type)}
            color={getTransactionTypeColor(transaction.type)}
            variant="outlined"
          />
        </TableCell>
        <TableCell
          className={`amount-cell ${isPositive ? 'positive' : 'negative'}`}
        >
          {isPositive ? '+' : ''}€{transaction.amount.toFixed(2)}
        </TableCell>
        <TableCell className="balance-cell">
          €{transaction.balance_after.toFixed(2)}
        </TableCell>
        <TableCell className="reference-cell">
          {transaction.reference || '-'}
        </TableCell>
      </TableRow>
    );
  };

  return (
    <Paper className={className}>
      <Box className="history-header">
        <Box className="header-title">
          <HistoryIcon className="history-icon" />
          <Typography variant="h6">{_('Transaction History')}</Typography>
        </Box>
        <Tooltip title={_('Refresh')}>
          <IconButton onClick={handleRefresh} disabled={loading} size="small">
            <RefreshIcon />
          </IconButton>
        </Tooltip>
      </Box>

      <Box className="history-content">
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>{_('Date')}</TableCell>
              <TableCell>{_('Type')}</TableCell>
              <TableCell>{_('Amount')}</TableCell>
              <TableCell>{_('Balance After')}</TableCell>
              <TableCell>{_('Reference')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading && renderSkeletonRows()}
            {!loading && data && data.transactions.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="empty-cell">
                  <Typography variant="body2" color="textSecondary">
                    {_('No transactions found')}
                  </Typography>
                </TableCell>
              </TableRow>
            )}
            {!loading &&
              data &&
              data.transactions.map((transaction) =>
                renderTransactionRow(transaction)
              )}
          </TableBody>
        </Table>
      </Box>

      {data && totalPages > 1 && (
        <Box className="history-pagination">
          <Pagination
            count={totalPages}
            page={page}
            onChange={handlePageChange}
            size="small"
            disabled={loading}
          />
        </Box>
      )}
    </Paper>
  );
};

const StyledTransactionHistory = styled(TransactionHistory)(({ theme }) => ({
  padding: 0,
  overflow: 'hidden',

  '& .history-header': {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 'var(--spacing-lg)',
    borderBottom: '1px solid var(--color-border)',
  },

  '& .header-title': {
    display: 'flex',
    alignItems: 'center',
    gap: 'var(--spacing-sm)',
  },

  '& .history-icon': {
    color: 'var(--color-primary)',
    fontSize: '24px',
  },

  '& .history-content': {
    overflowX: 'auto',
  },

  '& .MuiTable-root': {
    minWidth: '500px',
  },

  '& .MuiTableHead-root': {
    backgroundColor: 'var(--color-background-elevated)',

    '& .MuiTableCell-root': {
      fontWeight: 600,
      color: 'var(--color-text)',
      whiteSpace: 'nowrap',
    },
  },

  '& .MuiTableBody-root .MuiTableCell-root': {
    padding: 'var(--spacing-sm) var(--spacing-md)',
  },

  '& .date-cell': {
    whiteSpace: 'nowrap',
    color: 'var(--color-text-secondary)',
    fontSize: '0.875rem',
  },

  '& .amount-cell': {
    fontWeight: 600,
    fontFamily: 'monospace',
    whiteSpace: 'nowrap',

    '&.positive': {
      color: theme.palette.success.main,
    },

    '&.negative': {
      color: theme.palette.error.main,
    },
  },

  '& .balance-cell': {
    fontFamily: 'monospace',
    whiteSpace: 'nowrap',
  },

  '& .reference-cell': {
    color: 'var(--color-text-secondary)',
    fontSize: '0.875rem',
  },

  '& .empty-cell': {
    textAlign: 'center',
    padding: 'var(--spacing-xl) !important',
  },

  '& .history-pagination': {
    display: 'flex',
    justifyContent: 'center',
    padding: 'var(--spacing-md)',
    borderTop: '1px solid var(--color-border)',
  },

  [theme.breakpoints.down('sm')]: {
    '& .MuiTable-root': {
      minWidth: '400px',
    },
  },
}));

export default StyledTransactionHistory;
