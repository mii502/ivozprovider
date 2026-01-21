/**
 * MyDids Actions - Custom row actions for customer's phone numbers
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/Action/index.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

import { CustomActionsType } from '@irontec/ivoz-ui/entities/EntityInterface';

import ConfigureAction from './ConfigureAction';
import ReleaseAction from './ReleaseAction';

const customAction: CustomActionsType = {
  Configure: {
    action: ConfigureAction,
    multiselect: false,
  },
  Release: {
    action: ReleaseAction,
    multiselect: false,
  },
};

export default customAction;
