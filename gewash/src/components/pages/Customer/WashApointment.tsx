import { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useCreateAppointment } from '@/hooks/useCreateAppointment';
import { useLoadAppointmentsFromBackend } from '@/hooks/useLoadAppointmentsFromBackend';
import { useFetchBranches } from '@/hooks/useFetchBranches';
import { formatKaMonth } from '@/lib/format';
import { useFetchCars } from '@/hooks/useFetchCars';
import { useMyPackages } from '@/hooks/useActivePackages';
import { useTranslation } from '@/hooks/useTranslation';
import { branchPhoto, carParts, carPhoto } from '@/lib/v4';
import { ArrowLeft, ChevronLeft, ChevronRight, Droplets, Sparkles, Ticket, Zap } from 'lucide-react';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

type Service = {
  id: number;
  name: string;
};

export default function WashAppointment() {
  const t = useTranslation();
  const location = useLocation();
  const navigate = useNavigate();
  const { cars, loading: carsLoading } = useFetchCars();
  const { packages } = useMyPackages();
  const firstCar = cars[0];

  const slots = ['10:00', '11:00', '12:30', '14:30', '15:00', '16:30'];
  const slotAt = (day: Date, time: string) => {
    const [h, m] = time.split(':').map(Number);
    const slot = new Date(day);
    slot.setHours(h, m, 0, 0);
    return slot;
  };
  const freeOn = (day: Date, now = new Date()) => slots.filter((time) => slotAt(day, time).getTime() >= now.getTime());
  const defaultDay = (now = new Date()) => {
    const today = new Date(now);
    today.setHours(0, 0, 0, 0);
    if (freeOn(today, now).length) return today;
    return new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
  };
  const weekStartFor = (day: Date) => {
    const start = new Date(day);
    start.setHours(0, 0, 0, 0);
    const mondayOffset = (start.getDay() + 6) % 7;
    if (mondayOffset <= 4) start.setDate(start.getDate() - mondayOffset);
    return start;
  };
  const preset = location.state as { date?: string; time?: string } | null;
  const presetDate = preset?.date && /^\d{4}-\d{2}-\d{2}$/.test(preset.date)
    ? (() => {
        const [y, m, d] = preset.date.split('-').map(Number);
        return new Date(y, m - 1, d);
      })()
    : null;
  const initialDate = presetDate ?? defaultDay();
  const initialTime = preset?.time || freeOn(initialDate)[0] || slots[0];

  const [selectedDate, setSelectedDate] = useState<Date | undefined>(initialDate);
  const [pickedTime, setPickedTime] = useState(initialTime);
  const [weekStart, setWeekStart] = useState(() => weekStartFor(initialDate));
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const { createAppointment, loading, error, success } = useCreateAppointment();
  useLoadAppointmentsFromBackend();
  const branchId = location.state?.selectedBranchId;
  const { branches, loading: branchesLoading } = useFetchBranches();

  const branch = branches.find((b) => b.id === Number(branchId)) ?? branches[0];

  useEffect(() => {
    const state = location.state as { date?: string; time?: string } | null;
    if (!state?.date && !state?.time) return;
    if (state.date && /^\d{4}-\d{2}-\d{2}$/.test(state.date)) {
      const [y, m, d] = state.date.split('-').map(Number);
      const date = new Date(y, m - 1, d);
      setSelectedDate(date);
      setWeekStart(weekStartFor(date));
    }
    if (state.time) setPickedTime(state.time);
  }, [location.state]);

  useEffect(() => {
    if (!branch?.services?.length) return;
    const serviceId = (location.state as { serviceId?: number } | null)?.serviceId;
    const found = serviceId ? branch.services.find((service) => service.id === Number(serviceId)) : null;
    setSelectedService((current) => found ?? current ?? branch.services[0]);
  }, [branch, location.state]);
  const [carIndex, setCarIndex] = useState(0);
  const selectedCar = cars[carIndex] ?? firstCar;

  const isFormValid = Boolean(selectedDate && pickedTime && selectedService && branch && selectedCar);

  const handleSubmit = async () => {
    if (!isFormValid || isSubmitting || !branch || !selectedService || !selectedDate) return;

    setIsSubmitting(true);

    const formattedDate = `${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}`;

    await createAppointment({
      car_wash_id: branch.id,
      date: formattedDate,
      time: pickedTime,
      branchName: branch.name,
      branchAddress: branch.address,
      type: selectedService.name,
      service_id: selectedService.id,
      car_id: selectedCar?.id,
    });

    setSelectedDate(undefined);
    setPickedTime('');
    setSelectedService(null);
    setIsSubmitting(false);
  };

  if (branchesLoading || carsLoading || loading) {
    return <PageSkeleton />;
  }

  const dayNames = ['კვ', 'ორ', 'სამ', 'ოთხ', 'ხუთ', 'პარ', 'შაბ'];
  const days = Array.from({ length: 5 }, (_, i) => {
    const date = new Date(weekStart);
    date.setDate(weekStart.getDate() + i);
    return date;
  });
  const sameDay = (a?: Date, b?: Date) =>
    !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  const slotOff = (time: string) => {
    if (!selectedDate) return true;
    const [h, m] = time.split(':').map(Number);
    const slot = new Date(selectedDate);
    slot.setHours(h, m, 0, 0);
    return slot.getTime() < Date.now();
  };
  const monthLabel = formatKaMonth(selectedDate ?? weekStart);

  const pkg = selectedCar ? packages.find((item) => item.car.id === selectedCar.id) : null;
  const remaining = pkg ? pkg.number_of_washes - pkg.used_washes : null;
  const today0 = new Date();
  today0.setHours(0, 0, 0, 0);

  return (
    <div className="v4-screen ap-book">
      <div className="v4-hd">
        <button type="button" className="v4-ib" onClick={() => navigate(-1)} aria-label="უკან"><ArrowLeft size={21} /></button>
        <h1>რეცხვის დაჯავშნა</h1>
        <span className="spacer" />
      </div>
      <div className="v4-branch">
        {branch && <span className="ph" style={{ backgroundImage: `url(${branchPhoto(branch)})` }} />}
        <div style={{ flex: 1 }}>
          <div style={{ fontWeight: 800 }}>{branch?.name || 'აირჩიე ფილიალი'}</div>
          <div className="v4-kicker">
            {branch?.address}
            {branch?.isOpen === true && <span style={{ color: 'var(--ap-success)' }}> · {t('BranchInfoPanel.status.open')}</span>}
          </div>
        </div>
        <button type="button" onClick={() => navigate('/branches')} style={{ border: 0, background: 'transparent', color: 'var(--ap-lime-deep)', fontWeight: 800, cursor: 'pointer' }}>
          შეცვლა
        </button>
      </div>
      {cars.length > 0 && (
        <div className="v4-cars">
          {cars.map((car, index) => {
            const parts = carParts(car);
            return (
              <button key={car.id} type="button" className={`v4-cr${car.id === selectedCar?.id ? ' on' : ''}`} onClick={() => setCarIndex(index)}>
                <img src={carPhoto(parts.type, parts.image)} alt="" />
                <span>
                  <b>{parts.short || car.plate}</b>
                  <small>{car.plate}</small>
                </span>
              </button>
            );
          })}
        </div>
      )}
      <div style={{ display: 'flex', alignItems: 'center', marginTop: 16 }}>
        <span className="v4-sec" style={{ flex: 1 }}>თარიღი</span>
        <button type="button" className="v4-ib" style={{ width: 36, height: 36 }} aria-label="წინა" onClick={() => setWeekStart(new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() - 5))}>
          <ChevronLeft size={18} />
        </button>
        <span className="v4-kicker" style={{ textTransform: 'capitalize', margin: '0 8px' }}>{monthLabel}</span>
        <button type="button" className="v4-ib" style={{ width: 36, height: 36 }} aria-label="შემდეგი" onClick={() => setWeekStart(new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + 5))}>
          <ChevronRight size={18} />
        </button>
      </div>
      <div className="v4-week">
        {days.map((date) => {
          const past = date < today0;
          const on = sameDay(date, selectedDate);
          return (
            <button
              key={date.toISOString()}
              type="button"
              disabled={past}
              className={on ? 'on' : past ? 'off' : ''}
              onClick={() => { setSelectedDate(date); setPickedTime(freeOn(date)[0] || ''); }}
            >
              <small>{dayNames[date.getDay()]}</small>
              <b>{date.getDate()}</b>
            </button>
          );
        })}
      </div>
      <div className="v4-sec" style={{ marginTop: 16 }}>დრო</div>
      <div className="v4-slots">
        {slots.map((time) => (
          <button key={time} type="button" disabled={slotOff(time)} className={pickedTime === time ? 'on' : ''} onClick={() => setPickedTime(time)}>
            {time}
          </button>
        ))}
      </div>
      <div className="v4-sec" style={{ marginTop: 16 }}>სერვისი</div>
      <div className="v4-sv">
        {(branch?.services ?? []).map((service) => {
          const name = service.name.toLowerCase();
          const Icon = name.includes('ექსპრეს') || name.includes('express') ? Zap : name.includes('სალონ') ? Sparkles : Droplets;
          return (
            <button key={service.id} type="button" className={selectedService?.id === service.id ? 'on' : ''} onClick={() => setSelectedService(service)}>
              <Icon size={22} />
              {service.name}
            </button>
          );
        })}
      </div>
      {remaining != null && remaining > 0 && (
        <div className="v4-passnote">
          <span className="v4-limeico" style={{ background: '#F3F5F2' }}><Ticket size={19} /></span>
          <div>
            <div className="v4-kicker">ჩემი პაკეტი</div>
            <div style={{ fontWeight: 800, fontSize: 14.5 }}>ჩამოიჭრება 1 რეცხვა · დარჩება {Math.max(0, remaining - 1)}</div>
          </div>
        </div>
      )}
      {success && <p>{t('WashAppointment.form.success')}</p>}
      {!success && error && <p className="ap-error">{error}</p>}
      <button type="button" className="ap-btn" style={{ marginTop: 16 }} onClick={handleSubmit} disabled={!isFormValid || isSubmitting}>
        {t('WashAppointment.form.submit.button')}
      </button>
    </div>
  );
}
