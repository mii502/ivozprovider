/**
 * MyDids View - Detail view for customer's purchased phone numbers
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/MyDids/View.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import {
  FieldsetGroups,
  View as DefaultEntityView,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import { ViewProps } from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';

const View = (props: ViewProps): JSX.Element | null => {
  const groups: Array<FieldsetGroups | false> = [
    {
      legend: _('Phone Number Details'),
      fields: ['ddiE164', 'countryName', 'ddiType'],
    },
    {
      legend: _('Billing'),
      fields: ['monthlyPrice', 'assignedAt', 'nextRenewalAt'],
    },
  ];

  return <DefaultEntityView {...props} groups={groups} />;
};

export default View;
