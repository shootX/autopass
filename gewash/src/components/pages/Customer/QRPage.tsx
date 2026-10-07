import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useFetchCars } from '@/hooks/useFetchCars';
import { customFetch } from '@/utils/customFetch';
import { useMyPackages } from '@/hooks/useActivePackages';
import { useTranslation } from '@/hooks/useTranslation';
import { carParts, washCells } from '@/lib/v4';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';
import { Car, CarFront, Sun } from 'lucide-react';

export default function QRPage() {
  const t = useTranslation();
  const navigate = useNavigate();

  const {
    packages: carPackages,
    isLoading: packagesLoading,
    error: packagesError,
  } = useMyPackages();

  const { cars, loading: carsLoading, error: carsError } = useFetchCars();

  const [carIndex, setCarIndex] = useState(0);
  const [bright, setBright] = useState(false);
  const [activePackageId, setActivePackageId] = useState<number | null>(null);
  const [qrImageUrl, setQrImageUrl] = useState<string | null>(null);

  const carIdToPackage = Object.fromEntries(carPackages.map((p) => [p.car.id, p]));

  const selected = cars[carIndex] ?? null;
  const pkg = selected ? carIdToPackage[selected.id] : null;
  const remaining = pkg ? pkg.number_of_washes - pkg.used_washes : null;
  const total = pkg?.number_of_washes ?? null;
  const cells = washCells(total, remaining);

  useEffect(() => {
    if (pkg?.id && pkg.id !== activePackageId) {
      setQrImageUrl(null);
      setActivePackageId(pkg.id);
    }
  }, [pkg?.id]);

  useEffect(() => {
    if (!activePackageId) return;

    const token = localStorage.getItem('access_token');

    customFetch(`${import.meta.env.VITE_API_URL}/packages/${activePackageId}/qr`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error('Failed to fetch QR code');
        return res.blob();
      })
      .then((blob) => {
        const url = URL.createObjectURL(blob);
        setQrImageUrl(url);
      })
      .catch((err) => {
        console.error('QR fetch error:', err);
        setQrImageUrl(null);
      });
  }, [activePackageId]);

  if (carsLoading || packagesLoading) {
    return <PageSkeleton />;
  }

  if (carsError || packagesError) {
    return (
      <div className="ap-qr">
        <h1 className="ap-title">{t('QRPage.header.title')}</h1>
        <p className="ap-error">{t('QRPage.error')}</p>
      </div>
    );
  }

  const carTitle = selected ? carParts(selected).title : '';

  return (
    <div className="v4-screen ap-qr" style={bright ? { background: '#fff' } : undefined}>
      <div className="v4-hd" style={{ padding: 0 }}>
        <h1 className="ap-title" style={{ flex: 1, textAlign: 'left', fontSize: 28 }}>{t('CustomerNavBar.routes.qr')}</h1>
        <button type="button" className="v4-ib" aria-pressed={bright} aria-label="სიკაშკაშე" onClick={() => setBright((v) => !v)}>
          <Sun size={20} />
        </button>
      </div>
      {cars.length > 0 && (
        <div className="v4-chips">
          {cars.map((car, index) => {
            const parts = carParts(car);
            const on = index === carIndex;
            return (
              <button key={car.id} type="button" className={on ? 'on' : ''} onClick={() => setCarIndex(index)}>
                {on ? <CarFront size={17} /> : <Car size={17} />}
                {parts.title || car.plate}
              </button>
            );
          })}
        </div>
      )}
      {!selected && <p className="ap-sub">{t('QRPage.noCars')}</p>}
      {selected && !pkg && (
        <button type="button" className="ap-btn" style={{ marginTop: 24 }} onClick={() => navigate('/my-packages')}>
          შეარჩიე პაკეტი
        </button>
      )}
      {pkg && (
        <div className="v4-qrwrap">
          <div className="c" style={{ width: 300, height: 300, background: bright ? '#F7FBEA' : '#F5F9EC' }} />
          <div className="c" style={{ width: 250, height: 250, background: '#EAF4D6' }} />
          <div className="card">
            {qrImageUrl ? <img src={qrImageUrl} alt="QR" /> : <p>{t('QRPage.overlay.loading')}</p>}
          </div>
        </div>
      )}
      {selected && (
        <div className="v4-info">
          <div>
            <div className="v4-kicker">{t('QRPage.car.plate')}</div>
            <b>{selected.plate}</b>
          </div>
          <div>
            <div className="v4-kicker">{t('QRPage.car.model')}</div>
            <b>{carTitle || '—'}</b>
          </div>
        </div>
      )}
      {pkg && remaining != null && (
        <div className="v4-washbar v4-dk">
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 12.5, color: '#A7B1AA', fontWeight: 600 }}>დარჩენილი რეცხვა</div>
            {cells.length > 0 && (
              <div className="sq">{cells.map((on, i) => <i key={i} className={on ? 'on' : ''} />)}</div>
            )}
          </div>
          <div className="n">{remaining}{total != null && <span> / {total}</span>}</div>
        </div>
      )}
    </div>
  );
}
