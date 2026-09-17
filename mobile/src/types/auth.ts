export interface AuthUser {
  id: number;
  name: string;
  email: string;
  status: 'ATIVO' | 'INATIVO';
}

/** Espelha exatamente o payload de POST /auth/login e POST /auth/refresh. */
export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  refresh_expires_at: string;
}

export interface LoginResponse extends AuthTokens {
  user: AuthUser;
}

export interface LoginPayload {
  email: string;
  password: string;
}
