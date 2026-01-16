import { PropertySpec } from '@irontec/ivoz-ui/services/api/ParsedApiSpecInterface';
import {
  EntityValue,
  EntityValues,
} from '@irontec/ivoz-ui/services/entity/EntityService';

export type AvailableDdiPropertyList<T> = {
  id?: T;
  ddi?: T;
  ddiE164?: T;
  country?: T;
  countryName?: T;
  ddiType?: T;
  setupPrice?: T;
  monthlyPrice?: T;
  inventoryStatus?: T;
};

export type AvailableDdiProperties = AvailableDdiPropertyList<
  Partial<PropertySpec>
>;

export type AvailableDdiPropertiesList = Array<
  AvailableDdiPropertyList<EntityValue | EntityValues>
>;
