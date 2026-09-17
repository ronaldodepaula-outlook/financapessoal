/**
 * Formato do envelope de resposta usado por toda a API Laravel existente
 * (backend/app/Helpers/ApiResponse.php). Nunca desembrulhar manualmente —
 * o client em src/api/client.ts já devolve apenas `data`.
 */
export interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  errors: string[] | Record<string, string[]>;
}

export interface Pagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Paginated<T> {
  items: T[];
  pagination: Pagination;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}

/** Erro normalizado lançado pelo client Axios para todo o app (ver src/api/client.ts). */
export class ApiError extends Error {
  readonly status: number | undefined;
  readonly fieldErrors: Record<string, string[]> | undefined;
  readonly isNetworkError: boolean;

  constructor(params: {
    message: string;
    status?: number;
    fieldErrors?: Record<string, string[]>;
    isNetworkError?: boolean;
  }) {
    super(params.message);
    this.name = 'ApiError';
    this.status = params.status;
    this.fieldErrors = params.fieldErrors;
    this.isNetworkError = params.isNetworkError ?? false;
  }
}
