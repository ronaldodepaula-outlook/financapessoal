import {useAuthStore} from '../store/authStore';

export function useAuth() {
  const status = useAuthStore(state => state.status);
  const user = useAuthStore(state => state.user);
  const error = useAuthStore(state => state.error);
  const isSubmitting = useAuthStore(state => state.isSubmitting);
  const login = useAuthStore(state => state.login);
  const logout = useAuthStore(state => state.logout);

  return {
    status,
    user,
    error,
    isSubmitting,
    isAuthenticated: status === 'signed-in',
    login,
    logout,
  };
}
