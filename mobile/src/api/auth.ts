import {api, unwrap} from './client';
import type {ApiEnvelope} from '../types/api';
import type {AuthUser, LoginPayload, LoginResponse} from '../types/auth';

export function login(payload: LoginPayload): Promise<LoginResponse> {
  return unwrap(api.post<ApiEnvelope<LoginResponse>>('/auth/login', payload));
}

export function me(): Promise<AuthUser> {
  return unwrap(api.get<ApiEnvelope<AuthUser>>('/auth/me'));
}

export function logout(): Promise<null> {
  return unwrap(api.post<ApiEnvelope<null>>('/auth/logout'));
}
