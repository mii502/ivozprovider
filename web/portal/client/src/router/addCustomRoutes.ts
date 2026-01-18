import { Create, RouteSpec } from '@irontec/ivoz-ui';

import ActiveCallsComponent from '../components/ActiveCalls';
import HolidayDateRange from '../entities/HolidayDateRange/HolidayDateRange';
import AvailableDdi from '../entities/AvailableDdi/AvailableDdi';
import AvailableDdiDetail from '../entities/AvailableDdi/Detail';

const addCustomRoutes = (routes: Array<RouteSpec>): Array<RouteSpec> => {
  const activeCallsRoute = routes.find(
    (route) => route.key === 'ActiveCalls-list'
  );

  if (activeCallsRoute) {
    activeCallsRoute.component = ActiveCallsComponent;
  }

  routes.push({
    component: Create,
    entity: HolidayDateRange,
    key: 'HolidayDateRange-create',
    path: `/calendars/:parent_id_1${HolidayDateRange.path}`,
  });

  // Custom detail route for AvailableDdi (DID Marketplace)
  // Uses custom Detail component that fetches from our custom API endpoint
  // (bypasses withRowData HOC which requires API Platform schema)
  const availableDdiDetailIndex = routes.findIndex(
    (route) => route.key === 'AvailableDdi-detailed'
  );
  if (availableDdiDetailIndex >= 0) {
    // Replace the default detail route with our custom one
    routes[availableDdiDetailIndex].component = AvailableDdiDetail;
  } else {
    // Add the route if it doesn't exist
    routes.push({
      component: AvailableDdiDetail,
      entity: AvailableDdi,
      key: 'AvailableDdi-detailed',
      path: `${AvailableDdi.path}/:id/detailed`,
    });
  }

  return routes;
};

export default addCustomRoutes;
