import useFkChoices from '@irontec/ivoz-ui/entities/data/useFkChoices';
import {
  EntityFormProps,
  FieldsetGroups,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import { Form as DefaultEntityForm } from '@irontec/ivoz-ui/entities/DefaultEntityBehavior/Form';
import { useFormHandler } from '@irontec/ivoz-ui/entities/DefaultEntityBehavior/Form/useFormHandler';
import _ from '@irontec/ivoz-ui/services/translations/translate';

import { foreignKeyGetter } from './ForeignKeyGetter';
import useDefaultCountryId from './hooks/useDefaultCountryId';

const Form = (props: EntityFormProps): JSX.Element => {
  const { entityService, row, match, create, edit } = props;
  const fkChoices = useFkChoices({
    foreignKeyGetter,
    entityService,
    row,
    match,
  });

  const formik = useFormHandler(props);
  useDefaultCountryId({
    create,
    formik,
  });

  const hasCompany = edit && row.company !== null;
  const readOnlyProperties = {
    company: hasCompany,
  };

  const groups: Array<FieldsetGroups | false> = [
    {
      legend: _('Number data'),
      fields: [
        'company',
        'country',
        'ddi',
        'type',
        'ddiProvider',
        'description',
        'useDdiProviderRoutingTag',
        'routingTag',
      ],
    },
    {
      legend: _('Marketplace'),
      fields: [
        'setupPrice',
        'monthlyPrice',
        'inventoryStatus',
      ],
    },
  ];

  return (
    <DefaultEntityForm
      {...props}
      readOnlyProperties={readOnlyProperties}
      fkChoices={fkChoices}
      groups={groups}
      formik={formik}
    />
  );
};

export default Form;
