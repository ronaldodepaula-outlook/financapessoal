import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  countPending,
  findByDedupeKey,
  getAllCaptured,
  getPendingCaptured,
  insertIfNotDuplicate,
  setStatus,
} from '../captureRepository';
import type {CapturedTransaction} from '../../types/notification';

function makeCaptured(overrides: Partial<CapturedTransaction> = {}): CapturedTransaction {
  return {
    id: 'id-1',
    sourceApp: 'com.nu.production',
    sourceAppLabel: 'Nubank',
    title: 'Compra aprovada',
    content: 'R$ 152,80 SUPERMERCADO XYZ',
    amount: 152.8,
    merchant: 'SUPERMERCADO XYZ',
    cardLastDigits: '1234',
    occurredAt: '2026-09-16T20:30:00.000Z',
    rawPayload: '{}',
    dedupeKey: 'dedupe-1',
    status: 'PENDENTE_LAPIDACAO',
    createdAt: '2026-09-16T20:30:01.000Z',
    syncedAt: null,
    transactionId: null,
    pendingSync: null,
    syncError: null,
    ...overrides,
  };
}

describe('captureRepository', () => {
  beforeEach(async () => {
    await AsyncStorage.clear();
  });

  it('inserts a new capture', async () => {
    const result = await insertIfNotDuplicate(makeCaptured());
    expect(result).toBe('inserted');
    expect(await getAllCaptured()).toHaveLength(1);
  });

  it('rejects a second insert with the same dedupeKey', async () => {
    await insertIfNotDuplicate(makeCaptured({id: 'id-1'}));
    const second = await insertIfNotDuplicate(makeCaptured({id: 'id-2'}));

    expect(second).toBe('duplicate');
    expect(await getAllCaptured()).toHaveLength(1);
  });

  it('allows two captures with different dedupeKeys', async () => {
    await insertIfNotDuplicate(makeCaptured({id: 'id-1', dedupeKey: 'dedupe-1'}));
    await insertIfNotDuplicate(makeCaptured({id: 'id-2', dedupeKey: 'dedupe-2'}));

    expect(await getAllCaptured()).toHaveLength(2);
  });

  it('finds a capture by dedupeKey', async () => {
    await insertIfNotDuplicate(makeCaptured({dedupeKey: 'dedupe-x'}));
    const found = await findByDedupeKey('dedupe-x');
    expect(found?.dedupeKey).toBe('dedupe-x');
    expect(await findByDedupeKey('missing')).toBeUndefined();
  });

  it('filters pending captures and excludes processed/ignored ones', async () => {
    await insertIfNotDuplicate(makeCaptured({id: 'pending', dedupeKey: 'a'}));
    await insertIfNotDuplicate(
      makeCaptured({id: 'processed', dedupeKey: 'b', status: 'PROCESSADO'}),
    );

    const pending = await getPendingCaptured();
    expect(pending).toHaveLength(1);
    expect(pending[0].id).toBe('pending');
    expect(await countPending()).toBe(1);
  });

  it('updates status via setStatus and preserves other fields', async () => {
    await insertIfNotDuplicate(makeCaptured({id: 'id-1', dedupeKey: 'dedupe-1'}));
    await setStatus('id-1', 'PROCESSADO', {transactionId: 42, syncedAt: '2026-09-16T21:00:00.000Z'});

    const [item] = await getAllCaptured();
    expect(item.status).toBe('PROCESSADO');
    expect(item.transactionId).toBe(42);
    expect(item.merchant).toBe('SUPERMERCADO XYZ');
  });

  it('orders captures by occurredAt descending', async () => {
    await insertIfNotDuplicate(
      makeCaptured({id: 'older', dedupeKey: 'a', occurredAt: '2026-09-01T00:00:00.000Z'}),
    );
    await insertIfNotDuplicate(
      makeCaptured({id: 'newer', dedupeKey: 'b', occurredAt: '2026-09-16T00:00:00.000Z'}),
    );

    const all = await getAllCaptured();
    expect(all.map(item => item.id)).toEqual(['newer', 'older']);
  });
});
