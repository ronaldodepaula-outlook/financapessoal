import React from 'react';
import {ActivityIndicator, StyleSheet, Text, TouchableOpacity, ViewStyle} from 'react-native';
import {theme} from '../../theme';

export type ButtonVariant = 'primary' | 'secondary' | 'danger';

export interface ButtonProps {
  label: string;
  onPress: () => void;
  variant?: ButtonVariant;
  disabled?: boolean;
  loading?: boolean;
  style?: ViewStyle;
}

export function Button({label, onPress, variant = 'primary', disabled, loading, style}: ButtonProps) {
  const isDisabled = disabled || loading;
  return (
    <TouchableOpacity
      accessibilityRole="button"
      accessibilityState={{disabled: isDisabled}}
      onPress={onPress}
      disabled={isDisabled}
      style={[styles.base, styles[variant], isDisabled && styles.disabled, style]}>
      {loading ? (
        <ActivityIndicator color={variant === 'secondary' ? theme.colors.green : '#fff'} />
      ) : (
        <Text style={[styles.label, variant === 'secondary' && styles.labelSecondary]}>{label}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: 48,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.lg,
  },
  primary: {backgroundColor: theme.colors.green},
  secondary: {backgroundColor: '#fff', borderWidth: 1, borderColor: theme.colors.border},
  danger: {backgroundColor: theme.colors.red},
  disabled: {opacity: 0.5},
  label: {color: '#fff', fontWeight: theme.font.weight.bold, fontSize: theme.font.size.md},
  labelSecondary: {color: theme.colors.green},
});
