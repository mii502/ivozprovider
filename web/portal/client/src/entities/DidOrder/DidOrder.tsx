import defaultEntityBehavior from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import EntityInterface, {
  OrderDirection,
} from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';

import { DidOrderProperties, DidOrderPropertyList } from './DidOrderProperties';
import ListDecorator from './ListDecorator';

const properties: DidOrderProperties = {
  id: {
    label: _('ID'),
  },
  ddiNumber: {
    label: _('Phone Number'),
  },
  status: {
    label: _('Status'),
    enum: {
      pending_approval: _('Pending Approval'),
      approved: _('Approved'),
      rejected: _('Rejected'),
      expired: _('Expired'),
    },
  },
  requestedAt: {
    label: _('Requested On'),
  },
  approvedAt: {
    label: _('Approved On'),
  },
  rejectedAt: {
    label: _('Rejected On'),
  },
  rejectionReason: {
    label: _('Rejection Reason'),
  },
  setupFee: {
    label: _('Setup Fee'),
  },
  monthlyFee: {
    label: _('Monthly Fee'),
  },
};

const columns = ['ddiNumber', 'status', 'requestedAt', 'setupFee', 'monthlyFee'];

const DidOrder: EntityInterface = {
  ...defaultEntityBehavior,
  icon: ReceiptLongIcon,
  iden: 'DidOrder',
  title: _('My DID Orders'),
  path: '/did-orders',
  properties,
  columns,
  defaultOrderBy: 'requestedAt',
  defaultOrderDirection: OrderDirection.desc,
  // Read-only entity - customers can view their orders but not modify
  acl: {
    ...defaultEntityBehavior.acl,
    iden: 'DidOrders',
    create: false,
    update: false,
    delete: false,
    read: true,
    detail: true,
  },
  toStr: (row: DidOrderPropertyList<string>) => row.ddiNumber as string,
  ListDecorator,
  View: async () => {
    const module = await import('./View');
    return module.default;
  },
};

export default DidOrder;
