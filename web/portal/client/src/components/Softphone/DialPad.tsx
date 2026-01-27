/**
 * DialPad Component - 12-key phone keypad with Material Design styling
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/DialPad.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import { Box, ButtonBase, Typography } from '@mui/material';
import { useCallback, useRef } from 'react';

interface DialPadProps {
  onDigit: (digit: string) => void;
  disabled?: boolean;
}

interface KeyConfig {
  digit: string;
  letters: string;
  longPressValue?: string;
}

const KEYS: KeyConfig[][] = [
  [
    { digit: '1', letters: '' },
    { digit: '2', letters: 'ABC' },
    { digit: '3', letters: 'DEF' },
  ],
  [
    { digit: '4', letters: 'GHI' },
    { digit: '5', letters: 'JKL' },
    { digit: '6', letters: 'MNO' },
  ],
  [
    { digit: '7', letters: 'PQRS' },
    { digit: '8', letters: 'TUV' },
    { digit: '9', letters: 'WXYZ' },
  ],
  [
    { digit: '*', letters: '' },
    { digit: '0', letters: '+', longPressValue: '+' },
    { digit: '#', letters: '' },
  ],
];

const DialPad = ({ onDigit, disabled = false }: DialPadProps): JSX.Element => {
  const longPressTimer = useRef<NodeJS.Timeout | null>(null);
  const isLongPress = useRef(false);

  const handleMouseDown = useCallback(
    (key: KeyConfig) => {
      if (disabled) return;
      isLongPress.current = false;

      if (key.longPressValue) {
        longPressTimer.current = setTimeout(() => {
          isLongPress.current = true;
          onDigit(key.longPressValue!);
        }, 500);
      }
    },
    [disabled, onDigit]
  );

  const handleMouseUp = useCallback(
    (key: KeyConfig) => {
      if (disabled) return;

      if (longPressTimer.current) {
        clearTimeout(longPressTimer.current);
        longPressTimer.current = null;
      }

      if (!isLongPress.current) {
        onDigit(key.digit);
      }
      isLongPress.current = false;
    },
    [disabled, onDigit]
  );

  const handleMouseLeave = useCallback(() => {
    if (longPressTimer.current) {
      clearTimeout(longPressTimer.current);
      longPressTimer.current = null;
    }
    isLongPress.current = false;
  }, []);

  return (
    <Box
      sx={{
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 1.5,
        p: 2,
        maxWidth: 280,
        mx: 'auto',
      }}
    >
      {KEYS.flat().map((key) => (
        <ButtonBase
          key={key.digit}
          disabled={disabled}
          onMouseDown={() => handleMouseDown(key)}
          onMouseUp={() => handleMouseUp(key)}
          onMouseLeave={handleMouseLeave}
          onTouchStart={() => handleMouseDown(key)}
          onTouchEnd={() => handleMouseUp(key)}
          sx={{
            width: 72,
            height: 72,
            borderRadius: '50%',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            bgcolor: 'grey.100',
            transition: 'all 0.15s ease-out',
            cursor: disabled ? 'not-allowed' : 'pointer',
            opacity: disabled ? 0.5 : 1,
            boxShadow: '0 2px 4px rgba(0,0,0,0.08)',
            '&:hover': {
              bgcolor: disabled ? 'grey.100' : 'grey.200',
              transform: disabled ? 'none' : 'scale(1.02)',
            },
            '&:active': {
              bgcolor: disabled ? 'grey.100' : 'grey.300',
              transform: disabled ? 'none' : 'scale(0.96)',
              boxShadow: '0 1px 2px rgba(0,0,0,0.1)',
            },
            // Ripple effect
            overflow: 'hidden',
            '& .MuiTouchRipple-root': {
              color: 'primary.main',
            },
          }}
        >
          <Typography
            variant="h5"
            sx={{
              fontWeight: 500,
              color: 'text.primary',
              lineHeight: 1,
              fontFamily: '"PublicSans", "SF Pro Display", -apple-system, sans-serif',
            }}
          >
            {key.digit}
          </Typography>
          {key.letters && (
            <Typography
              variant="caption"
              sx={{
                fontSize: '0.65rem',
                fontWeight: 500,
                color: 'text.secondary',
                letterSpacing: '0.08em',
                mt: 0.25,
              }}
            >
              {key.letters}
            </Typography>
          )}
        </ButtonBase>
      ))}
    </Box>
  );
};

export default DialPad;
