import defaultEntityBehavior from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import EntityInterface, {
  OrderDirection,
} from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import StorefrontIcon from '@mui/icons-material/Storefront';

import {
  AvailableDdiProperties,
  AvailableDdiPropertyList,
} from './AvailableDdiProperties';

const properties: AvailableDdiProperties = {
  id: {
    label: _('ID'),
  },
  ddi: {
    label: _('Phone Number'),
  },
  ddiE164: {
    label: _('Number (E.164)'),
  },
  country: {
    label: _('Country', { count: 1 }),
  },
  countryName: {
    label: _('Country', { count: 1 }),
  },
  ddiType: {
    label: _('Type'),
    enum: {
      inout: _('Inbound & Outbound'),
      out: _('Outbound only'),
      virtual: _('Virtual'),
    },
  },
  setupPrice: {
    label: _('Setup Fee'),
  },
  monthlyPrice: {
    label: _('Monthly Cost'),
  },
  inventoryStatus: {
    label: _('Availability'),
    enum: {
      available: _('Available'),
      reserved: _('Reserved'),
      assigned: _('Assigned'),
      suspended: _('Suspended'),
      disabled: _('Disabled'),
    },
  },
};

const columns = ['ddiE164', 'countryName', 'ddiType', 'setupPrice', 'monthlyPrice', 'inventoryStatus'];

const AvailableDdi: EntityInterface = {
  ...defaultEntityBehavior,
  icon: StorefrontIcon,
  iden: 'AvailableDdi',
  title: _('DID Marketplace'),
  path: '/dids/marketplace',
  properties,
  columns,
  defaultOrderBy: 'countryName',
  defaultOrderDirection: OrderDirection.asc,
  // Read-only entity - customers can browse but not modify
  acl: {
    ...defaultEntityBehavior.acl,
    iden: 'AvailableDdis',
    create: false,
    update: false,
    delete: false,
    read: true,
    detail: true,
  },
  toStr: (row: AvailableDdiPropertyList<string>) => row.ddiE164 as string,
  ListDecorator: async () => {
    const module = await import('./ListDecorator');

    return module.default;
  },
  View: async () => {
    const module = await import('./View');

    return module.default;
  },
};

export default AvailableDdi;
