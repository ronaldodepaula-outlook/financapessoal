import {api, unwrap} from './client';
import type {ApiEnvelope, ListParams, Paginated} from '../types/api';
import type {Category, CategoryType} from '../types/category';

export interface CategoryListFilters extends ListParams {
  type?: CategoryType;
  parent_id?: number | null;
}

export function listCategories(filters: CategoryListFilters = {}): Promise<Paginated<Category>> {
  return unwrap(api.get<ApiEnvelope<Paginated<Category>>>('/categories', {params: filters}));
}

/** Busca todas as páginas — telas de seleção (lapidação) precisam da lista completa. */
export async function listAllCategories(type?: CategoryType): Promise<Category[]> {
  const items: Category[] = [];
  let page = 1;
  for (;;) {
     
    const result = await listCategories({page, per_page: 100, type, status: 'ATIVO'});
    items.push(...result.items);
    if (page >= result.pagination.last_page) {
      break;
    }
    page += 1;
  }
  return items;
}
