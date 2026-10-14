import { useState, useMemo } from "react";
import { motion } from "framer-motion";
import "../../../src/styles/dropdowns/car-brands-dropdown.scss";
import { catalogKey } from "@/lib/carCatalog";
import type { Brand } from "@/store/carSlice";
import { searchIconUrl } from "@/assets/staticUrls";
import { dragY } from "@/lib/dragOffset";
import { useTranslation } from "@/hooks/useTranslation";

export function CarBrandDropDown({
  open,
  setOpen,
  applyBrand,
  brands,
  loading,
  error,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  applyBrand: (brand: Brand) => void;
  brands: Brand[];
  loading: boolean;
  error: boolean;
}) {
  const t = useTranslation();
  const [search, setSearch] = useState("");
  const [selectedBrand, setSelectedBrand] = useState<Brand | null>(null);

  const groupedBrands = useMemo(() => {
    const filtered = brands.filter((b) => b.name.toLowerCase().includes(search.toLowerCase()));
    const groups: Record<string, Brand[]> = {};
    filtered.forEach((brand) => {
      const letter = brand.name[0]?.toUpperCase() ?? "";
      if (!groups[letter]) groups[letter] = [];
      groups[letter].push(brand);
    });
    return groups;
  }, [search, brands]);

  if (!open) return null;

  const letters = Object.entries(groupedBrands);

  return (
    <>
      <div className='add-car-overlay' />
      <motion.div
        drag='y'
        dragMomentum={false}
        onDragEnd={(_e, info) => {
          console.log(dragY(info.offset.y), dragY(info.velocity.y));
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

          <h3>{t("CarBrandDropDown.title")}</h3>

          <div className='search-wrapper'>
            <input
              type='text'
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t("CarBrandDropDown.search")}
              className='brand-search'
            />
            <img src={searchIconUrl} alt='' />
          </div>
          <div className='brand-letter-group'>
            {loading ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>{t("CarBrandDropDown.loading")}</p>
            ) : error ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>{t("CarBrandDropDown.error")}</p>
            ) : letters.length === 0 ? (
              <p style={{ color: "#999", paddingTop: "12px" }}>{t("CarBrandDropDown.empty")}</p>
            ) : (
              letters.map(([letter, group]) => (
                <div key={letter} className='brand-group'>
                  <div className='brand-letter'>
                    {letter} <hr />
                    <span className='count-brands'>{group.length}</span>
                  </div>
                  {group.map((brand) => (
                    <div
                      key={catalogKey(brand)}
                      className={`brand-option ${
                        selectedBrand && catalogKey(selectedBrand) === catalogKey(brand) ? "selected" : ""
                      }`}
                      onClick={() => setSelectedBrand(brand)}
                    >
                      {brand.name}
                    </div>
                  ))}
                </div>
              ))
            )}
          </div>
          <button
            onClick={() => {
              if (selectedBrand) {
                applyBrand(selectedBrand);
                setOpen(false);
              }
            }}
            disabled={!selectedBrand}
            className={`submit-button ${selectedBrand ? "active" : "disabled"}`}
          >
            {t("CarBrandDropDown.select")}
          </button>
        </div>
      </motion.div>
    </>
  );
}
