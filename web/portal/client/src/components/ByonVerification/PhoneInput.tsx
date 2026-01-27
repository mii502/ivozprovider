/**
 * Phone Input Component - E.164 format phone number input
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/PhoneInput.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import { Box, InputAdornment, styled, TextField, Typography } from '@mui/material';
import { ChangeEvent, KeyboardEvent, useCallback, useEffect, useState } from 'react';

interface PhoneInputProps {
  value: string;
  onChange: (value: string) => void;
  onSubmit?: () => void;
  disabled?: boolean;
  error?: string | null;
  autoFocus?: boolean;
}

// Validate E.164 format: starts with +, followed by country code and number (7-15 digits total)
const isValidE164 = (phone: string): boolean => {
  const e164Regex = /^\+[1-9]\d{6,14}$/;
  return e164Regex.test(phone);
};

// Format phone for display (add spaces for readability)
const formatPhoneDisplay = (phone: string): string => {
  // Remove all non-digit characters except +
  const cleaned = phone.replace(/[^\d+]/g, '');

  // Return cleaned number - no automatic formatting to allow user control
  return cleaned;
};

const PhoneInput = (props: PhoneInputProps): JSX.Element => {
  const { value, onChange, onSubmit, disabled = false, error, autoFocus = true } = props;

  const [localValue, setLocalValue] = useState(value);
  const [touched, setTouched] = useState(false);

  // Sync with parent value
  useEffect(() => {
    setLocalValue(value);
  }, [value]);

  const handleChange = useCallback((e: ChangeEvent<HTMLInputElement>) => {
    let newValue = e.target.value;

    // Only allow + at start, digits elsewhere
    newValue = newValue.replace(/(?!^\+)[^\d]/g, '');

    // Ensure + is always at the start if present
    if (!newValue.startsWith('+') && newValue.includes('+')) {
      newValue = '+' + newValue.replace(/\+/g, '');
    }

    // Limit length (+ plus up to 15 digits)
    if (newValue.length > 16) {
      newValue = newValue.slice(0, 16);
    }

    setLocalValue(newValue);
    onChange(newValue);
    setTouched(true);
  }, [onChange]);

  const handleKeyDown = useCallback((e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && onSubmit && isValidE164(localValue)) {
      onSubmit();
    }
  }, [onSubmit, localValue]);

  const handleBlur = useCallback(() => {
    setTouched(true);
  }, []);

  const isValid = isValidE164(localValue);
  const showError = touched && localValue.length > 0 && !isValid;

  return (
    <StyledPhoneInput>
      <TextField
        fullWidth
        value={formatPhoneDisplay(localValue)}
        onChange={handleChange}
        onKeyDown={handleKeyDown}
        onBlur={handleBlur}
        disabled={disabled}
        autoFocus={autoFocus}
        placeholder="+34612345678"
        error={!!error || showError}
        helperText={error || (showError ? _('Enter a valid phone number with country code') : '')}
        InputProps={{
          startAdornment: (
            <InputAdornment position="start">
              <span className="phone-icon">📱</span>
            </InputAdornment>
          ),
        }}
        inputProps={{
          inputMode: 'tel',
          'aria-label': _('Phone number'),
        }}
      />

      <Box className="format-hint">
        <Typography variant="caption" className="hint-text">
          {_('Enter your phone number in international format (e.g., +34612345678)')}
        </Typography>
      </Box>

      {localValue.length > 0 && (
        <Box className="validation-status">
          {isValid ? (
            <Typography variant="caption" className="valid-status">
              ✓ {_('Valid format')}
            </Typography>
          ) : (
            <Typography variant="caption" className="invalid-status">
              {_('Include country code (e.g., +34 for Spain)')}
            </Typography>
          )}
        </Box>
      )}
    </StyledPhoneInput>
  );
};

const StyledPhoneInput = styled(Box)(({ theme }) => ({
  width: '100%',

  '& .MuiTextField-root': {
    '& .MuiOutlinedInput-root': {
      fontSize: '1.1rem',
      fontFamily: 'monospace',
    },
  },

  '& .phone-icon': {
    fontSize: '1.2rem',
  },

  '& .format-hint': {
    marginTop: theme.spacing(1),
    textAlign: 'center',
  },

  '& .hint-text': {
    color: theme.palette.text.secondary,
  },

  '& .validation-status': {
    marginTop: theme.spacing(0.5),
    textAlign: 'center',
  },

  '& .valid-status': {
    color: theme.palette.success.main,
    fontWeight: 500,
  },

  '& .invalid-status': {
    color: theme.palette.warning.main,
  },
}));

export default PhoneInput;
export { isValidE164 };
