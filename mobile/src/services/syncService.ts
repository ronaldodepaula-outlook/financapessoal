import NetInfo from '@react-native-community/netinfo';
import {createTransaction} from '../api/transactions';
import {getAllCaptured, setStatus} from './captureRepository';
import {DEBUG} from '../constants/config';
import type {ApiError} from '../types/api';
import type {CapturedTransaction} from '../types/notification';
import {transactionFormInputToPayload} from '../types/transaction';

export async function isOnline(): Promise<boolean> {
  const state = await NetInfo.fetch();
  return Boolean(state.isConnected && state.isInternetReachable !== false);
}

/**
 * Envia para a API (POST /transactions, endpoint já existente) todas as
 * capturas que o usuário já lapidou mas que ainda não foram confirmadas —
 * seja porque a lapidação aconteceu offline, seja porque uma tentativa
 * anterior falhou por instabilidade de rede.
 */
export async function syncPendingCaptures(
  items: CapturedTransaction[] = [],
): Promise<{synced: number; failed: number}> {
  const online = await isOnline();
  if (!online) {
    if (DEBUG) {
       
      console.log('[SYNC] offline, skipping');
    }
    return {synced: 0, failed: 0};
  }

  const source = items.length ? items : await getAllCaptured();
  const queued = source.filter(
    item => item.status === 'AGUARDANDO_SINCRONIZACAO' && item.pendingSync,
  );

  let synced = 0;
  let failed = 0;

  for (const item of queued) {
    try {
       
      const transaction = await createTransaction(transactionFormInputToPayload(item.pendingSync!));
       
      await setStatus(item.id, 'PROCESSADO', {
        transactionId: transaction.id,
        syncedAt: new Date().toISOString(),
        pendingSync: null,
        syncError: null,
      });
      synced += 1;
    } catch (error) {
      const apiError = error as ApiError;
      failed += 1;
      if (apiError.isNetworkError) {
        // conexão caiu no meio da fila; para e tenta tudo de novo na próxima chamada.
        break;
      }
      // erro de validação (ex.: categoria arquivada) — devolve para revisão do usuário.
       
      await setStatus(item.id, 'PENDENTE_LAPIDACAO', {
        syncError: apiError.message ?? 'Não foi possível confirmar esta movimentação.',
      });
    }
  }

  return {synced, failed};
}

/**
 * Assina mudanças de conectividade e dispara a sincronização assim que o
 * aparelho volta a ter internet (seção 19/9 do briefing).
 */
export function watchConnectivityAndSync(onSynced?: (result: {synced: number; failed: number}) => void): () => void {
  let wasOffline = false;
  const unsubscribe = NetInfo.addEventListener(state => {
    const online = Boolean(state.isConnected && state.isInternetReachable !== false);
    if (online && wasOffline) {
      syncPendingCaptures()
        .then(result => onSynced?.(result))
        .catch(() => undefined);
    }
    wasOffline = !online;
  });
  return unsubscribe;
}
