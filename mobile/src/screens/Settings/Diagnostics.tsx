import React, {useCallback, useState} from 'react';
import {Alert, ScrollView, StyleSheet, Text, View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import {Button} from '../../components/Button';
import {Card} from '../../components/Card';
import {theme} from '../../theme';
import {API_BASE_URL} from '../../constants/config';
import {useAuth} from '../../hooks/useAuth';
import {useNotifications} from '../../hooks/useNotifications';
import {isOnline} from '../../services/syncService';
import {getMonitoredPackages, isNotificationAccessEnabled} from '../../services/notificationService';

export default function DiagnosticsScreen() {
  const {user} = useAuth();
  const {pending, queued, syncNow} = useNotifications();
  const [apiOnline, setApiOnline] = useState<boolean | null>(null);
  const [accessEnabled, setAccessEnabled] = useState<boolean | null>(null);
  const [monitoredCount, setMonitoredCount] = useState(0);
  const [isSyncing, setIsSyncing] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      (async () => {
        const [online, access, monitored] = await Promise.all([
          isOnline(),
          isNotificationAccessEnabled(),
          getMonitoredPackages(),
        ]);
        if (active) {
          setApiOnline(online);
          setAccessEnabled(access);
          setMonitoredCount(monitored.length);
        }
      })();
      return () => {
        active = false;
      };
    }, []),
  );

  const handleSync = async () => {
    setIsSyncing(true);
    try {
      const result = await syncNow();
      Alert.alert('Sincronização', `${result.synced} enviada(s), ${result.failed} com falha.`);
    } finally {
      setIsSyncing(false);
    }
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container}>
      <Card style={styles.card}>
        <Row label="API" value={apiOnline === null ? 'Verificando...' : apiOnline ? 'ONLINE' : 'OFFLINE'} />
        <Row label="URL" value={API_BASE_URL} />
        <Row label="AUTH" value={user ? `OK (${user.email})` : 'ERRO'} />
        <Row
          label="Acesso a notificações"
          value={accessEnabled === null ? 'Verificando...' : accessEnabled ? 'OK' : 'Não concedido'}
        />
        <Row label="Aplicativos monitorados" value={String(monitoredCount)} />
        <Row label="Pendências" value={String(pending.length)} />
        <Row label="Aguardando sincronização" value={String(queued.length)} />
      </Card>

      <Button label="Sincronizar agora" onPress={handleSync} loading={isSyncing} />
    </ScrollView>
  );
}

function Row({label, value}: {label: string; value: string}) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value} numberOfLines={2}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas},
  container: {padding: theme.spacing.lg, gap: theme.spacing.md, paddingBottom: theme.spacing.xxl},
  card: {gap: 0},
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    gap: theme.spacing.sm,
  },
  label: {fontSize: theme.font.size.sm, color: theme.colors.muted, fontWeight: '600'},
  value: {fontSize: theme.font.size.sm, color: theme.colors.ink, fontWeight: '700', flexShrink: 1, textAlign: 'right'},
});
