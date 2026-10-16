import React, { useEffect, useRef, useState } from "react";
import { CarBrandDropDown } from "@/components/ui/CarBrandDropDown";
import { CarModelDropDown } from "@/components/ui/CarModelDropDown";
import { useAppDispatch, useAppSelector } from "../../../hooks/hooks";
import { setBrand, setModel, addCar } from "@/store/carSlice";
import { useNavigate, useLocation } from "react-router-dom";
import { customFetch } from "@/utils/customFetch";
import { invalidateCarsCache } from "@/hooks/useFetchCars";
import { useCarBrands } from "@/hooks/useCarBrands";
import type { Brand, Model, Car } from "@/store/carSlice";
import { useCarYears } from "@/hooks/useCarYears";
import { readCar, record } from "@/lib/carResult";
import { useTranslation } from "@/hooks/useTranslation";
import { leftArrowUrl, closeIconUrl } from "@/assets/staticUrls";
import Header from "@/components/Header";
import { readAccessToken } from "@/lib/accessToken";

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
  const [year, setYear] = useState("");
  const [modelsLoading, setModelsLoading] = useState(false);
  const modelRequest = useRef(0);
  const yearOptions = useCarYears(brand, model);
  useEffect(() => { setYear(""); }, [brand?.id, model?.id]);
  const { brands, loading, error: brandsError } = useCarBrands();
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
    setModelDropdownOpen(false);
    setModels([]);
    setError("");
    setModelsLoading(true);
    const sequence = ++modelRequest.current;
    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/cars/brands/${selected.id}/models`, {
        headers: { Authorization: `Bearer ${readAccessToken()}` },
      });
      if (!res.ok) throw new Error("Models unavailable");
      const body = record(await res.json());
      if (sequence !== modelRequest.current) return;
      if (!Array.isArray(body?.models)) throw new Error("Invalid models");
      const options: Model[] = body.models.flatMap(value => {
        const row = record(value);
        return typeof row?.id === "number" && typeof row.name === "string"
          ? [{ id: row.id, name: row.name, source: row.source === "carapi" ? "carapi" : "local" }] : [];
      });
      setModels(options);
      setModelDropdownOpen(true);
    } catch {
      if (sequence === modelRequest.current) setError("modelsUnavailable");
    } finally {
      if (sequence === modelRequest.current) setModelsLoading(false);
    }
  };

  const isValidPlate = /^([A-Z]{2}) - ([0-9]{3}) - ([A-Z]{2})$/.test(
    numberPlate
  );
  const isFormReady = brand && model && year && isValidPlate && !yearOptions.loading;

  const handleSubmit = async () => {
    setIsSubmitting(true);
  
    if (!isValidPlate) {
      setError("Invalid number plate format");
      setIsSubmitting(false);
      return;
    }
  
    if (!brand || !model) {
      setIsSubmitting(false);
      return;
    }

    if (!year || !yearOptions.years.includes(Number(year))) {
      setError("chooseYear");
      setIsSubmitting(false);
      return;
    }
    const token = readAccessToken();
    const payload = {
      source: model.source ?? brand.source ?? "local",
      brand_id: brand.id, model_id: model.id, year: Number(year), plate: normalizedPlate,
    };
    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/mycars/add`, {
        method: "POST",
        headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const body: unknown = await res.json().catch(() => null);
      const data = record(body);
      if (!res.ok || data?.success !== true) {
        setError(data?.error_code === "plate_exists" ? "plateExists" : res.status === 401 ? "sessionExpired" : "saveFailed");
        return;
      }
      const createdCar = readCar(body);
      if (!createdCar) {
        setError("saveFailed");
        return;
      }
      invalidateCarsCache();
      dispatch(addCar(createdCar));
      setAddedCar(createdCar);
      setSubmitted(true);
    } catch {
      setError("saveFailed");
    } finally {
      setIsSubmitting(false);
    }
  };

  const resetForm = () => {
    dispatch(setBrand(null));
    dispatch(setModel(null));
    setNumberPlate("");
    setYear("");
    setSubmitted(false);
    setError("");
    setAddedCar(null);
  };
  const t = useTranslation();

  return (
    <div className='add-car-screen'>
      {shouldShowHeader && (
        <Header
          variant="bar"
          title={t("AddCar.header.title")}
          titleAs="h3"
          left={<img src={leftArrowUrl} alt={t("AddCar.header.backAlt")} onClick={() => navigate(-1)} style={{ cursor: "pointer" }} />}
          right={<img src={closeIconUrl} alt={t("AddCar.header.closeAlt")} onClick={() => navigate("/")} style={{ cursor: "pointer" }} />}
        />
      )}
      <div className='add-car-content'>
        {!shouldShowHeader && <h1>{t("AddCar.title")}</h1>}
        {!submitted ? (
          <>
            <p className='add-car-title-info'>{t("AddCar.subtitle")}</p>
            {brandsError && <span className="plate-error">{t("AddCar.errors.brandsUnavailable")}</span>}
            {error && (
              <span className='plate-error'>
                {error === "Invalid number plate format" ? t("AddCar.errors.invalidPlate") : t(`AddCar.errors.${error}`)}
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
                onClick={() => brand && !modelsLoading && setModelDropdownOpen(true)}
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

            <div className="input-block">
              <label htmlFor="car-year">{t("AddCar.labels.year")}</label>
              <select id="car-year" className="input-select" value={year} disabled={!model || yearOptions.loading}
                onChange={event => { setYear(event.target.value); setError(""); }}>
                <option value="">{t("AddCar.placeholders.chooseYear")}</option>
                {yearOptions.years.map(value => <option key={value} value={value}>{value}</option>)}
              </select>
              {model && !yearOptions.loading && !yearOptions.failed && yearOptions.source === "manual" && <p>{t("AddCar.yearManual")}</p>}
              {yearOptions.failed && <div role="alert">{t("AddCar.errors.yearsUnavailable")}
                <button type="button" onClick={yearOptions.retry}>{t("AddCar.retry")}</button>
              </div>}
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
                color: "#12284b",
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
                style={{ backgroundColor: "#83D69", color: "var(--ap-gold-text)" }}
              >
                {t("AddCar.buttons.addAnother")}
              </button>
              <button
                className='add-car-btn secondary'
                onClick={() => navigate("/customer-my-data")}
                style={{ background: "#83D69", color: "var(--ap-gold-text)" }}
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
