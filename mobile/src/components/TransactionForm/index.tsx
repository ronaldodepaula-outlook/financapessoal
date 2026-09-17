import React, {useEffect, useMemo, useState} from 'react';
import {Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {Controller, useForm} from 'react-hook-form';
import {zodResolver} from '@hookform/resolvers/zod';
import {z} from 'zod';
import {listAllCards, listAllAccounts} from '../../api/cards';
import {listAllCategories} from '../../api/categories';
import {Button} from '../Button';
import {Input} from '../Input';
import {Select} from '../Select';
import {theme} from '../../theme';
import {parseAmountToApiDecimal} from '../../utils/currency';
import {todayApiDate} from '../../utils/date';
import type {ApiError} from '../../types/api';
import type {Account, Card} from '../../types/card';
import type {Category} from '../../types/category';
import type {PaymentMethod, TransactionFormInput, TransactionType} from '../../types/transaction';

const PAYMENT_METHODS: Array<{value: PaymentMethod; label: string}> = [
  {value: 'PIX', label: 'Pix'},
  {value: 'DINHEIRO', label: 'Dinheiro'},
  {value: 'DEBITO', label: 'Débito'},
  {value: 'CREDITO', label: 'Crédito'},
  {value: 'TRANSFERENCIA', label: 'Transferência'},
  {value: 'BOLETO', label: 'Boleto'},
  {value: 'OUTROS', label: 'Outros'},
];

const schema = z
  .object({
    transactionType: z.enum(['DESPESA', 'RECEITA']),
    paymentMethod: z.enum(['PIX', 'DINHEIRO', 'DEBITO', 'CREDITO', 'TRANSFERENCIA', 'BOLETO', 'OUTROS']),
    cardId: z.number().optional(),
    accountId: z.number().optional(),
    categoryId: z.number({error: 'Selecione uma categoria.'}),
    subcategoryId: z.number().optional(),
    description: z.string().min(1, 'Informe uma descrição.'),
    amount: z
      .string()
      .min(1, 'Informe o valor.')
      .refine(value => parseAmountToApiDecimal(value) !== null, 'Informe um valor válido.'),
    transactionDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Use o formato AAAA-MM-DD.'),
    notes: z.string().optional(),
  })
  .superRefine((values, ctx) => {
    if (values.paymentMethod === 'CREDITO') {
      if (!values.cardId) {
        ctx.addIssue({code: z.ZodIssueCode.custom, path: ['cardId'], message: 'Selecione um cartão.'});
      }
    } else if (!values.accountId) {
      ctx.addIssue({code: z.ZodIssueCode.custom, path: ['accountId'], message: 'Selecione uma conta.'});
    }
  });

type FormValues = z.infer<typeof schema>;

export interface TransactionFormProps {
  /** Valores iniciais — usado tanto para pré-preencher com uma captura (lapidação) quanto para um lançamento em branco. */
  defaultValues?: Partial<TransactionFormInput>;
  /** Final dos 4 dígitos do cartão, quando conhecido (ex.: extraído de uma notificação) — pré-seleciona o cartão assim que a lista carrega. */
  cardLastDigitsHint?: string | null;
  /** Conteúdo extra exibido no topo do formulário (ex.: nome do estabelecimento capturado). */
  headerContent?: React.ReactNode;
  submitLabel?: string;
  onSubmit: (input: TransactionFormInput) => Promise<void>;
}

/**
 * Formulário completo de lançamento (receita ou despesa), reaproveitado tanto
 * pela lapidação de uma captura quanto pelo cadastro manual de uma movimentação
 * nova. Reproduz as mesmas regras de negócio já validadas pela API (ver
 * src/types/transaction.ts): crédito exige cartão sem conta, demais formas
 * exigem conta sem cartão; categoria principal do mesmo tipo; subcategoria
 * pertencente à categoria escolhida.
 */
export function TransactionForm({
  defaultValues,
  cardLastDigitsHint,
  headerContent,
  submitLabel = 'Salvar',
  onSubmit,
}: TransactionFormProps) {
  const [cards, setCards] = useState<Card[]>([]);
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const {
    control,
    handleSubmit,
    watch,
    setValue,
    formState: {errors},
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      transactionType: defaultValues?.transactionType ?? 'DESPESA',
      paymentMethod: defaultValues?.paymentMethod ?? 'PIX',
      cardId: defaultValues?.cardId,
      accountId: defaultValues?.accountId,
      categoryId: defaultValues?.categoryId,
      subcategoryId: defaultValues?.subcategoryId,
      description: defaultValues?.description ?? '',
      amount: defaultValues?.amount ?? '',
      transactionDate: defaultValues?.transactionDate ?? todayApiDate(),
      notes: defaultValues?.notes ?? '',
    },
  });

  const transactionType = watch('transactionType');
  const paymentMethod = watch('paymentMethod');
  const categoryId = watch('categoryId');

  useEffect(() => {
    listAllCards().then(setCards).catch(() => undefined);
    listAllAccounts().then(setAccounts).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (cardLastDigitsHint && cards.length > 0) {
      const match = cards.find(card => card.last_digits === cardLastDigitsHint);
      if (match) {
        setValue('paymentMethod', 'CREDITO');
        setValue('cardId', match.id);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cards]);

  useEffect(() => {
    listAllCategories(transactionType as TransactionType)
      .then(setCategories)
      .catch(() => undefined);
    // A categoria selecionada pertence ao tipo anterior — nunca fica válida ao trocar.
    setValue('categoryId', undefined as unknown as number);
    setValue('subcategoryId', undefined);
  }, [transactionType, setValue]);

  useEffect(() => {
    if (paymentMethod === 'CREDITO') {
      setValue('accountId', undefined);
    } else {
      setValue('cardId', undefined);
    }
  }, [paymentMethod, setValue]);

  const rootCategories = useMemo(
    () => categories.filter(category => category.parent_id === null),
    [categories],
  );
  const subcategories = useMemo(
    () => categories.filter(category => category.parent_id === categoryId),
    [categories, categoryId],
  );

  const onFormSubmit = handleSubmit(async values => {
    const amount = parseAmountToApiDecimal(values.amount);
    if (!amount) {
      return;
    }
    setIsSubmitting(true);
    try {
      await onSubmit({
        transactionType: values.transactionType,
        categoryId: values.categoryId,
        subcategoryId: values.subcategoryId,
        accountId: values.accountId,
        cardId: values.cardId,
        paymentMethod: values.paymentMethod,
        description: values.description,
        amount,
        transactionDate: values.transactionDate,
        notes: values.notes || undefined,
      });
    } catch (error) {
      Alert.alert('Não foi possível salvar', (error as ApiError).message);
    } finally {
      setIsSubmitting(false);
    }
  });

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
      {headerContent}

      <View style={styles.typeRow}>
        {(['DESPESA', 'RECEITA'] as TransactionType[]).map(type => (
          <TouchableOpacity
            key={type}
            style={[styles.typeChip, transactionType === type && styles.typeChipActive]}
            onPress={() => setValue('transactionType', type)}>
            <Text style={[styles.typeChipLabel, transactionType === type && styles.typeChipLabelActive]}>
              {type === 'DESPESA' ? 'Despesa' : 'Receita'}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <Controller
        control={control}
        name="amount"
        render={({field: {value, onChange, onBlur}}) => (
          <Input
            label="Valor"
            value={value}
            onChangeText={onChange}
            onBlur={onBlur}
            error={errors.amount?.message}
            keyboardType="decimal-pad"
            placeholder="0,00"
          />
        )}
      />

      <Controller
        control={control}
        name="transactionDate"
        render={({field: {value, onChange, onBlur}}) => (
          <Input
            label="Data"
            value={value}
            onChangeText={onChange}
            onBlur={onBlur}
            error={errors.transactionDate?.message}
            placeholder="AAAA-MM-DD"
          />
        )}
      />

      <Controller
        control={control}
        name="paymentMethod"
        render={({field: {value, onChange}}) => (
          <Select label="Forma de pagamento" value={value} onChange={onChange} options={PAYMENT_METHODS} />
        )}
      />

      {paymentMethod === 'CREDITO' ? (
        <Controller
          control={control}
          name="cardId"
          render={({field: {value, onChange}}) => (
            <Select
              label="Cartão"
              value={value}
              onChange={onChange}
              error={errors.cardId?.message}
              options={cards.map(card => ({value: card.id, label: card.name}))}
            />
          )}
        />
      ) : (
        <Controller
          control={control}
          name="accountId"
          render={({field: {value, onChange}}) => (
            <Select
              label="Conta"
              value={value}
              onChange={onChange}
              error={errors.accountId?.message}
              options={accounts.map(account => ({value: account.id, label: account.name}))}
            />
          )}
        />
      )}

      <Controller
        control={control}
        name="categoryId"
        render={({field: {value, onChange}}) => (
          <Select
            label="Categoria"
            value={value}
            onChange={onChange}
            error={errors.categoryId?.message}
            options={rootCategories.map(category => ({value: category.id, label: category.name}))}
          />
        )}
      />

      {subcategories.length > 0 ? (
        <Controller
          control={control}
          name="subcategoryId"
          render={({field: {value, onChange}}) => (
            <Select
              label="Subcategoria"
              value={value}
              onChange={onChange}
              placeholder="Nenhuma"
              options={subcategories.map(category => ({value: category.id, label: category.name}))}
            />
          )}
        />
      ) : null}

      <Controller
        control={control}
        name="description"
        render={({field: {value, onChange, onBlur}}) => (
          <Input label="Descrição" value={value} onChangeText={onChange} onBlur={onBlur} error={errors.description?.message} />
        )}
      />

      <Controller
        control={control}
        name="notes"
        render={({field: {value, onChange, onBlur}}) => (
          <Input label="Observação" value={value} onChangeText={onChange} onBlur={onBlur} multiline placeholder="Opcional" />
        )}
      />

      <Button label={submitLabel} onPress={onFormSubmit} loading={isSubmitting} style={styles.submit} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas},
  container: {padding: theme.spacing.lg, paddingBottom: theme.spacing.xxl},
  typeRow: {flexDirection: 'row', gap: 8, marginBottom: theme.spacing.md},
  typeChip: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 10,
    borderRadius: theme.radius.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: '#fff',
  },
  typeChipActive: {backgroundColor: theme.colors.green, borderColor: theme.colors.green},
  typeChipLabel: {fontSize: theme.font.size.sm, fontWeight: '700', color: theme.colors.muted},
  typeChipLabelActive: {color: '#fff'},
  submit: {marginTop: theme.spacing.sm},
});
