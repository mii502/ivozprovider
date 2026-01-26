/**
 * WebRTC Softphone Component - Browser-based SIP calling for Retail clients
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/index.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-25
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CallEndIcon from '@mui/icons-material/CallEnd';
import PhoneIcon from '@mui/icons-material/Phone';
import {
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
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { useStoreActions } from 'store';

import { useJsSipClient } from '../../hooks/useJsSipClient';

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

  // Fetch retail accounts when dialog opens
  useEffect(() => {
    if (open && retailAccounts.length === 0) {
      fetchRetailAccounts();
    }
  }, [open]);

  // Cleanup on unmount
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
            setRetailAccounts(response as RetailAccount[]);
            // Auto-select if only one account
            if (response.length === 1) {
              setSelectedAccount((response[0] as RetailAccount).id);
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

  const handleRegister = useCallback(async () => {
    if (!selectedAccount) return;

    try {
      setLoading(true);
      setFetchError(null);

      await apiGet({
        path: `/my/webrtc-credentials/${selectedAccount}`,
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
  }, [selectedAccount, apiGet, cancelToken, register]);

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
    setSelectedAccount(value === '' ? '' : Number(value));
  };

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

  return (
    <>
      {/* Floating Action Button */}
      <Fab
        color="primary"
        onClick={() => setOpen(true)}
        sx={{
          position: 'fixed',
          bottom: 24,
          right: 24,
          zIndex: 1000,
        }}
        aria-label={_('Open Softphone')}
      >
        <PhoneIcon />
      </Fab>

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
        <DialogTitle>{_('WebRTC Phone')}</DialogTitle>
        <DialogContent>
          <Box sx={{ pt: 1 }}>
            {/* Account Selector */}
            {retailAccounts.length > 1 && (
              <FormControl fullWidth sx={{ mb: 2 }}>
                <InputLabel id="account-select-label">
                  {_('Select Account')}
                </InputLabel>
                <Select
                  labelId="account-select-label"
                  value={selectedAccount}
                  onChange={handleAccountChange}
                  label={_('Select Account')}
                  disabled={registrationState === 'registered'}
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

                {/* Connect/Disconnect Button */}
                {registrationState !== 'registered' ? (
                  <Button
                    variant="contained"
                    onClick={handleRegister}
                    disabled={
                      !selectedAccount || registrationState === 'registering'
                    }
                    fullWidth
                    sx={{ mb: 2 }}
                  >
                    {registrationState === 'registering' ? (
                      <CircularProgress size={24} color="inherit" />
                    ) : (
                      _('Connect')
                    )}
                  </Button>
                ) : (
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

                    {/* Disconnect Button */}
                    <Button
                      variant="outlined"
                      onClick={() => unregister()}
                      fullWidth
                      sx={{ mt: 2 }}
                      disabled={callState !== 'idle'}
                    >
                      {_('Disconnect')}
                    </Button>
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
