/**
 * CallButton Component - Large circular call/hangup button
 * Server path: /opt/irontec/ivozprovider/web/portal/user/src/components/Softphone/CallButton.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import CallEndIcon from '@mui/icons-material/CallEnd';
import PhoneIcon from '@mui/icons-material/Phone';
import { Box, ButtonBase, CircularProgress } from '@mui/material';

type CallButtonState = 'idle' | 'calling' | 'active' | 'disabled';

interface CallButtonProps {
  state: CallButtonState;
  onClick: () => void;
  disabled?: boolean;
}

const CallButton = ({ state, onClick, disabled = false }: CallButtonProps): JSX.Element => {
  const isHangup = state === 'calling' || state === 'active';
  const isLoading = state === 'calling';
  const isDisabled = disabled || state === 'disabled';

  return (
    <Box
      sx={{
        display: 'flex',
        justifyContent: 'center',
        py: 2,
      }}
    >
      <ButtonBase
        onClick={onClick}
        disabled={isDisabled}
        sx={{
          width: 64,
          height: 64,
          borderRadius: '50%',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          bgcolor: isHangup ? 'error.main' : 'success.main',
          color: 'white',
          transition: 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
          boxShadow: isHangup
            ? '0 4px 20px rgba(244, 67, 54, 0.4)'
            : '0 4px 20px rgba(76, 175, 80, 0.4)',
          cursor: isDisabled ? 'not-allowed' : 'pointer',
          opacity: isDisabled ? 0.5 : 1,
          position: 'relative',
          overflow: 'hidden',
          '&:hover': {
            transform: isDisabled ? 'none' : 'scale(1.08)',
            boxShadow: isHangup
              ? '0 6px 28px rgba(244, 67, 54, 0.5)'
              : '0 6px 28px rgba(76, 175, 80, 0.5)',
          },
          '&:active': {
            transform: isDisabled ? 'none' : 'scale(0.95)',
          },
          // Pulse animation when calling
          ...(isLoading && {
            animation: 'pulse 1.5s infinite',
            '@keyframes pulse': {
              '0%': {
                boxShadow: '0 0 0 0 rgba(244, 67, 54, 0.5)',
              },
              '70%': {
                boxShadow: '0 0 0 15px rgba(244, 67, 54, 0)',
              },
              '100%': {
                boxShadow: '0 0 0 0 rgba(244, 67, 54, 0)',
              },
            },
          }),
        }}
      >
        {isLoading ? (
          <CircularProgress
            size={28}
            thickness={3}
            sx={{
              color: 'white',
              position: 'absolute',
            }}
          />
        ) : isHangup ? (
          <CallEndIcon sx={{ fontSize: 28 }} />
        ) : (
          <PhoneIcon sx={{ fontSize: 28 }} />
        )}
      </ButtonBase>
    </Box>
  );
};

export default CallButton;
