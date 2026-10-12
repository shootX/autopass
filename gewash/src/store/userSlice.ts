// src/store/userSlice.ts
import { createSlice, type PayloadAction } from "@reduxjs/toolkit";

export type UserRole = "manager" | "customer";

/** anonymous: no token. pending: GET /me in flight. ready: role is known. */
export type SessionPhase = "anonymous" | "pending" | "ready" | "unauthorized" | "error";

export interface User {
  id: number;
  firstName: string;
  lastName: string;
  sex: "male" | "female" | null;
  dateOfBirth: string | null;
  phone: string;
  email: string;
  emailVerified: boolean;
  role: number;
  createdAt: string;
  updatedAt: string;
  enablePushWashAppointment: boolean;
  enablePushRenewalSubscription: boolean;
  enablePushSpecialPromotions: boolean;
  points: number;
  referralsCount: number;
};

interface UserState {
  role: UserRole | null;
  data: User | null;
  session: SessionPhase;
}

function readInitialSession(): SessionPhase {
  try {
    if (typeof localStorage !== "undefined" && localStorage.getItem("access_token")) {
      return "pending";
    }
  } catch {
    /* private mode */
  }
  return "anonymous";
}

const initialState: UserState = {
  role: null,
  data: null,
  session: readInitialSession(),
};

export const userSlice = createSlice({
  name: "user",
  initialState,
  reducers: {
    setRole: (state, action: PayloadAction<UserRole>) => {
      state.role = action.payload;
    },
    setUser: (state, action: PayloadAction<User>) => {
      state.data = action.payload;
    },
    setSession: (state, action: PayloadAction<SessionPhase>) => {
      state.session = action.payload;
    },
    resetSession: (state) => {
      state.role = null;
      state.data = null;
      state.session = "anonymous";
    },
    rejectSession: (state) => {
      state.role = null;
      state.data = null;
      state.session = "unauthorized";
    },
  },
});

export const { setRole, setUser, setSession, resetSession, rejectSession } = userSlice.actions;
export default userSlice.reducer;
