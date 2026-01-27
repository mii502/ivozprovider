/**
 * StatusBar Component - Compact registration status with account selector
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/Softphone/StatusBar.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';
import {
  Box,
  CircularProgress,
  FormControl,
  IconButton,
  MenuItem,
  Select,
  SelectChangeEvent,
  Tooltip,
  Typography,
} from '@mui/material';

type RegistrationState = 'unregistered' | 'registering' | 'registered' | 'error';

interface RetailAccount {
  id: number;
  name: string;
  description?: string;
}

interface StatusBarProps {
  registrationState: RegistrationState;
  accounts: RetailAccount[];
  selectedAccount: number | '';
  onAccountChange: (accountId: number | '') => void;
  onDisconnect: () => void;
  onConnect: () => void;
  disabled?: boolean;
}

const StatusBar = ({
  registrationState,
  accounts,
  selectedAccount,
  onAccountChange,
  onDisconnect,
  onConnect,
  disabled = false,
}: StatusBarProps): JSX.Element => {
  const getStatusColor = () => {
    switch (registrationState) {
      case 'registered':
        return '#4caf50'; // success.main
      case 'registering':
        return '#ff9800'; // warning.main
      case 'error':
        return '#f44336'; // error.main
      default:
        return '#9e9e9e'; // grey.500
    }
  };

  const getStatusText = () => {
    switch (registrationState) {
      case 'registered':
        return _('Connected');
      case 'registering':
        return _('Connecting...');
      case 'error':
        return _('Failed');
      default:
        return _('Offline');
    }
  };

  const handleChange = (event: SelectChangeEvent<number | ''>) => {
    const value = event.target.value;
    onAccountChange(value === '' ? '' : Number(value));
  };

  const showDisconnect = registrationState === 'registered' && !disabled;
  const showConnect =
    (registrationState === 'unregistered' || registrationState === 'error') &&
    selectedAccount !== '' &&
    !disabled;

  return (
    <Box
      sx={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        px: 2,
        py: 1,
        borderBottom: '1px solid',
        borderColor: 'divider',
        bgcolor: 'grey.50',
        minHeight: 48,
      }}
    >
      {/* Status Indicator */}
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        {registrationState === 'registering' ? (
          <CircularProgress size={12} thickness={4} sx={{ color: getStatusColor() }} />
        ) : (
          <Box
            sx={{
              width: 10,
              height: 10,
              borderRadius: '50%',
              bgcolor: getStatusColor(),
              boxShadow: `0 0 8px ${getStatusColor()}`,
              transition: 'all 0.3s ease',
            }}
          />
        )}
        <Typography
          variant="caption"
          sx={{
            fontWeight: 500,
            color: 'text.secondary',
            textTransform: 'uppercase',
            letterSpacing: '0.05em',
            fontSize: '0.7rem',
          }}
        >
          {getStatusText()}
        </Typography>
      </Box>

      {/* Account Selector or Action */}
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        {accounts.length > 1 ? (
          <FormControl size="small" sx={{ minWidth: 100 }}>
            <Select
              value={selectedAccount}
              onChange={handleChange}
              disabled={disabled}
              displayEmpty
              sx={{
                fontSize: '0.75rem',
                '& .MuiSelect-select': {
                  py: 0.5,
                  px: 1,
                },
              }}
            >
              <MenuItem value="" disabled>
                <em>{_('Select')}</em>
              </MenuItem>
              {accounts.map((acc) => (
                <MenuItem key={acc.id} value={acc.id}>
                  {acc.description || acc.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        ) : accounts.length === 1 ? (
          <Typography
            variant="caption"
            sx={{
              color: 'text.secondary',
              fontSize: '0.75rem',
              maxWidth: 120,
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap',
            }}
          >
            {accounts[0].description || accounts[0].name}
          </Typography>
        ) : null}

        {/* Disconnect Button */}
        {showDisconnect && (
          <Tooltip title={_('Disconnect')}>
            <IconButton
              size="small"
              onClick={onDisconnect}
              sx={{
                color: 'text.secondary',
                p: 0.5,
                '&:hover': {
                  color: 'error.main',
                  bgcolor: 'error.lighter',
                },
              }}
            >
              <PowerSettingsNewIcon sx={{ fontSize: 18 }} />
            </IconButton>
          </Tooltip>
        )}

        {/* Connect Button */}
        {showConnect && (
          <Tooltip title={_('Connect')}>
            <IconButton
              size="small"
              onClick={onConnect}
              sx={{
                color: 'success.main',
                p: 0.5,
                '&:hover': {
                  bgcolor: 'success.lighter',
                },
              }}
            >
              <PowerSettingsNewIcon sx={{ fontSize: 18 }} />
            </IconButton>
          </Tooltip>
        )}
      </Box>
    </Box>
  );
};

export default StatusBar;
