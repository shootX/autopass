import React, { useState } from 'react';
import {
  addMonths,
  subMonths,
  format,
  startOfMonth,
  endOfMonth,
  startOfWeek,
  endOfWeek,
  eachDayOfInterval,
  isSameDay,
} from 'date-fns';
import ManagerOrder from '../ManagerOrder';
import DeleteReasonPopup from '../DeleteReasonPopup';
import { useAllRecords } from '@/hooks/useAllRecords';
import { useManagerActions } from '@/hooks/useManagerActions';
import { useTranslation } from '@/hooks/useTranslation';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

type Status = 'Confirm' | 'Rescheduled' | 'Expired' | 'Deleted' | 'New';
const dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

export default function ManagerCalendarGrid() {
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(new Date());
  const { appointments, refetch, loading } = useAllRecords();
  const managerActions = useManagerActions({ appointments, refetch });
  const t = useTranslation();
  if (loading) {
    return <PageSkeleton />;
  }
  const {
    popupVisible,
    setPopupVisible,
    setSelectedOrder,
    deleteReason,
    setDeleteReason,
    handleDelete,
    confirmDelete,
    handleReschedule,
    handleConfirm,
  } = managerActions;

  function mapApprovedToStatus(code: number): Status {
    switch (code) {
      case 0:
        return 'New';
      case 1:
        return 'Confirm';
      case 2:
        return 'Deleted';
      case 3:
        return 'Rescheduled';
      case 4:
        return 'Expired';
      default:
        return 'New';
    }
  }

  const orders = appointments.map((a) => {
    const parsedDate = new Date(`${a.date}T${a.time}`);
    return {
      id: a.id,
      status: mapApprovedToStatus(a.approved),
      date: `${a.date} · ${a.time.slice(0, 5)}`,
      parsedDate,
      type: a.services.map((s) => s.name).join(', '),
      customer: {
        name: `${a.user.name} ${a.user.surname}`,
        phone: a.user.phone,
      },
    };
  });

  const validOrders = orders.filter((order) => order.status !== 'Deleted');

  const ordersByDay = new Map<string, Status[]>();
  validOrders.forEach((order) => {
    const key = order.parsedDate.toDateString();
    ordersByDay.set(key, [...(ordersByDay.get(key) || []), order.status]);
  });

  const selectedOrders = validOrders.filter((order) => isSameDay(order.parsedDate, selectedDate));

  const countByStatus = {
    new: 0,
    rescheduled: 0,
    confirm: 0,
  };

  selectedOrders.forEach((order) => {
    const key = order.status.toLowerCase();
    if (key in countByStatus) countByStatus[key]++;
  });

  const monthStart = startOfMonth(currentMonth);
  const monthEnd = endOfMonth(currentMonth);
  const startDate = startOfWeek(monthStart, { weekStartsOn: 0 });
  const endDate = endOfWeek(monthEnd, { weekStartsOn: 0 });
  const days = eachDayOfInterval({ start: startDate, end: endDate });

  return (
    <div>
      <header style={{ justifyContent: 'center' }}>{t('ManagerCalendarGrid.header')}</header>

      {/* Навигация */}
      <div className="calendar-header">
        <button onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}>&lt;</button>
        <span>{format(currentMonth, 'MMMM yyyy')}</span>
        <button onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}>&gt;</button>
      </div>

      <div className="calendar-wrapper">
        {/* Дни недели */}
        <div className="calendar-daynames">
          {dayNames.map((day) => (
            <div key={day} className="calendar-dayname">
              {day}
            </div>
          ))}
        </div>

        {/* Сетка календаря */}
        <div className="calendar-grid">
          {days.map((day) => {
            const isSelected = isSameDay(day, selectedDate);
            const dayKey = day.toDateString();
            const statusesForDay = ordersByDay.get(dayKey) || [];

            const uniqueStatuses = Array.from(
              new Set(statusesForDay.filter((s) => s !== 'Deleted').map((s) => s.toLowerCase())),
            );

            return (
              <div
                key={day.toISOString()}
                className={`calendar-cell${isSelected ? ' selected' : ''}`}
                onClick={() => setSelectedDate(day)}
              >
                <div>{format(day, 'd')}</div>
                <div className="calendar-dots">
                  {uniqueStatuses.map((status, i) => (
                    <span key={i} className={`dot ${status}`} />
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Боковая панель */}
      <div className="date-events">
        <div className="current-date">
          <p>{format(selectedDate, 'dd MMMM yyyy')}</p>
          <p className="quantity-events">{selectedOrders.length} Events</p>
        </div>

        <div className="calendar-status-list">
          <div className="calendar-status-list new">
            <p>{t('ManagerCalendarGrid.statusLabels.new')}</p>
            <span>{countByStatus.new}</span>
          </div>
          <div className="calendar-status-list rescheduled">
            <p>{t('ManagerCalendarGrid.statusLabels.rescheduled')}</p>
            <span>{countByStatus.rescheduled}</span>
          </div>
          <div className="calendar-status-list confirmed">
            <p>{t('ManagerCalendarGrid.statusLabels.confirmed')}</p>
            <span>{countByStatus.confirm}</span>
          </div>
        </div>

        <div className="calendar-orders-list"></div>
      </div>

      {/* Заказы на выбранную дату */}
      {selectedOrders.map((order, index) => (
        <ManagerOrder
          key={index}
          status={order.status}
          date={order.date}
          type={order.type}
          customer={order.customer}
          onDelete={() => handleDelete(order)}
          onReschedule={() => handleReschedule(order.id, order.date)}
          onConfirmed={() => handleConfirm(order.id)}
        />
      ))}

      <DeleteReasonPopup
        visible={popupVisible}
        reason={deleteReason}
        setReason={setDeleteReason}
        onConfirm={confirmDelete}
        onCancel={() => {
          setDeleteReason('');
          setSelectedOrder(null);
          setPopupVisible(false);
        }}
      />
    </div>
  );
}
