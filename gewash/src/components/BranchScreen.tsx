import { lazy, Suspense, useEffect, useState } from 'react';
import { BranchCard } from './BranchCard';
import { type Branch } from '@/hooks/useFetchBranches';
const BranchMap = lazy(() => import('./BranchMap').then((m) => ({ default: m.BranchMap })));
import { useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import type { RootState } from '@/store';
import { BranchFilterDropDown } from './ui/BranchFilterDropDown';
import { fetchFilteredBranches, loadDefaultBranches, peekBranches } from '@/hooks/fetchFilteredBranches';
import { useLoadAppointmentsFromBackend } from '@/hooks/useLoadAppointmentsFromBackend';
import { useTranslation } from '@/hooks/useTranslation';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

export default function BranchScreen() {
  const [branches, setBranches] = useState<Branch[]>(peekBranches() ?? []);
  const [loading, setLoading] = useState(peekBranches() === null);
  const [error, setError] = useState<string | null>(null);
  const [viewMode, setViewMode] = useState<'list' | 'map'>('map');
  const [query, setQuery] = useState('');
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const location = useLocation();
  const locationState = location.state as {
    selectedBranchId?: number;
    viewMode?: 'map' | 'list';
  } | null;
  const [filterOpen, setFilterOpen] = useState(false);
  const [selectedServices, setSelectedServices] = useState<number[]>([]);
  const [roundTheClockOnly, setRoundTheClockOnly] = useState(false);
  const [onlyOpen, setOnlyOpen] = useState(false);

  const appointments = useSelector((state: RootState) => state.appointments.appointments);

  const activeBranchIds = appointments.map((a) => a.branchId);
  useLoadAppointmentsFromBackend();

  useEffect(() => {
    loadDefaultBranches()
      .then(setBranches)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
    const preload = () => {
      void import('./BranchMap');
    };
    const idle = window.requestIdleCallback?.(preload);
    const timer = window.setTimeout(preload, 400);
    return () => {
      if (idle) window.cancelIdleCallback?.(idle);
      window.clearTimeout(timer);
    };
  }, []);

  const handleApplyFilters = () => {
    setLoading(true);
    fetchFilteredBranches({
      selectedServices,
      onlyOpen,
      roundTheClockOnly,
    })
      .then(setBranches)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    if (locationState?.selectedBranchId) {
      setSelectedBranchId(locationState.selectedBranchId);
    }
    if (locationState?.viewMode) {
      setViewMode(locationState.viewMode);
    }
  }, [locationState]);

  const t = useTranslation();

  if (loading) {
    return <PageSkeleton />;
  }

  const shown = branches.filter((branch) => {
    const blob = `${branch.name} ${branch.address}`.toLowerCase();
    return blob.includes(query.trim().toLowerCase());
  });

  return (
    <div className="branch-screen-wrapper qbranch">
      <div className="qhead">
        <button type="button" onClick={() => window.history.back()} aria-label="უკან">←</button>
        <h1>{t('Branches.title')}</h1>
        <button type="button" aria-label="ფილტრი" onClick={() => setFilterOpen(true)}>⚙</button>
      </div>
      <input className="qsearch" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="მოძებნე ფილიალი" aria-label="მოძებნე ფილიალი" />

      <div className="branch-screen">
        {loading && <p>Loading branches...</p>}
        {error && <p className="error">{error}</p>}

        {viewMode === 'list' && (
          <div className="branch-list">
            {shown.map((branch) => (
              <BranchCard
                key={branch.id}
                branch={branch}
                isActive={activeBranchIds.includes(branch.id)}
                onClick={() => setSelectedBranchId(branch.id)}
              />
            ))}
          </div>
        )}

        {viewMode === 'map' && !loading && shown.length > 0 && (
          <Suspense fallback={<PageSkeleton />}>
            <BranchMap
              branches={shown}
              selectedBranchId={selectedBranchId}
              onSelect={(id) => setSelectedBranchId(id)}
            />
          </Suspense>
        )}
        <BranchFilterDropDown
          open={filterOpen}
          setOpen={setFilterOpen}
          selectedServices={selectedServices}
          setSelectedServices={setSelectedServices}
          roundTheClockOnly={roundTheClockOnly}
          setRoundTheClockOnly={setRoundTheClockOnly}
          onlyOpen={onlyOpen}
          setOnlyOpen={setOnlyOpen}
          onApplyFilters={handleApplyFilters}
        />
      </div>
    </div>
  );
}
