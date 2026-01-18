import {
  FieldsetGroups,
  View as DefaultEntityView,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import { ViewProps } from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import { Alert, Box, Button, Chip, Stack } from '@mui/material';
import { useState } from 'react';
import { useStoreActions } from 'store';
import { useNavigate } from 'react-router-dom';

import DidOrder from './DidOrder';

const View = (props: ViewProps): JSX.Element | null => {
  const { row } = props;
  const navigate = useNavigate();

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const apiPost = useStoreActions((store) => store.api.post);

  const handleApprove = async () => {
    setLoading(true);
    setError(null);
    try {
      await apiPost({
        path: `${DidOrder.path}/${row.id}/approve`,
        values: {},
        handleErrors: false,
      });
      navigate(DidOrder.path as string);
    } catch (err: unknown) {
      const error = err as { statusText: string; status: number };
      setError(`${error.statusText} (${error.status})`);
    } finally {
      setLoading(false);
    }
  };

  const groups: Array<FieldsetGroups | false> = [
    {
      legend: _('Order Details'),
      fields: ['companyName', 'ddiNumber', 'status', 'requestedAt'],
    },
    {
      legend: _('Pricing'),
      fields: ['setupFee', 'monthlyFee'],
    },
    row.status === 'pending_approval' && {
      legend: _('Reservation'),
      fields: ['reservedUntil'],
    },
    row.status === 'approved' && {
      legend: _('Approval'),
      fields: ['approvedByName', 'approvedAt'],
    },
    row.status === 'rejected' && {
      legend: _('Rejection'),
      fields: ['rejectedAt', 'rejectionReason'],
    },
  ];

  // Status-specific messaging and actions
  const getStatusContent = () => {
    switch (row.status) {
      case 'pending_approval':
        return (
          <>
            <Alert severity="warning" sx={{ mb: 2 }}>
              {_('This DID order is awaiting your approval. The DID is reserved until the reservation expires.')}
            </Alert>
            <Stack direction="row" spacing={2} sx={{ mb: 2 }}>
              <Button
                variant="contained"
                color="success"
                onClick={handleApprove}
                disabled={loading}
              >
                {loading ? _('Processing...') : _('Approve Order')}
              </Button>
              <Button
                variant="outlined"
                color="error"
                onClick={() => navigate(`${DidOrder.path}`)}
              >
                {_('Reject Order')}
              </Button>
            </Stack>
            {error && (
              <Alert severity="error" sx={{ mb: 2 }}>
                {error}
              </Alert>
            )}
          </>
        );
      case 'approved':
        return (
          <Alert severity="success" sx={{ mb: 2 }}>
            {_('This DID order was approved. The number has been provisioned to the company.')}
          </Alert>
        );
      case 'rejected':
        return (
          <Alert severity="error" sx={{ mb: 2 }}>
            {_('This DID order was rejected.')}
            {row.rejectionReason && (
              <Box sx={{ mt: 1 }}>
                <strong>{_('Reason')}:</strong> {row.rejectionReason}
              </Box>
            )}
          </Alert>
        );
      case 'expired':
        return (
          <Alert severity="info" sx={{ mb: 2 }}>
            {_('This DID order expired. The reservation was not processed in time.')}
          </Alert>
        );
      default:
        return null;
    }
  };

  return (
    <>
      {getStatusContent()}
      <Box sx={{ mb: 2 }}>
        <Chip
          label={row.status?.replace('_', ' ').toUpperCase()}
          color={
            row.status === 'approved'
              ? 'success'
              : row.status === 'rejected'
              ? 'error'
              : row.status === 'pending_approval'
              ? 'warning'
              : 'default'
          }
          size="medium"
        />
      </Box>
      <DefaultEntityView {...props} groups={groups} />
    </>
  );
};

export default View;
