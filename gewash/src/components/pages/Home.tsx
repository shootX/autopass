import Header from '../Header';
import { useFetchCars } from '@/hooks/useFetchCars';
import { useMyPackages } from '@/hooks/useActivePackages';
import 'keen-slider/keen-slider.min.css';
import { useKeenSlider } from 'keen-slider/react';
import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import type { RootState } from '@/store';
import { useTranslation } from '@/hooks/useTranslation';
import { customFetch } from '@/utils/customFetch';
import { Calendar, ChevronRight, Star, Ticket } from 'lucide-react';
import { previewAppointments, previewMode } from '@/preview/fixtures';
const NO_API_URL = import.meta.env.VITE_NO_API_URL;
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

export default function Home() {
  const user = useSelector((state: RootState) => state.user.data);
  const { cars, loading: carsLoading, error: carsError } = useFetchCars();
  const { packages, isLoading: packagesLoading, error: packagesError } = useMyPackages();
  const t = useTranslation();
  const navigate = useNavigate();

  const [currentSlide, setCurrentSlide] = useState(0);
  const [nextBooking, setNextBooking] = useState('');

  const [promo, setPromo] = useState<{
    title: string;
    description: string;
    url: string;
  } | null>(null);

  const [sliderRef, slider] = useKeenSlider(
    cars.length > 0
      ? {
          loop: true,
          mode: 'snap',
          slides: {
            perView: 1,
            spacing: 16,
          },
          slideChanged(slider) {
            setCurrentSlide(slider.track.details.rel);
          },
        }
      : undefined,
  );

  useEffect(() => {
    if (slider.current) {
      slider.current.update();
    }
  }, [cars]);

  const selectedCar = cars[currentSlide] ?? null;

  const activePackage = selectedCar ? packages.find((p) => p.car.id === selectedCar.id) : null;

  const remainingWashes = activePackage
    ? activePackage.number_of_washes - activePackage.used_washes
    : null;

  const totalWashes = activePackage?.number_of_washes ?? null;
  const daysLeft = activePackage?.end_date
    ? Math.ceil((new Date(activePackage.end_date).getTime() - Date.now()) / 86400000)
    : null;
  const progress = remainingWashes != null && totalWashes ? Math.max(0, Math.min(100, (remainingWashes / totalWashes) * 100)) : 0;

  useEffect(() => {
    const loadPromo = async () => {
      try {
        const response = await fetch(`${import.meta.env.VITE_API_URL}/promo`);

        if (!response.ok) return;

        const data = await response.json();

        if (data.success) {
          setPromo(data.promo);
        }
      } catch (e) {
        console.error(e);
      }
    };

    loadPromo();
  }, []);

  useEffect(() => {
    if (previewMode) {
      const next = previewAppointments[0];
      const label = new Date(next.date).toLocaleDateString('ka-GE', { day: 'numeric', month: 'long' });
      setNextBooking(`${label} · ${next.time}`);
      return;
    }
    const token = localStorage.getItem('access_token');
    customFetch(`${import.meta.env.VITE_API_URL}/myappointments`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((res) => res.json())
      .then((data) => {
        const today = new Date().toISOString().slice(0, 10);
        const next = (data.appointments ?? [])
          .filter((row: { approved?: number; date?: string }) => row.approved !== 2 && (row.date ?? '') >= today)
          .sort((a: { date?: string; time?: string }, b: { date?: string; time?: string }) =>
            `${a.date}${a.time}`.localeCompare(`${b.date}${b.time}`),
          )[0];
        if (!next) return;
        const label = new Date(next.date).toLocaleDateString('ka-GE', { day: 'numeric', month: 'long' });
        setNextBooking(`${label} · ${String(next.time ?? '').slice(0, 5)}`);
      })
      .catch(() => undefined);
  }, []);

  const isPageLoading = carsLoading || packagesLoading;

  // 2. Если данные ещё качаются — отдаем скелетон
  if (isPageLoading) {
    return <PageSkeleton />; // Или единый <PageSkeleton />
  }
  const carTitle = selectedCar ? [selectedCar.brand, selectedCar.model].filter(Boolean).join(' ') : '';

  return (
    <div className="qhome">
      <Header logoVariant="image" />
      <p className="qhome-hello">
        {t('Home.greeting')}
        <b>{user?.firstName || ''}</b>
      </p>
      <div className="qhome-hero keen-slider" ref={sliderRef}>
        {cars.map((car) => (
          <div className="keen-slider__slide" key={car.id}>
            {car.image ? (
              <img className="car" src={`${NO_API_URL}${car.image}`} alt={car.model || car.plate} />
            ) : (
              <svg className="car" viewBox="0 0 320 140" aria-hidden>
                <path d="M40 100h240M70 100l20-40h120l30 40" fill="none" stroke="#8CBCE7" strokeWidth="8" strokeLinecap="round" />
                <circle cx="90" cy="100" r="14" fill="#2474C1" />
                <circle cx="230" cy="100" r="14" fill="#2474C1" />
              </svg>
            )}
          </div>
        ))}
      </div>
      <div className="qhome-meta">
        <strong>{carTitle || t('Home.washes.title')}</strong>
        <span>{selectedCar?.plate || ''}</span>
        {carsError && <p>{t('Booking.fallback.error')}</p>}
      </div>
      <div className="qhome-dots">
        {cars.map((_, index) => (
          <button key={index} type="button" className={index === currentSlide ? 'on' : ''} aria-label={String(index + 1)} onClick={() => slider.current?.moveToIdx(index)} />
        ))}
      </div>
      {!cars.length && (
        <button type="button" className="pill-cta" style={{ margin: '16px 20px', width: 'auto' }} onClick={() => navigate('/add-car')}>
          დაამატე ავტომობილი
        </button>
      )}
      <section className="qhome-sheet">
        <h2>ჩემი პაკეტი</h2>
        {activePackage ? (
          <div className="qstats">
            <div>
              <strong>{remainingWashes}</strong>
              <p>დარჩენილი რეცხვა</p>
              <div className="qbar"><i style={{ width: `${progress}%` }} /></div>
            </div>
            <div>
              <strong>{daysLeft}<small>დღე</small></strong>
              <p>პაკეტის ვადა</p>
            </div>
          </div>
        ) : (
          <button type="button" className="pill-cta" onClick={() => navigate('/my-packages')}>შეარჩიე პაკეტი</button>
        )}
        <button type="button" className="pill-cta" onClick={() => navigate('/wash-appointment')}>
          რეცხვის დაჯავშნა
          <span className="arrow">→</span>
        </button>
        <button type="button" className="qpromo" onClick={() => navigate('/shop')}>
          <span className="qpromo-mark" aria-hidden><Ticket size={18} /></span>
          <span className="grow">
            <small>ვაუჩერი</small>
            <b>ქულები გადააქციე ფასდაკლებად</b>
          </span>
          <ChevronRight size={18} />
        </button>
        <button type="button" className="qrow" onClick={() => navigate('/customer-calendar')}>
          <span className="ico"><Calendar size={18} /></span>
          <span className="grow"><b>{nextBooking || 'ჯავშანი ჯერ არ არის'}</b></span>
          <ChevronRight className="chev" size={18} />
        </button>
        <button type="button" className="qrow" onClick={() => navigate('/my-points')}>
          <span className="ico"><Star size={18} /></span>
          <span className="grow"><b>{user?.points ?? 0} ქულა</b></span>
          <ChevronRight className="chev" size={18} />
        </button>
        {promo && (
          <a className="qrow" href={promo.url} target="_blank" rel="noreferrer">
            <span className="grow"><b>{promo.title}</b><small>{promo.description}</small></span>
            <ChevronRight className="chev" size={18} />
          </a>
        )}
      </section>
    </div>
  );
}
