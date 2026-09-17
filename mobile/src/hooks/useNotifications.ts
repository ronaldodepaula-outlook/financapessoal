import {useEffect} from 'react';
import {selectPending, selectQueued, useNotificationStore} from '../store/notificationStore';

/** Expõe a fila de capturas (pendentes/aguardando envio) e recarrega ao montar. */
export function useNotifications() {
  const captured = useNotificationStore(state => state.captured);
  const isLoading = useNotificationStore(state => state.isLoading);
  const refresh = useNotificationStore(state => state.refresh);
  const ignore = useNotificationStore(state => state.ignore);
  const lapidar = useNotificationStore(state => state.lapidar);
  const syncNow = useNotificationStore(state => state.syncNow);

  useEffect(() => {
    refresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return {
    captured,
    pending: selectPending(captured),
    queued: selectQueued(captured),
    isLoading,
    refresh,
    ignore,
    lapidar,
    syncNow,
  };
}
