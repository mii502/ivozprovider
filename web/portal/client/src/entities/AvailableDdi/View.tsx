/**
 * AvailableDdi View - DID marketplace item detail view with purchase button
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/AvailableDdi/View.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

import {
  FieldsetGroups,
  View as DefaultEntityView,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import { ViewProps } from '@irontec/ivoz-ui/entities/EntityInterface';
import _ from '@irontec/ivoz-ui/services/translations/translate';

import { PurchaseButton } from '../../components/DidPurchase';
import { DdiDetails } from '../../components/DidPurchase/types';

const View = (props: ViewProps): JSX.Element | null => {
  const { row } = props;

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

  // Map row data to DdiDetails type for PurchaseButton
  const ddiDetails: DdiDetails = {
    id: row.id as number,
    ddi: row.ddi as string,
    ddiE164: row.ddiE164 as string,
    country: row.country as string,
    countryName: row.countryName as string,
    setupPrice: row.setupPrice as string,
    monthlyPrice: row.monthlyPrice as string,
    inventoryStatus: row.inventoryStatus as string,
  };

  return (
    <>
      <DefaultEntityView {...props} groups={groups} />
      <PurchaseButton ddi={ddiDetails} />
    </>
  );
};

export default View;
