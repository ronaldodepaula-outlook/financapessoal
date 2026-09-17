/**
 * Finança Pessoal — app mobile
 * @format
 */

import React, {useEffect} from 'react';
import {StatusBar, StyleSheet, View} from 'react-native';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {AppNavigator} from './src/navigation/AppNavigator';
import {Loading} from './src/components/Loading';
import {useAuth} from './src/hooks/useAuth';
import {useAuthStore} from './src/store/authStore';
import {useNotificationStore} from './src/store/notificationStore';
import {watchConnectivityAndSync} from './src/services/syncService';
import {theme} from './src/theme';

function App() {
  const {status} = useAuth();
  const bootstrap = useAuthStore(state => state.bootstrap);
  const startCapture = useNotificationStore(state => state.startCapture);
  const syncNow = useNotificationStore(state => state.syncNow);

  useEffect(() => {
    bootstrap();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (status !== 'signed-in') {
      return undefined;
    }
    let unsubscribeCapture: (() => void) | undefined;
    startCapture().then(unsubscribe => {
      unsubscribeCapture = unsubscribe;
    });
    const unsubscribeConnectivity = watchConnectivityAndSync();
    syncNow().catch(() => undefined);

    return () => {
      unsubscribeCapture?.();
      unsubscribeConnectivity();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  return (
    <SafeAreaProvider>
      <StatusBar barStyle="dark-content" />
      <View style={styles.container}>
        {status === 'booting' ? <Loading label="Preparando o app..." /> : <AppNavigator />}
      </View>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: theme.colors.canvas},
});

export default App;
