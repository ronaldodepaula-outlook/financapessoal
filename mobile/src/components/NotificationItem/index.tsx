import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {theme} from '../../theme';
import {formatCurrency} from '../../utils/currency';
import {formatRelativeShort} from '../../utils/date';
import type {CapturedTransaction} from '../../types/notification';
import {Button} from '../Button';

/** Cartão de uma movimentação capturada, usado na tela de Pendências. */
export function NotificationItem({
  item,
  onLapidar,
  onIgnorar,
}: {
  item: CapturedTransaction;
  onLapidar: () => void;
  onIgnorar: () => void;
}) {
  return (
    <View style={styles.card}>
      <Text style={styles.merchant}>{item.merchant ?? 'Estabelecimento não identificado'}</Text>
      <Text style={styles.amount}>{formatCurrency(item.amount)}</Text>
      <Text style={styles.meta}>
        {formatRelativeShort(item.occurredAt.slice(0, 10))}
        {item.sourceAppLabel ? ` • ${item.sourceAppLabel}` : ''}
        {item.cardLastDigits ? ` • Cartão final ${item.cardLastDigits}` : ''}
      </Text>
      {item.syncError ? <Text style={styles.error}>{item.syncError}</Text> : null}
      <View style={styles.actions}>
        <Button label="Ignorar" variant="secondary" onPress={onIgnorar} style={styles.actionButton} />
        <Button label="Lapidar" onPress={onLapidar} style={styles.actionButton} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.md,
    gap: 4,
  },
  merchant: {fontSize: theme.font.size.md, fontWeight: '700', color: theme.colors.ink},
  amount: {fontSize: theme.font.size.xl, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  meta: {fontSize: theme.font.size.xs, color: theme.colors.muted},
  error: {fontSize: theme.font.size.xs, color: theme.colors.red, marginTop: 2},
  actions: {flexDirection: 'row', gap: 10, marginTop: 10},
  actionButton: {flex: 1},
});
