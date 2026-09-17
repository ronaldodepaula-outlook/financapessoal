import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {theme} from '../../theme';

export type BadgeTone = 'success' | 'warning' | 'neutral' | 'danger';

const TONE_STYLES: Record<BadgeTone, {bg: string; fg: string}> = {
  success: {bg: '#edf5eb', fg: '#5b8956'},
  warning: {bg: '#fcf5e7', fg: '#ac853c'},
  neutral: {bg: '#f0f2f0', fg: '#839088'},
  danger: {bg: '#fdf0ed', fg: theme.colors.red},
};

export function Badge({label, tone = 'neutral'}: {label: string; tone?: BadgeTone}) {
  const palette = TONE_STYLES[tone];
  return (
    <View style={[styles.badge, {backgroundColor: palette.bg}]}>
      <Text style={[styles.label, {color: palette.fg}]}>{label}</Text>
    </View>
  );
}

/** Rótulo de categoria/subcategoria — usado nas listas de movimentações e lapidação. */
export function CategoryBadge({category, subcategory}: {category?: string | null; subcategory?: string | null}) {
  const label = subcategory ? `${category ?? ''} › ${subcategory}` : category ?? 'Sem categoria';
  return <Badge label={label} tone="neutral" />;
}

export function statusTone(status: string): BadgeTone {
  if (status === 'PAGA' || status === 'PROCESSADO') return 'success';
  if (status === 'PENDENTE' || status === 'PENDENTE_LAPIDACAO' || status === 'AGUARDANDO_SINCRONIZACAO') return 'warning';
  if (status === 'CANCELADA' || status === 'IGNORADA') return 'danger';
  return 'neutral';
}

const styles = StyleSheet.create({
  badge: {borderRadius: 6, paddingVertical: 4, paddingHorizontal: 8, alignSelf: 'flex-start'},
  label: {fontSize: theme.font.size.xs, fontWeight: '700'},
});
