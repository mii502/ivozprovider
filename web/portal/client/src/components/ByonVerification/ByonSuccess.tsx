/**
 * BYON Success Component - Success message after verification
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/components/ByonVerification/ByonSuccess.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

import _ from '@irontec/ivoz-ui/services/translations/translate';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { Alert, Box, styled, Typography } from '@mui/material';

interface ByonSuccessProps {
  phoneNumber: string;
  ddiId?: number;
}

const ByonSuccess = (props: ByonSuccessProps): JSX.Element => {
  const { phoneNumber } = props;

  return (
    <StyledByonSuccess>
      <Box className="success-icon-container">
        <CheckCircleIcon className="success-icon" color="success" />
      </Box>

      <Typography variant="h6" className="success-title">
        {_('Number Verified!')}
      </Typography>

      <Typography variant="h5" className="phone-number">
        {phoneNumber}
      </Typography>

      <Alert severity="success" className="success-alert">
        <strong>{_('Your number is now active.')}</strong>
        <br />
        {_('You can use it for inbound calls and as a caller ID for outbound calls.')}
      </Alert>

      <Box className="info-section">
        <Typography variant="body2" className="info-text">
          {_('BYON numbers have no monthly fee and are owned by you.')}
        </Typography>
        <Typography variant="caption" className="note-text">
          {_('Configure call routing in DDI Configuration.')}
        </Typography>
      </Box>
    </StyledByonSuccess>
  );
};

const StyledByonSuccess = styled(Box)(({ theme }) => ({
  textAlign: 'center',

  '& .success-icon-container': {
    marginBottom: theme.spacing(2),
  },

  '& .success-icon': {
    fontSize: 64,
  },

  '& .success-title': {
    color: theme.palette.success.main,
    fontWeight: 600,
    marginBottom: theme.spacing(1),
  },

  '& .phone-number': {
    fontFamily: 'monospace',
    fontWeight: 700,
    color: theme.palette.text.primary,
    marginBottom: theme.spacing(3),
  },

  '& .success-alert': {
    textAlign: 'left',
    marginBottom: theme.spacing(2),
  },

  '& .info-section': {
    padding: theme.spacing(2),
    backgroundColor: theme.palette.grey[50],
    borderRadius: theme.shape.borderRadius,
  },

  '& .info-text': {
    color: theme.palette.text.secondary,
    marginBottom: theme.spacing(0.5),
  },

  '& .note-text': {
    color: theme.palette.text.secondary,
    fontStyle: 'italic',
  },
}));

export default ByonSuccess;
