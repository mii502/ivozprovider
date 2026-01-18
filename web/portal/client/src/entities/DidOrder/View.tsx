import {
  FieldsetGroups,
  View as DefaultEntityView,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import { ViewProps } from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import { Alert, Box, Chip } from '@mui/material';

const View = (props: ViewProps): JSX.Element | null => {
  const { row } = props;

  const groups: Array<FieldsetGroups | false> = [
    {
      legend: _('Order Details'),
      fields: ['ddiNumber', 'status', 'requestedAt'],
    },
    {
      legend: _('Pricing'),
      fields: ['setupFee', 'monthlyFee'],
    },
    row.status === 'approved' && {
      legend: _('Approval'),
      fields: ['approvedAt'],
    },
    row.status === 'rejected' && {
      legend: _('Rejection'),
      fields: ['rejectedAt', 'rejectionReason'],
    },
  ];

  // Status-specific messaging
  const getStatusMessage = () => {
    switch (row.status) {
      case 'pending_approval':
        return (
          <Alert severity="info" sx={{ mb: 2 }}>
            {_('Your DID order is pending approval from the administrator. You will be notified once it is reviewed.')}
          </Alert>
        );
      case 'approved':
        return (
          <Alert severity="success" sx={{ mb: 2 }}>
            {_('Your DID order has been approved! The number has been provisioned to your account.')}
          </Alert>
        );
      case 'rejected':
        return (
          <Alert severity="error" sx={{ mb: 2 }}>
            {_('Your DID order was rejected.')}
            {row.rejectionReason && (
              <Box sx={{ mt: 1 }}>
                <strong>{_('Reason')}:</strong> {row.rejectionReason}
              </Box>
            )}
          </Alert>
        );
      case 'expired':
        return (
          <Alert severity="warning" sx={{ mb: 2 }}>
            {_('Your DID order has expired. The reservation was not processed in time.')}
          </Alert>
        );
      default:
        return null;
    }
  };

  return (
    <>
      {getStatusMessage()}
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
