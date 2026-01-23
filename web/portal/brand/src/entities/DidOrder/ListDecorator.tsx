import { ListDecoratorType } from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import { Chip, Typography } from '@mui/material';

const ListDecorator: ListDecoratorType = (props) => {
  const { field, row } = props;

  // Company name - render as plain text
  if (field === 'companyName') {
    return (
      <Typography component="span">
        {row.companyName || '-'}
      </Typography>
    );
  }

  // Requested date - format as readable date
  if (field === 'requestedAt') {
    if (!row.requestedAt) {
      return <Typography component="span">-</Typography>;
    }
    const date = new Date(row.requestedAt);
    return (
      <Typography component="span">
        {date.toLocaleDateString('en-GB', {
          day: '2-digit',
          month: 'short',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })}
      </Typography>
    );
  }

  // Status badge with colors
  if (field === 'status') {
    const colorMap: Record<string, 'warning' | 'success' | 'error' | 'default'> = {
      pending_approval: 'warning',
      approved: 'success',
      rejected: 'error',
      expired: 'default',
    };
    const labelMap: Record<string, string> = {
      pending_approval: _('Pending'),
      approved: _('Approved'),
      rejected: _('Rejected'),
      expired: _('Expired'),
    };
    return (
      <Chip
        label={labelMap[row.status] || row.status}
        color={colorMap[row.status] || 'default'}
        size="small"
        variant={row.status === 'pending_approval' ? 'filled' : 'outlined'}
      />
    );
  }

  // Price formatting
  if (field === 'setupFee' || field === 'monthlyFee') {
    const value = parseFloat(row[field] || 0);
    const suffix = field === 'monthlyFee' ? '/mo' : '';
    return (
      <Typography
        component="span"
        sx={{
          fontWeight: value > 0 ? 'bold' : 'normal',
          color: value > 0 ? 'success.main' : 'text.secondary',
        }}
      >
        {value.toFixed(2)}{suffix}
      </Typography>
    );
  }

  // Phone number formatting (monospace)
  if (field === 'ddiNumber') {
    return (
      <Typography
        component="span"
        sx={{ fontFamily: 'monospace', fontWeight: 500 }}
      >
        {row[field]}
      </Typography>
    );
  }

  // Default: return the value as-is for any unhandled field
  if (row[field] !== undefined && row[field] !== null) {
    return <Typography component="span">{String(row[field])}</Typography>;
  }

  return null;
};

export default ListDecorator;
