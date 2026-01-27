/**
 * WebRTC Softphone Component - Modern phone-like UI for vPBX Terminal users
 * Server path: /opt/irontec/ivozprovider/web/portal/user/src/components/Softphone/index.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 *
 * Differences from client portal version:
 * - Uses Terminal credentials from profile (terminalName, terminalPassword, companyDomain)
 * - No account selector (single terminal per user)
 * - Auto-registers when panel opens if terminal exists
 * - Store path: state.userStatus.status.profile (not clientSession)
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import CloseIcon from '@mui/icons-material/Close';
import DialpadIcon from '@mui/icons-material/Dialpad';
import HistoryIcon from '@mui/icons-material/History';
import PhoneIcon from '@mui/icons-material/Phone';
import {
  Badge,
  Box,
  Button,
  ClickAwayListener,
  Fab,
  IconButton,
  Paper,
  Slide,
  Tab,
  Tabs,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useStoreState } from 'store';

import { useJsSipClient } from '../../hooks/useJsSipClient';
import CallButton from './CallButton';
import CallHistory from './CallHistory';
import DialPad from './DialPad';
import InCallView from './InCallView';
import IncomingCall from './IncomingCall';
import NumberDisplay from './NumberDisplay';
import StatusBar from './StatusBar';

interface WebRtcCredentials {
  sipUser: string;
  sipPassword: string;
  domain: string;
  displayName: string;
  wsServer: string;
  stunServers: string[];
  turnServers: { urls: string; username: string; credential: string }[];
}

const Softphone = (): JSX.Element | null => {
  // Get user profile to check for terminal credentials
  const profile = useStoreState((state) => state.userStatus.status.profile);

  const [open, setOpen] = useState(false);
  const [destination, setDestination] = useState('');
  const [activeTab, setActiveTab] = useState<'keypad' | 'history'>('keypad');

  // Track if we've already attempted auto-registration
  const autoRegisterAttemptedRef = useRef<boolean>(false);
  // Track if user manually disconnected (prevents auto-reconnect)
  const [manuallyDisconnected, setManuallyDisconnected] = useState(false);
  // Ref for FAB to exclude from ClickAwayListener
  const fabRef = useRef<HTMLDivElement>(null);

  const {
    registrationState,
    callState,
    error: sipError,
    remoteIdentity,
    isMuted,
    isSpeakerMuted,
    speakerVolume,
    currentDestination,
    register,
    unregister,
    call,
    hangup,
    answer,
    toggleMute,
    setSpeakerVolume,
    toggleSpeakerMute,
    sendDtmf,
  } = useJsSipClient();

  // Check if user has terminal credentials
  const hasTerminal =
    profile?.terminalName &&
    profile?.terminalPassword &&
    profile?.companyDomain;

  // Build WebRTC credentials from Terminal profile
  const buildWebRtcCredentials = useCallback((): WebRtcCredentials | null => {
    if (!hasTerminal || !profile) return null;

    const domain = profile.companyDomain;
    return {
      sipUser: profile.terminalName,
      sipPassword: profile.terminalPassword,
      domain: domain,
      displayName: profile.userName || profile.terminalName,
      wsServer: `wss://${domain}/ws-sip`,
      stunServers: [`stun:${domain}:3478`, 'stun:stun.l.google.com:19302'],
      turnServers: [
        {
          urls: `turn:${domain}:3478`,
          username: 'webrtc',
          credential: 'Wh8K3mNpQrStUvXyZ9AaBbCcDdEe',
        },
      ],
    };
  }, [hasTerminal, profile]);

  // Auto-register when panel opens (if terminal exists and not manually disconnected)
  useEffect(() => {
    // Skip if no terminal credentials
    if (!hasTerminal) return;
    // Skip if already registered
    if (registrationState === 'registered') return;
    // Skip if registering
    if (registrationState === 'registering') return;
    // Skip if already attempted
    if (autoRegisterAttemptedRef.current) return;
    // Skip if manually disconnected
    if (manuallyDisconnected) return;
    // Skip if panel not open
    if (!open) return;

    // Auto-register
    autoRegisterAttemptedRef.current = true;
    const credentials = buildWebRtcCredentials();
    if (credentials) {
      register(credentials);
    }
  }, [
    hasTerminal,
    registrationState,
    manuallyDisconnected,
    open,
    buildWebRtcCredentials,
    register,
  ]);

  // Cleanup on unmount only
  useEffect(() => {
    return () => {
      if (registrationState === 'registered') {
        unregister();
      }
    };
  }, [registrationState, unregister]);

  // Auto-open panel on incoming call
  useEffect(() => {
    if (callState === 'ringing' && !open) {
      setOpen(true);
    }
  }, [callState, open]);

  const handleCall = useCallback(() => {
    if (destination) {
      call(destination);
    }
  }, [destination, call]);

  const handleHangup = useCallback(() => {
    hangup();
  }, [hangup]);

  const handleAnswer = useCallback(() => {
    answer();
  }, [answer]);

  const handleClose = useCallback(() => {
    // Don't close during active calls
    if (callState !== 'idle' && callState !== 'ringing') return;
    setOpen(false);
  }, [callState]);

  const handleDisconnect = useCallback(() => {
    setManuallyDisconnected(true);
    unregister();
  }, [unregister]);

  const handleConnect = useCallback(() => {
    setManuallyDisconnected(false);
    autoRegisterAttemptedRef.current = false;
    const credentials = buildWebRtcCredentials();
    if (credentials) {
      register(credentials);
    }
  }, [buildWebRtcCredentials, register]);

  const handleDigit = useCallback((digit: string) => {
    setDestination((prev) => prev + digit);
  }, []);

  const handleBackspace = useCallback(() => {
    setDestination((prev) => prev.slice(0, -1));
  }, []);

  const handleRedial = useCallback((number: string) => {
    setDestination(number);
    setActiveTab('keypad');
  }, []);

  const handleTabChange = useCallback(
    (_event: React.SyntheticEvent, newValue: 'keypad' | 'history') => {
      setActiveTab(newValue);
    },
    []
  );

  // Show badge on FAB when registered
  const fabBadgeColor = registrationState === 'registered' ? 'success' : 'default';
  const showBadge = registrationState === 'registered' || callState !== 'idle';

  // Determine which view to show
  const isInCall = callState === 'calling' || callState === 'active' || callState === 'held';
  const isRinging = callState === 'ringing';
  const isIdle = callState === 'idle';

  // Can close if idle
  const canClose = isIdle;

  // Handle click away - exclude clicks on the FAB
  const handleClickAway = useCallback(
    (event: MouseEvent | TouchEvent) => {
      if (fabRef.current && fabRef.current.contains(event.target as Node)) {
        return;
      }
      if (canClose && open) {
        handleClose();
      }
    },
    [canClose, open, handleClose]
  );

  // Don't render softphone if user doesn't have terminal credentials
  if (!hasTerminal) {
    return null;
  }

  const displayName = profile?.userName || profile?.terminalName;

  return (
    <>
      {/* Floating Action Button with status badge */}
      <Box ref={fabRef} sx={{ position: 'fixed', bottom: 24, right: 24, zIndex: 1300 }}>
        <Badge
          color={callState !== 'idle' ? 'error' : fabBadgeColor}
          variant="dot"
          invisible={!showBadge}
          overlap="circular"
          anchorOrigin={{ vertical: 'top', horizontal: 'right' }}
          sx={{
            '& .MuiBadge-badge': {
              width: 14,
              height: 14,
              borderRadius: '50%',
              border: '2px solid white',
              // Pulse animation for incoming calls
              ...(isRinging && {
                animation: 'pulse-badge 1s infinite',
                '@keyframes pulse-badge': {
                  '0%': { transform: 'scale(1)' },
                  '50%': { transform: 'scale(1.3)' },
                  '100%': { transform: 'scale(1)' },
                },
              }),
            },
          }}
        >
          <Fab
            color={isRinging ? 'error' : 'primary'}
            onClick={() => setOpen(true)}
            aria-label={_('Open Softphone')}
            sx={{
              // Shake animation for incoming calls
              ...(isRinging && {
                animation: 'shake 0.5s infinite',
                '@keyframes shake': {
                  '0%, 100%': { transform: 'rotate(0deg)' },
                  '25%': { transform: 'rotate(-10deg)' },
                  '75%': { transform: 'rotate(10deg)' },
                },
              }),
            }}
          >
            <PhoneIcon />
          </Fab>
        </Badge>
      </Box>

      {/* Softphone Floating Panel */}
      <ClickAwayListener onClickAway={handleClickAway}>
        <Slide direction="up" in={open} mountOnEnter unmountOnExit>
          <Paper
            elevation={12}
            sx={{
              position: 'fixed',
              bottom: 96,
              right: 24,
              width: 320,
              maxHeight: 'calc(100vh - 120px)',
              borderRadius: 4,
              overflow: 'hidden',
              zIndex: 1200,
              display: 'flex',
              flexDirection: 'column',
              bgcolor: 'background.paper',
            }}
          >
            {/* Header */}
            <Box
              sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                px: 2,
                py: 1,
                bgcolor: 'primary.main',
                color: 'primary.contrastText',
              }}
            >
              <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                {_('Phone')}
              </Typography>
              {canClose && (
                <IconButton
                  size="small"
                  onClick={handleClose}
                  sx={{ color: 'inherit', opacity: 0.8, '&:hover': { opacity: 1 } }}
                >
                  <CloseIcon fontSize="small" />
                </IconButton>
              )}
            </Box>

            {/* Status Bar (simplified - no account dropdown) */}
            <StatusBar
              registrationState={registrationState}
              onDisconnect={handleDisconnect}
              onConnect={handleConnect}
              disabled={!isIdle}
              displayName={displayName}
            />

            {/* Content Area */}
            <Box sx={{ flex: 1, overflow: 'auto' }}>
              {/* Registering state */}
              {registrationState === 'registering' && (
                <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                  <Typography color="text.secondary">{_('Connecting...')}</Typography>
                </Box>
              )}

              {/* Not registered - prompt to connect */}
              {registrationState === 'unregistered' && !manuallyDisconnected && (
                <Box sx={{ p: 4, textAlign: 'center' }}>
                  <Typography color="text.secondary">{_('Connecting...')}</Typography>
                </Box>
              )}

              {/* Manually disconnected - show connect option */}
              {registrationState === 'unregistered' && manuallyDisconnected && (
                <Box sx={{ p: 4, textAlign: 'center' }}>
                  <Typography color="text.secondary" sx={{ mb: 2 }}>
                    {_('Click Connect to register')}
                  </Typography>
                </Box>
              )}

              {/* Registration error - show retry */}
              {registrationState === 'error' && (
                <Box sx={{ p: 4, textAlign: 'center' }}>
                  <Typography color="error" sx={{ mb: 2 }}>
                    {sipError || _('Connection failed')}
                  </Typography>
                  <Button variant="outlined" color="error" onClick={handleConnect}>
                    {_('Retry')}
                  </Button>
                </Box>
              )}

              {/* Incoming Call View */}
              {isRinging && (
                <IncomingCall
                  callerNumber={remoteIdentity?.number || currentDestination}
                  callerName={remoteIdentity?.displayName}
                  onAnswer={handleAnswer}
                  onDecline={handleHangup}
                />
              )}

              {/* In-Call View */}
              {isInCall && (
                <InCallView
                  callState={callState as 'calling' | 'active' | 'held'}
                  destination={currentDestination || destination}
                  onHangup={handleHangup}
                  onSendDtmf={sendDtmf}
                  isMicMuted={isMuted}
                  onMicMuteToggle={toggleMute}
                  speakerVolume={speakerVolume}
                  onSpeakerVolumeChange={setSpeakerVolume}
                  isSpeakerMuted={isSpeakerMuted}
                  onSpeakerMuteToggle={toggleSpeakerMute}
                />
              )}

              {/* Idle - Show Tabs (Keypad / History) */}
              {registrationState === 'registered' && isIdle && (
                <>
                  {/* Tab Navigation */}
                  <Tabs
                    value={activeTab}
                    onChange={handleTabChange}
                    variant="fullWidth"
                    sx={{
                      minHeight: 40,
                      bgcolor: 'grey.50',
                      borderBottom: 1,
                      borderColor: 'divider',
                      '& .MuiTab-root': {
                        minHeight: 40,
                        py: 1,
                        textTransform: 'none',
                        fontWeight: 500,
                      },
                      '& .Mui-selected': {
                        color: 'primary.main',
                      },
                    }}
                  >
                    <Tab
                      value="keypad"
                      icon={<DialpadIcon fontSize="small" />}
                      iconPosition="start"
                      label={_('Keypad')}
                    />
                    <Tab
                      value="history"
                      icon={<HistoryIcon fontSize="small" />}
                      iconPosition="start"
                      label={_('History')}
                    />
                  </Tabs>

                  {/* Keypad View */}
                  {activeTab === 'keypad' && (
                    <>
                      {/* Number Display */}
                      <NumberDisplay
                        value={destination}
                        onChange={setDestination}
                        onBackspace={handleBackspace}
                        onCall={handleCall}
                        placeholder="Enter number"
                        disabled={false}
                      />

                      {/* Dial Pad */}
                      <DialPad onDigit={handleDigit} disabled={false} />

                      {/* Call Button */}
                      <CallButton
                        state={destination ? 'idle' : 'disabled'}
                        onClick={handleCall}
                        disabled={!destination}
                      />

                      {/* Error Display */}
                      {sipError && (
                        <Typography
                          color="error"
                          variant="caption"
                          sx={{ display: 'block', textAlign: 'center', pb: 2 }}
                        >
                          {sipError}
                        </Typography>
                      )}
                    </>
                  )}

                  {/* History View */}
                  {activeTab === 'history' && (
                    <CallHistory onRedial={handleRedial} disabled={false} />
                  )}
                </>
              )}
            </Box>
          </Paper>
        </Slide>
      </ClickAwayListener>
    </>
  );
};

export default Softphone;
