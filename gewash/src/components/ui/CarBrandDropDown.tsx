import { useState, useMemo } from "react";
import { motion } from "framer-motion";
import "../../../src/styles/dropdowns/car-brands-dropdown.scss";
import type { Brand } from "@/store/carSlice";
import { searchIconUrl } from "@/assets/staticUrls";


export function CarBrandDropDown({
  open,
  setOpen,
  applyBrand,
  brands,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  applyBrand: (brand: Brand) => void;
  brands: Brand[];
  loading: boolean;
}) {
  const [search, setSearch] = useState("");
  const [selectedBrand, setSelectedBrand] = useState<Brand | null>(null);

    const groupedBrands = useMemo(() => {
  const filtered = brands.filter((b) =>
    b.name.toLowerCase().includes(search.toLowerCase())
  );
  const groups: Record<string, Brand[]> = {};
  filtered.forEach((brand) => {
    const letter = brand.name[0].toUpperCase();
    if (!groups[letter]) groups[letter] = [];
    groups[letter].push(brand);
  });
  return groups;
}, [search, brands]);

  if (!open) return null;

  return (
    <>
      <div className='add-car-overlay' />
      <motion.div
        drag='y'
        dragMomentum={false}
onDragEnd={(e, info) => {
    console.log(info.offset.y, info.velocity.y);

    if (info.offset.y > 60) {
        setOpen(false);
    }
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

          <h3>Choose car brand</h3>

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
  {Object.entries(groupedBrands).map(([letter, brands]) => (
    <div key={letter} className='brand-group'>
      <div className='brand-letter'>
        {letter} <hr />
        <span className='count-brands'>{brands.length}</span>
      </div>
      {brands.map((brand) => (
        <div
          key={brand.id}
          className={`brand-option ${
            selectedBrand?.id === brand.id ? "selected" : ""
          }`}
          onClick={() => setSelectedBrand(brand)}
        >
          {brand.name}
        </div>
      ))}
    </div>
  ))}
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
  Select
</button>
        </div>
      </motion.div>
    </>
  );
}
