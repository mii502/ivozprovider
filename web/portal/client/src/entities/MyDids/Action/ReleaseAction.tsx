/**
 * Release Action - Row action to release a DID back to marketplace
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/Action/ReleaseAction.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

import { MoreMenuItem } from '@irontec/ivoz-ui/components/List/Content/Shared/MoreChildEntityLinks';
import { StyledTableRowCustomCta } from '@irontec/ivoz-ui/components/List/Content/Table/ContentTable.styles';
import {
  ActionFunctionComponent,
  ActionItemProps,
} from '@irontec/ivoz-ui/router/routeMapParser';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import { Tooltip } from '@mui/material';
import { useState } from 'react';

import { ReleaseConfirmModal } from '../../../components/DidRelease';

const ReleaseAction: ActionFunctionComponent = (props: ActionItemProps) => {
  const { row, variant = 'icon' } = props;
  const [open, setOpen] = useState(false);

  if (!row) {
    return null;
  }

  const ddiId = row.id as number;
  const ddiNumber = row.ddiE164 as string;

  const handleClickOpen = () => {
    setOpen(true);
  };

  const handleClose = () => {
    setOpen(false);
  };

  return (
    <>
      {variant === 'text' && (
        <MoreMenuItem onClick={handleClickOpen}>
          {_('Release')}
        </MoreMenuItem>
      )}
      {variant === 'icon' && (
        <Tooltip
          title={_('Release this number')}
          placement="bottom-start"
          enterTouchDelay={0}
        >
          <span>
            <StyledTableRowCustomCta>
              <DeleteOutlineIcon
                onClick={handleClickOpen}
                color="error"
                sx={{ cursor: 'pointer' }}
              />
            </StyledTableRowCustomCta>
          </span>
        </Tooltip>
      )}
      {open && (
        <ReleaseConfirmModal
          open={open}
          onClose={handleClose}
          ddiId={ddiId}
          ddiNumber={ddiNumber}
        />
      )}
    </>
  );
};

export default ReleaseAction;
