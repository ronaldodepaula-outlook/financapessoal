import React, {PropsWithChildren} from 'react';
import {StyleSheet, Text, View, ViewStyle} from 'react-native';
import {theme} from '../../theme';

export function Card({children, style}: PropsWithChildren<{style?: ViewStyle}>) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export type StatCardTone = 'default' | 'expense' | 'featured';

export function StatCard({
  title,
  value,
  footer,
  tone = 'default',
}: {
  title: string;
  value: string;
  footer?: string;
  tone?: StatCardTone;
}) {
  return (
    <View style={[styles.statCard, tone === 'featured' && styles.statCardFeatured]}>
      <Text style={[styles.statTitle, tone === 'featured' && styles.statTitleFeatured]}>{title}</Text>
      <Text
        style={[
          styles.statValue,
          tone === 'expense' && styles.statValueExpense,
          tone === 'featured' && styles.statValueFeatured,
        ]}>
        {value}
      </Text>
      {footer ? (
        <Text style={[styles.statFooter, tone === 'featured' && styles.statFooterFeatured]}>{footer}</Text>
      ) : null}
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
  },
  statCard: {
    flex: 1,
    minWidth: 150,
    backgroundColor: '#fff',
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  statCardFeatured: {backgroundColor: theme.colors.greenDark, borderColor: theme.colors.greenDark},
  statTitle: {fontSize: theme.font.size.xs, color: theme.colors.muted, fontWeight: '600'},
  statTitleFeatured: {color: '#c0d6ca'},
  statValue: {
    fontSize: theme.font.size.xl,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
    marginTop: 6,
  },
  statValueExpense: {color: theme.colors.red},
  statValueFeatured: {color: '#fff'},
  statFooter: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: 4},
  statFooterFeatured: {color: '#c0d6ca'},
});
