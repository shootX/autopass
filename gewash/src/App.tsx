import { BrowserRouter, Routes, Route, Navigate, useLocation } from "react-router-dom";
import { shouldShowOnboarding } from "./lib/onboardingGate";
import NotFound from "./components/NotFound";
import { customerAliasPaths, managerAliasPaths } from "./lib/coverageRoutes";
import { Provider, useDispatch, useSelector } from "react-redux";
import type { RootState } from "./store";
import { store } from "./store";
import { useUserRole } from "./hooks/useUserRole";
import { ProtectedRoute } from "./components/ProtectedRoute";
import { useUser, useRefreshUserOnBfcacheRestore, useSessionBoot } from "@/hooks/useUser";
import { selectAppRoute } from "@/lib/sessionRouting";
import { lazy, Suspense, useEffect, useState } from "react";
import { setTranslations } from "./store/langSlice";
import { resetSession, setRole, setUser } from "./store/userSlice";
import { loginPathForRole, onSessionExpired, readSessionRole } from "./lib/session";
import { ensureDeviceToken } from "./hooks/useDeviceToken";

import ManagerLayout from "./components/Layouts/ManagerLayout";
import CustomerLayout from "./components/Layouts/CustomerLayout";
import { CarCheckRoute } from "./components/CarCheckRoute";
import { customFetch } from "./utils/customFetch";
import { loadShopBundle } from "./lib/shopApi";
import { loadDefaultBranches } from "./hooks/fetchFilteredBranches";
import SplashScreen from "./components/v4/SplashScreen";
import { SPLASH_MAX_MS, splashShouldBlock } from "./lib/splashGate";
import Onboarding from "./components/v4/Onboarding";
import PaymentSuccess from "./components/v4/PaymentSuccess";
import { readAccessToken } from "@/lib/accessToken";
import { authBypassEnabled } from "./lib/productionFlags";
import { HelpShell, PaymentSandboxGate } from "./components/PublicEntry";

const Home = lazy(() => import("./components/pages/Home"));
const CustomerCalendar = lazy(() => import("./components/Calendars/CustomerCalendar"));
const ManagerCalendar = lazy(() => import("./components/Calendars/ManagerCalendar"));
const ManagerCarwashStatistics = lazy(() => import("./components/pages/Manager/ManagerCarwashStatistics"));
const Booking = lazy(() => import("./components/pages/Manager/Booking"));
const ReschudelingOrder = lazy(() => import("./components/pages/Manager/ReschudelingOrder"));
const CustomerMyData = lazy(() => import("./components/pages/Customer/CustomerMyData"));
const Authentication = lazy(() => import("./components/pages/Customer/Authentication"));
const Registration = lazy(() => import("./components/pages/Customer/Registration"));
const PhoneNumberChanging = lazy(() => import("./components/pages/Customer/PhoneNumberChanging"));
const EmailChanging = lazy(() => import("./components/pages/Customer/EmailChanging"));
const AddCar = lazy(() => import("./components/pages/Customer/AddCar"));
const EditCar = lazy(() => import("./components/pages/Customer/EditCar"));
const BranchScreen = lazy(() => import("./components/BranchScreen"));
const BranchDetail = lazy(() => import("./components/pages/Customer/BranchDetail"));
const ProfilePage = lazy(() => import("./components/pages/Customer/ProfilePage"));
const WashAppointment = lazy(() => import("./components/pages/Customer/WashApointment"));
const MyReviews = lazy(() => import("./components/pages/Customer/MyReviews"));
const MyPackages = lazy(() => import("./components/pages/Customer/MyPackages"));
const MyPoints = lazy(() => import("./components/pages/Customer/MyPoints"));
const MyAreaPage = lazy(() => import("./components/pages/Customer/MyAreaPage"));
const PointsInfo = lazy(() => import("./components/pages/Customer/PointsInfo"));
const ReferralsInfo = lazy(() => import("./components/pages/Customer/ReferralsInfo"));
const ShopPage = lazy(() => import("./components/pages/Customer/ShopPage"));
const DiscountsPage = lazy(() => import("./components/pages/Customer/DiscountsPage"));
const DiscountDetailPage = lazy(() => import("./components/pages/Customer/DiscountDetailPage"));
const GiveawayPage = lazy(() => import("./components/pages/Customer/GiveawayPage"));
const GiveawayDetailPage = lazy(() => import("./components/pages/Customer/GiveawayDetailPage"));
const QRPage = lazy(() => import("./components/pages/Customer/QRPage"));
const ManagerScanner = lazy(() => import("./components/pages/Manager/ManagerScanner"));
const Messages = lazy(() => import("./components/pages/Messages"));
const Help = lazy(() => import("./components/pages/Help"));
const Contacts = lazy(() => import("./components/Contacts"));
const MyData = lazy(() => import("./components/pages/Manager/MyData"));
const RenewPasswordPage = lazy(() => import("./components/pages/RenewPasswordPage"));
const PrivacyPage = lazy(() => import("./components/pages/PrivacyPage"));
const SettingsPage = lazy(() => import("./components/pages/SettingsPage"));

