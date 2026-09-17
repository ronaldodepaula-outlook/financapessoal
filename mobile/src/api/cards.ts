import {api, unwrap} from './client';
import type {ApiEnvelope, ListParams, Paginated} from '../types/api';
import type {Account, Card} from '../types/card';

export function listCards(filters: ListParams = {}): Promise<Paginated<Card>> {
  return unwrap(api.get<ApiEnvelope<Paginated<Card>>>('/cards', {params: filters}));
}

export async function listAllCards(): Promise<Card[]> {
  const items: Card[] = [];
  let page = 1;
  for (;;) {
     
    const result = await listCards({page, per_page: 100, status: 'ATIVO'});
    items.push(...result.items);
    if (page >= result.pagination.last_page) {
      break;
    }
    page += 1;
  }
  return items;
}

export function listAccounts(filters: ListParams = {}): Promise<Paginated<Account>> {
  return unwrap(api.get<ApiEnvelope<Paginated<Account>>>('/accounts', {params: filters}));
}

export async function listAllAccounts(): Promise<Account[]> {
  const items: Account[] = [];
  let page = 1;
  for (;;) {
     
    const result = await listAccounts({page, per_page: 100, status: 'ATIVO'});
    items.push(...result.items);
    if (page >= result.pagination.last_page) {
      break;
    }
    page += 1;
  }
  return items;
}
