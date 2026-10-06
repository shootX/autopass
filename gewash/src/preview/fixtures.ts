import type { User } from "@/store/userSlice";
import type { Branch } from "@/hooks/useFetchBranches";
import type { Package } from "@/hooks/useActivePackages";
import type { ShopBundle } from "@/lib/shopApi";

/** Local screenshots only. Active when VITE_BYPASS_AUTH=true. */
export const previewMode = import.meta.env.VITE_BYPASS_AUTH === "true";

export const previewUser: User = {
  id: 1,
  firstName: "შოთა",
  lastName: "გელაშვილი",
  sex: "male",
  dateOfBirth: "1992-04-12",
  phone: "995555112233",
  email: "shota@geocar.ge",
  emailVerified: true,
  role: 0,
  createdAt: "2024-01-01T00:00:00Z",
  updatedAt: "2026-10-01T00:00:00Z",
  enablePushWashAppointment: true,
  enablePushRenewalSubscription: true,
  enablePushSpecialPromotions: false,
  points: 1280,
  referralsCount: 3,
};

export const previewCars = [
  {
    id: 1,
    plate: "AA-001-BB",
    type: "sedan",
    model: "Camry",
    brand: "Toyota",
    image: null,
  },
  {
    id: 2,
    plate: "GG-220-TT",
    type: "suv",
    model: "RAV4",
    brand: "Toyota",
    image: null,
  },
];

export const previewPackages: Package[] = [
  {
    id: 11,
    package: {
      id: 3,
      car_type: "sedan",
      count_washes: 8,
      created_at: null,
      updated_at: null,
    },
    car: {
      id: 1,
      user_id: 1,
      model_id: 1,
      plate: "AA-001-BB",
      created_at: "2026-09-01",
      updated_at: "2026-09-01",
    },
    start_date: "2026-09-01",
    end_date: "2026-12-01",
    number_of_washes: 8,
    used_washes: 3,
    renewal: true,
  },
];

const manager = {
  id: 2,
  name: "ნინო",
  surname: "ბერიძე",
  email: "nino@geocar.ge",
  phone: "995555000111",
};

export const previewBranches: Branch[] = [
  {
    id: 1,
    name: "Geocar ვაკე",
    address: "ჭავჭავაძის გამზ. 37, თბილისი",
    lat: 41.7095,
    lng: 44.7518,
    phone: "995322000111",
    manager,
    services: [
      { id: 1, name: "Eco wash", pivot: { car_wash_id: 1 } },
      { id: 2, name: "Premium", pivot: { car_wash_id: 1 } },
    ],
    isOpen: true,
  } as Branch,
  {
    id: 2,
    name: "Geocar საბურთალო",
    address: "წერეთლის 117, თბილისი",
    lat: 41.7252,
    lng: 44.751,
    phone: "995322000222",
    manager,
    services: [{ id: 1, name: "Eco wash", pivot: { car_wash_id: 2 } }],
    isOpen: true,
  } as Branch,
  {
    id: 3,
    name: "Geocar ისანი",
    address: "კახეთის გზატკეცილი 20, თბილისი",
    lat: 41.698,
    lng: 44.832,
    phone: "995322000333",
    manager,
    services: [{ id: 3, name: "Interior", pivot: { car_wash_id: 3 } }],
    isOpen: false,
  } as Branch,
];

export const previewShop: ShopBundle = {
  vouchers: [
    { id: 1, img: "", title: "Premium detailing −20%", category: "Wash", discount: "20%", coins: 400, description: "სრული დეტეილინგი ფასდაკლებით." },
    { id: 2, img: "", title: "Interior clean", category: "Care", discount: "15%", coins: 250, description: "სალონის წმენდა." },
  ],
  tickets: [
    { id: 1, img: "", title: "Gold membership", daysLeft: 18, ticketCost: 1, coinPrice: 150 },
    { id: 2, img: "", title: "Weekend wash", daysLeft: 6, ticketCost: 1, coinPrice: 80 },
  ],
  myVouchers: [{ id: 9, voucherId: 1, title: "Premium detailing −20%", img: "", code: "GE22779A", discount: "20%" }],
  myTickets: [{ id: 4, ticketId: 1, title: "Gold membership", img: "", qty: 2 }],
};

export const previewContacts = {
  address: "ჭავჭავაძის გამზ. 37, თბილისი",
  email: "hello@geocar.ge",
  phone: "+995 32 2 00 01 11",
  website: "https://geocar.ge",
  working_hours: "09:00 – 21:00",
};

export const previewAppointments = [
  {
    id: 41,
    branchId: 1,
    branchName: "Geocar ვაკე",
    branchAddress: "ჭავჭავაძის გამზ. 37, თბილისი",
    date: "2026-10-08",
    time: "14:30",
    type: "Eco wash",
    approved: 1,
    carId: 1,
  },
];
