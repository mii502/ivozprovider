/**
 * AvailableDdi Actions - Custom row actions for DID marketplace
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/AvailableDdi/Action/index.ts
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 */

import { CustomActionsType } from '@irontec/ivoz-ui/entities/EntityInterface';

import BuyAction from './BuyAction';

const customAction: CustomActionsType = {
  Buy: {
    action: BuyAction,
    multiselect: false,
  },
};

export default customAction;
