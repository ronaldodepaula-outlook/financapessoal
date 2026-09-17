import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {theme} from '../../theme';
import type {FinancialInsight, InsightTone} from '../../utils/insights';

const TONE_COLOR: Record<InsightTone, string> = {
  positive: theme.colors.green,
  warning: theme.colors.amber,
  neutral: theme.colors.blue,
};

export function InsightsCard({insights}: {insights: FinancialInsight[]}) {
  return (
    <View style={styles.card}>
      <Text style={styles.title}>Insights financeiros</Text>
      <Text style={styles.subtitle}>Observações automáticas com base nos seus lançamentos deste mês.</Text>
      <View style={styles.list}>
        {insights.map(insight => (
          <View key={insight.id} style={styles.row}>
            <View style={[styles.marker, {backgroundColor: TONE_COLOR[insight.tone]}]} />
            <Text style={styles.text}>{insight.text}</Text>
          </View>
        ))}
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
  },
  title: {fontSize: theme.font.size.md, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  subtitle: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: 2, marginBottom: theme.spacing.sm},
  list: {gap: theme.spacing.sm},
  row: {flexDirection: 'row', gap: 10, alignItems: 'flex-start'},
  marker: {width: 8, height: 8, borderRadius: 4, marginTop: 5},
  text: {flex: 1, fontSize: theme.font.size.sm, color: theme.colors.ink, lineHeight: 19},
});
