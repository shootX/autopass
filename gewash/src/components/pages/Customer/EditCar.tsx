import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation, useParams } from 'react-router-dom';
import { customFetch } from '@/utils/customFetch';
import { invalidateCarsCache } from '@/hooks/useFetchCars';
import leftArrow from '@/assets/icons/left-arrow.svg';
import closeIcon from '@/assets/icons/close-icon.svg';
import { useTranslation } from '@/hooks/useTranslation';
import Header from '@/components/Header';
import { apiErrorMessage, type ApiBody } from '@/lib/apiResult';
import { readCar, formatPlate } from '@/lib/carResult';

export default function EditCar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { carid } = useParams();

  const routeState = location.state as { car?: unknown } | null;
  const [carToEdit, setCarToEdit] = useState(() => readCar(routeState?.car));
  const [brand, setBrandLocal] = useState('');
  const [model, setModelLocal] = useState('');
  const [numberPlate, setNumberPlate] = useState('');
  const [year, setYear] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [deletePopupShow, setDeletePopupShow] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const t = useTranslation();

  const carId = carid ?? carToEdit?.id;

  useEffect(() => {
    if ((!carToEdit || carToEdit.id !== Number(carId) || !carToEdit.model.id) && carId) {
      const token = localStorage.getItem('access_token');
      customFetch(`${import.meta.env.VITE_API_URL}/mycars/${carId}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
        .then(async (res) => {
          if (!res.ok) throw new Error('Failed to fetch car');
          const data = readCar(await res.json());
          if (!data) throw new Error('Invalid car data');
          setCarToEdit(data);
        })
        .catch((err) => {
          console.error(err);
          setError('loadFailed');
        });
    } else if (carToEdit) {
      setBrandLocal(carToEdit.model.brand.name);
      setModelLocal(carToEdit.model.name);
      setNumberPlate(formatPlate(carToEdit.plate));
      setYear(carToEdit.year == null ? '' : String(carToEdit.year));
    }
  }, [carToEdit, carId]);

  const handlePlateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const part1 = value.slice(0, 2);
    const part2 = value.slice(2, 5);
    const part3 = value.slice(5, 7);
    const formatted = part1 + (part2 ? ' - ' + part2 : '') + (part3 ? ' - ' + part3 : '');
    setNumberPlate(formatted);
    setError('');
  };

  const isValidPlate = /^([A-Z]{2}) - ([0-9]{3}) - ([A-Z]{2})$/.test(numberPlate);
  const isFormReady = brand && model && isValidPlate && carToEdit?.id === Number(carId);

  const handleSubmit = async () => {
    if (!isValidPlate) {
      setError(t('EditCar.errors.invalidPlate'));
      return;
    }

    if (saving || !carToEdit) return;
    setSaving(true);
    const token = localStorage.getItem('access_token');

    try {
      const sendPlate = numberPlate.replace(/[^a-zA-Z0-9]/g, '');

      const response = await customFetch(`${import.meta.env.VITE_API_URL}/mycars/${carId}/edit`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          plate: sendPlate,
          year: year ? Number(year) : null,
        }),
      });
      let resData: ApiBody | null = null;
      try {
        resData = (await response.json()) as ApiBody;
      } catch {
        resData = null;
      }

      const failure = apiErrorMessage(response.ok, resData, t('EditCar.errors.saveFailed'));
      if (failure) {
        console.error('Update car error [Status ' + response.status + ']:', resData);
        throw new Error(failure);
      }

      invalidateCarsCache();
      navigate('/customer-my-data');
    } catch (err) {
      console.error(err);
      setError(err instanceof Error ? err.message : t('EditCar.errors.saveFailed'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    setIsClosing(true);
    const token = localStorage.getItem('access_token');

    try {
      const response = await customFetch(`${import.meta.env.VITE_API_URL}/mycars/${carId}/remove`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      let resData: ApiBody | null = null;
      try {
        resData = (await response.json()) as ApiBody;
      } catch {
        resData = null;
      }
      const failure = apiErrorMessage(response.ok, resData, t('EditCar.errors.deleteFailed'));
      if (failure) {
        setError(failure);
        setIsClosing(false);
        return;
      }
      invalidateCarsCache();

      setTimeout(() => {
        setDeletePopupShow(false);
        setIsClosing(false);
        navigate('/customer-my-data');
      }, 300);
    } catch (err) {
      console.error(err);
      setError(t('EditCar.errors.deleteFailed'));
      setIsClosing(false);
    }
  };

  return (
    <div className="edit-car-screen">
      <Header
        variant="bar"
        title={t('EditCar.header.title')}
        titleAs="h3"
        left={<img src={leftArrow} alt="Back" onClick={() => navigate(-1)} />}
        right={<img src={closeIcon} alt={t('EditCar.header.closeAlt')} onClick={() => navigate('/customer-my-data')} />}
      />

      <div className="edit-car-content">
        <p className="edit-car-title-info">{t('EditCar.titleInfo')}</p>

        <div className="input-block">
          <label>{t('EditCar.labels.brand')}</label>
          <div className="input-select disabled">
            <span className="active">{brand || 'Unknown brand'}</span>
            <img src={leftArrow} alt="Locked" />
          </div>
        </div>

        <div className="input-block">
          <label>{t('EditCar.labels.model')}</label>
          <div className="input-select disabled">
            <span className="active">{model || 'Unknown model'}</span>
            <img src={leftArrow} alt="Locked" />
          </div>
        </div>

        <div className="input-block">
          <label htmlFor="edit-car-year">{t('AddCar.labels.year')}</label>
          <input id="edit-car-year" type="number" className="custom-input" min="1950" max={new Date().getFullYear() + 1}
            value={year} onChange={event => setYear(event.target.value)} />
        </div>

        <div className="number-plate-input">
          <label>{t('EditCar.labels.plate')}</label>
          <div className="input-with-prefix">
            <input
              placeholder="XX - XXX - XX"
              className="custom-input"
              value={numberPlate}
              onChange={handlePlateChange}
              maxLength={14}
            />
          </div>
          {error && <span className="plate-error">{error === 'loadFailed' ? t('EditCar.errors.loadFailed') : error}</span>}
        </div>

        <button
          className={`add-car-btn ${isFormReady ? 'active' : ''}`}
          onClick={handleSubmit}
          disabled={!isFormReady || saving}
        >
          {t('EditCar.buttons.save')}
        </button>
        <button className="vehicle-delete-btn" onClick={() => setDeletePopupShow(true)}>
          {t('EditCar.buttons.delete')}
        </button>
      </div>

      {deletePopupShow && (
        <>
          <div className={`delete-vehicle-overlay ${isClosing ? 'fade-out' : ''}`} />
          <div className={`delete-vehicle-popup ${isClosing ? 'fade-out' : ''}`}>
            <h4>{t('EditCar.popup.title')}</h4>
            <p>{t('EditCar.popup.message')}</p>
            <div className="delete-vehicle-popup-btns">
              <button onClick={() => setDeletePopupShow(false)}>
                {t('EditCar.buttons.cancel')}
              </button>
              <button onClick={handleDelete}>{t('EditCar.buttons.confirmDelete')}</button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
