/**
 * BYON Modal Component - Multi-step verification dialog
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/ByonModal.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import Modal from '@irontec/ivoz-ui/components/shared/Modal/Modal';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import ErrorOutlineIcon from '@mui/icons-material/ErrorOutline';
import PhoneAndroidIcon from '@mui/icons-material/PhoneAndroid';
import { Alert, Box, CircularProgress, styled, Typography } from '@mui/material';
import { useCallback, useEffect, useState } from 'react';

import ByonSuccess from './ByonSuccess';
import OtpInput from './OtpInput';
import PhoneInput, { isValidE164 } from './PhoneInput';
import { ByonStep, InitiateResponse, VerifyResponse } from './types';
import useByonApi from './useByonApi';

interface ByonModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess?: () => void;
  className?: string;
}

const ByonModal = (props: ByonModalProps): JSX.Element => {
  const { open, onClose, onSuccess, className } = props;

  const [step, setStep] = useState<ByonStep>('phone');
  const [phoneNumber, setPhoneNumber] = useState('');
  const [otpCode, setOtpCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [expiresIn, setExpiresIn] = useState(600);
  const [verifiedDdiId, setVerifiedDdiId] = useState<number | undefined>();

  const { initiate, verify, loading } = useByonApi();

  // Reset state when modal opens
  useEffect(() => {
    if (open) {
      setStep('phone');
      setPhoneNumber('');
      setOtpCode('');
      setError(null);
      setExpiresIn(600);
      setVerifiedDdiId(undefined);
    }
  }, [open]);

  const handleSendCode = useCallback(async () => {
    if (!isValidE164(phoneNumber)) {
      setError(_('Please enter a valid phone number'));
      return;
    }

    setStep('verifying');
    setError(null);

    const result: InitiateResponse = await initiate(phoneNumber);

    if (result.success) {
      setExpiresIn(result.expiresIn || 600);
      setStep('otp');
    } else {
      setError(result.message || _('Failed to send verification code'));
      setStep('error');
    }
  }, [phoneNumber, initiate]);

  const handleVerifyCode = useCallback(async (code: string) => {
    if (code.length !== 6) {
      return;
    }

    setStep('checking');
    setError(null);

    const result: VerifyResponse = await verify(phoneNumber, code);

    if (result.success) {
      setVerifiedDdiId(result.ddi?.id);
      setStep('success');
    } else {
      setError(result.message || _('Verification failed'));
      setStep('otp'); // Stay on OTP to allow retry
    }
  }, [phoneNumber, verify]);

  const handleOtpChange = useCallback((code: string) => {
    setOtpCode(code);
    setError(null);
  }, []);

  const handleClose = useCallback(() => {
    // Prevent closing during loading states
    if (step === 'verifying' || step === 'checking') return;

    if (step === 'success') {
      // Refresh the list when closing after success
      if (onSuccess) {
        onSuccess();
      } else {
        window.location.reload();
      }
    }

    onClose();
  }, [step, onClose, onSuccess]);

  const handleBackToPhone = useCallback(() => {
    setStep('phone');
    setOtpCode('');
    setError(null);
  }, []);

  const handleRetry = useCallback(() => {
    setStep('phone');
    setError(null);
  }, []);

  // Modal title based on step
  const getTitle = (): string => {
    switch (step) {
      case 'phone':
      case 'verifying':
        return _('Add Your Phone Number');
      case 'otp':
      case 'checking':
        return _('Verify Your Number');
      case 'success':
        return _('Verification Complete');
      case 'error':
        return _('Verification Failed');
      default:
        return _('BYON Verification');
    }
  };

  // Modal buttons based on step
  const getButtons = () => {
    switch (step) {
      case 'phone':
        return [
          {
            label: _('Cancel'),
            onClick: handleClose,
            variant: 'outlined' as const,
          },
          {
            label: _('Send Code'),
            onClick: handleSendCode,
            variant: 'solid' as const,
            disabled: !isValidE164(phoneNumber),
          },
        ];
      case 'otp':
        return [
          {
            label: _('Back'),
            onClick: handleBackToPhone,
            variant: 'outlined' as const,
          },
          {
            label: _('Verify'),
            onClick: () => handleVerifyCode(otpCode),
            variant: 'solid' as const,
            disabled: otpCode.length !== 6,
          },
        ];
      case 'verifying':
      case 'checking':
        return [];
      case 'success':
        return [
          {
            label: _('Close'),
            onClick: handleClose,
            variant: 'solid' as const,
            autoFocus: true,
          },
        ];
      case 'error':
        return [
          {
            label: _('Cancel'),
            onClick: handleClose,
            variant: 'outlined' as const,
          },
          {
            label: _('Try Again'),
            onClick: handleRetry,
            variant: 'solid' as const,
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
      title={getTitle()}
      buttons={getButtons()}
      keepMounted={false}
    >
      <StyledByonModalContent className={className}>
        {/* Phone Input Step */}
        {step === 'phone' && (
          <>
            <Box className="step-header">
              <PhoneAndroidIcon className="header-icon" color="primary" />
              <Typography variant="body1" className="step-description">
                {_('Enter your phone number to receive a verification code via SMS.')}
              </Typography>
            </Box>

            <Box className="input-container">
              <PhoneInput
                value={phoneNumber}
                onChange={setPhoneNumber}
                onSubmit={handleSendCode}
                error={error}
                autoFocus
              />
            </Box>

            <Alert severity="info" className="info-alert">
              <Typography variant="body2">
                <strong>{_('BYON (Bring Your Own Number)')}</strong>
                <br />
                {_('Verify ownership of your phone number to use it as a DDI at no cost.')}
              </Typography>
            </Alert>
          </>
        )}

        {/* Sending Code State */}
        {step === 'verifying' && (
          <Box className="loading-container">
            <CircularProgress size={48} />
            <Typography variant="body1" className="loading-text">
              {_('Sending verification code...')}
            </Typography>
            <Typography variant="body2" className="loading-phone">
              {phoneNumber}
            </Typography>
          </Box>
        )}

        {/* OTP Input Step */}
        {step === 'otp' && (
          <>
            <Box className="step-header">
              <Typography variant="body1" className="phone-display">
                {phoneNumber}
              </Typography>
            </Box>

            <OtpInput
              value={otpCode}
              onChange={handleOtpChange}
              onComplete={handleVerifyCode}
              error={error}
              expiresIn={expiresIn}
              autoFocus
            />

            <Box className="resend-section">
              <Typography variant="body2" className="resend-text">
                {_("Didn't receive the code?")}{' '}
                <span className="resend-link" onClick={handleSendCode}>
                  {_('Resend')}
                </span>
              </Typography>
            </Box>
          </>
        )}

        {/* Checking Code State */}
        {step === 'checking' && (
          <Box className="loading-container">
            <CircularProgress size={48} />
            <Typography variant="body1" className="loading-text">
              {_('Verifying code...')}
            </Typography>
          </Box>
        )}

        {/* Success Step */}
        {step === 'success' && (
          <ByonSuccess phoneNumber={phoneNumber} ddiId={verifiedDdiId} />
        )}

        {/* Error Step */}
        {step === 'error' && (
          <>
            <Box className="error-header">
              <ErrorOutlineIcon className="error-icon" color="error" />
            </Box>

            <Alert severity="error" className="error-alert">
              {error || _('An error occurred during verification.')}
            </Alert>

            <Typography variant="body2" className="error-help">
              {_('Please check your phone number and try again. If the problem persists, contact support.')}
            </Typography>
          </>
        )}
      </StyledByonModalContent>
    </Modal>
  );
};

