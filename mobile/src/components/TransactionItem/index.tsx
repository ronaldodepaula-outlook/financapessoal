import React from 'react';
import {StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {theme} from '../../theme';
import {formatCurrency} from '../../utils/currency';
import {formatRelativeShort} from '../../utils/date';
import type {Transaction} from '../../types/transaction';
import {Badge, statusTone} from '../CategoryBadge';

export function TransactionItem({
  transaction,
  cardName,
  categoryName,
  subcategoryName,
  onPress,
}: {
  transaction: Transaction;
  cardName?: string | null;
  categoryName?: string | null;
  subcategoryName?: string | null;
  onPress?: () => void;
}) {
  const isExpense = transaction.transaction_type === 'DESPESA';
  return (
    <TouchableOpacity
      accessibilityRole="button"
      onPress={onPress}
      disabled={!onPress}
      style={styles.row}>
      <View style={styles.info}>
        <Text style={styles.description} numberOfLines={1}>
          {transaction.description}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {formatRelativeShort(transaction.transaction_date)}
          {cardName ? ` • ${cardName}` : ''}
        </Text>
        <View style={styles.badgeRow}>
          {categoryName ? <Badge label={subcategoryName ? `${categoryName} › ${subcategoryName}` : categoryName} /> : null}
          <Badge label={transaction.status} tone={statusTone(transaction.status)} />
        </View>
      </View>
      <Text style={[styles.amount, isExpense ? styles.expense : styles.income]}>
        {isExpense ? '- ' : '+ '}
        {formatCurrency(transaction.amount)}
      </Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#edf1ec',
    gap: 12,
  },
  info: {flex: 1, gap: 4},
  description: {fontSize: theme.font.size.md, fontWeight: '700', color: theme.colors.ink},
  meta: {fontSize: theme.font.size.xs, color: theme.colors.muted},
  badgeRow: {flexDirection: 'row', gap: 6, marginTop: 4, flexWrap: 'wrap'},
  amount: {fontSize: theme.font.size.md, fontWeight: theme.font.weight.bold},
  expense: {color: theme.colors.ink},
  income: {color: theme.colors.green},
});
