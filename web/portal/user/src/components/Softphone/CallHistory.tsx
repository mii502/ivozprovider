/**
 * CallHistory Component - Scrollable call history list with date grouping and redial
 * Server path: /opt/irontec/ivozprovider/web/portal/user/src/components/Softphone/CallHistory.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Last updated: 2026-01-27
 *
 * Differences from client portal version:
 * - Store path: state.userStatus.status.profile (not clientSession)
 * - Always uses /users_cdrs endpoint (vPBX users only)
 * - No isVpbxUser check needed
 */

import useCancelToken from '@irontec/ivoz-ui/hooks/useCancelToken';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import CallMadeIcon from '@mui/icons-material/CallMade';
import CallMissedIcon from '@mui/icons-material/CallMissed';
import CallReceivedIcon from '@mui/icons-material/CallReceived';
import PhoneCallbackIcon from '@mui/icons-material/PhoneCallback';
import RefreshIcon from '@mui/icons-material/Refresh';
import {
  Box,
  CircularProgress,
  Divider,
  IconButton,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { useStoreActions } from 'store';

// Call record interface for UsersCdr
interface CallRecord {
  id: number;
  startTime: string;
  duration: number | string;
  direction: 'inbound' | 'outbound';
  caller: string;
  callee: string;
  disposition: 'answered' | 'missed' | 'busy' | 'error';
}

interface CallHistoryProps {
  onRedial: (number: string) => void;
  disabled?: boolean;
}

interface GroupedCalls {
  label: string;
  calls: CallRecord[];
}

// Helper to format duration from seconds or HH:MM:SS string
const formatDuration = (duration: number | string): string => {
  let seconds: number;

  if (typeof duration === 'string') {
    // Parse HH:MM:SS format
    const parts = duration.split(':').map(Number);
    if (parts.length === 3) {
      seconds = parts[0] * 3600 + parts[1] * 60 + parts[2];
    } else if (parts.length === 2) {
      seconds = parts[0] * 60 + parts[1];
    } else {
      seconds = parseInt(duration, 10) || 0;
    }
  } else {
    seconds = duration;
  }

  if (seconds < 60) {
    return `${seconds}s`;
  }

  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;

  if (mins < 60) {
    return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
  }

  const hours = Math.floor(mins / 60);
  const remainingMins = mins % 60;
  return remainingMins > 0 ? `${hours}h ${remainingMins}m` : `${hours}h`;
};

// Helper to parse server timestamp as UTC
// Server returns timestamps in UTC, but format may vary:
// - "2026-01-27T10:00:00+00:00" (ISO with timezone - parsed correctly)
// - "2026-01-27 10:00:00" (no timezone - must be treated as UTC)
const parseServerTimestamp = (dateString: string): Date => {
  // If already has timezone info (Z or +/-), parse directly
  if (dateString.includes('Z') || /[+-]\d{2}:\d{2}$/.test(dateString)) {
    return new Date(dateString);
  }
  // Otherwise, append 'Z' to treat as UTC
  // Also handle space separator by converting to 'T'
  const isoString = dateString.replace(' ', 'T') + 'Z';
  return new Date(isoString);
};

// Helper to format relative time
const formatRelativeTime = (dateString: string): string => {
  const date = parseServerTimestamp(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} min ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays} days ago`;

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

// Helper to get date label for grouping
const getDateLabel = (dateString: string): string => {
  const date = parseServerTimestamp(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  const yesterdayOnly = new Date(yesterday.getFullYear(), yesterday.getMonth(), yesterday.getDate());

  if (dateOnly.getTime() === todayOnly.getTime()) return 'Today';
  if (dateOnly.getTime() === yesterdayOnly.getTime()) return 'Yesterday';

  return date.toLocaleDateString(undefined, {
    weekday: 'long',
    month: 'short',
    day: 'numeric'
  });
};

// Group calls by date
const groupCallsByDate = (calls: CallRecord[]): GroupedCalls[] => {
  const groups = new Map<string, CallRecord[]>();

  calls.forEach((call) => {
    const label = getDateLabel(call.startTime);
    const existing = groups.get(label) || [];
    existing.push(call);
    groups.set(label, existing);
  });

  return Array.from(groups.entries()).map(([label, groupCalls]) => ({
    label,
    calls: groupCalls,
  }));
};

const CallHistory = ({ onRedial, disabled = false }: CallHistoryProps): JSX.Element => {
  const [calls, setCalls] = useState<CallRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const apiGet = useStoreActions((store) => store.api.get);
  const [, cancelToken] = useCancelToken();

  // User portal always uses /users_cdrs (vPBX terminal users)
  const apiPath = '/users_cdrs';

  const fetchCallHistory = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      await apiGet({
        path: apiPath,
        params: {
          _itemsPerPage: 50,
          _order: { startTime: 'DESC' },
        },
        cancelToken,
        successCallback: async (response: unknown) => {
          if (Array.isArray(response)) {
            // Transform response to CallRecord format
            const records = (response as Array<Record<string, unknown>>).map((item) => {
              // Parse duration to determine missed calls
              const duration = item.duration as number | string;
              let durationSeconds = 0;
              if (typeof duration === 'string') {
                const parts = duration.split(':').map(Number);
                if (parts.length === 3) {
                  durationSeconds = parts[0] * 3600 + parts[1] * 60 + parts[2];
                } else if (parts.length === 2) {
                  durationSeconds = parts[0] * 60 + parts[1];
                } else {
                  durationSeconds = parseInt(duration, 10) || 0;
                }
              } else if (typeof duration === 'number') {
                durationSeconds = duration;
              }

              // UsersCdr has disposition field
              const disposition = (item.disposition as string) ||
                (durationSeconds <= 1 ? 'missed' : 'answered');

              return {
                id: item.id as number,
                startTime: item.startTime as string,
                duration: item.duration as number | string,
                direction: item.direction as 'inbound' | 'outbound',
                caller: item.caller as string,
                callee: item.callee as string,
                disposition: disposition as 'answered' | 'missed' | 'busy' | 'error',
              };
            });
            setCalls(records);
          }
          setLoading(false);
        },
      });
    } catch (err) {
      setError('Failed to load call history');
      setLoading(false);
    }
  }, [apiGet, apiPath, cancelToken]);

  useEffect(() => {
    fetchCallHistory();
  }, [fetchCallHistory]);

  const handleRedial = useCallback(
    (call: CallRecord) => {
      if (disabled) return;
      // For outbound calls, redial the callee; for inbound, redial the caller
      const number = call.direction === 'outbound' ? call.callee : call.caller;
      onRedial(number);
    },
    [disabled, onRedial]
  );

  const groupedCalls = groupCallsByDate(calls);

  // Get icon based on call direction and disposition
  const getCallIcon = (call: CallRecord) => {
    if (call.disposition === 'missed') {
      return <CallMissedIcon sx={{ color: 'error.main' }} />;
    }
    if (call.direction === 'inbound') {
      return <CallReceivedIcon sx={{ color: 'success.main' }} />;
    }
    return <CallMadeIcon sx={{ color: 'primary.main' }} />;
  };

  // Get the phone number to display
  const getDisplayNumber = (call: CallRecord) => {
    return call.direction === 'outbound' ? call.callee : call.caller;
  };

  // Get disposition text
  const getDispositionText = (call: CallRecord) => {
    if (call.disposition === 'missed') return 'Missed';
    if (call.disposition === 'busy') return 'Busy';
    if (call.disposition === 'error') return 'Failed';
    return formatDuration(call.duration);
  };

  // Fixed height to match Keypad view (NumberDisplay 65px + DialPad 356px + CallButton 96px = 517px)
  const containerMinHeight = 517;

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: containerMinHeight }}>
        <CircularProgress size={32} />
      </Box>
    );
  }

  if (error) {
    return (
      <Box sx={{ p: 3, textAlign: 'center', minHeight: containerMinHeight, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
        <Typography color="error" sx={{ mb: 2 }}>
          {error}
        </Typography>
        <IconButton onClick={fetchCallHistory} color="primary">
          <RefreshIcon />
        </IconButton>
      </Box>
    );
  }

  if (calls.length === 0) {
    return (
      <Box sx={{ p: 4, textAlign: 'center', minHeight: containerMinHeight, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <Typography color="text.secondary">{_('No call history')}</Typography>
      </Box>
    );
  }

  return (
    <Box sx={{ flex: 1, overflow: 'auto', minHeight: containerMinHeight, maxHeight: containerMinHeight }}>
      <List disablePadding>
        {groupedCalls.map((group, groupIndex) => (
          <Box key={group.label}>
            {/* Date Header */}
            <Box
              sx={{
                px: 2,
                py: 1,
                bgcolor: 'grey.50',
                borderBottom: '1px solid',
                borderColor: 'divider',
                position: 'sticky',
                top: 0,
                zIndex: 1,
              }}
            >
              <Typography
                variant="caption"
                sx={{
                  fontWeight: 600,
                  color: 'text.secondary',
                  textTransform: 'uppercase',
                  letterSpacing: '0.05em',
                }}
              >
                {group.label}
              </Typography>
            </Box>

            {/* Call Entries */}
            {group.calls.map((call, callIndex) => (
              <Box key={call.id || `${group.label}-${callIndex}`}>
                <ListItem disablePadding>
                  <ListItemButton
                    onClick={() => handleRedial(call)}
                    disabled={disabled}
                    sx={{
                      py: 1.5,
                      px: 2,
                      '&:hover': {
                        bgcolor: 'action.hover',
                      },
                    }}
                  >
                    <ListItemIcon sx={{ minWidth: 40 }}>
                      {getCallIcon(call)}
                    </ListItemIcon>
                    <ListItemText
                      primary={
                        <Typography
                          variant="body2"
                          sx={{
                            fontWeight: 500,
                            fontFamily: 'monospace',
                            color: call.disposition === 'missed' ? 'error.main' : 'text.primary',
                          }}
                        >
                          {getDisplayNumber(call)}
                        </Typography>
                      }
                      secondary={
                        <Typography variant="caption" color="text.secondary">
                          {formatRelativeTime(call.startTime)}
                        </Typography>
                      }
                    />
                    <Box sx={{ textAlign: 'right' }}>
                      <Typography
                        variant="body2"
                        sx={{
                          color: call.disposition === 'missed' ? 'error.main' : 'text.secondary',
                          fontWeight: call.disposition === 'missed' ? 500 : 400,
                        }}
                      >
                        {getDispositionText(call)}
                      </Typography>
                      <IconButton
                        size="small"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleRedial(call);
                        }}
                        disabled={disabled}
                        sx={{
                          mt: 0.5,
                          color: 'primary.main',
                          '&:hover': {
                            bgcolor: 'primary.light',
                            color: 'primary.contrastText',
                          },
                        }}
                      >
                        <PhoneCallbackIcon fontSize="small" />
                      </IconButton>
                    </Box>
                  </ListItemButton>
                </ListItem>
                {callIndex < group.calls.length - 1 && (
                  <Divider variant="inset" component="li" sx={{ ml: 7 }} />
                )}
              </Box>
            ))}

            {groupIndex < groupedCalls.length - 1 && <Divider />}
          </Box>
        ))}
      </List>
    </Box>
  );
};

export default CallHistory;
