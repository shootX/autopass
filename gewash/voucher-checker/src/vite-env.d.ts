/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL: string;
  readonly VITE_AUTH_LOGIN_PATH?: string;
  readonly VITE_VOUCHER_CHECK_PATH?: string;
  readonly VITE_VOUCHER_USE_PATH?: string;
  readonly VITE_MOCK_VOUCHER_CHECK?: string;
  readonly VITE_NGROK_SKIP_BROWSER_WARNING?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

declare module "*.svg" {
  const src: string;
  export default src;
}
