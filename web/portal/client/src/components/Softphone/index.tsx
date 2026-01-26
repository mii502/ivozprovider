/**
 * WebRTC Softphone Component - Browser-based SIP calling for Retail clients
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/index.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-26
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CallEndIcon from '@mui/icons-material/CallEnd';
import PhoneIcon from '@mui/icons-material/Phone';
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';
import {
  Badge,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogContent,
  DialogTitle,
  Fab,
  FormControl,
  IconButton,
  InputLabel,
  MenuItem,
  Select,
  SelectChangeEvent,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useStoreActions } from 'store';

import { useJsSipClient } from '../../hooks/useJsSipClient';

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

  // Track if we've already attempted auto-registration for this account selection
  const autoRegisterAttemptedRef = useRef<number | null>(null);
  // Track if user manually disconnected (prevents auto-reconnect)
  const [manuallyDisconnected, setManuallyDisconnected] = useState(false);

  const {
    registrationState,
    callState,
    error: sipError,
    register,
    unregister,
    call,
    hangup,
    answer,
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

  // Fetch retail accounts when dialog opens (only once)
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
    // Skip if dialog not open (first load)
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
    }
  }, [destination, call]);

  const handleHangup = useCallback(() => {
    hangup();
  }, [hangup]);

  const handleAnswer = useCallback(() => {
    answer();
  }, [answer]);

  const handleClose = useCallback(() => {
    setOpen(false);
  }, []);

  const handleAccountChange = (event: SelectChangeEvent<number | ''>) => {
    const value = event.target.value;
    const newAccountId = value === '' ? '' : Number(value);

    // If changing to a different account while registered, unregister first
    if (newAccountId !== '' && newAccountId !== selectedAccount && registrationState === 'registered') {
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

  const getStatusColor = () => {
    switch (registrationState) {
      case 'registered':
        return 'success.main';
      case 'registering':
        return 'warning.main';
      case 'error':
        return 'error.main';
      default:
        return 'grey.500';
    }
  };

  const getStatusText = () => {
    switch (registrationState) {
      case 'registered':
        return _('Registered');
      case 'registering':
        return _('Registering...');
      case 'error':
        return _('Registration Failed');
      default:
        return _('Not Registered');
    }
  };

  const getCallStateText = () => {
    switch (callState) {
      case 'calling':
        return _('Calling...');
      case 'ringing':
        return _('Incoming Call');
      case 'active':
        return _('On Call');
      case 'held':
        return _('On Hold');
      default:
        return '';
    }
  };

  const error = fetchError || sipError;

  // Show badge on FAB when registered
  const fabBadgeColor = registrationState === 'registered' ? 'success' : 'default';
  const showBadge = registrationState === 'registered' || callState !== 'idle';

  return (
    <>
      {/* Floating Action Button with status badge */}
      <Badge
        color={callState !== 'idle' ? 'error' : fabBadgeColor}
        variant="dot"
        invisible={!showBadge}
        overlap="circular"
        anchorOrigin={{ vertical: 'top', horizontal: 'right' }}
        sx={{
          position: 'fixed',
          bottom: 24,
          right: 24,
          zIndex: 1000,
          '& .MuiBadge-badge': {
            width: 14,
            height: 14,
            borderRadius: '50%',
            border: '2px solid white',
          },
        }}
      >
        <Fab
          color="primary"
          onClick={() => setOpen(true)}
          aria-label={_('Open Softphone')}
        >
          <PhoneIcon />
        </Fab>
      </Badge>

      {/* Softphone Dialog */}
      <Dialog
        open={open}
        onClose={handleClose}
        maxWidth="xs"
        fullWidth
        PaperProps={{
          sx: { minHeight: 350 },
        }}
      >
        <DialogTitle
          sx={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          {_('WebRTC Phone')}
          {/* Disconnect button in header - only show when registered */}
          {registrationState === 'registered' && callState === 'idle' && (
            <Tooltip title={_('Disconnect')}>
              <IconButton
                size="small"
                onClick={handleDisconnect}
                sx={{ color: 'text.secondary' }}
              >
                <PowerSettingsNewIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
        </DialogTitle>
        <DialogContent>
          <Box sx={{ pt: 1 }}>
            {/* Account Selector - always allow changing */}
            {retailAccounts.length > 1 && (
              <FormControl fullWidth sx={{ mb: 2 }}>
                <InputLabel id="account-select-label">
                  {_('Account')}
                </InputLabel>
                <Select
                  labelId="account-select-label"
                  value={selectedAccount}
                  onChange={handleAccountChange}
                  label={_('Account')}
                  disabled={callState !== 'idle'}
                >
                  {retailAccounts.map((acc) => (
                    <MenuItem key={acc.id} value={acc.id}>
                      {acc.description || acc.name}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            )}

            {/* Loading State */}
            {loading && retailAccounts.length === 0 && (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress />
              </Box>
            )}

            {/* Registration Status */}
            {!loading && retailAccounts.length > 0 && (
              <>
                <Box
                  sx={{
                    mb: 2,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 1,
                  }}
                >
                  <Box
                    sx={{
                      width: 12,
                      height: 12,
                      borderRadius: '50%',
                      bgcolor: getStatusColor(),
                    }}
                  />
                  <Typography variant="body2">{getStatusText()}</Typography>
                </Box>

                {/* Registering state - show spinner */}
                {registrationState === 'registering' && (
                  <Box sx={{ display: 'flex', justifyContent: 'center', py: 2 }}>
                    <CircularProgress size={32} />
                  </Box>
                )}

                {/* Not registered - prompt to select account if multiple */}
                {registrationState === 'unregistered' &&
                  retailAccounts.length > 1 &&
                  selectedAccount === '' && (
                    <Typography
                      variant="body2"
                      color="text.secondary"
                      sx={{ textAlign: 'center', py: 2 }}
                    >
                      {_('Select an account to connect')}
                    </Typography>
                  )}

                {/* Registration error - show retry option */}
                {registrationState === 'error' && selectedAccount !== '' && (
                  <Button
                    variant="outlined"
                    onClick={() => {
                      autoRegisterAttemptedRef.current = null;
                      setManuallyDisconnected(false);
                      doRegister(selectedAccount as number);
                    }}
                    fullWidth
                    sx={{ mb: 2 }}
                  >
                    {_('Retry Connection')}
                  </Button>
                )}

                {/* Disconnected (manually or externally) - show connect option */}
                {registrationState === 'unregistered' &&
                  selectedAccount !== '' &&
                  (manuallyDisconnected ||
                    autoRegisterAttemptedRef.current === selectedAccount) && (
                    <Button
                      variant="contained"
                      onClick={() => {
                        setManuallyDisconnected(false);
                        autoRegisterAttemptedRef.current = null;
                        doRegister(selectedAccount as number);
                      }}
                      fullWidth
                      sx={{ mb: 2 }}
                    >
                      {_('Connect')}
                    </Button>
                  )}

                {/* Registered - show dialer */}
                {registrationState === 'registered' && (
                  <>
                    {/* Dialer */}
                    <TextField
                      value={destination}
                      onChange={(e) => setDestination(e.target.value)}
                      placeholder={_('Enter number (e.g., 00306946223761)')}
                      fullWidth
                      sx={{ mb: 2 }}
                      disabled={callState !== 'idle'}
                    />

                    {/* Call Controls */}
                    {callState === 'idle' ? (
                      <Button
                        variant="contained"
                        color="success"
                        onClick={handleCall}
                        disabled={!destination}
                        fullWidth
                        startIcon={<PhoneIcon />}
                      >
                        {_('Call')}
                      </Button>
                    ) : (
                      <Box>
                        <Typography sx={{ mb: 1, textAlign: 'center' }}>
                          {getCallStateText()}
                        </Typography>

                        {/* Answer button for incoming calls */}
                        {callState === 'ringing' && (
                          <Button
                            variant="contained"
                            color="success"
                            onClick={handleAnswer}
                            fullWidth
                            startIcon={<PhoneIcon />}
                            sx={{ mb: 1 }}
                          >
                            {_('Answer')}
                          </Button>
                        )}

                        {/* Hangup button */}
                        <Button
                          variant="contained"
                          color="error"
                          onClick={handleHangup}
                          fullWidth
                          startIcon={<CallEndIcon />}
                        >
                          {_('Hang Up')}
                        </Button>
                      </Box>
                    )}
                  </>
                )}
              </>
            )}

            {/* Error Display */}
            {error && (
              <Typography color="error" sx={{ mt: 2 }}>
                {error}
              </Typography>
            )}

            {/* No Accounts Message */}
            {!loading && retailAccounts.length === 0 && !fetchError && (
              <Typography color="text.secondary" sx={{ textAlign: 'center' }}>
                {_('No retail accounts found')}
              </Typography>
            )}
          </Box>
        </DialogContent>
      </Dialog>
    </>
  );
};

export default Softphone;
