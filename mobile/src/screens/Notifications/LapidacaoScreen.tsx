import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {Button} from '../../components/Button';
import {EmptyState} from '../../components/EmptyState';
import {TransactionForm} from '../../components/TransactionForm';
import {theme} from '../../theme';
import {useNotifications} from '../../hooks/useNotifications';
import type {RootStackParamList} from '../../navigation/types';
import type {TransactionFormInput} from '../../types/transaction';

type Nav = NativeStackNavigationProp<RootStackParamList>;
type LapidacaoRoute = RouteProp<RootStackParamList, 'Lapidacao'>;

export default function LapidacaoScreen() {
  const navigation = useNavigation<Nav>();
  const {params} = useRoute<LapidacaoRoute>();
  const {captured, lapidar} = useNotifications();
  const item = captured.find(entry => entry.id === params.capturedId);

  if (!item) {
    return (
      <View style={styles.centered}>
        <EmptyState title="Movimentação não encontrada" description="Ela pode já ter sido lapidada ou ignorada.">
          <Button label="Voltar" onPress={() => navigation.goBack()} />
        </EmptyState>
      </View>
    );
  }

  const handleSubmit = async (input: TransactionFormInput) => {
    await lapidar(item.id, input);
    navigation.goBack();
  };

  return (
    <TransactionForm
      cardLastDigitsHint={item.cardLastDigits}
      defaultValues={{
        description: item.merchant ?? item.title ?? 'Movimentação',
        amount: item.amount !== null ? String(item.amount) : '',
        transactionDate: item.occurredAt.slice(0, 10),
      }}
      headerContent={
        <View style={styles.header}>
          <Text style={styles.merchant}>{item.merchant ?? item.title}</Text>
          {item.sourceAppLabel ? <Text style={styles.source}>{item.sourceAppLabel}</Text> : null}
        </View>
      }
      onSubmit={handleSubmit}
    />
  );
}

const styles = StyleSheet.create({
  centered: {flex: 1, justifyContent: 'center', backgroundColor: theme.colors.canvas},
  header: {marginBottom: theme.spacing.md},
  merchant: {fontSize: theme.font.size.xl, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  source: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: 2},
});
