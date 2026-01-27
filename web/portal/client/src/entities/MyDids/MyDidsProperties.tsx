import { PropertySpec } from '@irontec/ivoz-ui/services/api/ParsedApiSpecInterface';
import {
  EntityValue,
  EntityValues,
} from '@irontec/ivoz-ui/services/entity/EntityService';

export type MyDidsPropertyList<T> = {
  id?: T;
  ddiE164?: T;
  countryName?: T;
  ddiType?: T;
  isByon?: T;
  monthlyPrice?: T;
  assignedAt?: T;
  nextRenewalAt?: T;
};

export type MyDidsProperties = MyDidsPropertyList<Partial<PropertySpec>>;

export type MyDidsPropertiesList = Array<
  MyDidsPropertyList<EntityValue | EntityValues>
>;
