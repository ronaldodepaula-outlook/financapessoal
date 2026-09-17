import React, {PropsWithChildren} from 'react';
import {StyleSheet, Text, View} from 'react-native';
import {theme} from '../../theme';

export function EmptyState({
  title,
  description,
  children,
}: PropsWithChildren<{title: string; description?: string}>) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>{title}</Text>
      {description ? <Text style={styles.description}>{description}</Text> : null}
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {alignItems: 'center', paddingVertical: 32, paddingHorizontal: 24, gap: 10},
  title: {fontSize: theme.font.size.lg, fontWeight: theme.font.weight.bold, color: theme.colors.ink, textAlign: 'center'},
  description: {fontSize: theme.font.size.sm, color: theme.colors.muted, textAlign: 'center', lineHeight: 20},
});
