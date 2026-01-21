/**
 * MyDids Entity - Customer's purchased phone numbers
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/MyDids.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase, ivozprovider-did-release
 */

import defaultEntityBehavior from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import EntityInterface, {
  OrderDirection,
} from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import PhoneIcon from '@mui/icons-material/Phone';

import Action from './Action';
import { MyDidsProperties, MyDidsPropertyList } from './MyDidsProperties';

const properties: MyDidsProperties = {
  id: {
    label: _('ID'),
  },
  ddiE164: {
    label: _('Phone Number'),
  },
  countryName: {
    label: _('Country', { count: 1 }),
  },
  ddiType: {
    label: _('Type'),
    enum: {
      inout: _('Inbound & Outbound'),
      out: _('Outbound only'),
    },
  },
  monthlyPrice: {
    label: _('Monthly Cost'),
  },
  assignedAt: {
    label: _('Purchased On'),
  },
  nextRenewalAt: {
    label: _('Next Renewal'),
  },
};

const columns = [
  'ddiE164',
  'countryName',
  'monthlyPrice',
  'assignedAt',
  'nextRenewalAt',
];

const MyDids: EntityInterface = {
  ...defaultEntityBehavior,
  icon: PhoneIcon,
  iden: 'MyDids',
  title: _('My Phone Numbers'),
  path: '/dids/my-dids',
  properties,
  columns,
  defaultOrderBy: 'assignedAt',
  defaultOrderDirection: OrderDirection.desc,
  // Read-only entity - customers can view but not modify via standard CRUD
  // Custom release action allows releasing DIDs
  acl: {
    ...defaultEntityBehavior.acl,
    iden: 'MyDids',
    create: false,
    update: false,
    delete: false,
    read: true,
    detail: true,
  },
  customActions: Action,
  toStr: (row: MyDidsPropertyList<string>) => row.ddiE164 as string,
  View: async () => {
    const module = await import('./View');
    return module.default;
  },
};

export default MyDids;
