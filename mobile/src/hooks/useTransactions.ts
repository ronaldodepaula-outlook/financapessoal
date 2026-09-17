import {useEffect} from 'react';
import {useTransactionStore} from '../store/transactionStore';
import type {TransactionListFilters} from '../types/transaction';

/** Busca a lista ao montar e expõe estado + ações da store de movimentações. */
export function useTransactions(initialFilters?: TransactionListFilters) {
  const items = useTransactionStore(state => state.items);
  const pagination = useTransactionStore(state => state.pagination);
  const isLoading = useTransactionStore(state => state.isLoading);
  const isLoadingMore = useTransactionStore(state => state.isLoadingMore);
  const error = useTransactionStore(state => state.error);
  const fetch = useTransactionStore(state => state.fetch);
  const loadMore = useTransactionStore(state => state.loadMore);
  const refresh = useTransactionStore(state => state.refresh);

  useEffect(() => {
    fetch(initialFilters);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(initialFilters)]);

  return {items, pagination, isLoading, isLoadingMore, error, fetch, loadMore, refresh};
}
