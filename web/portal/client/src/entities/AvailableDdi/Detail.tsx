/**
 * AvailableDdi Detail - Custom detail component that fetches data from custom API
 * Server path: /opt/irontec/ivozprovider/web/portal/client/src/entities/AvailableDdi/Detail.tsx
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 *
 * This component bypasses the default withRowData HOC because our custom API endpoint
 * (/api/client/dids/marketplace/{id}) isn't registered in the API Platform schema.
 */

import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { Box, CircularProgress, Alert } from '@mui/material';
import axios from 'axios';

import { PurchaseButton } from '../../components/DidPurchase';
import { DdiDetails } from '../../components/DidPurchase/types';
import {
  FieldsetGroups,
  View as DefaultEntityView,
} from '@irontec/ivoz-ui/entities/DefaultEntityBehavior';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import useCurrentPathMatch from '@irontec/ivoz-ui/hooks/useCurrentPathMatch';
import { useStoreState } from '@irontec/ivoz-ui/store';

interface AvailableDdiDetailProps {
  entityService: any;
  foreignKeyResolver: any;
  foreignKeyGetter: any;
  unmarshaller: any;
  properties: any;
}

interface ApiDdiResponse {
  id: number;
  ddi: string;
  ddiE164: string;
  description?: string;
  country: {
    id: number;
    code: string;
    name: string;
    countryCode: string;
  };
  ddiType: string;
  pricing: {
    setupPrice: string;
    monthlyPrice: string;
    currency: string;
  };
  inventoryStatus: string;
}

const AvailableDdiDetail = (props: AvailableDdiDetailProps): JSX.Element | null => {
  const { entityService } = props;
  const params = useParams();
  const ddiId = params.id;
  const match = useCurrentPathMatch();

  // Get auth token from store
  const token = useStoreState((state) => state.auth.token);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [row, setRow] = useState<Record<string, any> | null>(null);

  useEffect(() => {
    const fetchDdi = async () => {
      if (!ddiId) {
        setError('No DID ID provided');
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        setError(null);

        const response = await axios.get<ApiDdiResponse>(
          `/api/client/dids/marketplace/${ddiId}`,
          {
            headers: {
              'Accept': 'application/json',
              ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
            },
          }
        );

        // Transform API response to flat row format expected by View
        const data = response.data;
        const transformedRow = {
          id: data.id,
          ddi: data.ddi,
          ddiE164: data.ddiE164,
          description: data.description,
          country: data.country?.id,
          countryName: data.country?.name || 'Unknown',
          ddiType: data.ddiType,
          setupPrice: data.pricing?.setupPrice || '0.00',
          monthlyPrice: data.pricing?.monthlyPrice || '0.00',
          inventoryStatus: data.inventoryStatus,
        };

        setRow(transformedRow);
        setLoading(false);
      } catch (err: any) {
        console.error('Failed to fetch DID:', err);
        setError(err.response?.data?.error || err.message || 'Failed to load DID details');
        setLoading(false);
      }
    };

    fetchDdi();
  }, [ddiId, token]);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  if (error || !row) {
    return (
      <Box className="card" p={2}>
        <Alert severity="error">
          {error || 'Failed to load DID details'}
        </Alert>
      </Box>
    );
  }

  const groups: Array<FieldsetGroups | false> = [
    {
      legend: _('DID Details'),
      fields: ['ddiE164', 'countryName', 'ddiType'],
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
    <Box className="card">
      <DefaultEntityView
        entityService={entityService}
        row={row}
        groups={groups}
        match={match}
        {...props}
      />
      <PurchaseButton ddi={ddiDetails} />
    </Box>
  );
};

export default AvailableDdiDetail;
