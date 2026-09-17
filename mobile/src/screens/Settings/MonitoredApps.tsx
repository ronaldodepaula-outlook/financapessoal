import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {FlatList, StyleSheet, Switch, Text, View} from 'react-native';
import {Input} from '../../components/Input';
import {Loading} from '../../components/Loading';
import {EmptyState} from '../../components/EmptyState';
import {theme} from '../../theme';
import {
  getInstalledApps,
  getMonitoredPackages,
  setMonitoredPackages,
  type InstalledApp,
} from '../../services/notificationService';

export default function MonitoredAppsScreen() {
  const [apps, setApps] = useState<InstalledApp[]>([]);
  const [monitored, setMonitored] = useState<Set<string>>(new Set());
  const [search, setSearch] = useState('');
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const [installed, monitoredList] = await Promise.all([
        getInstalledApps(),
        getMonitoredPackages(),
      ]);
      setApps([...installed].sort((a, b) => a.label.localeCompare(b.label, 'pt-BR')));
      setMonitored(new Set(monitoredList));
      setIsLoading(false);
    })();
  }, []);

  const toggle = useCallback(
    (packageName: string) => {
      setMonitored(previous => {
        const next = new Set(previous);
        if (next.has(packageName)) {
          next.delete(packageName);
        } else {
          next.add(packageName);
        }
        setMonitoredPackages(Array.from(next)).catch(() => undefined);
        return next;
      });
    },
    [],
  );

  const filteredApps = useMemo(() => {
    if (!search.trim()) {
      return apps;
    }
    const term = search.trim().toLowerCase();
    return apps.filter(app => app.label.toLowerCase().includes(term));
  }, [apps, search]);

  if (isLoading) {
    return <Loading label="Carregando aplicativos instalados..." />;
  }

  return (
    <View style={styles.screen}>
      <Text style={styles.note}>
        Escolha quais aplicativos o app pode observar para capturar notificações financeiras
        automaticamente. Nenhum outro aplicativo é monitorado.
      </Text>
      <Input
        label="Buscar"
        placeholder="Nome do aplicativo"
        value={search}
        onChangeText={setSearch}
        autoCapitalize="none"
      />
      <FlatList
        data={filteredApps}
        keyExtractor={item => item.packageName}
        contentContainerStyle={styles.listContent}
        renderItem={({item}) => (
          <View style={styles.row}>
            <Text style={styles.label} numberOfLines={1}>
              {item.label}
            </Text>
            <Switch
              value={monitored.has(item.packageName)}
              onValueChange={() => toggle(item.packageName)}
              trackColor={{true: theme.colors.green}}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState title="Nenhum aplicativo encontrado" description="Tente buscar por outro nome." />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas, padding: theme.spacing.lg},
  note: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginBottom: theme.spacing.sm},
  listContent: {paddingBottom: theme.spacing.xxl},
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  label: {flex: 1, paddingRight: theme.spacing.sm, fontSize: theme.font.size.md, color: theme.colors.ink},
});
