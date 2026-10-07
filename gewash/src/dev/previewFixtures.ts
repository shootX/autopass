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
  points: 1250,
  referralsCount: 4,
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
  {
    id: 3,
    name: "autopass საბურთალო",
    address: "ვაჟა-ფშაველას გამზ. 71",
    work_start: "00:00:00",
    work_end: "00:00:00",
    phone: "995555778899",
    location: "41.723,44.752",
    manager: { id: 4, name: "ანა", surname: "გელაშვილი", email: "ana@example.com", phone: "995555778899" },
    services: [
      { id: 1, name: "სრული რეცხვა" },
      { id: 3, name: "სალონის წმენდა" },
    ],
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
  if (match(url, "/me/get-referral-link")) {
    return json({ success: true, referral_link: "https://app.geocar.ge/ref/preview" });
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
        { id: 1, plate: "KL-482-TB", image: null, model: { name: "XC90", type: "suv", brand: { name: "Volvo" } } },
        { id: 2, plate: "TB-117-AA", image: null, model: { name: "CLA", type: "sedan", brand: { name: "Mercedes" } } },
      ],
    });
  }
  if (match(url, "/packages") && !match(url, "/packages/my") && !match(url, "/qr") && !match(url, "/buy")) {
    const carId = Number(new URL(url, "http://preview.local").searchParams.get("carid") || "1");
    const type = carId === 2 ? "sedan" : "suv";
    const catalog = [
      {
        id: 1,
        car_type: "suv",
        washes: 4,
        prices: [
          { id: 11, package_id: 1, month: 1, price: 90 },
          { id: 12, package_id: 1, month: 3, price: 240 },
        ],
      },
      {
        id: 2,
        car_type: "suv",
        washes: 8,
        prices: [
          { id: 21, package_id: 2, month: 1, price: 140 },
          { id: 22, package_id: 2, month: 3, price: 360 },
          { id: 23, package_id: 2, month: 6, price: 640 },
        ],
      },
      {
        id: 3,
        car_type: "sedan",
        washes: 4,
        prices: [
          { id: 31, package_id: 3, month: 1, price: 70 },
          { id: 32, package_id: 3, month: 3, price: 180 },
        ],
      },
      {
        id: 4,
        car_type: "sedan",
        washes: 8,
        prices: [
          { id: 41, package_id: 4, month: 1, price: 110 },
          { id: 42, package_id: 4, month: 3, price: 280 },
          { id: 43, package_id: 4, month: 6, price: 500 },
        ],
      },
    ];
    return json({ success: true, packages: catalog.filter((pkg) => pkg.car_type === type) });
  }
  if (match(url, "/packages/my")) {
    return json({
      success: true,
      packages: [
        {
          id: 9,
          package: { id: 2, car_type: "suv", count_washes: 8, created_at: null, updated_at: null },
          car: { id: 1, user_id: 1, model_id: 1, plate: "KL-482-TB", created_at: "", updated_at: "" },
          start_date: "2026-09-26",
          end_date: "2026-12-26",
          number_of_washes: 8,
          used_washes: 3,
          renewal: true,
        },
      ],
    });
  }
  if (match(url, "/packages/") && match(url, "/qr")) {
    const png = Uint8Array.from(atob("iVBORw0KGgoAAAANSUhEUgAAAQgAAAEICAIAAAAslP2oAAAFCUlEQVR4nO3dMW4kNxRAQctwvoET3/90Thz4BLOJEwOvAwL8YFNbFY96Rq15INAUya/P5/Mb8H+/n/4A8EbCgCAMCMKAIAwIwoAgDAjCgCAMCMKAIAwIwoAgDAjCgCAMCH+s/sCPv/6c+Bzb/fv3P6PXX70Pq59n133e9b5P1/mu3wcjBgRhQBAGBGFAEAYEYUAQBoTleYwn0/MGT1afo68+p1+163n/6nVO3f8nt3wfnhgxIAgDgjAgCAOCMCAIA4IwIGybx3hyal3BtFvmK94273HL98GIAUEYEIQBQRgQhAFBGBCEAWF8HuMWu/ZTetu6jie37Ad1ihEDgjAgCAOCMCAIA4IwIAgDwi83j3H7OopVt+9PdYoRA4IwIAgDgjAgCAOCMCAIA8L4PMYtz8VPzTOsun1fqVu+D0YMCMKAIAwIwoAgDAjCgCAMCNvmMW7Zp2h6/6jpc8en5yXetp7kFCMGBGFAEAYEYUAQBgRhQBAGhOV5jFv+n/52t8wDfNfvgxEDgjAgCAOCMCAIA4IwIAgDwtfn81n6gbftUzS9XuJt+zhNz2+87b6t2nWfjRgQhAFBGBCEAUEYEIQBQRgQxs/HmN436Zb1ANPzG9P34fZ5iVVGDAjCgCAMCMKAIAwIwoAgDAjL6zGeTP+//vT73rLeYJdd9+HU+07PbxgxIAgDgjAgCAOCMCAIA4IwICyvx5jex+nU+65eZ9f6iunn8bs+/y3zRbsYMSAIA4IwIAgDgjAgCAOCMCBs21dqer5i9fW3r7uY/py79vWanudZtet9jRgQhAFBGBCEAUEYEIQBQRgQts1jTJ+Hfctz8enzLlbnDd5m199x+vc1YkAQBgRhQBAGBGFAEAYEYUBYPh/j1L5S0/Mbp86FuMXb1r1MM2JAEAYEYUAQBgRhQBAGBGFAOHbO96q3rbs4tT/VqfPRV506R/yJfaVgA2FAEAYEYUAQBgRhQBAGhG3zGE9Ord94Mr1O423zLavXeXJqvcSp/cSMGBCEAUEYEIQBQRgQhAFBGBCWz8d423kRT6bPC1+1a95g+vn9qf24Vn/f6XkYIwYEYUAQBgRhQBAGBGFAEAaEbed8v+0c7tXXv209wy3noE87tZ7HiAFBGBCEAUEYEIQBQRgQhAHh2Dnft+y/tOt9n9xyH3b9XtPX2XU/jRgQhAFBGBCEAUEYEIQBQRgQtq3H2GV6n6LpeYa3res4dQ7JruufOhfciAFBGBCEAUEYEIQBQRgQhAFh/Jzvtzm1T9GpdQXTn3/VLet5jBgQhAFBGBCEAUEYEIQBQRgQtp3z/TZvOzd6+jqn/i6n9q2aZsSAIAwIwoAgDAjCgCAMCMKAsG1fqVPnSX/X9Qardq1neNv5Hqe+V0YMCMKAIAwIwoAgDAjCgCAMCOPnY7xtnuHJ2/Z3ml7PMP36VafmK54YMSAIA4IwIAgDgjAgCAOCMCC87pzvt3nbueO7TK/HODUvtOv6RgwIwoAgDAjCgCAMCMKAIAwI5jH+c+q5/pPb17Hsev0pRgwIwoAgDAjCgCAMCMKAIAwI4/MYb9sv6Mmp+Yrp65/6vd523sjq5zFiQBAGBGFAEAYEYUAQBgRhQPj6fD5LP3DL/9OfOvd62i3zQqvedr64EQOCMCAIA4IwIAgDgjAgCAPC8jwG/AqMGBCEAUEYEIQBQRgQhAFBGBCEAUEYEIQBQRgQhAFBGBCEAUEYEH4CUqIVMb+LhGYAAAAASUVORK5CYII="), (c) => c.charCodeAt(0));
    return new Response(png, { status: 200, headers: { "Content-Type": "image/png" } });
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
