import React, {useMemo, useState} from 'react';
import {FlatList, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {Button} from '../../components/Button';
import {EmptyState} from '../../components/EmptyState';
import {Loading} from '../../components/Loading';
import {TransactionItem} from '../../components/TransactionItem';
import {useTransactions} from '../../hooks/useTransactions';
import {theme} from '../../theme';
import type {RootStackParamList} from '../../navigation/types';
import type {TransactionListFilters, TransactionStatus, TransactionType} from '../../types/transaction';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const STATUS_FILTERS: Array<{label: string; value: TransactionStatus | undefined}> = [
  {label: 'Todas', value: undefined},
  {label: 'Pendentes', value: 'PENDENTE'},
  {label: 'Pagas', value: 'PAGA'},
];

const TYPE_FILTERS: Array<{label: string; value: TransactionType | undefined}> = [
  {label: 'Todos', value: undefined},
  {label: 'Despesas', value: 'DESPESA'},
  {label: 'Receitas', value: 'RECEITA'},
];

export default function TransactionsScreen() {
  const navigation = useNavigation<Nav>();
  const [status, setStatus] = useState<TransactionStatus | undefined>(undefined);
  const [type, setType] = useState<TransactionType | undefined>(undefined);

  const filters = useMemo<TransactionListFilters>(
    () => ({status, transaction_type: type}),
    [status, type],
  );

  const {items, pagination, isLoading, isLoadingMore, error, loadMore, refresh} =
    useTransactions(filters);

  const canLoadMore = Boolean(pagination && pagination.current_page < pagination.last_page);

  return (
    <View style={styles.screen}>
      <Text style={styles.title}>Movimentações</Text>

      <View style={styles.filterGroup}>
        <FilterRow options={STATUS_FILTERS} selected={status} onSelect={setStatus} />
        <FilterRow options={TYPE_FILTERS} selected={type} onSelect={setType} />
      </View>

      {isLoading && items.length === 0 ? (
        <Loading label="Carregando movimentações..." />
      ) : error && items.length === 0 ? (
        <EmptyState title="Não foi possível carregar" description={error}>
          <Button label="Tentar novamente" onPress={refresh} />
        </EmptyState>
      ) : (
        <FlatList
          data={items}
          keyExtractor={item => String(item.id)}
          renderItem={({item}) => (
            <TransactionItem
              transaction={item}
              onPress={() => navigation.navigate('TransactionDetail', {id: item.id})}
            />
          )}
          contentContainerStyle={styles.listContent}
          refreshing={isLoading}
          onRefresh={refresh}
          onEndReachedThreshold={0.4}
          onEndReached={() => {
            if (canLoadMore) {
              loadMore();
            }
          }}
          ListFooterComponent={isLoadingMore ? <Loading label="Carregando mais..." /> : undefined}
          ListEmptyComponent={
            <EmptyState
              title="Nenhuma movimentação encontrada"
              description="Ajuste os filtros ou cadastre um novo lançamento pelo aplicativo web."
            />
          }
        />
      )}
    </View>
  );
}

function FilterRow<T extends string | undefined>({
  options,
  selected,
  onSelect,
}: {
  options: Array<{label: string; value: T}>;
  selected: T;
  onSelect: (value: T) => void;
}) {
  return (
    <View style={styles.filterRow}>
      {options.map(option => {
        const active = option.value === selected;
        return (
          <TouchableOpacity
            key={option.label}
            onPress={() => onSelect(option.value)}
            style={[styles.filterChip, active && styles.filterChipActive]}>
            <Text style={[styles.filterChipLabel, active && styles.filterChipLabelActive]}>
              {option.label}
            </Text>
          </TouchableOpacity>
        );
      })}
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
    marginBottom: theme.spacing.sm,
  },
  filterGroup: {gap: theme.spacing.xs, marginBottom: theme.spacing.sm},
  filterRow: {flexDirection: 'row', gap: 8, flexWrap: 'wrap'},
  filterChip: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: theme.radius.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: '#fff',
  },
  filterChipActive: {backgroundColor: theme.colors.green, borderColor: theme.colors.green},
  filterChipLabel: {fontSize: theme.font.size.xs, color: theme.colors.muted, fontWeight: '700'},
  filterChipLabelActive: {color: '#fff'},
  listContent: {paddingBottom: theme.spacing.xxl, flexGrow: 1},
});
