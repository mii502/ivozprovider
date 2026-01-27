import DeleteIcon from '@mui/icons-material/Delete';
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  IconButton,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material';
import _ from '@irontec/ivoz-ui/services/translations/translate';
import { useCallback, useEffect, useState } from 'react';
import { useStoreState } from 'store';

interface ByonDdiRow {
  id: number;
  ddiE164: string;
  companyId: number;
  companyName: string;
  isByon: boolean;
  verifiedAt: string | null;
}

const List = (): JSX.Element => {
  const [rows, setRows] = useState<ByonDdiRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [releaseDialogOpen, setReleaseDialogOpen] = useState(false);
  const [selectedRow, setSelectedRow] = useState<ByonDdiRow | null>(null);
  const [releasing, setReleasing] = useState(false);

  const token = useStoreState((state) => state.auth.token);

  const fetchByonDdis = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch('/api/brand/byon/ddis', {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      setRows(Array.isArray(data) ? data : []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    fetchByonDdis();
  }, [fetchByonDdis]);

  const handleReleaseClick = (row: ByonDdiRow) => {
    setSelectedRow(row);
    setReleaseDialogOpen(true);
  };

  const handleReleaseConfirm = async () => {
    if (!selectedRow) return;

    try {
      setReleasing(true);

      const response = await fetch('/api/brand/byon/release', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ ddiId: selectedRow.id }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      setReleaseDialogOpen(false);
      setSelectedRow(null);
      fetchByonDdis();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Release failed');
    } finally {
      setReleasing(false);
    }
  };

  const handleReleaseCancel = () => {
    setReleaseDialogOpen(false);
    setSelectedRow(null);
  };

  const formatDate = (dateStr: string | null): string => {
    if (!dateStr) return '-';
    try {
      const date = new Date(dateStr);
      return date.toLocaleString();
    } catch {
      return dateStr;
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight={200}>
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Alert severity="error" sx={{ m: 2 }}>
        {_('Error loading BYON numbers')}: {error}
      </Alert>
    );
  }

  if (rows.length === 0) {
    return (
      <Alert severity="info" sx={{ m: 2 }}>
        {_('No BYON numbers found')}
      </Alert>
    );
  }

  return (
    <>
      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>{_('Phone Number')}</TableCell>
              <TableCell>{_('Client')}</TableCell>
              <TableCell>{_('Verified On')}</TableCell>
              <TableCell align="right">{_('Actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.map((row) => (
              <TableRow key={row.id}>
                <TableCell>
                  <Typography variant="body2" fontFamily="monospace">
                    {row.ddiE164}
                  </Typography>
                </TableCell>
                <TableCell>{row.companyName}</TableCell>
                <TableCell>{formatDate(row.verifiedAt)}</TableCell>
                <TableCell align="right">
                  <Tooltip title={_('Release BYON Number')}>
                    <IconButton
                      size="small"
                      color="error"
                      onClick={() => handleReleaseClick(row)}
                    >
                      <DeleteIcon />
                    </IconButton>
                  </Tooltip>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <Dialog open={releaseDialogOpen} onClose={handleReleaseCancel}>
        <DialogTitle>{_('Release BYON Number')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {_('Are you sure you want to release this BYON number?')}
            <br />
            <strong>{selectedRow?.ddiE164}</strong> ({selectedRow?.companyName})
            <br /><br />
            {_('This will remove the number from the company and delete the DDI.')}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleReleaseCancel} disabled={releasing}>
            {_('Cancel')}
          </Button>
          <Button
            onClick={handleReleaseConfirm}
            color="error"
            variant="contained"
            disabled={releasing}
          >
            {releasing ? <CircularProgress size={24} /> : _('Release')}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
};

export default List;
