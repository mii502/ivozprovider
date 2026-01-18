import { ListDecoratorType } from '@irontec/ivoz-ui/entities/EntityInterface';
import { Chip, Typography } from '@mui/material';

const ListDecorator: ListDecoratorType = (props) => {
  const { field, row, property } = props;

  // Price formatting
  if (field === 'setupPrice' || field === 'monthlyPrice') {
    const value = parseFloat(row[field] || 0);
    const suffix = field === 'monthlyPrice' ? '/mo' : '';
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

  // Status badge with colors
  if (field === 'inventoryStatus') {
    const colorMap: Record<string, 'success' | 'warning' | 'default' | 'error'> = {
      available: 'success',
      reserved: 'warning',
      assigned: 'default',
      suspended: 'error',
      disabled: 'default',
    };
    const labelMap: Record<string, string> = {
      available: 'Available',
      reserved: 'Reserved',
      assigned: 'Assigned',
      suspended: 'Suspended',
      disabled: 'Disabled',
    };
    return (
      <Chip
        label={labelMap[row.inventoryStatus] || row.inventoryStatus}
        color={colorMap[row.inventoryStatus] || 'default'}
        size="small"
        variant={row.inventoryStatus === 'available' ? 'filled' : 'outlined'}
      />
    );
  }

  // Phone number formatting (monospace)
  if (field === 'ddiE164' || field === 'ddi') {
    return (
      <Typography
        component="span"
        sx={{ fontFamily: 'monospace', fontWeight: 500 }}
      >
        {row[field]}
      </Typography>
    );
  }

  // Country name - display as text
  if (field === 'countryName') {
    return (
      <Typography component="span">
        {row.countryName || row.country || '-'}
      </Typography>
    );
  }

  // DID type with friendly labels
  if (field === 'ddiType') {
    const typeLabels: Record<string, string> = {
      inout: 'Inbound & Outbound',
      out: 'Outbound only',
      virtual: 'Virtual',
    };
    return (
      <Typography component="span">
        {typeLabels[row.ddiType] || row.ddiType || '-'}
      </Typography>
    );
  }

  // Default: return the value as-is for any unhandled field
  // This ensures fields render even without explicit handling
  if (row[field] !== undefined && row[field] !== null) {
    return <Typography component="span">{String(row[field])}</Typography>;
  }

  return null;
};

export default ListDecorator;
