/**
 * Buy Action - Row action to purchase a DID from the marketplace
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/AvailableDdi/Action/BuyAction.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 */

import { MoreMenuItem } from '@irontec/ivoz-ui/components/List/Content/Shared/MoreChildEntityLinks';
import { StyledTableRowCustomCta } from '@irontec/ivoz-ui/components/List/Content/Table/ContentTable.styles';
import {
  ActionFunctionComponent,
  ActionItemProps,
} from '@irontec/ivoz-ui/router/routeMapParser';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import { Tooltip } from '@mui/material';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { DdiDetails, PurchaseModal } from '../../../components/DidPurchase';
import MyDids from '../../MyDids/MyDids';

const BuyAction: ActionFunctionComponent = (props: ActionItemProps) => {
  const { row, variant = 'icon' } = props;
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  if (!row) {
    return null;
  }

  // Check if DID is available for purchase
  const isAvailable = row.inventoryStatus === 'available';

  const handleClickOpen = () => {
    if (isAvailable) {
      setOpen(true);
    }
  };

  const handleClose = () => {
    setOpen(false);
  };

  const handleSuccess = () => {
    // Navigate to My DIDs after successful purchase
    // Use process.env.BASE_URL to ensure correct path with /client/ prefix
    const basePath = process.env.BASE_URL || '/client/';
    navigate(`${basePath}${MyDids.path.replace(/^\//, '')}`);
  };

  // Map row data to DdiDetails type for PurchaseModal
  const ddiDetails: DdiDetails = {
    id: row.id as number,
    ddi: row.ddi as string,
    ddiE164: row.ddiE164 as string,
    country: row.country as string,
    countryName: row.countryName as string,
    setupPrice: row.setupPrice as string,
    monthlyPrice: row.monthlyPrice as string,
    inventoryStatus: row.inventoryStatus as string,
  };

  return (
    <>
      {variant === 'text' && (
        <MoreMenuItem onClick={handleClickOpen} disabled={!isAvailable}>
          {_('Buy')}
        </MoreMenuItem>
      )}
      {variant === 'icon' && (
        <Tooltip
          title={isAvailable ? _('Buy this number') : _('Not available')}
          placement="bottom-start"
          enterTouchDelay={0}
        >
          <span>
            <StyledTableRowCustomCta disabled={!isAvailable}>
              <ShoppingCartIcon
                onClick={handleClickOpen}
                color={isAvailable ? 'primary' : 'disabled'}
                sx={{ cursor: isAvailable ? 'pointer' : 'not-allowed' }}
              />
            </StyledTableRowCustomCta>
          </span>
        </Tooltip>
      )}
      {open && (
        <PurchaseModal
          open={open}
          onClose={handleClose}
          onSuccess={handleSuccess}
          ddi={ddiDetails}
        />
      )}
    </>
  );
};

export default BuyAction;
