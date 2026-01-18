import { PropertySpec } from '@irontec/ivoz-ui/services/api/ParsedApiSpecInterface';
import {
  EntityValue,
  EntityValues,
} from '@irontec/ivoz-ui/services/entity/EntityService';

export type DidOrderPropertyList<T> = {
  id?: T;
  ddi?: T;
  ddiNumber?: T;
  company?: T;
  companyName?: T;
  status?: T;
  requestedAt?: T;
  approvedBy?: T;
  approvedByName?: T;
  approvedAt?: T;
  rejectedAt?: T;
  rejectionReason?: T;
  setupFee?: T;
  monthlyFee?: T;
  reservedUntil?: T;
};

export type DidOrderProperties = DidOrderPropertyList<Partial<PropertySpec>>;

export type DidOrderPropertiesList = Array<
  DidOrderPropertyList<EntityValue | EntityValues>
>;
