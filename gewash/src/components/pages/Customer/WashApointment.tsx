import { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { type RootState } from '@/store';
import { useCreateAppointment } from '@/hooks/useCreateAppointment';
import { useLoadAppointmentsFromBackend } from '@/hooks/useLoadAppointmentsFromBackend';
import { useFetchBranches } from '@/hooks/useFetchBranches';
import { useFetchCars } from '@/hooks/useFetchCars';
import { useTranslation } from '@/hooks/useTranslation';

import { SingleCalendarMobileSheet } from '@/components/Calendars/SingleCalendarDropDownSheet';
import { TimePickerMobileSheet } from '@/components/ui/TimePickerMobileSheet';
import { TypeWashingDropDown } from '@/components/ui/TypeWashingDropDown';
import { BranchInfoPanel } from './BranchInfoPanel';
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
  const [calendarOpen, setCalendarOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>();
  const [pickedTime, setPickedTime] = useState('');
  const [timePickerOpen, setTimePickerOpen] = useState(false);
  const [typeOpen, setTypeOpen] = useState(false);
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const { createAppointment, loading, error, success } = useCreateAppointment();
  useLoadAppointmentsFromBackend();
  const branchId = location.state?.selectedBranchId;
  const { branches, loading: branchesLoading } = useFetchBranches();

  const branch = branches.find((b) => b.id === Number(branchId));
  const [carIndex, setCarIndex] = useState(0);
  const selectedCar = cars[carIndex] ?? firstCar;

  const isFormValid = Boolean(selectedDate && pickedTime && selectedService && branch && selectedCar);

  const [weekStart, setWeekStart] = useState(() => {
    const now = new Date();
    const day = (now.getDay() + 6) % 7;
    now.setDate(now.getDate() - day);
    now.setHours(0, 0, 0, 0);
    return now;
  });
  const slots = ['10:00', '11:00', '12:30', '14:30', '15:00', '16:30'];

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

    const formattedDate = selectedDate.toISOString().split('T')[0];

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
  const monthLabel = (selectedDate ?? weekStart).toLocaleDateString('ka-GE', { month: 'long', year: 'numeric' });

  return (
    <div className="qbook">
      <div className="qhead">
        <button type="button" onClick={() => navigate(-1)} aria-label="უკან">←</button>
        <h1>{t('WashAppointment.header.title')}</h1>
        <button type="button" aria-label="მეტი" onClick={() => setCalendarOpen(true)}>•</button>
      </div>
      <div className="qbook-car">
        {selectedCar?.image && <img src={`${import.meta.env.VITE_NO_API_URL}${selectedCar.image}`} alt="" />}
        <div>
          <strong>{[selectedCar?.brand, selectedCar?.model].filter(Boolean).join(' ') || 'ავტომობილი'}</strong>
          <div>{selectedCar?.plate}</div>
        </div>
        {cars.length > 1 && (
          <button type="button" onClick={() => setCarIndex((i) => (i + 1) % cars.length)} aria-label="სხვა მანქანა">›</button>
        )}
      </div>
      <button type="button" className="qrow" onClick={() => navigate('/branches')}>
        <span className="grow"><small>ფილიალი</small><b>{branch?.name || 'აირჩიე ფილიალი'}</b></span>
        <span>›</span>
      </button>
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
      <button type="button" className="pill-cta" style={{ marginTop: 16 }} onClick={handleSubmit} disabled={!isFormValid || isSubmitting}>
        {t('WashAppointment.form.submit.button')}
        <span className="arrow">→</span>
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
