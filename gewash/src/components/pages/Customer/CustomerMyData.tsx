import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { SingleCalendarMobileSheet } from '@/components/Calendars/SingleCalendarDropDownSheet';
import { SexDropDown } from '@/components/ui/SexDropDown';
import CustomerContactInfo from '@/components/CustomerContactInfo';
import MyVehicles from './MyVehicles';
import NotificationSettings from './NotificationSettings';
import { useEditUser } from '@/hooks/useEditUser';
import { useSelector, useDispatch } from 'react-redux';
import type { RootState } from '@/store';
import { useTranslation } from '@/hooks/useTranslation';
import { customFetch } from '@/utils/customFetch';
import { leftArrowUrl, calendarIconUrl, dangerUrl } from '@/assets/staticUrls';
import { refreshCurrentUser } from '@/hooks/useUser';

export default function CustomerMyData() {
  const navigate = useNavigate();
  const user = useSelector((state: RootState) => state.user.data);
  const { editUser } = useEditUser();

  const [firstName, setFirstName] = useState('');
  const [secondName, setSecondName] = useState('');
  const [selectedDate, setSelectedDate] = useState<Date | undefined>();
  const [selectedSex, setSelectedSex] = useState<'male' | 'female' | ''>('');
  const [calendarOpen, setCalendarOpen] = useState(false);
  const [sexOpen, setSexOpen] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [wash, setWash] = useState(false);
  const [subscription, setSubscription] = useState(true);
  const [promo, setPromo] = useState(false);
  const [saved, setSaved] = useState(false);
  const savedTimer = useRef<number | null>(null);
  const dispatch = useDispatch();

  const t = useTranslation();

  const isFormFilled = firstName.trim() && secondName.trim() && selectedDate && selectedSex !== '';

  useEffect(() => {
    const load = async () => {
      await refreshCurrentUser(dispatch);
    };

    load();
  }, []);

  useEffect(() => {
    if (user) {
      setFirstName(user.firstName);
      setSecondName(user.lastName);
      setSelectedDate(user.dateOfBirth ? new Date(user.dateOfBirth) : undefined);
      setSelectedSex(user.sex ?? '');

      setWash(user.enablePushWashAppointment);
      setSubscription(user.enablePushRenewalSubscription);
      setPromo(user.enablePushSpecialPromotions);
    }
  }, [user]);

  const handleWashChange = async (value: boolean) => {
    setWash(value);
    await savePushSettings(value, subscription, promo);
  };

  const handleSubscriptionChange = async (value: boolean) => {
    setSubscription(value);
    await savePushSettings(wash, value, promo);
  };

  const handlePromoChange = async (value: boolean) => {
    setPromo(value);
    await savePushSettings(wash, subscription, value);
  };

  const savePushSettings = async (wash: boolean, subscription: boolean, promo: boolean) => {
    try {
      const token = localStorage.getItem('access_token');

      await customFetch(`${import.meta.env.VITE_API_URL}/push_settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          wash_appointment: wash,
          renewal_subscription: subscription,
          special_promotions: promo,
        }),
      });
    } catch (err) {
      console.error(err);
    }
  };

  const handleSave = async () => {
    const dateOfBirth = selectedDate
      ? `${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}`
      : undefined;

    const success = await editUser({
      ...(firstName.trim() ? { name: firstName.trim() } : {}),
      ...(secondName.trim() ? { surname: secondName.trim() } : {}),
      ...(selectedSex !== '' ? { sex: selectedSex } : {}),
      ...(dateOfBirth ? { date_of_birth: dateOfBirth } : {}),
    });

    if (success) {
      setModalOpen(false);
      await refreshCurrentUser(dispatch);
      setSaved(true);
      if (savedTimer.current) window.clearTimeout(savedTimer.current);
      savedTimer.current = window.setTimeout(() => setSaved(false), 1500);
    }
  };

  return (
    <div className="customer-data-container">
      <header className="header">
        <img src={leftArrowUrl} alt="back" onClick={() => navigate(-1)} />
        <h2>{t('CustomerMyData.header.title')}</h2>
        

        <button
          type="button"
          style={{ width: 'auto', fontSize: saved ? 20 : undefined }}
          className="points-info-promo__btn"
          onClick={handleSave}
        >
          {saved ? t('CustomerMyData.header.saved') : t('ReschudelingOrder.form.save.button')}
        </button>

        {/* <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke={isFormFilled ? '#14482F' : '#A2ABA4'}
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          xmlns="http://www.w3.org/2000/svg"
          style={{ cursor: isFormFilled ? 'pointer' : 'default' }}
          onClick={() => isFormFilled && setModalOpen(true)}
        >
          <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
          <path d="M17 21v-8H7v8" />
          <path d="M7 3v5h8" />
        </svg> */}
      </header>

      <div className="customer-data-wrapper">
        <h1>{t('CustomerMyData.form.title')}</h1>
        <form className="customer-data-form">
          {[
            {
              label: t('CustomerMyData.form.fields.firstName'),
              value: firstName,
              setter: setFirstName,
            },
            {
              label: t('CustomerMyData.form.fields.secondName'),
              value: secondName,
              setter: setSecondName,
            },
          ].map(({ label, value, setter }) => (
            <div className="form-group" key={label}>
              <label>{label}</label>
              <input
                type="text"
                placeholder={t('CustomerMyData.form.placeholders.text')}
                value={value}
                onChange={(e) => setter(e.target.value)}
              />
            </div>
          ))}

          <div className="form-group">
            <label>{t('CustomerMyData.form.fields.dateOfBirth')}</label>
            <div
              className="input-with-icon-customer"
              onClick={() => setCalendarOpen(true)}
              style={{ cursor: 'pointer' }}
            >
              <input
                type="text"
                placeholder={t('CustomerMyData.form.placeholders.date')}
                value={
                  selectedDate
                    ? selectedDate.toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                      })
                    : ''
                }
                readOnly
              />
              <img src={calendarIconUrl} alt="calendar" />
            </div>
          </div>

          <SingleCalendarMobileSheet
            open={calendarOpen}
            setOpen={setCalendarOpen}
            applyDate={setSelectedDate}
            initialDate={selectedDate}
            title={t('CustomerMyData.form.calendarTitle')}
          />

          <div className="form-group">
            <label>{t('CustomerMyData.form.fields.sex')}</label>
            <div
              className="input-with-icon-customer"
              style={{ cursor: 'pointer' }}
              onClick={() => setSexOpen(true)}
            >
              <input
                type="text"
                placeholder="---"
                value={selectedSex ? selectedSex[0].toUpperCase() + selectedSex.slice(1) : ''}
                readOnly
              />
              <img className="dropdown-arrow-sex" src={leftArrowUrl} alt="select" />
            </div>
          </div>

          <SexDropDown
            open={sexOpen}
            setOpen={setSexOpen}
            applySex={(sex) => {
              setSelectedSex(sex);
              setSexOpen(false);
            }}
            currentSex={selectedSex}
          />
        </form>
      </div>

      {modalOpen && (
        <div className="save-data-modal-backdrop">
          <div className="save-data-modal-window">
            <div className="save-data-modal-cap">
              <img src={dangerUrl} alt="danger" />
              <h4>{t('CustomerMyData.modal.title')}</h4>
            </div>
            <p>{t('CustomerMyData.modal.message')}</p>
            <div className="actions">
              <button className="leave" onClick={() => setModalOpen(false)}>
                {t('CustomerMyData.modal.buttons.leave')}
              </button>
              <button className="save" onClick={handleSave}>
                {t('CustomerMyData.modal.buttons.save')}
              </button>
            </div>
          </div>
        </div>
      )}

      <CustomerContactInfo />
      <MyVehicles />
      <NotificationSettings
        wash={wash}
        setWash={handleWashChange}
        subscription={subscription}
        setSubscription={handleSubscriptionChange}
        promo={promo}
        setPromo={handlePromoChange}
      />
    </div>
  );
}
