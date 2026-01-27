/**
 * WebRTC Softphone Component - Modern phone-like UI for Retail clients
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/index.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CloseIcon from '@mui/icons-material/Close';
import DialpadIcon from '@mui/icons-material/Dialpad';
import HistoryIcon from '@mui/icons-material/History';
import PhoneIcon from '@mui/icons-material/Phone';
import {
  Badge,
  Box,
  Button,
  CircularProgress,
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
import { useStoreActions } from 'store';

import { useJsSipClient } from '../../hooks/useJsSipClient';
import CallButton from './CallButton';
import CallHistory from './CallHistory';
import DialPad from './DialPad';
import InCallView from './InCallView';
import IncomingCall from './IncomingCall';
import NumberDisplay from './NumberDisplay';
import StatusBar from './StatusBar';

const STORAGE_KEY = 'softphone_last_account';

interface RetailAccount {
  id: number;
  name: string;
  description?: string;
}

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
  const [open, setOpen] = useState(false);
  const [destination, setDestination] = useState('');
  const [selectedAccount, setSelectedAccount] = useState<number | ''>('');
  const [retailAccounts, setRetailAccounts] = useState<RetailAccount[]>([]);
  const [loading, setLoading] = useState(false);
  const [fetchError, setFetchError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'keypad' | 'history'>('keypad');

  // Track if we've already attempted auto-registration for this account selection
  const autoRegisterAttemptedRef = useRef<number | null>(null);
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

  const apiGet = useStoreActions((store) => store.api.get);
  const [, cancelToken] = useCancelToken();

  // Load last selected account from localStorage
  useEffect(() => {
    const savedAccount = localStorage.getItem(STORAGE_KEY);
    if (savedAccount) {
      const accountId = parseInt(savedAccount, 10);
      if (!isNaN(accountId)) {
        setSelectedAccount(accountId);
      }
    }
  }, []);

  // Save selected account to localStorage
  useEffect(() => {
    if (selectedAccount !== '') {
      localStorage.setItem(STORAGE_KEY, String(selectedAccount));
    }
  }, [selectedAccount]);

  // Fetch retail accounts when panel opens (only once)
  useEffect(() => {
    if (open && retailAccounts.length === 0) {
      fetchRetailAccounts();
    }
  }, [open]);

  // Auto-register when:
  // 1. Single account loaded and not yet registered
  // 2. Account selection changes (for multiple accounts)
  useEffect(() => {
    // Skip if already registered to the same account
    if (registrationState === 'registered') return;
    // Skip if already registering
    if (registrationState === 'registering') return;
    // Skip if no account selected
    if (selectedAccount === '') return;
    // Skip if we already attempted to register this account
    if (autoRegisterAttemptedRef.current === selectedAccount) return;
    // Skip if user manually disconnected (until they change account)
    if (manuallyDisconnected) return;
    // Skip if accounts haven't loaded yet
    if (retailAccounts.length === 0) return;
    // Skip if panel not open (first load)
    if (!open) return;

    // Verify selected account exists in the list
    const accountExists = retailAccounts.some((acc) => acc.id === selectedAccount);
    if (!accountExists) {
      // If saved account doesn't exist, select first available
      if (retailAccounts.length === 1) {
        setSelectedAccount(retailAccounts[0].id);
      }
      return;
    }

    // Auto-register
    autoRegisterAttemptedRef.current = selectedAccount;
    doRegister(selectedAccount);
  }, [selectedAccount, retailAccounts, registrationState, open]);

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

  const fetchRetailAccounts = async () => {
    try {
      setLoading(true);
      setFetchError(null);

      await apiGet({
        path: '/retail_accounts',
        params: {},
        cancelToken,
        successCallback: async (response: unknown) => {
          if (Array.isArray(response)) {
            const accounts = response as RetailAccount[];
            setRetailAccounts(accounts);

            // Auto-select logic:
            // 1. If savedAccount exists in list, keep it (already set from localStorage)
            // 2. If savedAccount doesn't exist or not set, and only one account, select it
            if (accounts.length === 1) {
              setSelectedAccount(accounts[0].id);
            } else if (accounts.length > 1 && selectedAccount !== '') {
              // Verify saved account exists
              const exists = accounts.some((acc) => acc.id === selectedAccount);
              if (!exists) {
                // Saved account no longer exists, clear selection
                setSelectedAccount('');
              }
            }
          }
          setLoading(false);
        },
      });
    } catch (err) {
      setFetchError(_('Failed to load accounts'));
      setLoading(false);
    }
  };

  const doRegister = useCallback(
    async (accountId: number) => {
      try {
        setLoading(true);
        setFetchError(null);

        await apiGet({
          path: `/my/webrtc-credentials/${accountId}`,
          params: {},
          cancelToken,
          successCallback: async (response: unknown) => {
            if (response && typeof response === 'object') {
              const credentials = response as WebRtcCredentials;
              register(credentials);
            }
            setLoading(false);
          },
        });
      } catch (err) {
        setFetchError(_('Failed to get credentials'));
        setLoading(false);
      }
    },
    [apiGet, cancelToken, register]
  );

  const handleCall = useCallback(() => {
    if (destination) {
      call(destination);
      // Don't clear destination - keep it for redial
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

  const handleAccountChange = (accountId: number | '') => {
    const newAccountId = accountId;

    // If changing to a different account while registered, unregister first
    if (
      newAccountId !== '' &&
      newAccountId !== selectedAccount &&
      registrationState === 'registered'
    ) {
      unregister();
    }

    // Reset auto-register tracking for new account
    // Note: Don't reset manuallyDisconnected - respect user's explicit disconnect
    if (newAccountId !== selectedAccount) {
      autoRegisterAttemptedRef.current = null;
    }

    setSelectedAccount(newAccountId);
  };

  const handleDisconnect = useCallback(() => {
    setManuallyDisconnected(true);
    unregister();
  }, [unregister]);

  const handleConnect = useCallback(() => {
    if (selectedAccount !== '') {
      setManuallyDisconnected(false);
      autoRegisterAttemptedRef.current = null;
      doRegister(selectedAccount as number);
    }
  }, [selectedAccount, doRegister]);

  const handleDigit = useCallback((digit: string) => {
    setDestination((prev) => prev + digit);
  }, []);

  const handleBackspace = useCallback(() => {
    setDestination((prev) => prev.slice(0, -1));
  }, []);

  const handleRedial = useCallback(
    (number: string) => {
      setDestination(number);
      setActiveTab('keypad');
    },
    []
  );

  const handleTabChange = useCallback(
    (_event: React.SyntheticEvent, newValue: 'keypad' | 'history') => {
      setActiveTab(newValue);
    },
    []
  );

  const error = fetchError || sipError;

  // Show badge on FAB when registered
  const fabBadgeColor = registrationState === 'registered' ? 'success' : 'default';
  const showBadge = registrationState === 'registered' || callState !== 'idle';

  // Determine which view to show
  const isInCall = callState === 'calling' || callState === 'active' || callState === 'held';
  const isRinging = callState === 'ringing';
  const isIdle = callState === 'idle';

  // Can close if idle or ringing (to decline without interaction)
  const canClose = isIdle;

  // Handle click away - exclude clicks on the FAB
  const handleClickAway = useCallback(
    (event: MouseEvent | TouchEvent) => {
      // Don't close if click was on the FAB
      if (fabRef.current && fabRef.current.contains(event.target as Node)) {
        return;
      }
      if (canClose && open) {
        handleClose();
      }
    },
    [canClose, open, handleClose]
  );

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

            {/* Status Bar */}
            <StatusBar
              registrationState={registrationState}
              accounts={retailAccounts}
              selectedAccount={selectedAccount}
              onAccountChange={handleAccountChange}
              onDisconnect={handleDisconnect}
              onConnect={handleConnect}
              disabled={!isIdle || loading}
            />

            {/* Content Area */}
            <Box sx={{ flex: 1, overflow: 'auto' }}>
              {/* Loading State */}
              {loading && retailAccounts.length === 0 && (
                <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                  <CircularProgress />
                </Box>
              )}

              {/* No Accounts Message */}
              {!loading && retailAccounts.length === 0 && !fetchError && (
                <Box sx={{ p: 4, textAlign: 'center' }}>
                  <Typography color="text.secondary">
                    {_('No retail accounts found')}
                  </Typography>
                </Box>
              )}

              {/* Registering state */}
              {registrationState === 'registering' && (
                <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                  <CircularProgress />
                </Box>
              )}

              {/* Not registered - prompt to select account */}
              {registrationState === 'unregistered' &&
                retailAccounts.length > 0 &&
                !loading &&
                !manuallyDisconnected &&
                autoRegisterAttemptedRef.current !== selectedAccount && (
                  <Box sx={{ p: 4, textAlign: 'center' }}>
                    <Typography color="text.secondary">
                      {selectedAccount === ''
                        ? _('Select an account to connect')
                        : _('Connecting...')}
                    </Typography>
                  </Box>
                )}

              {/* Registration error - show retry */}
              {registrationState === 'error' && (
                <Box sx={{ p: 4, textAlign: 'center' }}>
                  <Typography color="error" sx={{ mb: 2 }}>
                    {error || _('Connection failed')}
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
                      {error && (
                        <Typography
                          color="error"
                          variant="caption"
                          sx={{ display: 'block', textAlign: 'center', pb: 2 }}
                        >
                          {error}
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
