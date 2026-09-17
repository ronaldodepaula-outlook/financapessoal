import React from 'react';
import {ActivityIndicator, StyleSheet, Text, View} from 'react-native';
import {theme} from '../../theme';

export function Loading({label = 'Carregando...'}: {label?: string}) {
  return (
    <View style={styles.wrap}>
      <ActivityIndicator color={theme.colors.green} />
      <Text style={styles.label}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {flex: 1, alignItems: 'center', justifyContent: 'center', gap: 10, paddingVertical: 40},
  label: {color: theme.colors.muted, fontSize: theme.font.size.sm},
});
