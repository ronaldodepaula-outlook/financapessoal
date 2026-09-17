import React from 'react';
import {StyleSheet, Text, TextInput, TextInputProps, View} from 'react-native';
import {theme} from '../../theme';

export interface InputProps extends TextInputProps {
  label: string;
  error?: string;
}

export function Input({label, error, style, ...rest}: InputProps) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        placeholderTextColor="#acb6ad"
        style={[styles.input, error ? styles.inputError : null, style]}
        {...rest}
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {marginBottom: theme.spacing.md},
  label: {
    fontSize: theme.font.size.sm,
    fontWeight: '700',
    color: '#506559',
    marginBottom: 6,
  },
  input: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: '#dfe6dc',
    borderRadius: theme.radius.sm,
    paddingHorizontal: 12,
    fontSize: theme.font.size.md,
    color: theme.colors.ink,
    backgroundColor: '#fff',
  },
  inputError: {borderColor: theme.colors.red},
  error: {color: theme.colors.red, fontSize: theme.font.size.xs, marginTop: 4},
});
