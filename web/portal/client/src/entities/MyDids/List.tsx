/**
 * MyDids List - Custom list view with info banner
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/List.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import { ListContentProps } from '@irontec/ivoz-ui/components/List/Content/ListContent';
import DefaultEntityList from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { Alert, Box, Link, styled, Typography } from '@mui/material';
import { Link as RouterLink } from 'react-router-dom';

const StyledInfoBanner = styled(Box)(({ theme }) => ({
  marginBottom: 'var(--spacing-lg)',

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

const List = (props: ListContentProps): JSX.Element => {
  return (
    <>
      <StyledInfoBanner>
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
      </StyledInfoBanner>
      <DefaultEntityList.List {...props} />
    </>
  );
};

export default List;
