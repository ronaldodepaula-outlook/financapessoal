import React from 'react';
import {Alert, FlatList, RefreshControl, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {EmptyState} from '../../components/EmptyState';
import {NotificationItem} from '../../components/NotificationItem';
import {theme} from '../../theme';
import {useNotifications} from '../../hooks/useNotifications';
import {formatCurrency} from '../../utils/currency';
import {formatRelativeShort} from '../../utils/date';
import type {RootStackParamList} from '../../navigation/types';
import type {CapturedTransaction} from '../../types/notification';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function PendingScreen() {
  const navigation = useNavigation<Nav>();
  const {pending, queued, isLoading, refresh, ignore} = useNotifications();

  const handleIgnore = (item: CapturedTransaction) => {
    Alert.alert('Ignorar movimentação', 'Esta captura não vai virar um lançamento. Continuar?', [
      {text: 'Voltar', style: 'cancel'},
      {text: 'Ignorar', style: 'destructive', onPress: () => ignore(item.id)},
    ]);
  };

  return (
    <View style={styles.screen}>
      <Text style={styles.title}>Lapidar movimentações</Text>
      <Text style={styles.counter}>{pending.length} movimentações pendentes</Text>

      <FlatList
        data={pending}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} colors={[theme.colors.green]} tintColor={theme.colors.green} />
        }
        renderItem={({item}) => (
          <NotificationItem
            item={item}
            onLapidar={() => navigation.navigate('Lapidacao', {capturedId: item.id})}
            onIgnorar={() => handleIgnore(item)}
          />
        )}
        ListEmptyComponent={
          <EmptyState
            title="Nenhuma pendência"
            description="Suas próximas compras capturadas vão aparecer aqui, prontas para você lapidar."
          />
        }
        ListFooterComponent={
          queued.length > 0 ? (
            <View style={styles.queuedSection}>
              <Text style={styles.queuedTitle}>Aguardando conexão ({queued.length})</Text>
              {queued.map(item => (
                <View key={item.id} style={styles.queuedRow}>
                  <Text style={styles.queuedMerchant} numberOfLines={1}>
                    {item.merchant ?? item.title}
                  </Text>
                  <Text style={styles.queuedMeta}>
                    {formatCurrency(item.amount)} • {formatRelativeShort(item.occurredAt.slice(0, 10))}
                  </Text>
                </View>
              ))}
            </View>
          ) : undefined
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas, paddingHorizontal: theme.spacing.lg},
  title: {
    fontSize: theme.font.size.xxl,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
    marginTop: theme.spacing.lg,
  },
  counter: {fontSize: theme.font.size.sm, color: theme.colors.muted, marginTop: 4, marginBottom: theme.spacing.md},
  listContent: {paddingBottom: theme.spacing.xxl, flexGrow: 1},
  queuedSection: {marginTop: theme.spacing.lg, gap: theme.spacing.xs},
  queuedTitle: {fontSize: theme.font.size.sm, fontWeight: '700', color: theme.colors.muted},
  queuedRow: {
    backgroundColor: '#fff',
    borderRadius: theme.radius.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.sm,
  },
  queuedMerchant: {fontSize: theme.font.size.sm, fontWeight: '700', color: theme.colors.ink},
  queuedMeta: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: 2},
});
