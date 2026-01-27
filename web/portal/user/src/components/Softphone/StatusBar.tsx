/**
 * StatusBar Component - Compact registration status (simplified for user portal)
 * Server path: /opt/irontec/ivozprovider/web/portal/user/src/components/Softphone/StatusBar.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 *
 * Differences from client portal version:
 * - No account dropdown (user portal has single terminal)
 * - Shows terminal name as static text via displayName prop
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';
import {
  Box,
  CircularProgress,
  IconButton,
  Tooltip,
  Typography,
} from '@mui/material';

type RegistrationState = 'unregistered' | 'registering' | 'registered' | 'error';

interface StatusBarProps {
  registrationState: RegistrationState;
  onDisconnect: () => void;
  onConnect: () => void;
  disabled?: boolean;
  displayName?: string; // Terminal name shown as static text
}

const StatusBar = ({
  registrationState,
  onDisconnect,
  onConnect,
  disabled = false,
  displayName,
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

  const showDisconnect = registrationState === 'registered' && !disabled;
  const showConnect =
    (registrationState === 'unregistered' || registrationState === 'error') &&
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

      {/* Terminal Name and Actions */}
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        {/* Display terminal name as static text */}
        {displayName && (
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
            {displayName}
          </Typography>
        )}

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
