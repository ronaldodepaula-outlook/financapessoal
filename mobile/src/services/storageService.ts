import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import {AUTH_SECURE_STORE_KEY, STORAGE_KEYS} from '../constants/config';
import type {AuthTokens, AuthUser} from '../types/auth';

/**
 * Sessão persistida via Android Keystore (expo-secure-store), nunca em
 * AsyncStorage puro. Contém os únicos dados sensíveis do app: os tokens.
 *
 * Usamos expo-secure-store (não react-native-keychain) porque é o módulo
 * de armazenamento seguro que já vem embutido no Expo Go — necessário para
 * o app rodar via `npx expo start` + Expo Go sem exigir um build nativo
 * próprio (react-native-keychain é um módulo de terceiros que o Expo Go
 * não inclui, e falha com "Cannot read property ... of null").
 */
export interface StoredSession extends AuthTokens {
  user: AuthUser | null;
}

export async function saveSession(session: StoredSession): Promise<void> {
  await SecureStore.setItemAsync(AUTH_SECURE_STORE_KEY, JSON.stringify(session));
}

export async function getStoredSession(): Promise<StoredSession | null> {
  const raw = await SecureStore.getItemAsync(AUTH_SECURE_STORE_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw) as StoredSession;
  } catch {
    return null;
  }
}

/** Usado pelo interceptor do axios (src/api/client.ts) para montar o header Authorization. */
export async function getStoredTokens(): Promise<AuthTokens | null> {
  return getStoredSession();
}

/** Atualiza só os tokens após um refresh, preservando o usuário já armazenado. */
export async function saveTokens(tokens: AuthTokens): Promise<void> {
  const current = await getStoredSession();
  await saveSession({...tokens, user: current?.user ?? null});
}

export async function clearStoredSession(): Promise<void> {
  await SecureStore.deleteItemAsync(AUTH_SECURE_STORE_KEY);
}

/** Preferências e estado de sincronização — não sensíveis, ficam em AsyncStorage. */
export async function getSetting<T>(key: string, fallback: T): Promise<T> {
  const raw = await AsyncStorage.getItem(key);
  if (raw == null) {
    return fallback;
  }
  try {
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}

export async function setSetting<T>(key: string, value: T): Promise<void> {
  await AsyncStorage.setItem(key, JSON.stringify(value));
}

export async function isCaptureEnabled(): Promise<boolean> {
  return getSetting<boolean>(STORAGE_KEYS.captureEnabled, false);
}

export async function setCaptureEnabled(enabled: boolean): Promise<void> {
  await setSetting(STORAGE_KEYS.captureEnabled, enabled);
}
