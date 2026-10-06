import { createSlice,  type PayloadAction } from "@reduxjs/toolkit";

export type Brand = {
  id: number;
  name: string;
};

export type Model = {
  id: number;
  name: string;
};
 
 export type Car = {
  id: number;
  plate: string;
  model: {
    name: string;
    type: string;
    brand: {
      name: string;
    };
  };
};


export type CarPreview = {
  id: number;
  plate: string;
};



const initialState: {
  brand: Brand | null;
  model: Model | null;
  cars: Car[];
} = {
  brand: null,
  model: null,
  cars: [],
};

export const carSlice = createSlice({
  name: "car",
  initialState,
  reducers: {
    setBrand: (state, action: PayloadAction<Brand | null>) => {
      state.brand = action.payload;
      state.model = null;
    },
    setModel: (state, action: PayloadAction<Model | null>) => {
      state.model = action.payload;
    },
    addCar: (state, action: PayloadAction<Car>) => {
      state.cars.push(action.payload);
    },    
    updateCar: (
      state,
      action: PayloadAction<{
        id: number;
        brand: Brand;
        model: Model;
        plate: string;
      }>
    ) => {
      const { id, brand, model, plate } = action.payload;
      const index = state.cars.findIndex((c) => c.id === id);
      if (index !== -1) {
        state.cars[index] = { id, brand, model, plate };
      }
    },    
    removeCar: (state, action: PayloadAction<number>) => {
      state.cars = state.cars.filter((car) => car.id !== action.payload);
    },    
    setCars: (state, action: PayloadAction<Car[]>) => {
      state.cars = action.payload;
    },
  }  
});

export const { setBrand, setModel, addCar, updateCar, removeCar, setCars} = carSlice.actions;
export default carSlice.reducer;

