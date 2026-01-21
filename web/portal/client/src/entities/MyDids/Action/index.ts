/**
 * MyDids Actions - Custom row actions for customer's phone numbers
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/Action/index.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

import { CustomActionsType } from '@irontec/ivoz-ui/entities/EntityInterface';

import ReleaseAction from './ReleaseAction';

const customAction: CustomActionsType = {
  Release: {
    action: ReleaseAction,
    multiselect: false,
  },
};

export default customAction;