const StyledByonModalContent = styled(Box)(({ theme }) => ({
  minWidth: 360,
  maxWidth: 480,

  '& .step-header': {
    textAlign: 'center',
    marginBottom: theme.spacing(3),
  },

  '& .header-icon': {
    fontSize: 48,
    marginBottom: theme.spacing(1),
  },

  '& .step-description': {
    color: theme.palette.text.secondary,
  },

  '& .input-container': {
    marginBottom: theme.spacing(3),
  },

  '& .info-alert': {
    '& .MuiAlert-message': {
      width: '100%',
    },
  },

  '& .loading-container': {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    padding: theme.spacing(4),
    gap: theme.spacing(2),
  },

  '& .loading-text': {
    color: theme.palette.text.secondary,
  },

  '& .loading-phone': {
    fontFamily: 'monospace',
    fontWeight: 600,
    color: theme.palette.text.primary,
  },

  '& .phone-display': {
    fontFamily: 'monospace',
    fontSize: '1.25rem',
    fontWeight: 600,
    color: theme.palette.primary.main,
    marginBottom: theme.spacing(1),
  },

  '& .resend-section': {
    textAlign: 'center',
    marginTop: theme.spacing(3),
  },

  '& .resend-text': {
    color: theme.palette.text.secondary,
  },

  '& .resend-link': {
    color: theme.palette.primary.main,
    cursor: 'pointer',
    textDecoration: 'underline',
    '&:hover': {
      textDecoration: 'none',
    },
  },

  '& .error-header': {
    textAlign: 'center',
    marginBottom: theme.spacing(2),
  },

  '& .error-icon': {
    fontSize: 48,
  },

  '& .error-alert': {
    marginBottom: theme.spacing(2),
  },

  '& .error-help': {
    color: theme.palette.text.secondary,
    textAlign: 'center',
  },

  [theme.breakpoints.down('sm')]: {
    minWidth: 280,
  },
}));

export default ByonModal;
