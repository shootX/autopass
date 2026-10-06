// store/slices/plateSlice.ts
import { createSlice, type PayloadAction } from "@reduxjs/toolkit";

interface PlateState {
  scanned: string | null;
}

const initialState: PlateState = {
  scanned: null,
};

export const plateSlice = createSlice({
  name: "plate",
  initialState,
  reducers: {
    setScannedPlate(state, action: PayloadAction<string>) {
      state.scanned = action.payload;
    },
  },
});

export const { setScannedPlate } = plateSlice.actions;
export default plateSlice.reducer;
