/**
 * InCallView Component - Active call screen with timer and audio controls
 * Server path: /opt/irontec/ivozprovider/web/portal/user/src/components/Softphone/InCallView.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import CallEndIcon from '@mui/icons-material/CallEnd';
import DialpadIcon from '@mui/icons-material/Dialpad';
import MicIcon from '@mui/icons-material/Mic';
import MicOffIcon from '@mui/icons-material/MicOff';
import VolumeOffIcon from '@mui/icons-material/VolumeOff';
import VolumeUpIcon from '@mui/icons-material/VolumeUp';
import { Box, ButtonBase, IconButton, Slider, Tooltip, Typography } from '@mui/material';
import { useCallback, useEffect, useState } from 'react';

import DialPad from './DialPad';

type CallState = 'calling' | 'active' | 'held';

interface InCallViewProps {
  callState: CallState;
  destination: string;
  onHangup: () => void;
  onSendDtmf: (digit: string) => void;
  isMicMuted: boolean;
  onMicMuteToggle: () => void;
  speakerVolume: number;
  onSpeakerVolumeChange: (volume: number) => void;
  isSpeakerMuted: boolean;
  onSpeakerMuteToggle: () => void;
}

const InCallView = ({
  callState,
  destination,
  onHangup,
  onSendDtmf,
  isMicMuted,
  onMicMuteToggle,
  speakerVolume,
  onSpeakerVolumeChange,
  isSpeakerMuted,
  onSpeakerMuteToggle,
}: InCallViewProps): JSX.Element => {
  const [duration, setDuration] = useState(0);
  const [showDtmf, setShowDtmf] = useState(false);

  // Timer for call duration
  useEffect(() => {
    let interval: NodeJS.Timeout | null = null;

    if (callState === 'active') {
      interval = setInterval(() => {
        setDuration((prev) => prev + 1);
      }, 1000);
    }

    return () => {
      if (interval) {
        clearInterval(interval);
      }
    };
  }, [callState]);

  // Reset duration when call starts
  useEffect(() => {
    if (callState === 'calling') {
      setDuration(0);
    }
  }, [callState]);

  const formatDuration = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  const getStatusText = () => {
    switch (callState) {
      case 'calling':
        return _('Calling...');
      case 'active':
        return _('On Call');
      case 'held':
        return _('On Hold');
      default:
        return '';
    }
  };

  const handleVolumeChange = useCallback(
    (_event: Event, value: number | number[]) => {
      onSpeakerVolumeChange(value as number);
    },
    [onSpeakerVolumeChange]
  );

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        bgcolor: 'background.paper',
      }}
    >
      {/* Call Info Header */}
      <Box
        sx={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          pt: 3,
          pb: 2,
          px: 2,
          background: 'linear-gradient(180deg, rgba(0,0,0,0.02) 0%, rgba(0,0,0,0) 100%)',
        }}
      >
        {/* Avatar placeholder */}
        <Box
          sx={{
            width: 64,
            height: 64,
            borderRadius: '50%',
            bgcolor: 'primary.light',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            mb: 1.5,
            boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
          }}
        >
          <Typography variant="h5" sx={{ color: 'primary.contrastText', fontWeight: 600 }}>
            {destination.charAt(0) || '?'}
          </Typography>
        </Box>

        {/* Phone number */}
        <Typography
          variant="h6"
          sx={{
            fontFamily: '"SF Mono", "Roboto Mono", monospace',
            fontWeight: 500,
            color: 'text.primary',
            mb: 0.5,
          }}
        >
          {destination}
        </Typography>

        {/* Status / Duration */}
        <Typography
          variant="body2"
          sx={{
            color: callState === 'active' ? 'success.main' : 'text.secondary',
            fontWeight: 500,
            display: 'flex',
            alignItems: 'center',
            gap: 1,
          }}
        >
          {callState === 'active' && (
            <Box
              component="span"
              sx={{
                width: 8,
                height: 8,
                borderRadius: '50%',
                bgcolor: 'success.main',
                animation: 'blink 1s infinite',
                '@keyframes blink': {
                  '0%, 100%': { opacity: 1 },
                  '50%': { opacity: 0.5 },
                },
              }}
            />
          )}
          {getStatusText()}
          {callState === 'active' && ` · ${formatDuration(duration)}`}
        </Typography>
      </Box>

      {/* Audio Controls */}
      <Box sx={{ px: 3, py: 2 }}>
        {/* Mic Control */}
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 2 }}>
          <Tooltip title={isMicMuted ? _('Unmute Mic') : _('Mute Mic')}>
            <IconButton
              onClick={onMicMuteToggle}
              sx={{
                bgcolor: isMicMuted ? 'error.light' : 'grey.100',
                color: isMicMuted ? 'error.main' : 'text.secondary',
                '&:hover': {
                  bgcolor: isMicMuted ? 'error.light' : 'grey.200',
                },
              }}
            >
              {isMicMuted ? <MicOffIcon /> : <MicIcon />}
            </IconButton>
          </Tooltip>
          <Typography variant="caption" sx={{ color: 'text.secondary', flex: 1 }}>
            {_('Microphone')}
          </Typography>
        </Box>

        {/* Speaker Control */}
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
          <Tooltip title={isSpeakerMuted ? _('Unmute Speaker') : _('Mute Speaker')}>
            <IconButton
              onClick={onSpeakerMuteToggle}
              sx={{
                bgcolor: isSpeakerMuted ? 'error.light' : 'grey.100',
                color: isSpeakerMuted ? 'error.main' : 'text.secondary',
                '&:hover': {
                  bgcolor: isSpeakerMuted ? 'error.light' : 'grey.200',
                },
              }}
            >
              {isSpeakerMuted ? <VolumeOffIcon /> : <VolumeUpIcon />}
            </IconButton>
          </Tooltip>
          <Slider
            value={isSpeakerMuted ? 0 : speakerVolume}
            onChange={handleVolumeChange}
            min={0}
            max={1}
            step={0.1}
            disabled={isSpeakerMuted}
            sx={{
              flex: 1,
              color: 'primary.main',
              '& .MuiSlider-thumb': {
                width: 16,
                height: 16,
              },
            }}
          />
        </Box>
      </Box>

      {/* DTMF Toggle */}
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 1 }}>
        <Tooltip title={_('Keypad')}>
          <IconButton
            onClick={() => setShowDtmf(!showDtmf)}
            sx={{
              bgcolor: showDtmf ? 'primary.light' : 'grey.100',
              color: showDtmf ? 'primary.main' : 'text.secondary',
              '&:hover': {
                bgcolor: showDtmf ? 'primary.light' : 'grey.200',
              },
            }}
          >
            <DialpadIcon />
          </IconButton>
        </Tooltip>
      </Box>

      {/* DTMF Keypad (collapsible) */}
      {showDtmf && (
        <Box
          sx={{
            overflow: 'hidden',
            animation: 'slideDown 0.2s ease-out',
            '@keyframes slideDown': {
              from: { maxHeight: 0, opacity: 0 },
              to: { maxHeight: 400, opacity: 1 },
            },
          }}
        >
          <DialPad onDigit={onSendDtmf} />
        </Box>
      )}

      {/* Spacer */}
      <Box sx={{ flex: 1 }} />

      {/* Hangup Button */}
      <Box sx={{ display: 'flex', justifyContent: 'center', pb: 3, pt: 2 }}>
        <ButtonBase
          onClick={onHangup}
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
      </Box>
    </Box>
  );
};

export default InCallView;
