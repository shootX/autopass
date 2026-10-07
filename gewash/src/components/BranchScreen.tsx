import { lazy, Suspense, useEffect, useState } from 'react';
import { type Branch } from '@/hooks/useFetchBranches';
const BranchMap = lazy(() => import('./BranchMap').then((m) => ({ default: m.BranchMap })));
import { useLocation, useNavigate } from 'react-router-dom';
import { BranchFilterDropDown } from './ui/BranchFilterDropDown';
import { ArrowUpRight, Search, SlidersHorizontal } from 'lucide-react';
import { branchPhoto, hourSpan, isRoundTheClock, kmBetween } from '@/lib/v4';
import { fetchFilteredBranches, loadDefaultBranches, peekBranches } from '@/hooks/fetchFilteredBranches';
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
  const [origin, setOrigin] = useState<{ lat: number; lng: number } | null>(null);
  const navigate = useNavigate();

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
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => setOrigin({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => undefined,
      { maximumAge: 60000, timeout: 4000 },
    );
  }, []);

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
    <div className={viewMode === 'map' ? 'ap-branches v4-map' : 'v4-screen'}>
      {error && <p className="ap-error">{error}</p>}
      {viewMode === 'list' ? (
        <>
          <div className="v4-hd" style={{ padding: 0 }}>
            <h1 className="ap-title" style={{ flex: 1, textAlign: 'left', fontSize: 28 }}>ფილიალები</h1>
            <button type="button" className="v4-ib" style={{ background: 'var(--ap-ink)', color: 'var(--ap-lime)' }} aria-label="ფილტრი" onClick={() => setFilterOpen(true)}>
              <SlidersHorizontal size={20} />
            </button>
          </div>
          <form className="v4-list-search" onSubmit={(e) => e.preventDefault()}>
            <Search size={20} />
            <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="მოძებნე ფილიალი" aria-label="მოძებნე ფილიალი" />
          </form>
          <div className="v4-seg">
            <button type="button" onClick={() => setViewMode('map')}>რუკა</button>
            <button type="button" className="on">სია</button>
          </div>
          <div className="v4-rows">
            {shown.map((branch) => {
              const km = origin ? kmBetween(origin.lat, origin.lng, branch.lat, branch.lng) : null;
              const selected = branch.id === selectedBranchId;
              return (
                <div key={branch.id} className={`v4-brow${selected ? ' on' : ''}`}>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedBranchId(branch.id);
                      setViewMode('map');
                    }}
                    style={{ display: 'flex', gap: 12, alignItems: 'center', flex: 1, border: 0, background: 'transparent', padding: 0, textAlign: 'left', color: 'inherit', cursor: 'pointer' }}
                  >
                    <span className="ph" style={{ backgroundImage: `url(${branchPhoto(branch)})` }} />
                    <span style={{ flex: 1, minWidth: 0 }}>
                      <b>{branch.name}</b>
                      <span className="v4-kicker" style={{ display: 'block', marginTop: 2 }}>{branch.address}</span>
                      <span style={{ display: 'flex', gap: 6, marginTop: 8, flexWrap: 'wrap' }}>
                        {isRoundTheClock(branch.workStart, branch.workEnd) ? (
                          <span className="v4-chip lime">24/7</span>
                        ) : branch.isOpen === true ? (
                          <span className="v4-chip ok">{t('BranchInfoPanel.status.open')}</span>
                        ) : branch.isOpen === false ? (
                          <span className="v4-chip">{t('BranchInfoPanel.status.close')}</span>
                        ) : null}
                        {km != null && <span className="v4-chip">{km.toFixed(1)} კმ</span>}
                        {!km && hourSpan(branch.workStart, branch.workEnd) && <span className="v4-chip">{hourSpan(branch.workStart, branch.workEnd)}</span>}
                      </span>
                    </span>
                  </button>
                  <button
                    type="button"
                    className="go"
                    aria-label="სერვისზე ჩაწერა"
                    onClick={() => navigate('/wash-appointment', { state: { selectedBranchId: branch.id } })}
                  >
                    <ArrowUpRight size={19} />
                  </button>
                </div>
              );
            })}
          </div>
        </>
      ) : (
        <>
          <form className="v4-map-search" onSubmit={(e) => e.preventDefault()}>
            <Search size={20} />
            <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="მოძებნე ფილიალი" aria-label="მოძებნე ფილიალი" />
          </form>
          <button type="button" className="v4-map-filter" aria-label="ფილტრი" onClick={() => setFilterOpen(true)}>
            <SlidersHorizontal size={21} />
          </button>
          <div className="v4-tgl">
            <button type="button" className="on">რუკა</button>
            <button type="button" onClick={() => setViewMode('list')}>სია</button>
          </div>
          {shown.length > 0 && (
            <Suspense fallback={<PageSkeleton />}>
              <BranchMap branches={shown} selectedBranchId={selectedBranchId} onSelect={(id) => setSelectedBranchId(id)} />
            </Suspense>
          )}
        </>
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
