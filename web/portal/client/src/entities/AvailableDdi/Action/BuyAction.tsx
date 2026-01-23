/**
 * Buy Action - Row action to purchase/order a DID from the marketplace
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/AvailableDdi/Action/BuyAction.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 *
 * Shows different modals based on billing method:
 * - Prepaid/Pseudoprepaid: PurchaseModal (instant balance deduction)
 * - Postpaid: OrderModal (admin approval required)
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
import { useStoreState } from 'store';

import { DdiDetails, PurchaseModal, OrderModal } from '../../../components/DidPurchase';
import MyDids from '../../MyDids/MyDids';
import DidOrder from '../../DidOrder/DidOrder';

const BuyAction: ActionFunctionComponent = (props: ActionItemProps) => {
  const { row, variant = 'icon' } = props;
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  // Get billing method from store to determine which flow to show
  const aboutMe = useStoreState((state) => state.clientSession.aboutMe.profile);
  const isPostpaid = aboutMe?.billingMethod === 'postpaid';

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
    // Navigate to appropriate page after success
    // Postpaid: Go to DID Orders to track order status
    // Prepaid: Go to My DIDs to see purchased DID
    const basePath = process.env.BASE_URL || '/client/';
    const targetPath = isPostpaid ? DidOrder.path : MyDids.path;
    navigate(`${basePath}${targetPath.replace(/^\//, '')}`);
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

  // Button text/tooltip based on billing method
  const actionLabel = isPostpaid ? _('Request') : _('Buy');
  const tooltipText = isAvailable
    ? (isPostpaid ? _('Request this number') : _('Buy this number'))
    : _('Not available');

  return (
    <>
      {variant === 'text' && (
        <MoreMenuItem onClick={handleClickOpen} disabled={!isAvailable}>
          {actionLabel}
        </MoreMenuItem>
      )}
      {variant === 'icon' && (
        <Tooltip
          title={tooltipText}
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
      {open && isPostpaid && (
        <OrderModal
          open={open}
          onClose={handleClose}
          onSuccess={handleSuccess}
          ddi={ddiDetails}
        />
      )}
      {open && !isPostpaid && (
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
