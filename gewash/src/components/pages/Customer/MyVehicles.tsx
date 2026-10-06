import React from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAppSelector } from "@/hooks/hooks";
import { useFetchCars } from "@/hooks/useFetchCars";
import { useTranslation } from "@/hooks/useTranslation";
import { defaultCarPlaceholderUrl, editNoteIconUrl } from "@/assets/staticUrls";
const NO_API_URL = import.meta.env.VITE_NO_API_URL;

export default function MyVehicles() {
  const vehicles = useAppSelector((state) => state.car.cars);
  const navigate = useNavigate();
  const { cars, loading, error } = useFetchCars();
  const t = useTranslation();

  return (
    <div className='vehicles-wrapper'>
      <h1>{t("MyVehicles.title")}</h1>

      {loading && <p>{t("MyVehicles.loading")}</p>}
      {error && <p className='error'>{error}</p>}

      {!loading && cars.length === 0 && <p>{t("MyVehicles.empty")}</p>}

      {cars.map((car, index) => (
        <div key={index}>
          <div className='image-wrapper'>
            <img
              className='vehicles-image'
              src={
                car.image
                  ? `${NO_API_URL}${car.image}`
                  : defaultCarPlaceholderUrl
              }
              alt='vehicle'
            />
          </div>
          <div className='vehicles-flex'>
            <div className='vehicles-info'>
              <h4 className='auto-number'>{car.plate?.replace(/^([A-Z]{2})(\d{3})([A-Z]{2})$/, '$1-$2-$3') ?? ''}</h4>
              <p className='car-type'>{car.type}</p>
            </div>
            <div>
              <button
                onClick={() =>
                  navigate(`/edit-car/${car.id}`, { state: { car } })
                }
                style={{ padding: "0px" }}
              >
                <img
                  src={editNoteIconUrl}
                  alt='edit-icon'
                />
              </button>
            </div>
          </div>
          <hr />
        </div>
      ))}

      <Link to='/add-car'>
        <button>{t("MyVehicles.buttons.add")}</button>
      </Link>
    </div>
  );
}
