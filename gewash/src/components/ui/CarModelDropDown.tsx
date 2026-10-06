import { useState, useMemo } from "react";
import { motion } from "framer-motion";
import "../../../src/styles/dropdowns/car-model-dropdown.scss";
import type { Brand, Model} from "@/store/carSlice";
import { searchIconUrl } from "@/assets/staticUrls";

export function CarModelDropDown({
  open,
  setOpen,
  selectedBrand,
  models,
  applyModel,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  selectedBrand: Brand | null;
  models: Model[]; // ✅ добавлено
  applyModel: (model: Model) => void;
}) {
  const [search, setSearch] = useState("");
  const [selectedModel, setSelectedModel] = useState<Model | null>(null);

  const filteredModels = useMemo(() => {
    return models.filter((m) =>
      m.name.toLowerCase().includes(search.toLowerCase())
    );
  }, [search, models]);

  if (!open || !selectedBrand) return null;

  return (
    <>
      <div className='add-car-overlay' />
      <motion.div
        drag='y'
        dragMomentum={false}
        onDragEnd={(e, info) => {
          if (info.offset.y > 60) setOpen(false);
        }}
        onUpdate={(latest) => {
          if (latest.y < 0) latest.y = 0;
        }}
        className='add-car-sheet'
      >
        <div className='add-car-sheet-content'>
          <div className='drag-bar'>
            <div />
          </div>

          <h3>Choose model of {selectedBrand.name}</h3>

          <div className='search-wrapper'>
            <input
              type='text'
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder='Search...'
              className='brand-search'
            />
            <img src={searchIconUrl} alt='' />
          </div>

          <div className='brand-letter-group'>
            <div className='brand-letter' style={{ whiteSpace: "nowrap" }}>
              {selectedBrand.name} <hr />{" "}
              <span className='count-brands'>{filteredModels.length}</span>
            </div>

            {filteredModels.length === 0 ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>
                No models available for {selectedBrand.name}
              </p>
            ) : (
              filteredModels.map((model) => (
                <div
                  key={model.id}
                  className={`brand-option ${
                    selectedModel?.name === model.name ? "selected" : ""
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
            Select
          </button>
        </div>
      </motion.div>
    </>
  );
}
