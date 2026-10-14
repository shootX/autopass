import { useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import "../../../src/styles/dropdowns/car-model-dropdown.scss";
import { catalogKey } from "@/lib/carCatalog";
import type { Brand, Model } from "@/store/carSlice";
import { searchIconUrl } from "@/assets/staticUrls";
import { dragY } from "@/lib/dragOffset";
import { useTranslation } from "@/hooks/useTranslation";
import { useCarModels } from "@/hooks/useCarModels";

function withName(template: string, name: string): string {
  return template.replaceAll("{name}", name);
}

export function CarModelDropDown({
  open,
  setOpen,
  selectedBrand,
  applyModel,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  selectedBrand: Brand | null;
  applyModel: (model: Model) => void;
}) {
  const t = useTranslation();
  const [search, setSearch] = useState("");
  const [selectedModel, setSelectedModel] = useState<Model | null>(null);
  const { models, loading, error } = useCarModels(selectedBrand);
  const brandKey = selectedBrand ? catalogKey(selectedBrand) : "";

  useEffect(() => {
    setSelectedModel(null);
  }, [brandKey]);

  const filteredModels = useMemo(() => {
    return models.filter(
      (model) =>
        model.source === selectedBrand?.source &&
        model.name.toLowerCase().includes(search.toLowerCase()),
    );
  }, [search, models, selectedBrand?.source]);

  if (!open || !selectedBrand) return null;

  const brandName = selectedBrand.name;

  return (
    <>
      <div className='add-car-overlay' />
      <motion.div
        drag='y'
        dragMomentum={false}
        onDragEnd={(_e, info) => {
          if (dragY(info.offset.y) > 60) setOpen(false);
        }}
        onUpdate={(latest) => {
          if (dragY(latest.y) < 0) latest.y = 0;
        }}
        className='add-car-sheet'
      >
        <div className='add-car-sheet-content'>
          <div className='drag-bar'>
            <div />
          </div>

          <h3>{withName(t("CarModelDropDown.title"), brandName)}</h3>

          <div className='search-wrapper'>
            <input
              type='text'
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t("CarModelDropDown.search")}
              className='brand-search'
            />
            <img src={searchIconUrl} alt='' />
          </div>

          <div className='brand-letter-group'>
            <div className='brand-letter' style={{ whiteSpace: "nowrap" }}>
              {brandName} <hr /> <span className='count-brands'>{filteredModels.length}</span>
            </div>

            {loading ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>{t("CarModelDropDown.loading")}</p>
            ) : error ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>{t("CarModelDropDown.error")}</p>
            ) : filteredModels.length === 0 ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>
                {withName(t("CarModelDropDown.empty"), brandName)}
              </p>
            ) : (
              filteredModels.map((model) => (
                <div
                  key={catalogKey(model)}
                  className={`brand-option ${
                    selectedModel && catalogKey(selectedModel) === catalogKey(model) ? "selected" : ""
                  }`}
                  onClick={() => setSelectedModel(model)}
                >
                  {model.name}
                </div>
              ))
            )}
          </div>

          <button
            onClick={() => {
              if (selectedModel) {
                applyModel(selectedModel);
                setOpen(false);
              }
            }}
            disabled={!selectedModel}
            className={`submit-button ${selectedModel ? "active" : "disabled"}`}
          >
            {t("CarModelDropDown.select")}
          </button>
        </div>
      </motion.div>
    </>
  );
}
