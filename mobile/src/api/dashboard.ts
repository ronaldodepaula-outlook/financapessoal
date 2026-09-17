import {api, unwrap} from './client';
import type {ApiEnvelope} from '../types/api';
import type {DashboardData} from '../types/dashboard';

export function getDashboard(year: number, month: number): Promise<DashboardData> {
  return unwrap(
    api.get<ApiEnvelope<DashboardData>>('/dashboard', {params: {year, month}}),
  );
}
