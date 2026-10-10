import React, { useState } from "react";
import { CarBrandDropDown } from "@/components/ui/CarBrandDropDown";
import { CarModelDropDown } from "@/components/ui/CarModelDropDown";
import { useAppDispatch, useAppSelector } from "../../../hooks/hooks";
import { setBrand, setModel, addCar } from "@/store/carSlice";
import { useNavigate } from "react-router-dom";
import { useLocation } from "react-router-dom";
import { customFetch } from "@/utils/customFetch";
import { invalidateCarsCache } from "@/hooks/useFetchCars";
import { useCarBrands } from "@/hooks/useCarBrands";
import type { Brand, Model, Car } from "@/store/carSlice";
import { useTranslation } from "@/hooks/useTranslation";
import { leftArrowUrl, closeIconUrl } from "@/assets/staticUrls";

export default function AddCar({ showHeader }: { showHeader?: boolean }) {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();

  const brand = useAppSelector((state) => state.car.brand);
  const model = useAppSelector((state) => state.car.model);

  const [brandDropdownOpen, setBrandDropdownOpen] = useState(false);
  const [modelDropdownOpen, setModelDropdownOpen] = useState(false);
  const [numberPlate, setNumberPlate] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState("");
  const [addedCar, setAddedCar] = useState<Car | null>(null);
  const location = useLocation();
  const shouldShowHeader = showHeader !== false && location.state?.fromRegistration !== true;
  const [models, setModels] = useState<Model[]>([]);
  const { brands, loading } = useCarBrands();
  const normalizedPlate = numberPlate.replace(/[\s-]/g, "");
  const [isSubmitting, setIsSubmitting] = useState(false);
  

  const handlePlateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, "");
    const part1 = value.slice(0, 2);
    const part2 = value.slice(2, 5);
    const part3 = value.slice(5, 7);
  
    const formatted =
      part1 + (part2 ? " - " + part2 : "") + (part3 ? " - " + part3 : "");
  
    setNumberPlate(formatted);
    setError("");
  };
  

  const handleBrandSelect = async (selected: Brand) => {
    dispatch(setBrand(selected));
    setBrandDropdownOpen(false);

    const token = localStorage.getItem("access_token");
    const res = await customFetch(
      `${import.meta.env.VITE_API_URL}/cars/brands/${selected.id}/models`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      }
    );

    if (res.ok) {
      const data = await res.json();
      setModels(data.models);
      setModelDropdownOpen(true);
    } else {
      console.error("Failed to load models");
    }
  };

  const isValidPlate = /^([A-Z]{2}) - ([0-9]{3}) - ([A-Z]{2})$/.test(
    numberPlate
  );
  const isFormReady = brand && model && isValidPlate;

  const handleSubmit = async () => {
    setIsSubmitting(true);
  
    if (!isValidPlate) {
      setError("Invalid number plate format");
      setIsSubmitting(false); // ⬅️ сброс при валидации
      return;
    }
  
    const newCar: Car = {
      brand: brand!,
      model: model!,
      plate: numberPlate,
    };
  
    const token = localStorage.getItem("access_token");
    const payload = {
      model_id: model!.id,
      plate: normalizedPlate,
    };
  
    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/mycars/add`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });
  
      if (!res.ok) {
        throw new Error("Failed to add car to server");
      }
  
      invalidateCarsCache();
      dispatch(addCar(newCar));
      setAddedCar(newCar);
      setSubmitted(true);
    } catch (err) {
      console.error(err);
      setError("Number plate is already exist");
    } finally {
      setIsSubmitting(false);
    }
  };
  

  const resetForm = () => {
    dispatch(setBrand(null));
    dispatch(setModel(null));
    setNumberPlate("");
    setSubmitted(false);
    setError("");
    setAddedCar(null);
  };
  const t = useTranslation();

  return (
    <div className='add-car-screen'>
      {shouldShowHeader && (
        <header>
          <img src={leftArrowUrl} alt={t("AddCar.header.backAlt")} onClick={() => navigate(-1)} style={{ cursor: "pointer" }} />
          <h3>{t("AddCar.header.title")}</h3>
          <img src={closeIconUrl} alt={t("AddCar.header.closeAlt")} onClick={() => navigate("/")} style={{ cursor: "pointer" }} />
        </header>
      )}
      <div className='add-car-content'>
        <h1>{t("AddCar.title")}</h1>
        {!submitted ? (
          <>
            <p className='add-car-title-info'>{t("AddCar.subtitle")}</p>
            {error && (
              <span className='plate-error'>
                {error === "Invalid number plate format"
                  ? t("AddCar.errors.invalidPlate")
                  : t("AddCar.errors.plateExists")}
              </span>
            )}
            <div className='input-block'>
              <label>{t("AddCar.labels.brand")}</label>
              <div
                className='input-select'
                onClick={() => setBrandDropdownOpen(true)}
              >
                <span className={brand ? "active" : ""}>
                  {brand?.name || t("AddCar.placeholders.chooseBrand")}
                </span>
                <img
                  src={leftArrowUrl}
                  alt={t("AddCar.icons.arrowAlt")}
                />
              </div>
            </div>

            <div className='input-block'>
              <label>{t("AddCar.labels.model")}</label>
              <div
                className={`input-select ${!brand ? "disabled" : ""}`}
                onClick={() => brand && setModelDropdownOpen(true)}
              >
                <span className={model ? "active" : ""}>
                  {model?.name || t("AddCar.placeholders.chooseModel")}
                </span>
                <img
                  src={leftArrowUrl}
                  alt={t("AddCar.icons.arrowAlt")}
                />
              </div>
            </div>

            <div className='number-plate-input'>
              <label>{t("AddCar.labels.plate")}</label>
              <div className='input-with-prefix'>
                <input
                  placeholder={t("AddCar.placeholders.plate")}
                  className='custom-input'
                  value={numberPlate}
                  onChange={handlePlateChange}
                  maxLength={14}
                />
              </div>
            </div>

            <button
              className={`add-car-btn ${isFormReady ? "active" : ""}`}
              onClick={handleSubmit}
              disabled={!isFormReady || isSubmitting}
            >
              {isSubmitting ? (
                <span className='spinner' />
              ) : (
                t("AddCar.buttons.add")
              )}
            </button>
          </>
        ) : (
          <div className='add-car-success'>
            <p
              style={{
                color: "#14482F",
                fontSize: "18px",
                textAlign: "center",
              }}
            >
              <span style={{ fontWeight: "700" }}>
                {t("AddCar.success.message")}
              </span>
            </p>

            <div className='car-preview'>
              <img
                src='images/hatchback_image.png'
                alt={t("AddCar.success.carAlt")}
              />
              <div>
                <h4>{addedCar?.plate}</h4>
                <span className='car-type'>{t("AddCar.success.carType")}</span>
              </div>
            </div>

            <div className='after-submit-btns'>
              <button
                className='add-car-btn'
                onClick={resetForm}
                style={{ backgroundColor: "#83D69", color: "#B5DD3A" }}
              >
                {t("AddCar.buttons.addAnother")}
              </button>
              <button
                className='add-car-btn secondary'
                onClick={() => navigate("/customer-my-data")}
                style={{ background: "#83D69", color: "#B5DD3A" }}
              >
                {t("AddCar.buttons.useApp")}
              </button>
            </div>
          </div>
        )}
      </div>

      <CarBrandDropDown
        open={brandDropdownOpen}
        setOpen={setBrandDropdownOpen}
        applyBrand={handleBrandSelect}
        brands={brands}
        loading={loading}
      />

      <CarModelDropDown
        open={modelDropdownOpen}
        setOpen={setModelDropdownOpen}
        selectedBrand={brand}
        models={models}
        applyModel={(m) => dispatch(setModel(m))}
      />
    </div>
  );
}
