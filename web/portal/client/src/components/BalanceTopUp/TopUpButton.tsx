/**
 * Top-Up Button - Triggers the top-up modal
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/BalanceTopUp/TopUpButton.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-15
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import AddCardIcon from '@mui/icons-material/AddCard';
import { Button, styled } from '@mui/material';

interface TopUpButtonProps {
  onClick: () => void;
  disabled?: boolean;
  variant?: 'contained' | 'outlined' | 'text';
  size?: 'small' | 'medium' | 'large';
  className?: string;
}

const TopUpButton = (props: TopUpButtonProps): JSX.Element => {
  const {
    onClick,
    disabled = false,
    variant = 'contained',
    size = 'medium',
    className,
  } = props;

  return (
    <Button
      className={className}
      variant={variant}
      size={size}
      onClick={onClick}
      disabled={disabled}
      startIcon={<AddCardIcon />}
    >
      {_('Top Up Balance')}
    </Button>
  );
};

const StyledTopUpButton = styled(TopUpButton)(({ theme }) => ({
  fontWeight: 500,
  textTransform: 'none',
  borderRadius: '8px',
  padding: '8px 24px',

  '&.MuiButton-contained': {
    backgroundColor: 'var(--color-primary)',
    color: 'white',
    boxShadow: '0px 2px 4px rgba(0, 0, 0, 0.1)',

    '&:hover': {
      backgroundColor: 'var(--color-primary)',
      filter: 'brightness(1.1)',
      boxShadow: '0px 4px 8px rgba(0, 0, 0, 0.15)',
    },
  },

  '&.MuiButton-outlined': {
    borderColor: 'var(--color-primary)',
    color: 'var(--color-primary)',

    '&:hover': {
      backgroundColor: 'rgba(var(--color-primary-rgb), 0.04)',
    },
  },

  '& .MuiButton-startIcon': {
    marginRight: 'var(--spacing-sm)',
  },

  [theme.breakpoints.down('sm')]: {
    width: '100%',
  },
}));

export default StyledTopUpButton;
