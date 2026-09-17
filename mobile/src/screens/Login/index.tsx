import React from 'react';
import {KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Controller, useForm} from 'react-hook-form';
import {zodResolver} from '@hookform/resolvers/zod';
import {z} from 'zod';
import {Button} from '../../components/Button';
import {Input} from '../../components/Input';
import {useAuth} from '../../hooks/useAuth';
import {theme} from '../../theme';

const loginSchema = z.object({
  email: z.string().min(1, 'Informe seu e-mail.').email('Informe um e-mail válido.'),
  password: z.string().min(1, 'Informe sua senha.'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function LoginScreen() {
  const {login, isSubmitting, error} = useAuth();
  const {
    control,
    handleSubmit,
    formState: {errors},
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {email: '', password: ''},
  });

  const onSubmit = handleSubmit(async values => {
    try {
      await login(values);
    } catch {
      // Erro amigável já fica disponível em useAuth().error e é exibido abaixo.
    }
  });

  return (
    <SafeAreaView style={styles.flex} edges={['top', 'bottom']}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          contentContainerStyle={styles.container}
          keyboardShouldPersistTaps="handled">
          <View style={styles.header}>
            <Text style={styles.brand}>Finança Pessoal</Text>
            <Text style={styles.subtitle}>Entre com sua conta para continuar.</Text>
          </View>

          <View style={styles.form}>
            <Controller
              control={control}
              name="email"
              render={({field: {value, onChange, onBlur}}) => (
                <Input
                  label="E-mail"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.email?.message}
                  placeholder="voce@exemplo.com"
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="email-address"
                  textContentType="emailAddress"
                />
              )}
            />

            <Controller
              control={control}
              name="password"
              render={({field: {value, onChange, onBlur}}) => (
                <Input
                  label="Senha"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.password?.message}
                  placeholder="Sua senha"
                  secureTextEntry
                  textContentType="password"
                />
              )}
            />

            {error ? <Text style={styles.formError}>{error}</Text> : null}

            <Button
              label="Entrar"
              onPress={onSubmit}
              loading={isSubmitting}
              disabled={isSubmitting}
              style={styles.submit}
            />
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  flex: {flex: 1, backgroundColor: theme.colors.canvas},
  container: {flexGrow: 1, justifyContent: 'center', padding: theme.spacing.xl},
  header: {marginBottom: theme.spacing.xxl, alignItems: 'center'},
  brand: {
    fontSize: theme.font.size.xxl,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.green,
  },
  subtitle: {
    fontSize: theme.font.size.sm,
    color: theme.colors.muted,
    marginTop: theme.spacing.xs,
    textAlign: 'center',
  },
  form: {width: '100%'},
  formError: {
    color: theme.colors.red,
    fontSize: theme.font.size.sm,
    marginBottom: theme.spacing.md,
    textAlign: 'center',
  },
  submit: {marginTop: theme.spacing.sm},
});
