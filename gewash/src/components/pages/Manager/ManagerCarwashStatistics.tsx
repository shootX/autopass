import React, { useState, useEffect } from 'react';
import PercentBar from '../../PercentBar';
import { MobileDropdownSheet } from '../../ui/MobileDropdown';
import { CalendarMobileSheet } from '../../Calendars/CalendarDropDownSheet';
import { useTranslation } from '@/hooks/useTranslation';
import { format } from 'date-fns';
import { customFetch } from '@/utils/customFetch';
import { WorkloadGraph } from '@/components/WorkloadGraph';
import {
  rightArrowUrl,
  whashesUrl,
  branchPackageUrl,
  branchHeaderCarUrl,
  branchSedanUrl,
  branchWagonUrl,
  branchCoupeUrl,
  branchPickupUrl,
  branchWorkloadUrl,
} from '@/assets/staticUrls';
import PageSkeleton from '@/components/Skeletons/PageSkeleton';

export default function CarwashStatistics() {
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [calendarOpen, setCalendarOpen] = useState(false);
  const [selectedPeriod, setSelectedPeriod] = useState('week');
  const [range, setRange] = useState<{ from: Date; to: Date }>();
  const [stats, setStats] = useState<any>(null);
  const [isStatsLoading, setIsStatsLoading] = useState<boolean>(false);

  const t = useTranslation();
  const [branch, setBranch] = useState(null);

  useEffect(() => {
    const token = localStorage.getItem('access_token');

    customFetch(`${import.meta.env.VITE_API_URL}/manager/my-washes`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('Failed to fetch wash info');
        const data = await res.json();

        const wash = data['my_wash'];
        if (wash) setBranch(wash);
      })
      .catch((err) => console.error('Wash fetch error:', err));
  }, []);

  useEffect(() => {
    if (!branch?.id) return;

    const token = localStorage.getItem('access_token');
    setIsStatsLoading(true);

    customFetch(
      `${import.meta.env.VITE_API_URL}/washes/${branch.id}/stats?period=${selectedPeriod.toLowerCase()}`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      },
    )
      .then(async (res) => {
        if (!res.ok) throw new Error('Failed to fetch wash stats');
        const data = await res.json();

        setStats(data);
      })
      .catch((err) => console.error('Stats fetch error:', err))
      .finally(() => setIsStatsLoading(false));
  }, [branch, selectedPeriod]);

  const graphData = stats?.data?.graph ?? stats?.graph ?? null;
  const formatDate = (date: Date) => {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
  };

  const getDateRangeText = (period: string) => {
    const now = new Date();
    const start = new Date(now);
    const end = new Date(now);

    switch (period) {
      case 'today':
        return `${formatDate(now)} - ${formatDate(now)}`;

      case 'yesterday':
        start.setDate(now.getDate() - 1);
        return `${formatDate(start)} - ${formatDate(start)}`;

      case 'week':
      case 'last7Days':
        start.setDate(now.getDate() - 6);
        return `${formatDate(start)} - ${formatDate(end)}`;

      case 'month':
      case 'lastMonth':
        start.setDate(now.getDate() - 29);
        return `${formatDate(start)} - ${formatDate(end)}`;

      default:
        return `${formatDate(now)}`;
    }
  };

  const packageLabels: Record<string, string> = {
    '12': `12 ${t('ManagerCarwashStatistics.packages.washesSuffix')}`,
    '24': `24 ${t('ManagerCarwashStatistics.packages.washesSuffix')}`,
    unlimit: t('ManagerCarwashStatistics.packages.unlimit'),
  };

  const getDaysInPeriod = (period: string): number => {
    switch (period) {
      case 'today':
      case 'yesterday':
        return 1;
      case 'week':
      case 'last7Days':
        return 7;
      case 'month':
      case 'lastMonth':
        return 30;
      default:
        return 1;
    }
  };

  const calculateAvgByPeriod = (data?: Record<string, number> | null, period = 'today') => {
    if (!data) return 0;

    const total = Object.values(data).reduce((sum, count) => sum + Number(count), 0);
    const days = getDaysInPeriod(period);

    return Number((total / days).toFixed(1));
  };

  const avgPerDay = calculateAvgByPeriod(stats?.graph ?? graphData, selectedPeriod);
  const isBranchOpen = (startTime?: string, endTime?: string): boolean => {
    if (!startTime || !endTime) return false;
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const [startHours, startMinutes] = startTime.split(':').map(Number);
    const [endHours, endMinutes] = endTime.split(':').map(Number);
    const startTotal = startHours * 60 + startMinutes;
    let endTotal = endHours * 60 + endMinutes;

    if (endTotal <= startTotal) {
      endTotal += 24 * 60;
      if (currentMinutes < startTotal) {
        return currentMinutes + 24 * 60 >= startTotal && currentMinutes + 24 * 60 <= endTotal;
      }
    }
    return currentMinutes >= startTotal && currentMinutes <= endTotal;
  };

  const isOpen = isBranchOpen(branch?.work_time_start, branch?.work_time_end);

  if (isStatsLoading || !stats) {
    return <PageSkeleton></PageSkeleton>;
  }
  return (
    <div>
      <header style={{ justifyContent: 'center' }}>
        {t('ManagerCarwashStatistics.header.title')}
      </header>
      <div className="branch-block">
        <h4>{t('ManagerCarwashStatistics.branch.title')}</h4>
        <div className="branch-info">
          <div className="branch-info-header">
            <div>
              <p className="bold">{branch?.name}</p>
              <p>{branch?.address}</p>
            </div>
            <button className={isOpen ? 'branch-open-btn open' : 'branch-open-btn closed'}>
              {isOpen
                ? t('ManagerCarwashStatistics.branch.open')
                : t('ManagerCarwashStatistics.branch.closed')}
            </button>
          </div>
          <div className="branch-time">
            <p>
              {t('ManagerCarwashStatistics.branch.time')}{' '}
              <span className="yellow">
                {branch?.work_time_start?.slice(0, 5)} - {branch?.work_time_end?.slice(0, 5)}
              </span>
            </p>
          </div>

          <div className="period">
            <div className="period-dropdown">
              <p style={{ textTransform: 'capitalize' }}>
                {selectedPeriod !== '' ? selectedPeriod : 'Select period'}
              </p>
              <button onClick={() => setDropdownOpen(true)} className="ml-2">
                <img className="right-arrow" src={rightArrowUrl} alt="right-arrow" />
              </button>
            </div>

            <MobileDropdownSheet
              open={dropdownOpen}
              setOpen={setDropdownOpen}
              setPeriod={(label) => {
                setSelectedPeriod(label);
                if (label === 'Custom period') {
                  setDropdownOpen(false);
                  setCalendarOpen(true);
                } else {
                  // обычные периоды
                  setCalendarOpen(false);
                }
              }}
            />
            {/* Календарь */}
            <CalendarMobileSheet
              open={calendarOpen}
              setOpen={setCalendarOpen}
              applyRange={(selected) => {
                setRange(selected);
                setSelectedPeriod('Custom period');
              }}
            />
          </div>
        </div>
      </div>
      <div className="count-whashes">
        <div className="whashes-img">
          <img src={whashesUrl} alt="whashes" />
        </div>
        <div className="whashes-flex">
          <div className="whashes-info">
            <p>{t('ManagerCarwashStatistics.washes.title')}</p>
            <p className="yellow">{t('ManagerCarwashStatistics.washes.period')}</p>
            <p>{getDateRangeText(selectedPeriod)}</p>
          </div>
          <div className="stats">{stats.data.whashes}</div>
        </div>
      </div>
      <div className="packeges">
        <div className="packeges-header">
          <div className="yellow-box">
            <img src={branchPackageUrl} alt="" />
          </div>
          <div>
            <p>{t('ManagerCarwashStatistics.packages.title')}</p>
            <p className="yellow">{t('ManagerCarwashStatistics.packages.distribution')}</p>
          </div>
        </div>
        {(() => {
          const packages = stats?.packages || {};
          const entries = Object.entries(packages);

          const totalPackagesCount = entries.reduce((acc, [_, count]) => acc + Number(count), 0);

          if (entries.length === 0) {
            return <p>No packages data</p>;
          }

          return entries.map(([packageKey, count]) => {
            const numericCount = Number(count);

            const percent =
              totalPackagesCount > 0 ? Math.round((numericCount / totalPackagesCount) * 100) : 0;

            const labelText = packageLabels[packageKey] || `${packageKey} washes`;

            return (
              <div key={packageKey}>
                <PercentBar percent={percent} label={labelText} />
              </div>
            );
          });
        })()}
      </div>

      <div className="car-types-wrapper">
        <div className="packeges-header">
          <div className="yellow-box">
            <img src={branchHeaderCarUrl} alt="" />
          </div>
          <div>
            <p>{t('ManagerCarwashStatistics.carTypes.title')}</p>
            <p className="yellow">{t('ManagerCarwashStatistics.carTypes.distribution')}</p>
          </div>
        </div>

        <div className="car-types-list">
          {(() => {
            const carTypes = stats?.car_types || {};

            const totalCars = Object.values(carTypes).reduce(
              (acc: number, val: any) => acc + Number(val),
              0,
            );

            const defaultTypes = [
              {
                key: 'sedan',
                label: t('ManagerCarwashStatistics.carTypes.sedan'),
                icon: branchSedanUrl,
              },
              {
                key: 'wagon',
                label: t('ManagerCarwashStatistics.carTypes.wagon'),
                icon: branchWagonUrl,
              },
              {
                key: 'coupe',
                label: t('ManagerCarwashStatistics.carTypes.coupe'),
                icon: branchCoupeUrl,
              },
              {
                key: 'pickup',
                label: t('ManagerCarwashStatistics.carTypes.pickup'),
                icon: branchPickupUrl,
              },
            ];

            return defaultTypes.map(({ key, label, icon }) => {
              const rawCount = carTypes[key] || carTypes[label] || carTypes[key.toUpperCase()] || 0;
              const count = Number(rawCount);

              const percent = totalCars > 0 ? Math.round((count / totalCars) * 100) : 0;

              return (
                <div key={key} className="car-types-row">
                  <div className="blue-box">
                    <img src={icon} alt={label} />
                  </div>
                  <div className="bar-container">
                    <PercentBar percent={percent} label={label} />
                  </div>
                </div>
              );
            });
          })()}
        </div>
      </div>

      <div className="workload">
        <div className="packeges-header">
          <div className="yellow-box">
            <img src={branchWorkloadUrl} alt="" />
          </div>
          <div>
            <p>{t('ManagerCarwashStatistics.workload.title')}</p>
            <p>
              <span className="yellow"> {t('ManagerCarwashStatistics.workload.period')}</span>{' '}
              {getDateRangeText(selectedPeriod)}
            </p>
          </div>
        </div>
        <div className="graphics-container">
          <WorkloadGraph graphData={graphData} />
          <div className="avg-whashes">
            <div>
              <p className="yellow">{t('ManagerCarwashStatistics.workload.avgWashes')}</p>
              <p>{t('ManagerCarwashStatistics.workload.average')}</p>
            </div>
            <span>{avgPerDay}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
