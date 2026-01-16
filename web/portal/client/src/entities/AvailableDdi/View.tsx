import {
  FieldsetGroups,
  View as DefaultEntityView,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import { ViewProps } from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';

const View = (props: ViewProps): JSX.Element | null => {
  const groups: Array<FieldsetGroups | false> = [
    {
      legend: _('DID Details'),
      fields: ['ddiE164', 'country', 'countryName', 'ddiType'],
    },
    {
      legend: _('Pricing'),
      fields: ['setupPrice', 'monthlyPrice'],
    },
    {
      legend: _('Status'),
      fields: ['inventoryStatus'],
    },
  ];

  return <DefaultEntityView {...props} groups={groups} />;
};

export default View;
