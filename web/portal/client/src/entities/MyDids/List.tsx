/**
 * MyDids List - Custom list view with info banner and BYON button
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/List.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase, ivozprovider-byon
 */

import { ListContentProps } from '@irontec/ivoz-ui/components/List/Content/ListContent';
import DefaultEntityList from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { Alert, Box, CircularProgress, Link, styled, Typography } from '@mui/material';
import { useCallback, useEffect, useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';

import { ByonButton, ByonModal, useByonApi } from '../../components/ByonVerification';

const List = (props: ListContentProps): JSX.Element => {
  const [byonModalOpen, setByonModalOpen] = useState(false);
  const { status, fetchStatus, loading } = useByonApi();

  // Fetch BYON status when component mounts
  useEffect(() => {
    fetchStatus();
  }, [fetchStatus]);

  const handleByonClick = useCallback(() => {
    setByonModalOpen(true);
  }, []);

  const handleByonClose = useCallback(() => {
    setByonModalOpen(false);
  }, []);

  const handleByonSuccess = useCallback(() => {
    // Refresh the list after successful BYON verification
    window.location.reload();
  }, []);

  return (
    <>
      <StyledListContainer>
        {/* BYON Button Section */}
        <Box className="byon-section">
          {loading && !status ? (
            <Box className="byon-loading">
              <CircularProgress size={20} />
              <Typography variant="body2" color="textSecondary">
                {_('Loading...')}
              </Typography>
            </Box>
          ) : (
            <ByonButton
              status={status}
              loading={loading}
              onClick={handleByonClick}
            />
          )}
        </Box>

        {/* Info Banner */}
        <Box className="info-banner">
          <Alert
            severity="info"
            icon={<InfoOutlinedIcon />}
            className="info-alert"
          >
            <Typography variant="subtitle2" className="info-title">
              {_('Managing Your Phone Numbers')}
            </Typography>
            <Typography variant="body2" className="info-message">
              {_('This page shows your purchased numbers and billing information. To configure call settings (forwarding, voicemail, routing), click the gear icon or visit')}{' '}
              <Link
                component={RouterLink}
                to="/client/ddis"
                className="config-link"
              >
                {_('DDI Configuration')}
              </Link>
              .
            </Typography>
          </Alert>
        </Box>

        {/* Entity List */}
        <DefaultEntityList.List {...props} />
      </StyledListContainer>

      {/* BYON Verification Modal */}
      <ByonModal
        open={byonModalOpen}
        onClose={handleByonClose}
        onSuccess={handleByonSuccess}
      />
    </>
  );
};

const StyledListContainer = styled(Box)(({ theme }) => ({
  '& .byon-section': {
    marginBottom: theme.spacing(3),
    display: 'flex',
    justifyContent: 'flex-start',
    alignItems: 'center',
  },

  '& .byon-loading': {
    display: 'flex',
    alignItems: 'center',
    gap: theme.spacing(1),
  },

  '& .info-banner': {
    marginBottom: 'var(--spacing-lg)',
  },

  '& .info-alert': {
    borderRadius: '8px',
    backgroundColor: 'var(--color-background-elevated)',
    border: '1px solid var(--color-border)',

    '& .MuiAlert-icon': {
      color: theme.palette.info.main,
    },
  },

  '& .info-title': {
    fontWeight: 600,
    marginBottom: 'var(--spacing-xs)',
    color: 'var(--color-text-primary)',
  },

  '& .info-message': {
    color: 'var(--color-text-secondary)',
  },

  '& .config-link': {
    fontWeight: 500,
  },
}));

export default List;
