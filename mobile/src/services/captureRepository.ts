import AsyncStorage from '@react-native-async-storage/async-storage';
import type {CapturedTransaction, CapturedTransactionStatus} from '../types/notification';

/**
 * Fila local de movimentações capturadas. Guardada como uma lista única em
 * AsyncStorage (volume esperado é de dezenas/poucas centenas de itens — um
 * banco relacional embutido seria complexidade desnecessária aqui, ver
 * seção 4 do briefing: "SQLite... somente se realmente necessário").
 */
const STORAGE_KEY = 'capture.queue.v1';

async function readAll(): Promise<CapturedTransaction[]> {
  const raw = await AsyncStorage.getItem(STORAGE_KEY);
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? (parsed as CapturedTransaction[]) : [];
  } catch {
    return [];
  }
}

async function writeAll(items: CapturedTransaction[]): Promise<void> {
  await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

export async function getAllCaptured(): Promise<CapturedTransaction[]> {
  const items = await readAll();
  return [...items].sort((a, b) => b.occurredAt.localeCompare(a.occurredAt));
}

export async function getPendingCaptured(): Promise<CapturedTransaction[]> {
  const items = await getAllCaptured();
  return items.filter(item => item.status === 'PENDENTE_LAPIDACAO');
}

export async function findByDedupeKey(
  dedupeKey: string,
): Promise<CapturedTransaction | undefined> {
  const items = await readAll();
  return items.find(item => item.dedupeKey === dedupeKey);
}

export type UpsertResult = 'inserted' | 'duplicate';

/**
 * Insere um item novo, a menos que já exista um com o mesmo dedupeKey — nesse
 * caso a notificação é descartada silenciosamente (ver utils/notificationParser).
 */
export async function insertIfNotDuplicate(item: CapturedTransaction): Promise<UpsertResult> {
  const items = await readAll();
  if (items.some(existing => existing.dedupeKey === item.dedupeKey)) {
    return 'duplicate';
  }
  items.push(item);
  await writeAll(items);
  return 'inserted';
}

export async function updateCaptured(
  id: string,
  patch: Partial<CapturedTransaction>,
): Promise<void> {
  const items = await readAll();
  const index = items.findIndex(item => item.id === id);
  if (index === -1) {
    return;
  }
  items[index] = {...items[index], ...patch};
  await writeAll(items);
}

export async function setStatus(
  id: string,
  status: CapturedTransactionStatus,
  extra: Partial<CapturedTransaction> = {},
): Promise<void> {
  await updateCaptured(id, {status, ...extra});
}

export async function countPending(): Promise<number> {
  const pending = await getPendingCaptured();
  return pending.length;
}
