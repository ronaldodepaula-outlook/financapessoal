import {api, unwrap} from './client';
import type {ApiEnvelope, Paginated} from '../types/api';
import type {
  Transaction,
  TransactionCreatePayload,
  TransactionListFilters,
  TransactionUpdatePayload,
} from '../types/transaction';

export function listTransactions(
  filters: TransactionListFilters = {},
): Promise<Paginated<Transaction>> {
  return unwrap(
    api.get<ApiEnvelope<Paginated<Transaction>>>('/transactions', {params: filters}),
  );
}

export function getTransaction(id: number): Promise<Transaction> {
  return unwrap(api.get<ApiEnvelope<Transaction>>(`/transactions/${id}`));
}

export function createTransaction(payload: TransactionCreatePayload): Promise<Transaction> {
  return unwrap(api.post<ApiEnvelope<Transaction>>('/transactions', payload));
}

export function updateTransaction(
  id: number,
  payload: TransactionUpdatePayload,
): Promise<Transaction> {
  return unwrap(api.put<ApiEnvelope<Transaction>>(`/transactions/${id}`, payload));
}

/** Cancela (soft delete) preservando histórico — não existe exclusão física na API. */
export function cancelTransaction(id: number): Promise<null> {
  return unwrap(api.delete<ApiEnvelope<null>>(`/transactions/${id}`));
}
