import { EntityValues } from '@irontec/ivoz-ui';
import defaultEntityBehavior from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import EntityInterface from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import VerifiedUserIcon from '@mui/icons-material/VerifiedUser';

import { ByonDdiProperties, ByonDdiPropertyList } from './ByonDdiProperties';
import List from './List';

const properties: ByonDdiProperties = {
  id: {
    label: _('ID'),
  },
  ddiE164: {
    label: _('Phone Number'),
  },
  companyId: {
    label: _('Company ID'),
  },
  companyName: {
    label: _('Client'),
  },
  isByon: {
    label: _('BYON'),
    enum: {
      '0': _('No'),
      '1': _('Yes'),
    },
  },
  verifiedAt: {
    label: _('Verified On'),
    format: 'date-time',
  },
};

const ByonDdi: EntityInterface = {
  ...defaultEntityBehavior,
  icon: VerifiedUserIcon,
  iden: 'ByonDdi',
  title: _('BYON Number', { count: 2 }),
  path: '/byon/ddis',
  toStr: (row: ByonDdiPropertyList<EntityValues>) => `${row.ddiE164}`,
  properties,
  defaultOrderBy: '',
  columns: ['ddiE164', 'companyName', 'verifiedAt'],
  acl: {
    ...defaultEntityBehavior.acl,
    iden: 'ByonDdis',
    create: false,
    update: false,
    delete: true,
    read: true,
  },
  List,
};

export default ByonDdi;
