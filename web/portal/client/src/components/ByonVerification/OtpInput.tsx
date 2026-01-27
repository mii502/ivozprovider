/**
 * OTP Input Component - 6-digit verification code input
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/OtpInput.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import { Box, styled, TextField, Typography } from '@mui/material';
import {
  ChangeEvent,
  ClipboardEvent,
  KeyboardEvent,
  useCallback,
  useEffect,
  useRef,
  useState,
} from 'react';

interface OtpInputProps {
  value: string;
  onChange: (value: string) => void;
  onComplete?: (code: string) => void;
  disabled?: boolean;
  error?: string | null;
  expiresIn?: number;
  autoFocus?: boolean;
}

const OTP_LENGTH = 6;

const OtpInput = (props: OtpInputProps): JSX.Element => {
  const {
    value,
    onChange,
    onComplete,
    disabled = false,
    error,
    expiresIn = 600,
    autoFocus = true,
  } = props;

  const [digits, setDigits] = useState<string[]>(Array(OTP_LENGTH).fill(''));
  const [countdown, setCountdown] = useState(expiresIn);
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

  // Initialize digits from value
  useEffect(() => {
    const newDigits = value.split('').slice(0, OTP_LENGTH);
    while (newDigits.length < OTP_LENGTH) {
      newDigits.push('');
    }
    setDigits(newDigits);
  }, [value]);

  // Countdown timer
  useEffect(() => {
    if (countdown <= 0) return;

    const timer = setInterval(() => {
      setCountdown((prev) => prev - 1);
    }, 1000);

    return () => clearInterval(timer);
  }, [countdown]);

  // Auto-focus first input
  useEffect(() => {
    if (autoFocus && inputRefs.current[0]) {
      inputRefs.current[0].focus();
    }
  }, [autoFocus]);

  const handleChange = useCallback(
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      const digit = e.target.value.replace(/\D/g, '').slice(-1);

      const newDigits = [...digits];
      newDigits[index] = digit;
      setDigits(newDigits);

      const newCode = newDigits.join('');
      onChange(newCode);

      // Auto-advance to next field
      if (digit && index < OTP_LENGTH - 1) {
        inputRefs.current[index + 1]?.focus();
      }

      // Auto-submit when complete
      if (newCode.length === OTP_LENGTH && onComplete) {
        onComplete(newCode);
      }
    },
    [digits, onChange, onComplete]
  );

  const handleKeyDown = useCallback(
    (index: number) => (e: KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Backspace' && !digits[index] && index > 0) {
        // Move to previous field on backspace
        inputRefs.current[index - 1]?.focus();
      } else if (e.key === 'ArrowLeft' && index > 0) {
        inputRefs.current[index - 1]?.focus();
      } else if (e.key === 'ArrowRight' && index < OTP_LENGTH - 1) {
        inputRefs.current[index + 1]?.focus();
      }
    },
    [digits]
  );

  const handlePaste = useCallback(
    (e: ClipboardEvent<HTMLInputElement>) => {
      e.preventDefault();
      const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, OTP_LENGTH);

      if (pastedData) {
        const newDigits = pastedData.split('');
        while (newDigits.length < OTP_LENGTH) {
          newDigits.push('');
        }
        setDigits(newDigits);

        const newCode = newDigits.join('');
        onChange(newCode);

        // Focus last filled or last input
        const lastFilledIndex = newDigits.findLastIndex((d) => d !== '');
        const focusIndex = lastFilledIndex < OTP_LENGTH - 1 ? lastFilledIndex + 1 : lastFilledIndex;
        inputRefs.current[focusIndex]?.focus();

        // Auto-submit if complete
        if (pastedData.length === OTP_LENGTH && onComplete) {
          onComplete(newCode);
        }
      }
    },
    [onChange, onComplete]
  );

  const formatCountdown = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const isExpired = countdown <= 0;

  return (
    <StyledOtpInput>
      <Box className="otp-label">
        <Typography variant="body2" className="label-text">
          {_('Enter the 6-digit code sent to your phone')}
        </Typography>
      </Box>

      <Box className="otp-inputs">
        {digits.map((digit, index) => (
          <TextField
            key={index}
            inputRef={(el) => {
              inputRefs.current[index] = el;
            }}
            value={digit}
            onChange={handleChange(index)}
            onKeyDown={handleKeyDown(index)}
            onPaste={index === 0 ? handlePaste : undefined}
            disabled={disabled || isExpired}
            error={!!error}
            className="otp-field"
            inputProps={{
              maxLength: 1,
              inputMode: 'numeric',
              pattern: '[0-9]*',
              'aria-label': `${_('Digit')} ${index + 1}`,
              autoComplete: 'one-time-code',
            }}
          />
        ))}
      </Box>

      {error && (
        <Typography variant="caption" className="error-text" color="error">
          {error}
        </Typography>
      )}

      <Box className="timer-container">
        {isExpired ? (
          <Typography variant="body2" className="expired-text" color="error">
            {_('Code expired. Please request a new one.')}
          </Typography>
        ) : (
          <Typography variant="body2" className="timer-text">
            {_('Code expires in')} <span className="timer-value">{formatCountdown(countdown)}</span>
          </Typography>
        )}
      </Box>
    </StyledOtpInput>
  );
};

const StyledOtpInput = styled(Box)(({ theme }) => ({
  width: '100%',

  '& .otp-label': {
    textAlign: 'center',
    marginBottom: theme.spacing(2),
  },

  '& .label-text': {
    color: theme.palette.text.secondary,
  },

  '& .otp-inputs': {
    display: 'flex',
    justifyContent: 'center',
    gap: theme.spacing(1),
    marginBottom: theme.spacing(1),
  },

  '& .otp-field': {
    width: 48,

    '& .MuiOutlinedInput-root': {
      '& input': {
        textAlign: 'center',
        fontSize: '1.5rem',
        fontWeight: 600,
        padding: theme.spacing(1.5),
        fontFamily: 'monospace',
      },
    },
  },

  '& .error-text': {
    display: 'block',
    textAlign: 'center',
    marginBottom: theme.spacing(1),
  },

  '& .timer-container': {
    textAlign: 'center',
    marginTop: theme.spacing(2),
  },

  '& .timer-text': {
    color: theme.palette.text.secondary,
  },

  '& .timer-value': {
    fontWeight: 600,
    color: theme.palette.primary.main,
  },

  '& .expired-text': {
    fontWeight: 500,
  },

  [theme.breakpoints.down('sm')]: {
    '& .otp-field': {
      width: 40,

      '& .MuiOutlinedInput-root': {
        '& input': {
          fontSize: '1.25rem',
          padding: theme.spacing(1),
        },
      },
    },
  },
}));

export default OtpInput;
