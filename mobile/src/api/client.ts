import axios, {AxiosError, AxiosInstance, InternalAxiosRequestConfig} from 'axios';
import {API_BASE_URL, API_TIMEOUT_MS, DEBUG} from '../constants/config';
import {clearStoredSession, getStoredTokens, saveTokens} from '../services/storageService';
import type {ApiEnvelope} from '../types/api';
import {ApiError} from '../types/api';
import type {AuthTokens} from '../types/auth';

type RetriableConfig = InternalAxiosRequestConfig & {_retried?: boolean};

type SessionExpiredListener = () => void;
let onSessionExpired: SessionExpiredListener | null = null;

/** Chamado pelo authStore no boot do app para reagir a uma sessão que não pôde ser renovada. */
export function setOnSessionExpired(listener: SessionExpiredListener | null): void {
  onSessionExpired = listener;
}

export const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: API_TIMEOUT_MS,
});

let refreshPromise: Promise<AuthTokens> | null = null;

/**
 * Chama POST /auth/refresh diretamente (sem passar pela instância `api`) para
 * não reentrar nos interceptors abaixo e criar um loop de refresh.
 */
async function performRefresh(): Promise<AuthTokens> {
  const stored = await getStoredTokens();
  if (!stored?.refresh_token) {
    throw new ApiError({message: 'Sessão expirada. Faça login novamente.', status: 401});
  }
  const response = await axios.post<ApiEnvelope<AuthTokens>>(
    `${API_BASE_URL}/auth/refresh`,
    {refresh_token: stored.refresh_token},
    {timeout: API_TIMEOUT_MS},
  );
  const fresh = response.data.data;
  await saveTokens(fresh);
  return fresh;
}

api.interceptors.request.use(async config => {
  const tokens = await getStoredTokens();
  if (tokens?.access_token) {
    config.headers.set('Authorization', `Bearer ${tokens.access_token}`);
  }
  if (DEBUG) {
     
    console.log('[API]', config.method?.toUpperCase(), config.url);
  }
  return config;
});

api.interceptors.response.use(
  response => response,
  async (error: AxiosError<ApiEnvelope<unknown>>) => {
    const original = error.config as RetriableConfig | undefined;

    if (!error.response) {
      return Promise.reject(
        new ApiError({
          message:
            'Não foi possível conectar ao servidor. Verifique se o celular está conectado à mesma rede e se o servidor está disponível.',
          isNetworkError: true,
        }),
      );
    }

    const status = error.response.status;
    const isAuthCall =
      original?.url?.includes('/auth/login') || original?.url?.includes('/auth/refresh');

    if (status === 401 && original && !original._retried && !isAuthCall) {
      original._retried = true;
      try {
        refreshPromise = refreshPromise ?? performRefresh();
        const fresh = await refreshPromise;
        refreshPromise = null;
        original.headers.set('Authorization', `Bearer ${fresh.access_token}`);
        return api.request(original);
      } catch {
        refreshPromise = null;
        await clearStoredSession();
        onSessionExpired?.();
        return Promise.reject(
          new ApiError({message: 'Sessão expirada. Faça login novamente.', status: 401}),
        );
      }
    }

    const envelope = error.response.data;
    const message = envelope?.message ?? 'Não foi possível concluir a solicitação.';
    const fieldErrors =
      envelope && envelope.errors && !Array.isArray(envelope.errors)
        ? (envelope.errors as Record<string, string[]>)
        : undefined;

    if (DEBUG) {
       
      console.log('[API] error', status, original?.url, message);
    }

    return Promise.reject(new ApiError({message, status, fieldErrors}));
  },
);

/** Desembrulha o envelope {success,message,data,errors} devolvendo só `data`. */
export async function unwrap<T>(request: Promise<{data: ApiEnvelope<T>}>): Promise<T> {
  const response = await request;
  return response.data.data;
}
