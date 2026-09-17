import {create} from 'zustand';
import * as transactionsApi from '../api/transactions';
import type {ApiError, Pagination} from '../types/api';
import type {Transaction, TransactionListFilters} from '../types/transaction';

interface TransactionState {
  items: Transaction[];
  pagination: Pagination | null;
  filters: TransactionListFilters;
  isLoading: boolean;
  isLoadingMore: boolean;
  error: string | null;
  fetch: (filters?: TransactionListFilters) => Promise<void>;
  loadMore: () => Promise<void>;
  refresh: () => Promise<void>;
}

export const useTransactionStore = create<TransactionState>((set, get) => ({
  items: [],
  pagination: null,
  filters: {},
  isLoading: false,
  isLoadingMore: false,
  error: null,

  fetch: async filters => {
    const nextFilters = filters ?? get().filters;
    set({isLoading: true, error: null, filters: nextFilters});
    try {
      const result = await transactionsApi.listTransactions({...nextFilters, page: 1});
      set({items: result.items, pagination: result.pagination, isLoading: false});
    } catch (error) {
      set({isLoading: false, error: (error as ApiError).message});
    }
  },

  loadMore: async () => {
    const {pagination, filters, items, isLoadingMore} = get();
    if (isLoadingMore || !pagination || pagination.current_page >= pagination.last_page) {
      return;
    }
    set({isLoadingMore: true});
    try {
      const result = await transactionsApi.listTransactions({
        ...filters,
        page: pagination.current_page + 1,
      });
      set({
        items: [...items, ...result.items],
        pagination: result.pagination,
        isLoadingMore: false,
      });
    } catch (error) {
      set({isLoadingMore: false, error: (error as ApiError).message});
    }
  },

  refresh: async () => {
    await get().fetch(get().filters);
  },
}));
