import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation, useParams } from 'react-router-dom';
import { customFetch } from '@/utils/customFetch';
import { invalidateCarsCache } from '@/hooks/useFetchCars';
import leftArrow from '@/assets/icons/left-arrow.svg';
import closeIcon from '@/assets/icons/close-icon.svg';
import { useTranslation } from '@/hooks/useTranslation';

export default function EditCar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { carid } = useParams();

  const [carToEdit, setCarToEdit] = useState(location.state?.car || null);
  const [brand, setBrandLocal] = useState('');
  const [model, setModelLocal] = useState('');
  const [numberPlate, setNumberPlate] = useState('');
  const [error, setError] = useState('');
  const [deletePopupShow, setDeletePopupShow] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const t = useTranslation();

  const carId = carToEdit?.id || carid;

  useEffect(() => {
    if (!carToEdit && carId) {
      const token = localStorage.getItem('access_token');
      customFetch(`${import.meta.env.VITE_API_URL}/mycars/${carId}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
        .then(async (res) => {
          if (!res.ok) throw new Error('Failed to fetch car');
          const data = await res.json();
          setCarToEdit(data);
          setBrandLocal(data.brand || '');
          setModelLocal(data.model || '');
          setNumberPlate(data.plate || '');
        })
        .catch((err) => {
          console.error(err);
          setError('Failed to load car data');
        });
    } else if (carToEdit) {
      setBrandLocal(carToEdit.brand || '');
      setModelLocal(carToEdit.model || '');
      setNumberPlate(carToEdit.plate || '');
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
  const isFormReady = brand && model && isValidPlate;

  const handleSubmit = async () => {
    if (!isValidPlate) {
      setError('Invalid number plate format');
      return;
    }

    const token = localStorage.getItem('access_token');

    try {
      let sendPlate = numberPlate.replace(/[^a-zA-Z0-9]/g, '');

      console.log(brand, model, sendPlate);
      const response = await customFetch(`${import.meta.env.VITE_API_URL}/mycars/${carId}/edit`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          // brand,
          // model,
          plate: sendPlate,
        }),
      });
      let resData: any = null;
      try {
        resData = await response.json();
      } catch (e) {
        resData = await response.text();
      }

      if (!response.ok) {
        console.error('Update car error [Status ' + response.status + ']:', resData);

        const errorMessage =
          resData?.message ||
          (resData?.errors ? JSON.stringify(resData.errors) : 'Failed to update car');

        throw new Error(errorMessage);
      }

      invalidateCarsCache();
      navigate('/customer-my-data');
    } catch (err) {
      console.error(err);
      setError('Failed to save changes');
    }
  };

  const handleDelete = async () => {
    setIsClosing(true);
    const token = localStorage.getItem('access_token');

    try {
      await customFetch(`${import.meta.env.VITE_API_URL}/mycars/${carId}/remove`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      invalidateCarsCache();

      setTimeout(() => {
        setDeletePopupShow(false);
        setIsClosing(false);
        navigate('/customer-my-data');
      }, 300);
    } catch (err) {
      console.error(err);
      setError('Failed to delete car');
      setIsClosing(false);
    }
  };

  return (
    <div className="edit-car-screen">
      <header>
        <img src={leftArrow} alt="Back" onClick={() => navigate(-1)} />
        <h3>{t('EditCar.header.title')}</h3>
        <img src={closeIcon} alt="Close" />
      </header>

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
          {error && <span className="plate-error">{error}</span>}
        </div>

        <button
          className={`add-car-btn ${isFormReady ? 'active' : ''}`}
          onClick={handleSubmit}
          disabled={!isFormReady}
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
