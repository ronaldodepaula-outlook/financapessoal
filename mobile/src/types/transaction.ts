export type TransactionType = 'RECEITA' | 'DESPESA';

export type PaymentMethod =
  | 'PIX'
  | 'DINHEIRO'
  | 'DEBITO'
  | 'CREDITO'
  | 'TRANSFERENCIA'
  | 'BOLETO'
  | 'OUTROS';

export type TransactionStatus = 'PENDENTE' | 'PAGA' | 'CANCELADA';

/**
 * Espelha exatamente o recurso retornado por GET/POST/PUT /transactions
 * (backend/app/Http/Controllers/TransactionController.php). `amount` é uma
 * string decimal ("100.00") — nunca converter para float para exibir/enviar,
 * usar src/utils/currency.ts.
 */
export interface Transaction {
  id: number;
  description: string;
  transaction_type: TransactionType;
  account_id: number | null;
  card_id: number | null;
  category_id: number;
  subcategory_id: number | null;
  merchant_id: number | null;
  card_invoice_id: number | null;
  amount: string;
  payment_method: PaymentMethod;
  transaction_date: string;
  due_date: string | null;
  competence_year: number;
  competence_month: number;
  status: TransactionStatus;
  is_fixed: boolean;
  notes: string | null;
  loan_id: number | null;
  cash_flow_effect: boolean;
}

/**
 * Payload aceito por POST /transactions. Regras de negócio já existentes na API
 * (backend/app/Services/FinancialReferences.php) e que o app deve respeitar:
 * - payment_method === 'CREDITO' exige card_id e NÃO aceita account_id;
 * - qualquer outra forma de pagamento exige account_id e NÃO aceita card_id;
 * - category_id deve ser uma categoria principal (sem parent_id) do mesmo
 *   transaction_type; subcategory_id, se enviado, deve pertencer a category_id.
 */
export interface TransactionCreatePayload {
  description: string;
  transaction_type: TransactionType;
  category_id: number;
  amount: string;
  payment_method: PaymentMethod;
  transaction_date: string;
  competence_year: number;
  competence_month: number;
  account_id?: number;
  card_id?: number;
  subcategory_id?: number;
  merchant_id?: number;
  card_invoice_id?: number;
  due_date?: string;
  status?: TransactionStatus;
  is_fixed?: boolean;
  notes?: string;
}

export type TransactionUpdatePayload = Partial<TransactionCreatePayload>;

/**
 * Dados coletados pelo formulário de lançamento (src/components/TransactionForm),
 * usado tanto para criar uma movimentação nova quanto para lapidar uma captura.
 * Mesmas regras de negócio documentadas em TransactionCreatePayload acima.
 */
export interface TransactionFormInput {
  transactionType: TransactionType;
  categoryId: number;
  subcategoryId?: number;
  accountId?: number;
  cardId?: number;
  paymentMethod: PaymentMethod;
  description: string;
  amount: string;
  transactionDate: string;
  notes?: string;
}

/** Converte os dados do formulário para o payload que a API espera, já calculando a competência. */
export function transactionFormInputToPayload(
  input: TransactionFormInput,
  status: TransactionStatus = 'PAGA',
): TransactionCreatePayload {
  return {
    description: input.description,
    transaction_type: input.transactionType,
    category_id: input.categoryId,
    subcategory_id: input.subcategoryId,
    account_id: input.accountId,
    card_id: input.cardId,
    payment_method: input.paymentMethod,
    amount: input.amount,
    transaction_date: input.transactionDate,
    competence_year: Number(input.transactionDate.slice(0, 4)),
    competence_month: Number(input.transactionDate.slice(5, 7)),
    status,
    notes: input.notes,
  };
}

export interface TransactionListFilters {
  page?: number;
  per_page?: number;
  status?: TransactionStatus;
  search?: string;
  date_from?: string;
  date_to?: string;
  category_id?: number;
  account_id?: number;
  card_id?: number;
  merchant_id?: number;
  transaction_type?: TransactionType;
  competence_year?: number;
  competence_month?: number;
}
