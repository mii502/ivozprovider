/**
 * IncomingCall Component - Incoming call screen with caller ID and Answer/Decline
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/IncomingCall.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import CallEndIcon from '@mui/icons-material/CallEnd';
import PhoneIcon from '@mui/icons-material/Phone';
import { Box, ButtonBase, Typography } from '@mui/material';
import { useEffect, useRef } from 'react';

interface IncomingCallProps {
  callerNumber: string;
  callerName?: string;
  onAnswer: () => void;
  onDecline: () => void;
}

const IncomingCall = ({
  callerNumber,
  callerName,
  onAnswer,
  onDecline,
}: IncomingCallProps): JSX.Element => {
  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Play ringtone
  useEffect(() => {
    // Create audio element for ringtone
    // Note: You'll need to add a ring.mp3 file to public/assets/audio/
    // For now, we'll use a browser notification sound or skip if not available
    try {
      audioRef.current = new Audio('/client/assets/audio/ring.mp3');
      audioRef.current.loop = true;
      audioRef.current.volume = 0.5;
      audioRef.current.play().catch((e) => {
        // Autoplay may be blocked - that's OK
        console.log('[IncomingCall] Ringtone autoplay blocked:', e.message);
      });
    } catch (e) {
      console.log('[IncomingCall] Could not create ringtone audio');
    }

    return () => {
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current = null;
      }
    };
  }, []);

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        height: '100%',
        minHeight: 400,
        bgcolor: 'background.paper',
        position: 'relative',
        overflow: 'hidden',
        // Subtle animated background
        '&::before': {
          content: '""',
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background:
            'radial-gradient(circle at 50% 30%, rgba(76, 175, 80, 0.1) 0%, transparent 60%)',
          animation: 'pulse-bg 2s ease-in-out infinite',
          '@keyframes pulse-bg': {
            '0%, 100%': { opacity: 0.5 },
            '50%': { opacity: 1 },
          },
        },
      }}
    >
      {/* Incoming Call Label */}
      <Typography
        variant="overline"
        sx={{
          color: 'success.main',
          fontWeight: 600,
          letterSpacing: '0.15em',
          mb: 3,
          position: 'relative',
          zIndex: 1,
          animation: 'fadeInUp 0.3s ease-out',
          '@keyframes fadeInUp': {
            from: { opacity: 0, transform: 'translateY(10px)' },
            to: { opacity: 1, transform: 'translateY(0)' },
          },
        }}
      >
        {_('Incoming Call')}
      </Typography>

      {/* Pulsing Avatar Ring */}
      <Box
        sx={{
          position: 'relative',
          mb: 3,
          zIndex: 1,
        }}
      >
        {/* Outer pulse ring */}
        <Box
          sx={{
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: 100,
            height: 100,
            borderRadius: '50%',
            border: '2px solid',
            borderColor: 'success.main',
            animation: 'ripple 1.5s infinite',
            '@keyframes ripple': {
              '0%': {
                transform: 'translate(-50%, -50%) scale(1)',
                opacity: 0.8,
              },
              '100%': {
                transform: 'translate(-50%, -50%) scale(1.5)',
                opacity: 0,
              },
            },
          }}
        />
        <Box
          sx={{
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: 100,
            height: 100,
            borderRadius: '50%',
            border: '2px solid',
            borderColor: 'success.main',
            animation: 'ripple 1.5s infinite 0.5s',
          }}
        />

        {/* Avatar */}
        <Box
          sx={{
            width: 80,
            height: 80,
            borderRadius: '50%',
            bgcolor: 'success.light',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: '0 8px 32px rgba(76, 175, 80, 0.3)',
            position: 'relative',
          }}
        >
          <PhoneIcon sx={{ fontSize: 36, color: 'success.main' }} />
        </Box>
      </Box>

      {/* Caller Info */}
      <Box
        sx={{
          textAlign: 'center',
          mb: 4,
          zIndex: 1,
          animation: 'fadeInUp 0.3s ease-out 0.1s backwards',
        }}
      >
        <Typography
          variant="h5"
          sx={{
            fontFamily: '"SF Mono", "Roboto Mono", monospace',
            fontWeight: 500,
            color: 'text.primary',
            mb: 0.5,
          }}
        >
          {callerNumber || _('Unknown')}
        </Typography>
        {callerName && (
          <Typography
            variant="body1"
            sx={{
              color: 'text.secondary',
              fontWeight: 400,
            }}
          >
            {callerName}
          </Typography>
        )}
      </Box>

      {/* Action Buttons */}
      <Box
        sx={{
          display: 'flex',
          gap: 6,
          zIndex: 1,
          animation: 'fadeInUp 0.3s ease-out 0.2s backwards',
        }}
      >
        {/* Decline Button */}
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 1 }}>
          <ButtonBase
            onClick={onDecline}
            sx={{
              width: 64,
              height: 64,
              borderRadius: '50%',
              bgcolor: 'error.main',
              color: 'white',
              boxShadow: '0 4px 20px rgba(244, 67, 54, 0.4)',
              transition: 'all 0.2s ease',
              '&:hover': {
                transform: 'scale(1.08)',
                boxShadow: '0 6px 28px rgba(244, 67, 54, 0.5)',
              },
              '&:active': {
                transform: 'scale(0.95)',
              },
            }}
          >
            <CallEndIcon sx={{ fontSize: 28 }} />
          </ButtonBase>
          <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
            {_('Decline')}
          </Typography>
        </Box>

        {/* Answer Button */}
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 1 }}>
          <ButtonBase
            onClick={onAnswer}
            sx={{
              width: 64,
              height: 64,
              borderRadius: '50%',
              bgcolor: 'success.main',
              color: 'white',
              boxShadow: '0 4px 20px rgba(76, 175, 80, 0.4)',
              transition: 'all 0.2s ease',
              animation: 'bounce 1s infinite',
              '@keyframes bounce': {
                '0%, 100%': { transform: 'translateY(0)' },
                '50%': { transform: 'translateY(-4px)' },
              },
              '&:hover': {
                transform: 'scale(1.08)',
                boxShadow: '0 6px 28px rgba(76, 175, 80, 0.5)',
                animation: 'none',
              },
              '&:active': {
                transform: 'scale(0.95)',
              },
            }}
          >
            <PhoneIcon sx={{ fontSize: 28 }} />
          </ButtonBase>
          <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>
            {_('Answer')}
          </Typography>
        </Box>
      </Box>
    </Box>
  );
};

export default IncomingCall;
