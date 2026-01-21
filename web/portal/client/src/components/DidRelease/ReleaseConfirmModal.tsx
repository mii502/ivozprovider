/**
 * Release Confirm Modal - DID release confirmation with warnings
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/DidRelease/ReleaseConfirmModal.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

import Modal from '@irontec/ivoz-ui/components/shared/Modal/Modal';
import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import {
  Alert,
  Box,
  CircularProgress,
  styled,
  Typography,
} from '@mui/material';
import { useCallback, useState } from 'react';
import { useStoreActions } from 'store';

import { ReleaseModalState, ReleaseResponse } from './types';

interface ReleaseConfirmModalProps {
  open: boolean;
  onClose: () => void;
  ddiId: number;
  ddiNumber: string;
  className?: string;
}

const ReleaseConfirmModal = (props: ReleaseConfirmModalProps): JSX.Element => {
  const { open, onClose, ddiId, ddiNumber, className } = props;

  const [state, setState] = useState<ReleaseModalState>('confirm');
  const [error, setError] = useState<string | null>(null);

  const apiPost = useStoreActions((store) => store.api.post);
  const [, cancelToken] = useCancelToken();

  // Handle release confirmation
  const handleRelease = useCallback(async () => {
    setState('releasing');
    setError(null);

    try {
      const response = await apiPost({
        path: '/dids/release',
        values: { ddiId },
        contentType: 'application/json',
        cancelToken: cancelToken,
        silenceErrors: true,
      });

      const result = response as ReleaseResponse;

      if (result.success) {
        setState('success');
      } else {
        setError(result.message || _('Failed to release DID'));
        setState('error');
      }
    } catch (err: unknown) {
      const errorData = err as { data?: { detail?: string; message?: string } };
      setError(
        errorData?.data?.detail ||
          errorData?.data?.message ||
          _('Release failed. Please try again.')
      );
      setState('error');
    }
  }, [apiPost, cancelToken, ddiId]);

  const handleClose = () => {
    if (state === 'releasing') return;

    if (state === 'success') {
      // Reload the page to refresh the MyDids list
      window.location.reload();
    } else {
      // Reset state and close
      setState('confirm');
      setError(null);
      onClose();
    }
  };

  // Determine modal buttons based on state
  const getModalButtons = () => {
    switch (state) {
      case 'releasing':
        return [];
      case 'confirm':
        return [
          {
            label: _('Cancel'),
            onClick: handleClose,
            variant: 'outlined' as const,
          },
          {
            label: _('Release Number'),
            onClick: handleRelease,
            variant: 'solid' as const,
            color: 'error' as const,
            autoFocus: false,
          },
        ];
      case 'success':
      case 'error':
        return [
          {
            label: _('Close'),
            onClick: handleClose,
            variant: 'outlined' as const,
            autoFocus: true,
          },
        ];
      default:
        return [];
    }
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title={state === 'success' ? _('Number Released') : _('Release Phone Number')}
      buttons={getModalButtons()}
      keepMounted={false}
    >
      <Box className={className}>
        {/* Confirm State */}
        {state === 'confirm' && (
          <>
            <Box className="warning-header">
              <WarningAmberIcon className="warning-icon" color="warning" />
            </Box>

            <Box className="ddi-header">
              <Typography variant="h5" className="ddi-number">
                {ddiNumber}
              </Typography>
            </Box>

            <Alert severity="warning" className="warning-alert">
              <strong>{_('This action cannot be undone.')}</strong>
              <br />
              {_('You will lose this phone number permanently.')}
            </Alert>

            <Alert severity="info" className="info-alert">
              <strong>{_('No refund will be provided')}</strong>
              {' '}{_('for the remaining billing period.')}
              <br />
              {_('The number will be released to the marketplace immediately.')}
            </Alert>

            <Typography variant="body2" className="note-text">
              {_('After release, this number may be purchased by other customers.')}
            </Typography>
          </>
        )}

        {/* Releasing State */}
        {state === 'releasing' && (
          <Box className="loading-container">
            <CircularProgress size={40} />
            <Typography variant="body1" className="loading-text">
              {_('Releasing phone number...')}
            </Typography>
          </Box>
        )}

        {/* Success State */}
        {state === 'success' && (
          <Box className="success-container">
            <CheckCircleIcon className="success-icon" color="success" />
            <Alert severity="success" className="success-alert">
              <strong>{ddiNumber}</strong> {_('has been released successfully.')}
              <br />
              {_('It is now available in the marketplace.')}
            </Alert>
          </Box>
        )}

        {/* Error State */}
        {state === 'error' && (
          <>
            <Alert severity="error" className="error-alert">
              {error || _('An error occurred')}
            </Alert>
            <Box className="retry-actions">
              <Typography
                variant="body2"
                className="retry-link"
                onClick={() => setState('confirm')}
              >
                {_('Try again')}
              </Typography>
            </Box>
          </>
        )}
      </Box>
    </Modal>
  );
};

const StyledReleaseConfirmModal = styled(ReleaseConfirmModal)(({ theme }) => ({
  minWidth: '360px',
  maxWidth: '480px',

  '& .warning-header': {
    display: 'flex',
    justifyContent: 'center',
    marginBottom: 'var(--spacing-md)',
  },

  '& .warning-icon': {
    fontSize: '48px',
  },

  '& .ddi-header': {
    textAlign: 'center',
    marginBottom: 'var(--spacing-lg)',
  },

  '& .ddi-number': {
    fontFamily: 'monospace',
    fontWeight: 700,
    color: 'var(--color-text-primary)',
  },

  '& .warning-alert': {
    marginBottom: 'var(--spacing-md)',
  },

  '& .info-alert': {
    marginBottom: 'var(--spacing-md)',
  },

  '& .note-text': {
    color: 'var(--color-text-secondary)',
    textAlign: 'center',
    fontStyle: 'italic',
  },

  '& .loading-container': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    padding: 'var(--spacing-xl)',
    gap: 'var(--spacing-md)',
  },

  '& .loading-text': {
    color: 'var(--color-text-secondary)',
  },

  '& .success-container': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: 'var(--spacing-md)',
  },

  '& .success-icon': {
    fontSize: '48px',
  },

  '& .success-alert': {
    width: '100%',
  },

  '& .error-alert': {
    marginBottom: 'var(--spacing-md)',
  },

  '& .retry-actions': {
    textAlign: 'center',
  },

  '& .retry-link': {
    color: 'var(--color-primary)',
    cursor: 'pointer',
    textDecoration: 'underline',
    '&:hover': {
      textDecoration: 'none',
    },
  },

  [theme.breakpoints.down('sm')]: {
    minWidth: '280px',
  },
}));

export default StyledReleaseConfirmModal;
