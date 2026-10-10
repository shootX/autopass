import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { useAppDispatch } from '@/hooks/hooks';
import { setCars } from '@/store/carSlice';
import { type RootState } from '@/store';
import { ActivePackageCard } from '@/components/ActivePackageCard';
import { WashingPackageForm } from '@/components/WashingPackageForm';
import type { PackageData } from '@/types';
import { customFetch } from '@/utils/customFetch';
import { useMyPackages } from '@/hooks/useActivePackages';
import { useTranslation } from '@/hooks/useTranslation';
import { ArrowLeft, Pencil } from 'lucide-react';

export default function MyPackages() {
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const cars = useSelector((state: RootState) => state.car.cars);
  const { packages, isLoading, error } = useMyPackages();
  const t = useTranslation();

  const [editingPackage, setEditingPackage] = useState<PackageData | null>(null);
  const [tab, setTab] = useState<'active' | 'history'>('active');
  const [buying, setBuying] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    if (!token) return;

    customFetch(`${import.meta.env.VITE_API_URL}/mycars`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('Failed to fetch cars');
        const data = await res.json();
        dispatch(setCars(data.cars));
      })
      .catch((err) => {
        console.error('Car fetch error:', err);
      });
  }, []);

  const getMonthDiff = (start: string, end: string) => {
    const s = new Date(start);
    const e = new Date(end);
    return e.getMonth() - s.getMonth() + (e.getFullYear() - s.getFullYear()) * 12;
  };

  const today = new Date().toISOString().slice(0, 10);
  const activeRaw = packages.filter((pkg) => (pkg.end_date ?? '') >= today);
  const historyRaw = packages.filter((pkg) => (pkg.end_date ?? '') < today);

  const toData = (pkg: (typeof packages)[number]): PackageData => ({
    id: pkg.id,
    plate: pkg.car.plate,
    car_id: pkg.car.id,
    model: pkg.package.car_type,
    washes: pkg.number_of_washes - pkg.used_washes,
    period: getMonthDiff(pkg.start_date, pkg.end_date),
    startDate: new Date(pkg.start_date),
    autoRenewal: pkg.renewal,
  });

  const transformedPackages = activeRaw.map(toData);
  const availableCars = cars.filter((car) => !packages.some((pkg) => pkg.car.plate === car.plate && (pkg.end_date ?? '') >= today));
  const showForm = Boolean(editingPackage) || buying || (transformedPackages.length === 0 && availableCars.length > 0);

  return (
    <div className="v4-screen">
      <div className="v4-hd">
        <button type="button" className="v4-ib" onClick={() => (showForm && transformedPackages.length ? setBuying(false) : navigate(-1))} aria-label={t('MyPackages.header.backAlt')}>
          <ArrowLeft size={21} />
        </button>
        <h1>{showForm && !editingPackage ? t('WashingPackageForm.title.create') : t('MyPackages.header.title')}</h1>
        <button
          type="button"
          className="v4-ib"
          aria-label={t('ActivePackageCard.actions.editAlt')}
          onClick={() => {
            if (transformedPackages[0]) setEditingPackage(transformedPackages[0]);
          }}
        >
          <Pencil size={19} />
        </button>
      </div>

      {!showForm && (
        <div className="v4-seg">
          <button type="button" className={tab === 'active' ? 'on' : ''} onClick={() => setTab('active')}>{t('MyPackages.tabs.active')}</button>
          <button type="button" className={tab === 'history' ? 'on' : ''} onClick={() => setTab('history')}>{t('MyPackages.tabs.history')}</button>
        </div>
      )}

      <div style={{ marginTop: 16 }}>
        {isLoading ? (
          <p>{t('MyPackages.loading')}</p>
        ) : error ? (
          <p>{t('MyPackages.error').replace('{message}', error.message)}</p>
        ) : showForm ? (
          <WashingPackageForm
            mode={editingPackage ? 'edit' : 'create'}
            isVisible={true}
            cars={cars}
            activePackages={transformedPackages}
            initialPackage={editingPackage ?? undefined}
            onClose={() => {
              setEditingPackage(null);
              setBuying(false);
            }}
            onSubmit={() => {
              setEditingPackage(null);
              setBuying(false);
            }}
          />
        ) : tab === 'history' ? (
          historyRaw.length === 0 ? (
            <p className="v4-empty">{t('MyPackages.emptyHistory')}</p>
          ) : (
            historyRaw.map((pkg) => (
              <div key={pkg.id} className="v4-ro" style={{ marginBottom: 10, cursor: 'default' }}>
                <b>{pkg.car.plate}</b>
                <span className="pr">{pkg.end_date?.slice(0, 10)}</span>
              </div>
            ))
          )
        ) : transformedPackages.length === 0 ? (
          <p className="v4-empty">{t('MyPackages.empty')}</p>
        ) : (
          <>
            {activeRaw.map((pkg) => (
              <ActivePackageCard
                key={pkg.id}
                id={pkg.id}
                plate={pkg.car.plate}
                model={pkg.package.car_type}
                washes={pkg.number_of_washes - pkg.used_washes}
                totalWashes={pkg.number_of_washes}
                period={getMonthDiff(pkg.start_date, pkg.end_date)}
                startDate={new Date(pkg.start_date)}
                endDate={pkg.end_date}
                autoRenewal={pkg.renewal}
                setAutoRenewal={() => {}}
                onEdit={(updated) => setEditingPackage(updated)}
                onDelete={() => undefined}
                cars={cars}
              />
            ))}
            {availableCars.length > 0 && (
              <button type="button" className="ap-btn" style={{ marginTop: 16, background: 'var(--ap-ink)', color: '#fff', boxShadow: 'none' }} onClick={() => setBuying(true)}>
                {t('WashingPackageForm.button.create')}
              </button>
            )}
          </>
        )}
      </div>
    </div>
  );
}
