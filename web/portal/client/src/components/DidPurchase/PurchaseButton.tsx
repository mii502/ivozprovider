/**
 * Purchase Button - Button to trigger DID purchase modal
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidPurchase/PurchaseButton.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import { Box, Button, styled, Typography } from '@mui/material';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import PurchaseModal from './PurchaseModal';
import { DdiDetails } from './types';

interface PurchaseButtonProps {
  ddi: DdiDetails;
  className?: string;
}

const PurchaseButton = (props: PurchaseButtonProps): JSX.Element => {
  const { ddi, className } = props;
  const [modalOpen, setModalOpen] = useState(false);
  const navigate = useNavigate();

  // Only show for available DIDs
  if (ddi.inventoryStatus !== 'available') {
    return (
      <Box className={className}>
        <Typography variant="body2" color="text.secondary">
          {_('This number is not available for purchase.')}
        </Typography>
      </Box>
    );
  }

  const setupPrice = typeof ddi.setupPrice === 'string'
    ? parseFloat(ddi.setupPrice)
    : ddi.setupPrice;
  const monthlyPrice = typeof ddi.monthlyPrice === 'string'
    ? parseFloat(ddi.monthlyPrice)
    : ddi.monthlyPrice;

  const handleOpenModal = () => {
    setModalOpen(true);
  };

  const handleCloseModal = () => {
    setModalOpen(false);
  };

  const handlePurchaseSuccess = () => {
    // Navigate to My DIDs page after successful purchase
    navigate('/my/dids');
  };

  return (
    <Box className={className}>
      <Box className="price-summary">
        {setupPrice > 0 && (
          <Typography variant="body2" className="setup-price">
            {_('Setup')}: €{setupPrice.toFixed(2)}
          </Typography>
        )}
        <Typography variant="h6" className="monthly-price">
          €{monthlyPrice.toFixed(2)}
          <Typography component="span" className="per-month">
            /{_('month')}
          </Typography>
        </Typography>
      </Box>

      <Button
        variant="contained"
        color="primary"
        size="large"
        startIcon={<ShoppingCartIcon />}
        onClick={handleOpenModal}
        className="buy-button"
        fullWidth
      >
        {_('Buy This Number')}
      </Button>

      <PurchaseModal
        open={modalOpen}
        onClose={handleCloseModal}
        onSuccess={handlePurchaseSuccess}
        ddi={ddi}
      />
    </Box>
  );
};

const StyledPurchaseButton = styled(PurchaseButton)(({ theme }) => ({
  marginTop: 'var(--spacing-lg)',
  padding: 'var(--spacing-lg)',
  backgroundColor: 'var(--color-background-elevated)',
  borderRadius: '12px',
  border: '1px solid var(--color-border)',

  '& .price-summary': {
    textAlign: 'center',
    marginBottom: 'var(--spacing-md)',
  },

  '& .setup-price': {
    color: 'var(--color-text-secondary)',
    marginBottom: 'var(--spacing-xs)',
  },

  '& .monthly-price': {
    fontWeight: 700,
    color: 'var(--color-primary)',

    '& .per-month': {
      fontWeight: 400,
      fontSize: '0.875rem',
      color: 'var(--color-text-secondary)',
      marginLeft: '2px',
    },
  },

  '& .buy-button': {
    textTransform: 'none',
    fontWeight: 600,
    fontSize: '1rem',
    padding: 'var(--spacing-md) var(--spacing-lg)',
    borderRadius: '8px',
  },
}));

export default StyledPurchaseButton;
