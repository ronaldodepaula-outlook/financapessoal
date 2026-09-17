/**
 * Ponto único de configuração da API. Nunca referenciar o IP/host do backend
 * fora deste arquivo — qualquer outro módulo deve importar API_BASE_URL.
 */
export const API_BASE_URL = 'http://192.168.1.9/financapessoal/backend/public/api';

export const API_TIMEOUT_MS = 15000;

/** Chave usada no expo-secure-store (Android Keystore) para o blob de sessão/tokens. */
export const AUTH_SECURE_STORE_KEY = 'financapessoal.auth';

/** Chaves usadas no AsyncStorage para preferências não sensíveis. */
export const STORAGE_KEYS = {
  monitoredApps: 'settings.monitored_apps',
  captureEnabled: 'settings.capture_enabled',
  lastSyncAt: 'sync.last_sync_at',
} as const;

export const DEBUG = __DEV__;
