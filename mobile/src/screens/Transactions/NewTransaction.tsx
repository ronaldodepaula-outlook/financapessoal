import React from 'react';
import {useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {createTransaction} from '../../api/transactions';
import {TransactionForm} from '../../components/TransactionForm';
import {useTransactionStore} from '../../store/transactionStore';
import {transactionFormInputToPayload} from '../../types/transaction';
import type {RootStackParamList} from '../../navigation/types';
import type {TransactionFormInput} from '../../types/transaction';

type Nav = NativeStackNavigationProp<RootStackParamList>;

/** Cadastro manual de uma movimentação nova (receita ou despesa), aberto pelo botão flutuante do Dashboard. */
export default function NewTransactionScreen() {
  const navigation = useNavigation<Nav>();

  const handleSubmit = async (input: TransactionFormInput) => {
    await createTransaction(transactionFormInputToPayload(input));
    // Atualiza a lista de Movimentações em segundo plano para refletir o novo lançamento
    // na próxima vez que a tela for exibida, sem travar o retorno do usuário ao Dashboard.
    useTransactionStore.getState().refresh().catch(() => undefined);
    navigation.goBack();
  };

  return <TransactionForm submitLabel="Salvar movimentação" onSubmit={handleSubmit} />;
}
