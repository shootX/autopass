import { lazy, Suspense, useEffect, useState } from 'react';
import { BranchCard } from './BranchCard';
import { type Branch } from '@/hooks/useFetchBranches';
const BranchMap = lazy(() => import('./BranchMap').then((m) => ({ default: m.BranchMap })));
import { useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import type { RootState } from '@/store';
import { BranchFilterDropDown } from './ui/BranchFilterDropDown';
import { Search, SlidersHorizontal } from 'lucide-react';
import { fetchFilteredBranches, loadDefaultBranches, peekBranches } from '@/hooks/fetchFilteredBranches';
import { useLoadAppointmentsFromBackend } from '@/hooks/useLoadAppointmentsFromBackend';
import { useTranslation } from '@/hooks/useTranslation';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

export default function BranchScreen() {
  const [branches, setBranches] = useState<Branch[]>(peekBranches() ?? []);
  const [loading, setLoading] = useState(peekBranches() === null);
  const [error, setError] = useState<string | null>(null);
  const [viewMode, setViewMode] = useState<'list' | 'map'>('map');
  const location = useLocation();
  const locationState = location.state as {
    selectedBranchId?: number;
    viewMode?: 'map' | 'list';
    query?: string;
  } | null;
  const [query, setQuery] = useState(locationState?.query ?? '');
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
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
    <div className="ap-branches">
      {error && <p className="error" style={{ position: 'absolute', zIndex: 6, left: 20, top: 80 }}>{error}</p>}
      <form className="ap-search" onSubmit={(e) => e.preventDefault()}>
        <Search size={21} />
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="მოძებნე ფილიალი" aria-label="მოძებნე ფილიალი" />
        <button type="button" aria-label={viewMode === 'map' ? 'სია' : 'რუკა'} onClick={() => setViewMode(viewMode === 'map' ? 'list' : 'map')} style={{ fontSize: 13, fontWeight: 700, color: 'var(--ap-forest)' }}>
          {viewMode === 'map' ? 'სია' : 'რუკა'}
        </button>
        <button type="button" aria-label="ფილტრი" onClick={() => setFilterOpen(true)}>
          <SlidersHorizontal size={18} />
        </button>
      </form>

      {viewMode === 'list' && (
        <div className="branch-list" style={{ position: 'absolute', zIndex: 6, left: 16, right: 16, top: 84, bottom: 16, overflow: 'auto' }}>
          {shown.map((branch) => (
            <BranchCard
              key={branch.id}
              branch={branch}
              isActive={activeBranchIds.includes(branch.id)}
              onClick={() => {
                setSelectedBranchId(branch.id);
                setViewMode('map');
              }}
            />
          ))}
        </div>
      )}

      {viewMode === 'map' && shown.length > 0 && (
        <Suspense fallback={<PageSkeleton />}>
          <BranchMap branches={shown} selectedBranchId={selectedBranchId} onSelect={(id) => setSelectedBranchId(id)} />
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
  );
}
