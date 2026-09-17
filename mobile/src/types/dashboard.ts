import type {TransactionStatus} from './transaction';

/** Grupo genérico devolvido em by_category/by_account/by_card/future_installments. */
export interface DashboardGroup {
  key: string;
  label: string;
  income: string;
  expenses: string;
  pending_income: string;
  pending_expenses: string;
  count: number;
  /** Só presente em by_category (ReportService::categoryDetail). */
  subcategories?: DashboardGroup[];
}

export interface DashboardSummary {
  income: string;
  expenses: string;
  cash_expenses: string;
  expected_income: string;
  committed_expenses: string;
  committed_cash_expenses: string;
  fixed_expenses: string;
  variable_expenses: string;
  installments: string;
  payroll_loan: string;
  payroll_already_deducted: string;
  balance: string;
  projected_balance: string;
  planned_amount: string;
  actual_amount: string;
  budget_usage_percent: string | null;
}

export interface DashboardUpcomingItem {
  id: number;
  description: string;
  amount: string;
  due_date: string | null;
  transaction_date: string;
  status: TransactionStatus;
  category?: {name: string} | null;
  subcategory?: {name: string} | null;
}

export interface DashboardAccountBalance {
  id: number;
  name: string;
  current_balance: string;
}

/**
 * Só o subconjunto de GET /dashboard (backend/app/Services/ReportService.php)
 * que o app mobile consome. O endpoint devolve bem mais campos (budget,
 * fortnight, annual_evolution, highlights...) que a versão mobile não exibe.
 */
export interface DashboardData {
  year: number;
  month: number;
  summary: DashboardSummary;
  by_category: DashboardGroup[];
  by_account: DashboardGroup[];
  by_card: DashboardGroup[];
  monthly_evolution: Array<{month: number; label: string; income: string; expenses: string}>;
  accounts: DashboardAccountBalance[];
  upcoming: DashboardUpcomingItem[];
  warnings: string[];
}
