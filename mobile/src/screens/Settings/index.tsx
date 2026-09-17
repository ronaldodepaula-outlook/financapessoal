import React from 'react';
import {ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {Button} from '../../components/Button';
import {theme} from '../../theme';
import {useAuth} from '../../hooks/useAuth';
import type {RootStackParamList} from '../../navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const LINKS: Array<{label: string; description: string; screen: keyof RootStackParamList}> = [
  {
    label: 'Aplicativos monitorados',
    description: 'Escolha quais apps podem ter notificações capturadas',
    screen: 'MonitoredApps',
  },
  {
    label: 'Captura de notificações',
    description: 'Status do acesso do Android e sincronização',
    screen: 'NotificationAccess',
  },
  {
    label: 'Categorias',
    description: 'Consultar categorias e subcategorias',
    screen: 'Categories',
  },
  {
    label: 'Diagnóstico',
    description: 'Status da API, conexão e fila local',
    screen: 'Diagnostics',
  },
];

export default function SettingsScreen() {
  const navigation = useNavigation<Nav>();
  const {user, logout} = useAuth();

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container}>
      <Text style={styles.title}>Mais</Text>

      <View style={styles.profile}>
        <Text style={styles.profileName}>{user?.name}</Text>
        <Text style={styles.profileEmail}>{user?.email}</Text>
      </View>

      <View style={styles.linkList}>
        {LINKS.map(link => (
          <TouchableOpacity
            key={link.screen}
            style={styles.linkRow}
            onPress={() => navigation.navigate(link.screen as never)}>
            <View style={styles.linkText}>
              <Text style={styles.linkLabel}>{link.label}</Text>
              <Text style={styles.linkDescription}>{link.description}</Text>
            </View>
            <Text style={styles.chevron}>›</Text>
          </TouchableOpacity>
        ))}
      </View>

      <Button label="Sair" variant="danger" onPress={logout} style={styles.logout} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas},
  container: {padding: theme.spacing.lg, paddingBottom: theme.spacing.xxl, gap: theme.spacing.lg},
  title: {fontSize: theme.font.size.xxl, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  profile: {
    backgroundColor: '#fff',
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
  },
  profileName: {fontSize: theme.font.size.lg, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  profileEmail: {fontSize: theme.font.size.sm, color: theme.colors.muted, marginTop: 2},
  linkList: {gap: theme.spacing.sm},
  linkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  linkText: {flex: 1, paddingRight: theme.spacing.sm},
  linkLabel: {fontSize: theme.font.size.md, fontWeight: '700', color: theme.colors.ink},
  linkDescription: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: 2},
  chevron: {fontSize: theme.font.size.xl, color: theme.colors.muted},
  logout: {marginTop: theme.spacing.md},
});
