/**
 * BYON Button Component - "Add Your Number" button with status badge
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/ByonButton.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import AddIcon from '@mui/icons-material/Add';
import PhoneAndroidIcon from '@mui/icons-material/PhoneAndroid';
import { Box, Button, Chip, styled, Tooltip, Typography } from '@mui/material';

interface ByonButtonProps {
  byonCount: number;
  byonLimit: number;
  disabled?: boolean;
  loading?: boolean;
  onClick: () => void;
}

const ByonButton = (props: ByonButtonProps): JSX.Element => {
  const { byonCount, byonLimit, disabled = false, loading = false, onClick } = props;

  const isLimitReached = byonCount >= byonLimit;
  const isDisabled = disabled || isLimitReached || loading;

  const tooltipTitle = isLimitReached
    ? _('You have reached the maximum number of BYON numbers')
    : _('Add a phone number you own to use as a DDI');

  return (
    <StyledByonButton>
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
    </StyledByonButton>
  );
};

const StyledByonButton = styled(Box)(({ theme }) => ({
  display: 'flex',
  alignItems: 'center',
  gap: theme.spacing(2),

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

  [theme.breakpoints.down('sm')]: {
    flexDirection: 'column',
    gap: theme.spacing(1),

    '& .byon-button': {
      width: '100%',
    },
  },
}));

export default ByonButton;
