import { createSlice, type PayloadAction } from "@reduxjs/toolkit";

type LangState = {
  currentLang: "en" | "ru" | "ka";
  translations: Record<string, unknown>;
};

function readStoredLang(): LangState["currentLang"] {
  const stored = localStorage.getItem("lang");
  if (stored === "ka" || stored === "ge") {
    if (stored === "ge") localStorage.setItem("lang", "ka");
    return "ka";
  }
  if (stored === "ru") return "ru";
  if (stored === "en") return "en";
  return "ka";
}

const initialState: LangState = {
  currentLang: readStoredLang(),
  translations: {
    Giveaway: {
      title: "Giveaway",
      loading: "Loading…",
      empty: "No active offers.",
      notFound: "Offer not found.",
      activeOffers: "Active offers",
      daysLeft: "days left",
      joinBtn: "Join the giveaway",
      takePartBtn: "Take part",
      buyMoreBtn: "Buy more tickets",
      joinedSuccess: "You've joined the giveaway!",
      myTickets: "My tickets:",
      daysLeftLabel: "Days left",
      participants: "Participants",
      termsTitle: "Terms of Participation:",
      ticketPrice: "Ticket price",
      quantity: "Quantity",
      totalCost: "Total cost:",
      currentBalance: "Current balance:",
      newBalance: "New balance:",
      points: "points",
      confirmBtn: "Confirm",
      takeBtn: "Take",
      tickets: "Tickets",
      buyMorePointsBtn: "Buy more points",
      notEnoughTitle: "Not enough points",
      notEnoughDesc: "You are {amount} points short. Purchase them now.",
      amountToPay: "Amount to pay",
      buyBtn: "Buy",
      successTitle: "Successful purchase",
      successDesc: "You have successfully purchased {amount} points.",
      okBtn: "OK",
      takePartForBtn: "Take part for {price} points",
    },
    Discounts: {
      title: "Discounts",
      searchPlaceholder: "Search for a partner company",
      loading: "Loading…",
      empty: "No discounts found.",
      detail: {
        termsTitle: "Terms of Use:",
        buy: "Buy",
        take: "Take",
        currentBalance: "Current balance:",
        newBalance: "New balance:",
        points: "points",
        successMsg: "Purchased successfully",
        yourCode: "Your discount code:",
        notEnoughTitle: "Not enough points",
        notEnoughDesc: "You don't have enough points to get this discount. Buy more points to continue.",
        buyPoints: "Buy points",
      },
    },
    Shop: {
      header: { title: "Shop" },
      balance: { label: "Balance", unit: "Coins" },
      categories: {
        discounts: "Discounts",
        giveaway: "Giveaway",
      },
      all: "All",
      loading: "Loading…",
      loadError: "Could not load. Pull to refresh or try again later.",
      vouchers: {
        title: "My Vouchers",
        buyMore: "Buy more",
        empty: "No vouchers yet.",
      },
      tickets: {
        title: "My Tickets",
        daysLeft: "days left",
        join: "Join the giveaway",
        empty: "No giveaways yet.",
      },
    },
    ReferralsInfo: {
      header: { title: "Referrals" },
      promo: {
        title: "autopass referrals",
        desc: "This is a loyalty program that gives participants the opportunity to accumulate points and exchange them for gifts or discounts from partners.",
        btn: "Share Referral Link",
      },
      how: {
        title: "How it works?",
        step1: "Share your referral link to register new users",
        step2: "Earn points for packages connected by your referrals",
        step3: "Exchange points for discount vouchers from our partners",
      },
      sheet: {
        title: "Share Referral Link",
        yourLink: "Your link",
        shareTo: "Sharet to:",
      },
    },
    PointsInfo: {
      header: { title: "Points" },
      promo: {
        title: "autopass points",
        desc: "This is a loyalty program that gives participants the opportunity to accumulate points and exchange them for gifts or discounts from partners.",
        btn: "Buy autopass points",
      },
      sheet: {
        title: "Buy autopass points",
        placeholder: "Points amount",
        amountLabel: "Amount to pay",
        buyBtn: "Buy",
      },
      how: {
        title: "How it works?",
        step1: "Purchase points using a bank card or cryptocurrency",
        step2: "Earn points with your referrals",
        step3: "Exchange points for discount vouchers from our partners",
      },
    },
    MyPoints: {
      header: { title: "My points" },
      balance: { label: "Balance", unit: "points" },
      referrals: { label: "Referrals", unit: "people" },
      notice: { addedLine: "{count} points added" },
      history: { title: "Transaction history", empty: "No transactions" },
    },
    Sidebar: {
      menu: {
        myPackages: "My packages",
        shop: "Shop",
        myReviews: "My reviews",
        myPoints: "My points",
        messages: "Messages",
        settings: "Settings",
        contacts: "Contacts",
        help: "Help",
      },
    },
  },
};

export const langSlice = createSlice({
  name: "lang",
  initialState,
  reducers: {
    setLanguage(state, action: PayloadAction<LangState["currentLang"]>) {
      state.currentLang = action.payload;
      localStorage.setItem("lang", action.payload);
    },
    setTranslations(state, action: PayloadAction<Record<string, unknown>>) {
      state.translations = action.payload;
    },
  },
});

export const { setLanguage, setTranslations } = langSlice.actions;
export default langSlice.reducer;