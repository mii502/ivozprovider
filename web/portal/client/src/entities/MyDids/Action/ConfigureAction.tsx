/**
 * Configure Action - Row action to navigate to DDI configuration page
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/Action/ConfigureAction.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import { MoreMenuItem } from '@irontec/ivoz-ui/components/List/Content/Shared/MoreChildEntityLinks';
import { StyledTableRowCustomCta } from '@irontec/ivoz-ui/components/List/Content/Table/ContentTable.styles';
import {
  ActionFunctionComponent,
  ActionItemProps,
} from '@irontec/ivoz-ui/router/routeMapParser';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import SettingsIcon from '@mui/icons-material/Settings';
import { Tooltip } from '@mui/material';
import { useNavigate } from 'react-router-dom';

import Ddi from '../../Ddi/Ddi';

const ConfigureAction: ActionFunctionComponent = (props: ActionItemProps) => {
  const { row, variant = 'icon' } = props;
  const navigate = useNavigate();

  if (!row) {
    return null;
  }

  const handleConfigure = () => {
    // Navigate to DDI detail page for configuration
    // Use process.env.BASE_URL to ensure correct path with /client/ prefix
    const basePath = process.env.BASE_URL || '/client/';
    navigate(`${basePath}${Ddi.path.replace(/^\//, '')}/${row.id}`);
  };

  return (
    <>
      {variant === 'text' && (
        <MoreMenuItem onClick={handleConfigure}>
          {_('Configure')}
        </MoreMenuItem>
      )}
      {variant === 'icon' && (
        <Tooltip
          title={_('Configure call settings')}
          placement="bottom-start"
          enterTouchDelay={0}
        >
          <StyledTableRowCustomCta>
            <SettingsIcon
              onClick={handleConfigure}
              color="primary"
              sx={{ cursor: 'pointer' }}
            />
          </StyledTableRowCustomCta>
        </Tooltip>
      )}
    </>
  );
};

export default ConfigureAction;
