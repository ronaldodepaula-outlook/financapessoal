import type {RecordStatus} from './category';

/** Espelha GET/POST/PUT /cards. Valores monetários são strings decimais. */
export interface Card {
  id: number;
  name: string;
  institution: string | null;
  brand: string | null;
  last_digits: string | null;
  credit_limit: string;
  closing_day: number;
  due_day: number;
  status: RecordStatus;
  used_limit: string;
  available_limit: string;
}

export type AccountType =
  | 'CONTA_CORRENTE'
  | 'POUPANCA'
  | 'CARTEIRA'
  | 'CONTA_DIGITAL'
  | 'INVESTIMENTO';

/** Espelha GET/POST/PUT /accounts. */
export interface Account {
  id: number;
  name: string;
  institution: string | null;
  account_type: AccountType;
  initial_balance: string;
  status: RecordStatus;
  current_balance: string;
}