function PublicAuthRoutes() {
  return (
    <>
      <Route path="/auth" element={<Authentication />} />
      <Route path="/manager-auth" element={<Authentication />} />
      <Route path="/payment-success" element={<PaymentSuccess />} />
      <Route path="/register" element={<Registration />} />
      <Route path="/renew-password" element={<RenewPasswordPage />} />
      <Route path="/privacy" element={<PrivacyPage />} />
    </>
  );
}

function PublicPages() {
  return (
    <>
      {PublicAuthRoutes()}
      <Route path="/help" element={<HelpShell />}>
        <Route index element={<Help />} />
      </Route>
      <Route path="/payment/sandbox" element={<PaymentSandboxGate />} />
    </>
  );
}

function readLangCache(lang: string): Record<string, unknown> | null {
  try {
    const raw = sessionStorage.getItem(`gewash_lang:${lang}`);
    return raw ? (JSON.parse(raw) as Record<string, unknown>) : null;
  } catch {
    return null;
  }
}
function AppRoutes() {
  const role = useUserRole();
  const session = useSelector((s: RootState) => s.user.session);
  const decision = selectAppRoute(session, role);
  const dispatch = useDispatch();
  const currentLang = useSelector((s: RootState) => s.lang.currentLang);
  const [langLoading, setLangLoading] = useState(() => readLangCache(currentLang) === null);
  const [splashStarted] = useState(() => Date.now());
  const [splashNow, setSplashNow] = useState(() => Date.now());
  const [onboarded, setOnboarded] = useState(() => localStorage.getItem("autopass.onboarding.v4") === "1");
  const { pathname } = useLocation();
  useUser();
  useRefreshUserOnBfcacheRestore();
  const boot = useSessionBoot();

  useEffect(() => onSessionExpired(() => {
    dispatch(resetSession());
  }), [dispatch]);

  useEffect(() => {
    const left = SPLASH_MAX_MS - (Date.now() - splashStarted);
    if (left <= 0) return;
    const timer = window.setTimeout(() => setSplashNow(Date.now()), left);
    return () => window.clearTimeout(timer);
  }, [splashStarted]);

  useEffect(() => {
    if (import.meta.env.PROD || !authBypassEnabled(import.meta.env)) return;
    if (readAccessToken()) return;
    void import("./dev/previewFixtures").then(({ previewUser }) => {
      dispatch(setUser(previewUser));
      dispatch(setRole("customer"));
    });
  }, [dispatch]);

  useEffect(() => {
    void ensureDeviceToken();
  }, []);

//////////////////////////////////////

  useEffect(() => {
    let cancelled = false;
    const cached = readLangCache(currentLang);
    if (cached) {
      dispatch(setTranslations(cached));
      setLangLoading(false);
    } else {
      setLangLoading(true);
    }

    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 8000);

    customFetch(`${import.meta.env.VITE_API_URL}/lang/${currentLang}`, { signal: controller.signal })
      .then(async (res) => {
        if (!res.ok) throw new Error("Failed to load translations");
        return res.json();
      })
      .then((data) => {
        if (cancelled || !data || typeof data !== "object" || Array.isArray(data)) return;
        sessionStorage.setItem(`gewash_lang:${currentLang}`, JSON.stringify(data));
        dispatch(setTranslations(data));
      })
      .catch((err) => {
        if (!cancelled) console.error("Failed to load translations:", err);
      })
      .finally(() => {
        window.clearTimeout(timer);
        if (!cancelled) setLangLoading(false);
      });

    return () => {
      cancelled = true;
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [dispatch, currentLang]);

  useEffect(() => {
    if (langLoading) return;
    if (!readAccessToken()) return;
    const run = () => {
      void loadShopBundle();
      void loadDefaultBranches();
      void import("./components/BranchMap");
      if (import.meta.env.VITE_LIVE_MAP === "true") {
        void import("./components/georgiaMap").then((mod) => mod.prepareGeorgiaMap());
      }
    };
    const idle = window.requestIdleCallback?.(run);
    const timer = window.setTimeout(run, 250);
    return () => {
      if (idle) window.cancelIdleCallback?.(idle);
      window.clearTimeout(timer);
    };
  }, [langLoading]);

  if (boot === "pending" || boot === "hold") {
    return <SplashScreen />;
  }

  if (
    decision === "hold" ||
    splashShouldBlock({
      langLoading,
      waitingForRole: decision === "loading",
      elapsedMs: splashNow - splashStarted,
    })
  ) {
    return <SplashScreen />;
  }

  if (decision === "login") {
    return (
      <Suspense fallback={<SplashScreen />}>
        <Routes>
          {PublicPages()}
          <Route path="*" element={<Navigate to={loginPathForRole(readSessionRole())} replace />} />
        </Routes>
      </Suspense>
    );
  }

  const loggedOut = !readAccessToken() && !authBypassEnabled(import.meta.env);
  if (shouldShowOnboarding(loggedOut, onboarded, pathname)) {
    return (
      <Onboarding
        onDone={() => {
          localStorage.setItem("autopass.onboarding.v4", "1");
          setOnboarded(true);
        }}
      />
    );
  }

  return (
    <Suspense fallback={<SplashScreen />}>
    <Routes>
      {PublicPages()}

      {/* Secure routes */}
      <Route element={<ProtectedRoute />}>
        {decision === "manager" ? (
          <Route element={<ManagerLayout />}>
            <Route path="/" element={<ManagerCalendar />} />
            <Route path={managerAliasPaths.home} element={<Navigate to="/" replace />} />
            <Route path={managerAliasPaths.calendar} element={<ManagerCalendar />} />
            <Route path="/profile" element={<MyData />} />
            <Route path={managerAliasPaths.profile} element={<MyData />} />
            <Route path="/carwash-statistics" element={<ManagerCarwashStatistics />} />
            <Route path={managerAliasPaths.statistics} element={<ManagerCarwashStatistics />} />
            <Route path="/booking" element={<Booking />} />
            <Route path={managerAliasPaths.bookings} element={<Booking />} />
            <Route path="/reschedule/:id" element={<ReschudelingOrder />} />
            <Route path={managerAliasPaths.reschedule} element={<ReschudelingOrder />} />
            <Route path="/manager-qr-page" element={<ManagerScanner />} />
            <Route path={managerAliasPaths.scanner} element={<ManagerScanner />} />
            <Route path="/contacts" element={<Contacts />} />
            <Route path={managerAliasPaths.contacts} element={<Contacts />} />
            <Route path={managerAliasPaths.help} element={<Help />} />
            <Route path="/settings" element={<SettingsPage />} />
            <Route path={managerAliasPaths.settings} element={<SettingsPage />} />
          </Route>
        ) : (
          <>
            <Route element={<CustomerLayout />}>
              <Route path="/add-car" element={<AddCar showHeader />} />
              <Route path={customerAliasPaths.profileCarsNew} element={<AddCar showHeader />} />
            </Route>
            <Route element={<CarCheckRoute />}>
              <Route element={<CustomerLayout />}>
                <Route path="/" element={<Home />} />
                <Route path={customerAliasPaths.home} element={<Home />} />
                <Route path="/profile" element={<ProfilePage />} />
                <Route path="/customer-my-data" element={<CustomerMyData />} />
                <Route path="/change-phone" element={<PhoneNumberChanging />} />
                <Route path="/change-email" element={<EmailChanging />} />
                <Route path="/edit-car/:carid" element={<EditCar />} />
                <Route path={customerAliasPaths.profileCarEdit} element={<EditCar />} />
                <Route path="/branches/:id" element={<BranchDetail />} />
                <Route path="/branches" element={<BranchScreen />} />
                <Route path="/wash-appointment" element={<WashAppointment />} />
                <Route path="/my-reviews" element={<MyReviews />} />
                <Route path={customerAliasPaths.reviews} element={<MyReviews />} />
                <Route path="/my-packages" element={<MyPackages />} />
                <Route path="/my-points" element={<MyPoints />} />
                <Route path="/my-area" element={<MyAreaPage />} />
                <Route path="/my-points/info" element={<PointsInfo />} />
                <Route path="/my-points/referrals" element={<ReferralsInfo />} />
                <Route path={customerAliasPaths.referrals} element={<ReferralsInfo />} />
                <Route path="/shop" element={<ShopPage />} />
                <Route path="/shop/discounts" element={<DiscountsPage />} />
                <Route path="/shop/discounts/:id" element={<DiscountDetailPage />} />
                <Route path="/shop/giveaway" element={<GiveawayPage />} />
                <Route path="/shop/giveaway/:id" element={<GiveawayDetailPage />} />
                <Route path={customerAliasPaths.customerCalendar} element={<CustomerCalendar />} />
                <Route path={customerAliasPaths.calendar} element={<CustomerCalendar />} />
                <Route path="/customer-qr-page" element={<QRPage />} />
                <Route path={customerAliasPaths.qr} element={<QRPage />} />
                <Route path={customerAliasPaths.profileCars} element={<Navigate to="/customer-my-data#vehicles" replace />} />
                <Route path="/messages" element={<Messages />} />
                <Route path="/contacts" element={<Contacts />} />
                <Route path="/settings" element={<SettingsPage />} />
              </Route>
            </Route>
          </>
        )}
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
    </Suspense>
  );
}

function App() {
  return (
    <Provider store={store}>
      <BrowserRouter>
        <div className="main-container">
          <AppRoutes />
        </div>
      </BrowserRouter>
    </Provider>
  );
}

export default App;
