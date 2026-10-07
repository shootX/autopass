import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useFetchCars } from '@/hooks/useFetchCars';
import { customFetch } from '@/utils/customFetch';
import { useMyPackages } from '@/hooks/useActivePackages';
import { useTranslation } from '@/hooks/useTranslation';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

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
  const [activePackageId, setActivePackageId] = useState<number | null>(null);
  const [qrImageUrl, setQrImageUrl] = useState<string | null>(null);

  const carIdToPackage = Object.fromEntries(carPackages.map((p) => [p.car.id, p]));

  const selected = cars[carIndex] ?? null;
  const pkg = selected ? carIdToPackage[selected.id] : null;
  const remaining = pkg ? pkg.number_of_washes - pkg.used_washes : null;
  const total = pkg?.number_of_washes ?? null;
  const cells = total && remaining != null
    ? Array.from({ length: Math.min(total, 10) }, (_, i) => i < Math.round((Math.max(0, remaining) / total) * Math.min(total, 10)))
    : [];

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

  const carTitle = selected ? [selected.brand, selected.model].filter((part) => part && part !== 'Unknown').join(' ') : '';

  return (
    <div className="ap-qr">
      <h1 className="ap-title">{t('QRPage.header.title')}</h1>
      {selected && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 10 }}>
          {selected.plate && <span className="ap-plate" style={{ height: 26, fontSize: 13 }}><b>GE</b><span>{selected.plate}</span></span>}
          {carTitle && <span style={{ color: 'var(--ap-gray-600)' }}>{carTitle}</span>}
        </div>
      )}
      {!selected && <p className="ap-sub">{t('QRPage.noCars')}</p>}
      {selected && !pkg && (
        <button type="button" className="ap-btn" style={{ marginTop: 24 }} onClick={() => navigate('/my-packages')}>
          შეარჩიე პაკეტი
        </button>
      )}
      {pkg && (
        <div className="ap-qr-card" style={{ marginTop: 22 }}>
          {qrImageUrl ? <img src={qrImageUrl} alt="QR" /> : <p>{t('QRPage.overlay.loading')}</p>}
        </div>
      )}
      {cars.length > 1 && (
        <div className="ap-dots">
          {cars.map((car, index) => (
            <button key={car.id} type="button" className={index === carIndex ? 'on' : ''} aria-label={car.plate} onClick={() => setCarIndex(index)} />
          ))}
        </div>
      )}
      {pkg && remaining != null && (
        <div style={{ marginTop: 28 }}>
          <div style={{ display: 'flex', alignItems: 'center' }}>
            <span style={{ flex: 1, fontWeight: 700 }}>დარჩენილი რეცხვა</span>
            <span style={{ fontWeight: 800 }}>{remaining}{total != null && <span style={{ color: 'var(--ap-gray-400)', fontWeight: 600 }}> / {total}</span>}</span>
          </div>
          {cells.length > 0 && (
            <div className="ap-sq" style={{ marginTop: 12 }}>
              {cells.map((on, i) => <i key={i} className={on ? 'on' : ''} />)}
            </div>
          )}
          <p style={{ marginTop: 22, textAlign: 'center', color: 'var(--ap-gray-600)', fontSize: 14 }}>აჩვენეთ კოდი ოპერატორს სკანირებისთვის</p>
        </div>
      )}
    </div>
  );
}
