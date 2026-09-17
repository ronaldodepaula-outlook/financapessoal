import React, {useCallback, useState} from 'react';
import {StyleSheet, Switch, Text, View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import {Button} from '../../components/Button';
import {Card} from '../../components/Card';
import {theme} from '../../theme';
import {
  isNotificationAccessEnabled,
  openNotificationAccessSettings,
} from '../../services/notificationService';
import {isCaptureEnabled, setCaptureEnabled} from '../../services/storageService';

export default function NotificationAccessScreen() {
  const [accessEnabled, setAccessEnabled] = useState<boolean | null>(null);
  const [captureOn, setCaptureOn] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      (async () => {
        const [access, capture] = await Promise.all([
          isNotificationAccessEnabled(),
          isCaptureEnabled(),
        ]);
        if (active) {
          setAccessEnabled(access);
          setCaptureOn(capture);
        }
      })();
      return () => {
        active = false;
      };
    }, []),
  );

  const handleToggleCapture = async (value: boolean) => {
    setCaptureOn(value);
    await setCaptureEnabled(value);
  };

  return (
    <View style={styles.screen}>
      <Text style={styles.title}>Captura de notificações</Text>
      <Text style={styles.description}>
        Para registrar automaticamente suas movimentações, o aplicativo precisa acessar as
        notificações financeiras recebidas no dispositivo.
      </Text>

      <Card style={styles.card}>
        <View style={styles.row}>
          <Text style={styles.label}>Acesso do Android</Text>
          <Text style={accessEnabled ? styles.statusOn : styles.statusOff}>
            {accessEnabled === null ? 'Verificando...' : accessEnabled ? 'Ativado' : 'Não ativado'}
          </Text>
        </View>
        {!accessEnabled ? (
          <Button
            label="Ativar captura"
            onPress={openNotificationAccessSettings}
            style={styles.button}
          />
        ) : null}
      </Card>

      {accessEnabled ? (
        <Card style={styles.card}>
          <View style={styles.row}>
            <View style={styles.switchText}>
              <Text style={styles.label}>Processar capturas</Text>
              <Text style={styles.helper}>
                Com o acesso já concedido, escolha se o app deve efetivamente registrar o que
                observar nos aplicativos monitorados.
              </Text>
            </View>
            <Switch value={captureOn} onValueChange={handleToggleCapture} trackColor={{true: theme.colors.green}} />
          </View>
        </Card>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas, padding: theme.spacing.lg, gap: theme.spacing.md},
  title: {fontSize: theme.font.size.xl, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  description: {fontSize: theme.font.size.sm, color: theme.colors.muted, lineHeight: 20},
  card: {gap: theme.spacing.sm},
  row: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  switchText: {flex: 1, paddingRight: theme.spacing.sm},
  label: {fontSize: theme.font.size.md, fontWeight: '700', color: theme.colors.ink},
  helper: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: 4},
  statusOn: {color: theme.colors.green, fontWeight: '700'},
  statusOff: {color: theme.colors.muted, fontWeight: '700'},
  button: {marginTop: theme.spacing.sm},
});
