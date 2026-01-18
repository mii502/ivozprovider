import { CustomActionsType } from '@irontec/ivoz-ui/entities/EntityInterface';

import Approve from './Approve';
import Reject from './Reject';

const customActions: CustomActionsType = {
  Approve: {
    action: Approve,
  },
  Reject: {
    action: Reject,
  },
};

export default customActions;
