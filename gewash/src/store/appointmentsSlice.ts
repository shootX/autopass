import { createSlice, type PayloadAction } from "@reduxjs/toolkit";

export type Appointment = {
  id: number;
  branchId: number;
  branchName: string;
  branchAddress: string;
  date: string;
  time: string;
  type: string;
  carId: number;
};

type AppointmentsState = {
  appointments: Appointment[];
};

const initialState: AppointmentsState = {
  appointments: [],
};

export const appointmentsSlice = createSlice({
  name: "appointments",
  initialState,
  reducers: {
    addAppointment: (state, action: PayloadAction<Appointment>) => {
      state.appointments.push(action.payload);
    },

    removeAppointment: (
      state,
      action: PayloadAction<{ date: string; time: string }>
    ) => {
      state.appointments = state.appointments.filter(
        (a) => !(a.date === action.payload.date && a.time === action.payload.time)
      );
    },

    clearAppointments: (state) => {
      state.appointments = [];
    },

    setAppointments: (state, action: PayloadAction<Appointment[]>) => {
      state.appointments = action.payload;
    },
  },
});

export const {
  addAppointment,
  removeAppointment,
  clearAppointments,
  setAppointments,
} = appointmentsSlice.actions;

export default appointmentsSlice.reducer;
