export type CategoryType = 'RECEITA' | 'DESPESA';
export type RecordStatus = 'ATIVO' | 'INATIVO';

/** Espelha GET/POST/PUT /categories. parent_id nulo = categoria principal. */
export interface Category {
  id: number;
  name: string;
  type: CategoryType;
  parent_id: number | null;
  status: RecordStatus;
}
