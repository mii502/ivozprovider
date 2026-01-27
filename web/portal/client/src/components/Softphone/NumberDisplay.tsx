/**
 * NumberDisplay Component - Shows dialed number with direct text input and backspace
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/NumberDisplay.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import BackspaceOutlinedIcon from '@mui/icons-material/BackspaceOutlined';
import { Box, IconButton, InputBase } from '@mui/material';
import { ChangeEvent, KeyboardEvent, useCallback } from 'react';

interface NumberDisplayProps {
  value: string;
  onChange: (value: string) => void;
  onBackspace: () => void;
  onCall?: () => void;
  placeholder?: string;
  disabled?: boolean;
}

const NumberDisplay = ({
  value,
  onChange,
  onBackspace,
  onCall,
  placeholder = 'Enter number',
  disabled = false,
}: NumberDisplayProps): JSX.Element => {
  // Filter input to only allow phone number characters
  const handleInputChange = useCallback(
    (event: ChangeEvent<HTMLInputElement>) => {
      const newValue = event.target.value;
      // Allow digits, *, #, and + (for international prefix)
      const filtered = newValue.replace(/[^0-9*#+]/g, '');
      onChange(filtered);
    },
    [onChange]
  );

  // Handle Enter key to trigger call
  const handleKeyDown = useCallback(
    (event: KeyboardEvent<HTMLInputElement>) => {
      if (event.key === 'Enter' && value && onCall && !disabled) {
        event.preventDefault();
        onCall();
      }
    },
    [value, onCall, disabled]
  );

  return (
    <Box
      sx={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        px: 2,
        py: 1.5,
        minHeight: 64,
        borderBottom: '1px solid',
        borderColor: 'divider',
        bgcolor: 'background.paper',
      }}
    >
      <Box sx={{ flex: 1, overflow: 'hidden' }}>
        <InputBase
          value={value}
          onChange={handleInputChange}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          fullWidth
          inputProps={{
            'aria-label': 'Phone number input',
            autoComplete: 'tel',
            inputMode: 'tel',
          }}
          sx={{
            fontSize: value ? '1.75rem' : '1.25rem',
            fontWeight: 400,
            fontFamily: value
              ? '"SF Mono", "Roboto Mono", "Consolas", monospace'
              : 'inherit',
            color: value ? 'text.primary' : 'text.disabled',
            letterSpacing: value ? '0.02em' : 'normal',
            fontStyle: value ? 'normal' : 'italic',
            '& input': {
              padding: 0,
              height: 'auto',
            },
            '& input::placeholder': {
              opacity: 1,
              color: 'text.disabled',
              fontStyle: 'italic',
              fontSize: '1.25rem',
              fontFamily: 'inherit',
            },
          }}
        />
      </Box>

      <IconButton
        onClick={onBackspace}
        disabled={disabled || !value}
        sx={{
          ml: 1,
          color: 'text.secondary',
          opacity: value ? 1 : 0,
          transition: 'opacity 0.2s ease',
          '&:hover': {
            color: 'error.main',
            bgcolor: 'error.light',
          },
          '&:active': {
            transform: 'scale(0.9)',
          },
          '&.Mui-disabled': {
            opacity: 0,
          },
        }}
        size="medium"
      >
        <BackspaceOutlinedIcon />
      </IconButton>
    </Box>
  );
};

export default NumberDisplay;
