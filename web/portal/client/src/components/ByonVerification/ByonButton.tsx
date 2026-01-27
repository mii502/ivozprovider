/**
 * BYON Button Component - "Add Your Number" button with status badge
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/ByonButton.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import AddIcon from '@mui/icons-material/Add';
import PhoneAndroidIcon from '@mui/icons-material/PhoneAndroid';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { Alert, Box, Button, Chip, Collapse, styled, Tooltip, Typography } from '@mui/material';
import { ByonStatus } from './types';

interface ByonButtonProps {
  status: ByonStatus | null;
  loading?: boolean;
  onClick: () => void;
}

/**
 * Safe translation wrapper that ensures string return
 */
const safeTranslate = (key: string, replacements?: Record<string, string>): string => {
  const translated = _<string>(key);
  let result = typeof translated === 'string' ? translated : key;

  if (replacements) {
    Object.entries(replacements).forEach(([placeholder, value]) => {
      result = result.replace(`{{${placeholder}}}`, value);
    });
  }

  return result;
};

/**
 * Format the reset time in a user-friendly way
 */
const formatResetTime = (isoString: string | null): string | null => {
  if (!isoString) return null;

  try {
    const resetDate = new Date(isoString);
    const now = new Date();

    // Calculate hours until reset
    const hoursUntilReset = Math.ceil((resetDate.getTime() - now.getTime()) / (1000 * 60 * 60));

    if (hoursUntilReset <= 1) {
      return safeTranslate('less than 1 hour');
    } else if (hoursUntilReset < 24) {
      return `${hoursUntilReset} ${safeTranslate('hours')}`;
    } else {
      // Format as local time
      return resetDate.toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
      });
    }
  } catch {
    return null;
  }
};

const ByonButton = (props: ByonButtonProps): JSX.Element => {
  const { status, loading = false, onClick } = props;

  const byonCount = status?.byonCount ?? 0;
  const byonLimit = status?.byonLimit ?? 10;
  const disabledReason = status?.disabledReason ?? null;
  const dailyResetAt = status?.dailyResetAt ?? null;
  const canAddByon = status?.canAddByon ?? true;

  const isDisabled = !canAddByon || loading;
  const isLimitReached = disabledReason === 'BYON_LIMIT_REACHED';
  const isDailyLimitReached = disabledReason === 'DAILY_LIMIT_REACHED';

  // Generate tooltip message
  let tooltipTitle = _('Add a phone number you own to use as a DDI');
  if (isLimitReached) {
    tooltipTitle = _('You have reached the maximum number of BYON numbers');
  } else if (isDailyLimitReached) {
    tooltipTitle = _('Daily verification limit reached');
  }

  // Generate alert message for disabled state
  const getDisabledMessage = (): string | null => {
    if (isLimitReached) {
      return safeTranslate('You have reached the maximum of {{limit}} BYON numbers. Contact support to increase your limit.', {
        limit: String(byonLimit),
      });
    }
    if (isDailyLimitReached) {
      const resetIn = formatResetTime(dailyResetAt);
      if (resetIn) {
        return safeTranslate('Daily verification limit reached. Try again in {{time}}.', {
          time: resetIn,
        });
      }
      return safeTranslate('Daily verification limit reached. Try again tomorrow.');
    }
    return null;
  };

  const disabledMessage = getDisabledMessage();

  return (
    <StyledByonButton>
      <Box className="button-row">
        <Tooltip title={tooltipTitle} placement="bottom">
          <span>
            <Button
              variant="contained"
              color="primary"
              startIcon={<AddIcon />}
              onClick={onClick}
              disabled={isDisabled}
              className="byon-button"
            >
              <PhoneAndroidIcon className="phone-icon" />
              <span className="button-text">{_('Add Your Number')}</span>
            </Button>
          </span>
        </Tooltip>

        <Box className="status-badge">
          <Chip
            size="small"
            variant="outlined"
            label={
              <Typography variant="caption" className="badge-text">
                {byonCount}/{byonLimit} {_('BYON')}
              </Typography>
            }
            className={isLimitReached ? 'limit-reached' : ''}
          />
        </Box>
      </Box>

      <Collapse in={!!disabledMessage}>
        <Alert
          severity={isDailyLimitReached ? 'info' : 'warning'}
          icon={<InfoOutlinedIcon />}
          className="disabled-alert"
        >
          {disabledMessage}
        </Alert>
      </Collapse>
    </StyledByonButton>
  );
};

const StyledByonButton = styled(Box)(({ theme }) => ({
  display: 'flex',
  flexDirection: 'column',
  gap: theme.spacing(1.5),

  '& .button-row': {
    display: 'flex',
    alignItems: 'center',
    gap: theme.spacing(2),
  },

  '& .byon-button': {
    textTransform: 'none',
    fontWeight: 600,
    paddingLeft: theme.spacing(2),
    paddingRight: theme.spacing(3),
    gap: theme.spacing(0.5),

    '& .phone-icon': {
      marginLeft: theme.spacing(0.5),
      marginRight: theme.spacing(0.5),
    },
  },

  '& .status-badge': {
    '& .MuiChip-root': {
      borderColor: theme.palette.divider,
      backgroundColor: theme.palette.background.paper,

      '&.limit-reached': {
        borderColor: theme.palette.warning.main,
        backgroundColor: theme.palette.warning.light + '20',
      },
    },
  },

  '& .badge-text': {
    fontWeight: 500,
    color: theme.palette.text.secondary,
  },

  '& .disabled-alert': {
    marginTop: theme.spacing(0.5),
    padding: theme.spacing(0.75, 2),
    fontSize: '0.875rem',

    '& .MuiAlert-icon': {
      fontSize: '1.25rem',
      marginRight: theme.spacing(1),
    },
  },

  [theme.breakpoints.down('sm')]: {
    '& .button-row': {
      flexDirection: 'column',
      gap: theme.spacing(1),
    },

    '& .byon-button': {
      width: '100%',
    },
  },
}));

export default ByonButton;
