import DialogContentBody from '@irontec/ivoz-ui/components/Dialog/DialogContentBody';
import ErrorMessageComponent from '@irontec/ivoz-ui/components/ErrorMessageComponent';
import { MoreMenuItem } from '@irontec/ivoz-ui/components/List/Content/Shared/MoreChildEntityLinks';
import { StyledTableRowCustomCta } from '@irontec/ivoz-ui/components/List/Content/Table/ContentTable.styles';
import Modal from '@irontec/ivoz-ui/components/shared/Modal/Modal';
import {
  ActionFunctionComponent,
  ActionItemProps,
} from '@irontec/ivoz-ui/router/routeMapParser';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { Box, FormHelperText, Tooltip, Typography } from '@mui/material';
import { useState } from 'react';
import { useStoreActions } from 'store';

import DidOrder from '../DidOrder';

const Approve: ActionFunctionComponent = (props: ActionItemProps) => {
  const { row, variant = 'icon' } = props;

  const [open, setOpen] = useState(false);
  const [error, setError] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [loading, setLoading] = useState(false);

  const apiPost = useStoreActions((store) => store.api.post);
  const reload = useStoreActions((store) => store.list.reload);

  const handleClickOpen = () => {
    setOpen(true);
    setError(false);
  };

  const handleSubmit = async () => {
    setLoading(true);
    try {
      await apiPost({
        path: `${DidOrder.path}/${row.id}/approve`,
        values: {},
        handleErrors: false,
      });
      setOpen(false);
      setError(false);
      reload();
    } catch (error: unknown) {
      const err = error as { statusText: string; status: number };
      setErrorMsg(`${err.statusText} (${err.status})`);
      setError(true);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setOpen(false);
    setError(false);
  };

  // Only show for pending orders
  if (row.status !== 'pending_approval') {
    return null;
  }

  const customButtons = [
    {
      label: _('Cancel'),
      onClick: handleClose,
      variant: 'outlined' as const,
      autoFocus: false,
      disabled: loading,
    },
    {
      label: loading ? _('Processing...') : _('Approve'),
      onClick: handleSubmit,
      variant: 'solid' as const,
      autoFocus: true,
      disabled: loading,
    },
  ];

  return (
    <>
      <a onClick={handleClickOpen}>
        {variant === 'text' && (
          <MoreMenuItem>{_('Approve Order')}</MoreMenuItem>
        )}
        {variant === 'icon' && (
          <Tooltip
            title={_('Approve Order')}
            placement="bottom"
            enterTouchDelay={0}
          >
            <span>
              <StyledTableRowCustomCta>
                <CheckCircleIcon color="success" />
              </StyledTableRowCustomCta>
            </span>
          </Tooltip>
        )}
      </a>
      {open && (
        <Modal
          open={open}
          onClose={handleClose}
          title={_('Approve DID Order')}
          buttons={customButtons}
          keepMounted={true}
        >
          {!error && (
            <DialogContentBody
              child={
                <Box>
                  <FormHelperText sx={{ mb: 2 }}>
                    {_('Are you sure you want to approve this DID order?')}
                  </FormHelperText>
                  <Typography variant="body2" sx={{ mb: 1 }}>
                    <strong>{_('Company')}:</strong> {row.companyName}
                  </Typography>
                  <Typography variant="body2" sx={{ mb: 1 }}>
                    <strong>{_('Phone Number')}:</strong> {row.ddiNumber}
                  </Typography>
                  <Typography variant="body2" sx={{ mb: 1 }}>
                    <strong>{_('Setup Fee')}:</strong> {parseFloat(row.setupFee || 0).toFixed(2)}
                  </Typography>
                  <Typography variant="body2">
                    <strong>{_('Monthly Fee')}:</strong> {parseFloat(row.monthlyFee || 0).toFixed(2)}/mo
                  </Typography>
                  <FormHelperText sx={{ mt: 2 }}>
                    {_('This will provision the DID to the company and create a setup fee invoice.')}
                  </FormHelperText>
                </Box>
              }
            />
          )}
          {error && <ErrorMessageComponent message={errorMsg} />}
        </Modal>
      )}
    </>
  );
};

export default Approve;
