import defaultEntityBehavior from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import EntityInterface, {
  OrderDirection,
} from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';

import { DidOrderProperties, DidOrderPropertyList } from './DidOrderProperties';
import ListDecorator from './ListDecorator';
import Actions from './Action';

const properties: DidOrderProperties = {
  id: {
    label: _('ID'),
  },
  companyName: {
    label: _('Company', { count: 1 }),
  },
  ddiNumber: {
    label: _('Phone Number'),
  },
  status: {
    label: _('Status'),
    enum: {
      pending_approval: _('Pending'),
      approved: _('Approved'),
      rejected: _('Rejected'),
      expired: _('Expired'),
    },
  },
  requestedAt: {
    label: _('Requested On'),
  },
  approvedByName: {
    label: _('Approved By'),
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
  reservedUntil: {
    label: _('Reserved Until'),
  },
};

const columns = ['companyName', 'ddiNumber', 'status', 'requestedAt', 'setupFee', 'monthlyFee'];

const DidOrder: EntityInterface = {
  ...defaultEntityBehavior,
  icon: ReceiptLongIcon,
  iden: 'DidOrder',
  title: _('DID Orders'),
  path: '/did-orders',
  properties,
  columns,
  defaultOrderBy: 'requestedAt',
  defaultOrderDirection: OrderDirection.desc,
  // Read-only for list, but with custom actions for approve/reject
  acl: {
    ...defaultEntityBehavior.acl,
    iden: 'DidOrders',
    create: false,
    update: false,
    delete: false,
    read: true,
    detail: true,
  },
  toStr: (row: DidOrderPropertyList<string>) => `${row.companyName} - ${row.ddiNumber}`,
  ListDecorator,
  customActions: Actions,
  View: async () => {
    const module = await import('./View');
    return module.default;
  },
};

export default DidOrder;
