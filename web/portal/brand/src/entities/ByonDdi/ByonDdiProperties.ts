import { PropertySpec } from '@irontec/ivoz-ui/services/api/ParsedApiSpecInterface';
import {
  EntityValue,
  EntityValues,
} from '@irontec/ivoz-ui/services/entity/EntityService';

export type ByonDdiPropertyList<T> = {
  id?: T;
  ddiE164?: T;
  companyId?: T;
  companyName?: T;
  isByon?: T;
  verifiedAt?: T;
};

export type ByonDdiProperties = ByonDdiPropertyList<Partial<PropertySpec>>;
export type ByonDdiPropertiesList = Array<
  ByonDdiPropertyList<EntityValue | EntityValues>
>;
