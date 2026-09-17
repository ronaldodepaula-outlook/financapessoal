import React, {useCallback, useEffect, useState} from 'react';
import {Alert, ScrollView, StyleSheet, Text, View} from 'react-native';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {cancelTransaction, getTransaction, updateTransaction} from '../../api/transactions';
import {listAllCategories} from '../../api/categories';
import {Badge, statusTone} from '../../components/CategoryBadge';
import {Button} from '../../components/Button';
import {Card} from '../../components/Card';
import {EmptyState} from '../../components/EmptyState';
import {Input} from '../../components/Input';
import {Loading} from '../../components/Loading';
import {Select} from '../../components/Select';
import {theme} from '../../theme';
import {formatCurrency} from '../../utils/currency';
import {formatApiDate} from '../../utils/date';
import type {RootStackParamList} from '../../navigation/types';
import type {ApiError} from '../../types/api';
import type {Category} from '../../types/category';
import type {Transaction} from '../../types/transaction';

type Nav = NativeStackNavigationProp<RootStackParamList>;
type DetailRoute = RouteProp<RootStackParamList, 'TransactionDetail'>;

const PAYMENT_METHOD_LABELS: Record<Transaction['payment_method'], string> = {
  PIX: 'Pix',
  DINHEIRO: 'Dinheiro',
  DEBITO: 'Débito',
  CREDITO: 'Crédito',
  TRANSFERENCIA: 'Transferência',
  BOLETO: 'Boleto',
  OUTROS: 'Outros',
};

export default function TransactionDetailScreen() {
  const navigation = useNavigation<Nav>();
  const {params} = useRoute<DetailRoute>();
  const [transaction, setTransaction] = useState<Transaction | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [description, setDescription] = useState('');
  const [notes, setNotes] = useState('');
  const [categoryId, setCategoryId] = useState<number | undefined>(undefined);
  const [subcategoryId, setSubcategoryId] = useState<number | undefined>(undefined);
  const [isSaving, setIsSaving] = useState(false);
  const [isCancelling, setIsCancelling] = useState(false);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const found = await getTransaction(params.id);
      setTransaction(found);
      setDescription(found.description);
      setNotes(found.notes ?? '');
      setCategoryId(found.category_id);
      setSubcategoryId(found.subcategory_id ?? undefined);
      const allCategories = await listAllCategories(found.transaction_type);
      setCategories(allCategories);
    } catch (err) {
      setError((err as ApiError).message ?? 'Não foi possível carregar esta movimentação.');
    } finally {
      setIsLoading(false);
    }
  }, [params.id]);

  useEffect(() => {
    load();
  }, [load]);

  const rootCategories = categories.filter(category => category.parent_id === null);
  const subcategories = categories.filter(category => category.parent_id === categoryId);

  const handleSave = async () => {
    if (!transaction) {
      return;
    }
    setIsSaving(true);
    try {
      const updated = await updateTransaction(transaction.id, {
        description,
        notes: notes || undefined,
        category_id: categoryId,
        subcategory_id: subcategoryId,
      });
      setTransaction(updated);
      Alert.alert('Salvo', 'Movimentação atualizada.');
    } catch (err) {
      Alert.alert('Não foi possível salvar', (err as ApiError).message);
    } finally {
      setIsSaving(false);
    }
  };

  const handleCancel = () => {
    if (!transaction) {
      return;
    }
    Alert.alert(
      'Cancelar movimentação',
      'O lançamento será cancelado e o histórico preservado. Deseja continuar?',
      [
        {text: 'Voltar', style: 'cancel'},
        {
          text: 'Cancelar movimentação',
          style: 'destructive',
          onPress: async () => {
            setIsCancelling(true);
            try {
              await cancelTransaction(transaction.id);
              navigation.goBack();
            } catch (err) {
              Alert.alert('Não foi possível cancelar', (err as ApiError).message);
            } finally {
              setIsCancelling(false);
            }
          },
        },
      ],
    );
  };

  if (isLoading) {
    return <Loading label="Carregando movimentação..." />;
  }

  if (error || !transaction) {
    return (
      <View style={styles.centered}>
        <EmptyState title="Não foi possível carregar" description={error ?? 'Movimentação não encontrada.'}>
          <Button label="Tentar novamente" onPress={load} />
        </EmptyState>
      </View>
    );
  }

  const isExpense = transaction.transaction_type === 'DESPESA';
  const isCancelled = transaction.status === 'CANCELADA';

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container}>
      <Card>
        <Text style={[styles.amount, isExpense ? styles.amountExpense : styles.amountIncome]}>
          {formatCurrency(transaction.amount)}
        </Text>
        <Text style={styles.description}>{transaction.description}</Text>
        <View style={styles.badgeRow}>
          <Badge label={transaction.status} tone={statusTone(transaction.status)} />
          <Badge label={PAYMENT_METHOD_LABELS[transaction.payment_method]} />
          {transaction.is_fixed ? <Badge label="Fixa" /> : null}
        </View>
        <InfoRow label="Data" value={formatApiDate(transaction.transaction_date)} />
        {transaction.due_date ? (
          <InfoRow label="Vencimento" value={formatApiDate(transaction.due_date)} />
        ) : null}
      </Card>

      <Card style={styles.formCard}>
        <Text style={styles.sectionTitle}>Ajustar</Text>
        <Input label="Descrição" value={description} onChangeText={setDescription} editable={!isCancelled} />
        <Select
          label="Categoria"
          value={categoryId}
          onChange={value => {
            setCategoryId(value);
            setSubcategoryId(undefined);
          }}
          options={rootCategories.map(category => ({value: category.id, label: category.name}))}
        />
        {subcategories.length > 0 ? (
          <Select
            label="Subcategoria"
            value={subcategoryId}
            onChange={setSubcategoryId}
            placeholder="Nenhuma"
            options={subcategories.map(category => ({value: category.id, label: category.name}))}
          />
        ) : null}
        <Input
          label="Observação"
          value={notes}
          onChangeText={setNotes}
          editable={!isCancelled}
          multiline
        />
        <Button label="Salvar alterações" onPress={handleSave} loading={isSaving} disabled={isCancelled} />
      </Card>

      {!isCancelled ? (
        <Button
          label="Cancelar movimentação"
          variant="danger"
          onPress={handleCancel}
          loading={isCancelling}
          style={styles.cancelButton}
        />
      ) : null}
    </ScrollView>
  );
}

function InfoRow({label, value}: {label: string; value: string}) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas},
  container: {padding: theme.spacing.lg, gap: theme.spacing.md, paddingBottom: theme.spacing.xxl},
  centered: {flex: 1, justifyContent: 'center', backgroundColor: theme.colors.canvas},
  amount: {fontSize: theme.font.size.xxl, fontWeight: theme.font.weight.bold},
  amountExpense: {color: theme.colors.red},
  amountIncome: {color: theme.colors.green},
  description: {fontSize: theme.font.size.lg, fontWeight: '700', color: theme.colors.ink, marginTop: 4},
  badgeRow: {flexDirection: 'row', gap: 6, marginTop: theme.spacing.sm, flexWrap: 'wrap'},
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: theme.spacing.sm,
    paddingTop: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  infoLabel: {color: theme.colors.muted, fontSize: theme.font.size.sm},
  infoValue: {color: theme.colors.ink, fontSize: theme.font.size.sm, fontWeight: '700'},
  formCard: {gap: 0},
  sectionTitle: {
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
    marginBottom: theme.spacing.sm,
  },
  cancelButton: {marginTop: theme.spacing.sm},
});
