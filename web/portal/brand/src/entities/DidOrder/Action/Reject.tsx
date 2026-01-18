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
import CancelIcon from '@mui/icons-material/Cancel';
import { Box, FormHelperText, TextField, Tooltip, Typography } from '@mui/material';
import { useState } from 'react';
import { useStoreActions } from 'store';

import DidOrder from '../DidOrder';

const Reject: ActionFunctionComponent = (props: ActionItemProps) => {
  const { row, variant = 'icon' } = props;

  const [open, setOpen] = useState(false);
  const [error, setError] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [loading, setLoading] = useState(false);
  const [reason, setReason] = useState('');

  const apiPost = useStoreActions((store) => store.api.post);
  const reload = useStoreActions((store) => store.list.reload);

  const handleClickOpen = () => {
    setOpen(true);
    setError(false);
    setReason('');
  };

  const handleSubmit = async () => {
    if (!reason.trim()) {
      setErrorMsg(_('Please provide a rejection reason'));
      setError(true);
      return;
    }

    setLoading(true);
    try {
      await apiPost({
        path: `${DidOrder.path}/${row.id}/reject`,
        values: { reason: reason.trim() },
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
    setReason('');
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
      label: loading ? _('Processing...') : _('Reject'),
      onClick: handleSubmit,
      variant: 'solid' as const,
      autoFocus: false,
      disabled: loading || !reason.trim(),
    },
  ];

  return (
    <>
      <a onClick={handleClickOpen}>
        {variant === 'text' && (
          <MoreMenuItem>{_('Reject Order')}</MoreMenuItem>
        )}
        {variant === 'icon' && (
          <Tooltip
            title={_('Reject Order')}
            placement="bottom"
            enterTouchDelay={0}
          >
            <span>
              <StyledTableRowCustomCta>
                <CancelIcon color="error" />
              </StyledTableRowCustomCta>
            </span>
          </Tooltip>
        )}
      </a>
      {open && (
        <Modal
          open={open}
          onClose={handleClose}
          title={_('Reject DID Order')}
          buttons={customButtons}
          keepMounted={true}
        >
          {!error && (
            <DialogContentBody
              child={
                <Box>
                  <FormHelperText sx={{ mb: 2 }}>
                    {_('Please provide a reason for rejecting this DID order.')}
                  </FormHelperText>
                  <Typography variant="body2" sx={{ mb: 1 }}>
                    <strong>{_('Company')}:</strong> {row.companyName}
                  </Typography>
                  <Typography variant="body2" sx={{ mb: 2 }}>
                    <strong>{_('Phone Number')}:</strong> {row.ddiNumber}
                  </Typography>
                  <TextField
                    label={_('Rejection Reason')}
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    multiline
                    rows={3}
                    fullWidth
                    required
                    placeholder={_('e.g., DID not available in this region for your account type.')}
                  />
                  <FormHelperText sx={{ mt: 2 }}>
                    {_('The customer will be notified of the rejection with this reason.')}
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

export default Reject;
