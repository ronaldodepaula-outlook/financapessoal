import {create} from 'zustand';
import * as authApi from '../api/auth';
import {setOnSessionExpired} from '../api/client';
import {clearStoredSession, getStoredSession, saveSession} from '../services/storageService';
import type {ApiError} from '../types/api';
import type {AuthUser, LoginPayload} from '../types/auth';

interface AuthState {
  status: 'booting' | 'signed-out' | 'signed-in';
  user: AuthUser | null;
  error: string | null;
  isSubmitting: boolean;
  bootstrap: () => Promise<void>;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  status: 'booting',
  user: null,
  error: null,
  isSubmitting: false,

  bootstrap: async () => {
    const session = await getStoredSession();
    if (session?.access_token && session.user) {
      set({status: 'signed-in', user: session.user});
    } else {
      set({status: 'signed-out', user: null});
    }
    // Reage a uma sessão que não pôde ser renovada em nenhuma chamada futura
    // (ex.: access token expirado + refresh token também expirado/revogado).
    setOnSessionExpired(() => {
      if (get().status !== 'signed-out') {
        set({status: 'signed-out', user: null, error: 'Sessão expirada. Faça login novamente.'});
      }
    });
  },

  login: async payload => {
    set({isSubmitting: true, error: null});
    try {
      const response = await authApi.login(payload);
      const {user, ...tokens} = response;
      await saveSession({...tokens, user});
      set({status: 'signed-in', user, isSubmitting: false});
    } catch (error) {
      set({isSubmitting: false, error: (error as ApiError).message});
      throw error;
    }
  },

  logout: async () => {
    try {
      await authApi.logout();
    } catch {
      // mesmo se a chamada falhar (ex.: sem internet), a sessão local é encerrada.
    }
    await clearStoredSession();
    set({status: 'signed-out', user: null, error: null});
  },
}));
