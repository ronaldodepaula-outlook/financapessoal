import {api, unwrap} from './client';
import type {ApiEnvelope} from '../types/api';
import type {DashboardGroup} from '../types/dashboard';

export type ReportGroupBy = 'category' | 'subcategory' | 'account' | 'card' | 'merchant' | 'fixed' | 'month' | 'year';

export interface ReportSummary {
  filters: Record<string, unknown>;
  totals: Record<string, string>;
  groups: DashboardGroup[];
  records: number;
}

/**
 * GET /reports/summary — endpoint já existente na API (backend/app/Services/ReportService.php::report),
 * usado aqui só para o agrupamento por estabelecimento, que /dashboard não devolve.
 */
export function getReportSummary(params: {
  year: number;
  month: number;
  group_by: ReportGroupBy;
}): Promise<ReportSummary> {
  return unwrap(api.get<ApiEnvelope<ReportSummary>>('/reports/summary', {params}));
}
