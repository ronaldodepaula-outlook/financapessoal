import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import {createTransaction} from '../../api/transactions';
import {insertIfNotDuplicate, getAllCaptured} from '../captureRepository';
import {isOnline, syncPendingCaptures} from '../syncService';
import type {CapturedTransaction, LapidacaoInput} from '../../types/notification';
import {ApiError} from '../../types/api';

jest.mock('../../api/transactions', () => ({
  createTransaction: jest.fn(),
}));

const mockedCreateTransaction = createTransaction as jest.MockedFunction<typeof createTransaction>;
const mockedFetch = NetInfo.fetch as jest.Mock;

const lapidacaoInput: LapidacaoInput = {
  transactionType: 'DESPESA',
  categoryId: 1,
  paymentMethod: 'PIX',
  accountId: 1,
  description: 'Compra no mercado',
  amount: '89.90',
  transactionDate: '2026-09-16',
};

function queuedCapture(overrides: Partial<CapturedTransaction> = {}): CapturedTransaction {
  return {
    id: 'id-1',
    sourceApp: 'com.nu.production',
    sourceAppLabel: 'Nubank',
    title: 'Compra aprovada',
    content: 'R$ 89,90 MERCADO',
    amount: 89.9,
    merchant: 'MERCADO',
    cardLastDigits: null,
    occurredAt: '2026-09-16T12:00:00.000Z',
    rawPayload: '{}',
    dedupeKey: 'dedupe-1',
    status: 'AGUARDANDO_SINCRONIZACAO',
    createdAt: '2026-09-16T12:00:01.000Z',
    syncedAt: null,
    transactionId: null,
    pendingSync: lapidacaoInput,
    syncError: null,
    ...overrides,
  };
}

describe('syncService', () => {
  beforeEach(async () => {
    await AsyncStorage.clear();
    mockedCreateTransaction.mockReset();
    mockedFetch.mockReset();
    mockedFetch.mockResolvedValue({isConnected: true, isInternetReachable: true});
  });

  it('reports online when connected and reachable', async () => {
    expect(await isOnline()).toBe(true);
  });

  it('reports offline when there is no connection', async () => {
    mockedFetch.mockResolvedValue({isConnected: false, isInternetReachable: false});
    expect(await isOnline()).toBe(false);
  });

  it('does nothing when offline, without touching the queue', async () => {
    mockedFetch.mockResolvedValue({isConnected: false, isInternetReachable: false});
    await insertIfNotDuplicate(queuedCapture());

    const result = await syncPendingCaptures();

    expect(result).toEqual({synced: 0, failed: 0});
    expect(mockedCreateTransaction).not.toHaveBeenCalled();
    const [item] = await getAllCaptured();
    expect(item.status).toBe('AGUARDANDO_SINCRONIZACAO');
  });

  it('syncs a queued capture once connectivity is restored', async () => {
    await insertIfNotDuplicate(queuedCapture());
    mockedCreateTransaction.mockResolvedValue({id: 42} as never);

    const result = await syncPendingCaptures();

    expect(result).toEqual({synced: 1, failed: 0});
    expect(mockedCreateTransaction).toHaveBeenCalledTimes(1);
    const [item] = await getAllCaptured();
    expect(item.status).toBe('PROCESSADO');
    expect(item.transactionId).toBe(42);
    expect(item.pendingSync).toBeNull();
  });

  it('stops the batch on a network error, leaving remaining items queued', async () => {
    await insertIfNotDuplicate(queuedCapture({id: 'a', dedupeKey: 'a'}));
    await insertIfNotDuplicate(queuedCapture({id: 'b', dedupeKey: 'b'}));
    mockedCreateTransaction.mockRejectedValue(new ApiError({message: 'sem rede', isNetworkError: true}));

    const result = await syncPendingCaptures();

    expect(result.synced).toBe(0);
    expect(result.failed).toBe(1);
    const items = await getAllCaptured();
    expect(items.every(item => item.status === 'AGUARDANDO_SINCRONIZACAO')).toBe(true);
  });

  it('sends a rejected item back to review with the API error message', async () => {
    await insertIfNotDuplicate(queuedCapture());
    mockedCreateTransaction.mockRejectedValue(
      new ApiError({message: 'Categoria arquivada.', status: 422}),
    );

    const result = await syncPendingCaptures();

    expect(result).toEqual({synced: 0, failed: 1});
    const [item] = await getAllCaptured();
    expect(item.status).toBe('PENDENTE_LAPIDACAO');
    expect(item.syncError).toBe('Categoria arquivada.');
  });
});
