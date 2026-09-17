import React from 'react';
import {StyleSheet, Text, TouchableOpacity} from 'react-native';
import {theme} from '../../theme';

export interface FabProps {
  onPress: () => void;
  label?: string;
  accessibilityLabel?: string;
}

/**
 * Botão flutuante posicionado de forma absoluta — não interfere no layout do
 * conteúdo da tela (ScrollView etc.), só flutua por cima no canto inferior direito.
 */
export function Fab({onPress, label = '+', accessibilityLabel = 'Nova movimentação'}: FabProps) {
  return (
    <TouchableOpacity
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      onPress={onPress}
      style={styles.fab}
      activeOpacity={0.85}>
      <Text style={styles.label}>{label}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  fab: {
    position: 'absolute',
    right: theme.spacing.lg,
    bottom: theme.spacing.xl,
    width: 58,
    height: 58,
    borderRadius: theme.radius.pill,
    backgroundColor: theme.colors.green,
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 3},
    shadowOpacity: 0.25,
    shadowRadius: 5,
  },
  label: {color: '#fff', fontSize: 30, fontWeight: '600', marginTop: -2},
});
