import {create} from 'zustand';
import * as repository from '../services/captureRepository';
import {isOnline, syncPendingCaptures} from '../services/syncService';
import {createTransaction} from '../api/transactions';
import {drainQueuedEvents, subscribeToNotificationEvents} from '../services/notificationService';
import {isCaptureEnabled} from '../services/storageService';
import type {ApiError} from '../types/api';
import type {CapturedTransaction, LapidacaoInput, RawNotificationEvent} from '../types/notification';
import {transactionFormInputToPayload} from '../types/transaction';
import {parseNotification} from '../utils/notificationParser';

interface NotificationState {
  captured: CapturedTransaction[];
  isLoading: boolean;
  refresh: () => Promise<void>;
  ingest: (event: RawNotificationEvent, keywords?: string[]) => Promise<void>;
  ignore: (id: string) => Promise<void>;
  /** Confirma a lapidação: tenta enviar na hora; se falhar por rede, fica na fila. */
  lapidar: (id: string, input: LapidacaoInput) => Promise<{synced: boolean}>;
  syncNow: () => Promise<{synced: number; failed: number}>;
  /** Drena o que foi capturado com o app fechado e assina eventos ao vivo. Chamar uma vez no boot. */
  startCapture: () => Promise<() => void>;
}

export const useNotificationStore = create<NotificationState>((set, get) => ({
  captured: [],
  isLoading: false,

  refresh: async () => {
    set({isLoading: true});
    const captured = await repository.getAllCaptured();
    set({captured, isLoading: false});
  },

  ingest: async (event, keywords) => {
    const parsed = parseNotification(event, keywords ? {financialKeywords: keywords} : undefined);
    if (!parsed) {
      return;
    }
    const result = await repository.insertIfNotDuplicate(parsed);
    if (result === 'inserted') {
      await get().refresh();
    }
  },

  ignore: async id => {
    await repository.setStatus(id, 'IGNORADA');
    await get().refresh();
  },

  lapidar: async (id, input) => {
    const payload = transactionFormInputToPayload(input);

    const online = await isOnline();
    if (!online) {
      await repository.setStatus(id, 'AGUARDANDO_SINCRONIZACAO', {
        pendingSync: input,
        syncError: null,
      });
      await get().refresh();
      return {synced: false};
    }

    try {
      const transaction = await createTransaction(payload);
      await repository.setStatus(id, 'PROCESSADO', {
        transactionId: transaction.id,
        syncedAt: new Date().toISOString(),
        pendingSync: null,
        syncError: null,
      });
      await get().refresh();
      return {synced: true};
    } catch (error) {
      const apiError = error as ApiError;
      if (apiError.isNetworkError) {
        await repository.setStatus(id, 'AGUARDANDO_SINCRONIZACAO', {
          pendingSync: input,
          syncError: null,
        });
        await get().refresh();
        return {synced: false};
      }
      // erro de validação: mantém em PENDENTE_LAPIDACAO com o motivo, para o usuário corrigir.
      await repository.setStatus(id, 'PENDENTE_LAPIDACAO', {
        syncError: apiError.message,
      });
      await get().refresh();
      throw error;
    }
  },

  syncNow: async () => {
    const result = await syncPendingCaptures();
    await get().refresh();
    return result;
  },

  startCapture: async () => {
    const enabled = await isCaptureEnabled();
    if (!enabled) {
      return () => undefined;
    }

    try {
      const queued = await drainQueuedEvents();
      for (const event of queued) {
         
        await get().ingest(event);
      }
    } catch {
      // se o drain falhar, não bloqueia a assinatura de eventos ao vivo.
    }

    const unsubscribe = subscribeToNotificationEvents(event => {
      get().ingest(event).catch(() => undefined);
    });
    return unsubscribe;
  },
}));

export function selectPending(captured: CapturedTransaction[]): CapturedTransaction[] {
  return captured.filter(item => item.status === 'PENDENTE_LAPIDACAO');
}

export function selectQueued(captured: CapturedTransaction[]): CapturedTransaction[] {
  return captured.filter(item => item.status === 'AGUARDANDO_SINCRONIZACAO');
}
