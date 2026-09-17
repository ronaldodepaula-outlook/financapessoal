import React, {useCallback, useEffect, useState} from 'react';
import {FlatList, StyleSheet, Text, View} from 'react-native';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {listTransactions} from '../../api/transactions';
import {Button} from '../../components/Button';
import {EmptyState} from '../../components/EmptyState';
import {Loading} from '../../components/Loading';
import {TransactionItem} from '../../components/TransactionItem';
import {theme} from '../../theme';
import {formatCurrency} from '../../utils/currency';
import type {RootStackParamList} from '../../navigation/types';
import type {ApiError} from '../../types/api';
import type {Transaction} from '../../types/transaction';

type Nav = NativeStackNavigationProp<RootStackParamList>;
type FilteredTransactionsRoute = RouteProp<RootStackParamList, 'FilteredTransactions'>;

/**
 * Movimentações filtradas por categoria/subcategoria OU por estabelecimento,
 * no mês do Dashboard — aberta ao tocar numa fatia do gráfico, numa
 * categoria/subcategoria da lista, ou num dos "Top 10 estabelecimentos".
 * A API não tem filtro por subcategory_id em /transactions (só category_id
 * e merchant_id), então quando o filtro é por subcategoria buscamos por
 * category_id (até 100 lançamentos, suficiente para um mês) e filtramos por
 * subcategoria no próprio aparelho.
 */
export default function FilteredTransactionsScreen() {
  const navigation = useNavigation<Nav>();
  const {params} = useRoute<FilteredTransactionsRoute>();
  const [items, setItems] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const result = await listTransactions({
        category_id: params.categoryId,
        merchant_id: params.merchantId,
        competence_year: params.year,
        competence_month: params.month,
        per_page: 100,
      });
      let filtered = result.items;
      if (params.subcategoryId) {
        filtered = filtered.filter(item => item.subcategory_id === params.subcategoryId);
      } else if (params.onlyUncategorizedSubcategory) {
        filtered = filtered.filter(item => item.subcategory_id === null);
      }
      setItems(filtered);
    } catch (err) {
      setError((err as ApiError).message ?? 'Não foi possível carregar as movimentações.');
    } finally {
      setIsLoading(false);
    }
  }, [
    params.categoryId,
    params.merchantId,
    params.subcategoryId,
    params.onlyUncategorizedSubcategory,
    params.year,
    params.month,
  ]);

  useEffect(() => {
    load();
  }, [load]);

  const total = items.reduce((sum, item) => sum + Number(item.amount), 0);

  if (isLoading) {
    return <Loading label="Carregando movimentações..." />;
  }

  if (error) {
    return (
      <View style={styles.centered}>
        <EmptyState title="Não foi possível carregar" description={error}>
          <Button label="Tentar novamente" onPress={load} />
        </EmptyState>
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <View style={styles.header}>
        <Text style={styles.headerLabel}>{params.title}</Text>
        <Text style={styles.headerTotal}>{formatCurrency(String(total.toFixed(2)))}</Text>
      </View>
      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={styles.listContent}
        renderItem={({item}) => (
          <TransactionItem transaction={item} onPress={() => navigation.navigate('TransactionDetail', {id: item.id})} />
        )}
        ListEmptyComponent={
          <EmptyState
            title="Nenhuma movimentação encontrada"
            description="Não há lançamentos pagos ou pendentes aqui neste mês."
          />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas},
  centered: {flex: 1, justifyContent: 'center', backgroundColor: theme.colors.canvas},
  header: {
    padding: theme.spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    backgroundColor: '#fff',
  },
  headerLabel: {fontSize: theme.font.size.sm, color: theme.colors.muted, fontWeight: '700'},
  headerTotal: {fontSize: theme.font.size.xl, fontWeight: theme.font.weight.bold, color: theme.colors.ink, marginTop: 4},
  listContent: {paddingHorizontal: theme.spacing.lg, paddingBottom: theme.spacing.xxl, flexGrow: 1},
});
