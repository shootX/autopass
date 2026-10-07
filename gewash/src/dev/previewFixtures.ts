import type { User } from "@/store/userSlice";
import ka from "../../ka.json";
import en from "../../en.json";
import ru from "../../ru.json";

/** Preview-only data used when VITE_BYPASS_AUTH is on and the API is unreachable. */
export const previewUser: User = {
  id: 1,
  firstName: "გიორგი",
  lastName: "ბერიძე",
  sex: "male",
  dateOfBirth: "1994-04-12",
  phone: "995599123456",
  email: "giorgi@example.com",
  emailVerified: true,
  role: 0,
  createdAt: "2024-01-01",
  updatedAt: "2026-10-01",
  enablePushWashAppointment: true,
  enablePushRenewalSubscription: true,
  enablePushSpecialPromotions: false,
  points: 240,
  referralsCount: 2,
};

const langs: Record<string, unknown> = { ka, en, ru };

const branches = [
  {
    id: 1,
    name: "autopass ვაკე",
    address: "ი. ჭავჭავაძის გამზ. 64",
    work_start: "09:00:00",
    work_end: "21:00:00",
    phone: "995555112233",
    location: "41.709,44.766",
    manager: { id: 2, name: "ნინო", surname: "ბერიძე", email: "nino@example.com", phone: "995555112233" },
    services: [
      { id: 1, name: "სრული რეცხვა" },
      { id: 2, name: "ექსპრეს რეცხვა" },
    ],
  },
  {
    id: 2,
    name: "autopass ვერე",
    address: "ვერე, თბილისი",
    work_start: "10:00:00",
    work_end: "20:00:00",
    phone: "995555445566",
    location: "41.700,44.780",
    manager: { id: 3, name: "ლაშა", surname: "კაპანაძე", email: "lasha@example.com", phone: "995555445566" },
    services: [{ id: 1, name: "სრული რეცხვა" }],
  },
];

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function match(url: string, path: string) {
  return url.includes(path);
}

export function previewResponse(url: string, method = "GET"): Response | null {
  if (match(url, "/lang/")) {
    const code = url.split("/lang/")[1]?.split("?")[0] || "ka";
    return json(langs[code] ?? langs.ka);
  }
  if (match(url, "/me")) {
    return json({
      success: true,
      user: {
        id: previewUser.id,
        name: previewUser.firstName,
        surname: previewUser.lastName,
        sex: previewUser.sex,
        date_of_birth: previewUser.dateOfBirth,
        phone: previewUser.phone,
        email: previewUser.email,
        email_verified_at: "2024-01-01",
        role: 0,
        created_at: previewUser.createdAt,
        updated_at: previewUser.updatedAt,
        enable_push_wash_appointment: true,
        enable_push_renewal_subscription: true,
        enable_push_special_promotions: false,
        points: previewUser.points,
        referrals_count: previewUser.referralsCount,
      },
    });
  }
  if (match(url, "/mycars")) {
    return json({
      cars: [
        { id: 1, plate: "KL-482-TB", image: null, model: { name: "Prius", type: "sedan", brand: { name: "Toyota" } } },
        { id: 2, plate: "AB-190-QQ", image: null, model: { name: "Camry", type: "sedan", brand: { name: "Toyota" } } },
      ],
    });
  }
  if (match(url, "/packages/my")) {
    return json({
      success: true,
      packages: [
        {
          id: 9,
          package: { id: 1, car_type: "sedan", count_washes: 10, created_at: null, updated_at: null },
          car: { id: 1, user_id: 1, model_id: 1, plate: "KL-482-TB", created_at: "", updated_at: "" },
          start_date: "2026-09-20",
          end_date: "2026-10-25",
          number_of_washes: 10,
          used_washes: 3,
          renewal: true,
        },
      ],
    });
  }
  if (match(url, "/packages/") && match(url, "/qr")) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#fff"/><rect x="8" y="8" width="28" height="28" rx="4" fill="#0E1712"/><rect x="64" y="8" width="28" height="28" rx="4" fill="#0E1712"/><rect x="8" y="64" width="28" height="28" rx="4" fill="#0E1712"/><rect x="40" y="40" width="12" height="12" fill="#14482F"/><rect x="56" y="56" width="8" height="8" fill="#B5DD3A"/></svg>`;
    return new Response(svg, { status: 200, headers: { "Content-Type": "image/svg+xml" } });
  }
  if (match(url, "/myappointments")) {
    return json({
      appointments: [
        { id: 11, car_wash_id: 1, car_id: 1, date: "2026-10-13", time: "11:00:00", approved: 1, services: [{ name: "სრული რეცხვა" }], washing: { name: "autopass ვაკე" } },
        { id: 8, car_wash_id: 1, car_id: 1, date: "2026-09-29", time: "10:00:00", approved: 1, services: [{ name: "სრული რეცხვა" }], washing: { name: "autopass ვაკე" } },
        { id: 7, car_wash_id: 2, car_id: 1, date: "2026-09-21", time: "15:00:00", approved: 1, services: [{ name: "ექსპრეს რეცხვა" }], washing: { name: "autopass ვერე" } },
      ],
    });
  }
  if (match(url, "/branches")) return json({ success: true, branches });
  if (match(url, "/promo")) return json({ success: false });
  if (method === "GET" && (match(url, "/shop") || match(url, "/vouchers") || match(url, "/tickets") || match(url, "/contacts") || match(url, "/faq"))) {
    return json({ success: true, vouchers: [], tickets: [], contacts: [], faqs: [], data: [] });
  }
  return null;
}

export function installPreviewFetch() {
  const real = window.fetch.bind(window);
  window.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = typeof input === "string" ? input : input instanceof URL ? input.toString() : input.url;
    const method = init?.method || (typeof input === "object" && "method" in input ? input.method : "GET") || "GET";
    try {
      const res = await real(input, init);
      if (res.ok) return res;
    } catch {
      /* API down — fall through to preview fixtures */
    }
    const mocked = previewResponse(url, method);
    if (mocked) return mocked;
    return json({ success: false }, 404);
  };
}
