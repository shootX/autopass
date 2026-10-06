import { createSlice, type PayloadAction } from '@reduxjs/toolkit'

type Status = 'Confirm' | 'Rescheduled' | 'Expired' | 'Deleted' | 'New'
type Order = {
  id: number
  status: Status
  date: string
  type?: string
  customer?: {
    name?: string
    phone?: string
  }
}



const initialState: Order[] = [
  {
    id: 1,
    status: 'Expired',
    date: '31 July 2025 · 18:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },
  {
    id: 2,
    status: 'New',
    date: '31 July 2025 · 17:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },
  {
    id: 3,
    status: 'New',
    date: '31 July 2025 · 12:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },
  {
    id: 4,
    status: 'Deleted',
    date: '31 July 2025 · 17:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },
  {
    id: 5,
    status: 'Expired',
    date: '31 July 2025 · 17:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },
  {
    id: 6,
    status: 'Confirm',
    date: '31 July 2025 · 17:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },
  {
    id: 7,
    status: 'Rescheduled',
    date: '31 July 2025 · 17:00',
    type: 'Standard',
    customer: {
      name: 'John Doe',
      phone: '+1234567890',
    },
  },

]

const ordersSlice = createSlice({
  name: 'orders',
  initialState,
  reducers: {
    deleteOrder(state, action: PayloadAction<number>) {
      const index = state.findIndex(order => order.id === action.payload)
      if (index !== -1) {
        state[index].status = 'Deleted'
      }
    },
    updateOrder(state, action: PayloadAction<{ id: number; date: string; status: Status }>) {
        const index = state.findIndex(order => order.id === action.payload.id)
        if (index !== -1) {
          state[index].date = action.payload.date
          state[index].status = action.payload.status
        }
      },
  },
})

export const { deleteOrder, updateOrder } = ordersSlice.actions
export default ordersSlice.reducer
