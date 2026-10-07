import { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { type RootState } from '@/store';
import { useCreateAppointment } from '@/hooks/useCreateAppointment';
import { useLoadAppointmentsFromBackend } from '@/hooks/useLoadAppointmentsFromBackend';
import { useFetchBranches } from '@/hooks/useFetchBranches';
import { formatKaMonth } from '@/lib/format';
import { useFetchCars } from '@/hooks/useFetchCars';
import { useTranslation } from '@/hooks/useTranslation';

import { SingleCalendarMobileSheet } from '@/components/Calendars/SingleCalendarDropDownSheet';
import { TimePickerMobileSheet } from '@/components/ui/TimePickerMobileSheet';
import { TypeWashingDropDown } from '@/components/ui/TypeWashingDropDown';
import {
  leftArrowUrl,
  branchSummaryCalendarUrl,
  branchSummaryTimeUrl,
  branchSummaryAppliedUrl,
  carWashTypeIconUrl,
} from '@/assets/staticUrls';
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
  const firstCar = cars[0];

  const appointments = useSelector((state: RootState) => state.appointments.appointments);

  const APPOINTMENT_STATUS_LABELS: Record<number, string> = {
    0: 'New',
    1: 'Confirm',
    2: 'Deleted',
    3: 'Rescheduled',
  };
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

  const [calendarOpen, setCalendarOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(initialDate);
  const [pickedTime, setPickedTime] = useState(initialTime);
  const [weekStart, setWeekStart] = useState(() => weekStartFor(initialDate));
  const [timePickerOpen, setTimePickerOpen] = useState(false);
  const [typeOpen, setTypeOpen] = useState(false);
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
    const serviceId = (location.state as { serviceId?: number } | null)?.serviceId;
    if (!serviceId || !branch?.services?.length) return;
    const found = branch.services.find((service) => service.id === Number(serviceId));
    if (found) setSelectedService(found);
  }, [branch, location.state]);
  const [carIndex, setCarIndex] = useState(0);
  const selectedCar = cars[carIndex] ?? firstCar;

  const isFormValid = Boolean(selectedDate && pickedTime && selectedService && branch && selectedCar);

  const handleGoToMap = () => {
    if (branch) {
      navigate('/branches', {
        state: {
          selectedBranchId: branch.id,
          viewMode: 'map',
        },
      });
    }
  };

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

  return (
    <div className="ap-book">
      <button type="button" className="ap-back" onClick={() => navigate(-1)} aria-label="უკან">←</button>
      <h1 className="ap-title" style={{ marginTop: 12 }}>რეცხვის დაჯავშნა</h1>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 8, color: 'var(--ap-gray-600)' }}>
        <span style={{ flex: 1 }}>{[branch?.name, selectedService?.name].filter(Boolean).join(' · ') || 'აირჩიე ფილიალი'}</span>
        <button type="button" className="ap-link" style={{ border: 0, background: 'transparent', cursor: 'pointer' }} onClick={() => navigate('/branches')}>შეცვლა</button>
      </div>
      {cars.length > 1 && (
        <button type="button" className="ap-link" style={{ marginTop: 8, border: 0, background: 'transparent' }} onClick={() => setCarIndex((i) => (i + 1) % cars.length)}>
          {selectedCar?.plate}
        </button>
      )}
      <div className="qhead">
        <button type="button" aria-label="წინა" onClick={() => setWeekStart(new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() - 1))}>‹</button>
        <h1 style={{ textTransform: 'capitalize' }}>{monthLabel}</h1>
        <button type="button" aria-label="შემდეგი" onClick={() => setWeekStart(new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + 1))}>›</button>
      </div>
      <div className="qweek">
        {days.map((date) => (
          <button
            key={date.toISOString()}
            type="button"
            className={sameDay(date, selectedDate) ? 'on' : ''}
            onClick={() => { setSelectedDate(date); setPickedTime(''); }}
          >
            <small>{dayNames[date.getDay()]}</small>
            {date.getDate()}
          </button>
        ))}
      </div>
      <p style={{ margin: '14px 0 8px', fontWeight: 700 }}>თავისუფალი დრო</p>
      <div className="qtimes">
        {slots.map((time) => (
          <button key={time} type="button" disabled={slotOff(time)} className={pickedTime === time ? 'on' : ''} onClick={() => setPickedTime(time)}>
            {time}
          </button>
        ))}
      </div>
      <button type="button" className="qrow" onClick={() => setTypeOpen(true)}>
        <span className="grow"><b>{selectedService?.name || 'მომსახურება'}</b></span>
        <span>›</span>
      </button>
      {selectedDate && pickedTime && (
        <button type="button" className="qrow">
          <span className="grow"><b>{selectedDate.toLocaleDateString('ka-GE', { day: 'numeric', month: 'long' })} · {pickedTime}</b></span>
        </button>
      )}
      {success && <p>{t('WashAppointment.form.success')}</p>}
      {!success && error && <p>{error}</p>}
      <button type="button" className="ap-btn" style={{ marginTop: 16 }} onClick={handleSubmit} disabled={!isFormValid || isSubmitting}>
        {t('WashAppointment.form.submit.button')}
      </button>

        <SingleCalendarMobileSheet
          open={calendarOpen}
          setOpen={setCalendarOpen}
          applyDate={(date) => {
            setSelectedDate(date);
            setPickedTime('');
          }}
          initialDate={selectedDate}
          title={t('WashAppointment.form.date.pickerTitle')}
          disabled={true}
        />

        <TimePickerMobileSheet
          open={timePickerOpen}
          setOpen={setTimePickerOpen}
          applyTime={(time) => setPickedTime(time)}
          disabled={!selectedDate}
          toDate={selectedDate}
        />

        <TypeWashingDropDown
          open={typeOpen}
          setOpen={setTypeOpen}
          selectedService={selectedService}
          applyType={(service) => setSelectedService(service)}
          availableServices={branch?.services ?? []}
        />
    </div>
  );
}
