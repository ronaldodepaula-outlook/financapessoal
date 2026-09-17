# Definições financeiras pendentes

Estas dúvidas foram apresentadas ao usuário. A resposta com as credenciais do banco não define as regras abaixo e não será interpretada como confirmação delas.

1. **Consignado:** o prompt informa 24 parcelas e intervalo outubro/2026–outubro/2028. Primeira parcela em outubro/2026 implica última em setembro/2028. Contratação em outubro/2026 com primeira parcela em novembro/2026 permite término em outubro/2028. Confirmar a data do primeiro vencimento e seu dia. Até lá, não gerar cronograma nem despesas do contrato.
2. **Renda disponível:** confirmar se R$ 6.480 já é líquido do consignado. Evitar descontar R$ 1.024,77 duas vezes. A configuração permanecerá NULL até confirmação.
3. **Receitas quinzenais:** períodos e valores estão definidos (01–15: R$ 3.780; 16–31: R$ 2.700), mas os dias exatos de recebimento não foram informados. Não inventar as datas de crédito.
4. **Saldo do consignado:** principal contratado não equivale à soma de prestações futuras. Exibir separadamente principal, prestações futuras e saldo informado, sem calcular amortizações/quitação a partir de suposições.
5. **Contas fixas:** valor inicial ainda não definido; orçamento inicial configurável, sem despesa fictícia.

As fases 6–8 já oferecem os módulos e validações. A inicialização por usuário prepara o consignado como RASCUNHO, receitas como inativas e metas orçamentárias editáveis. Nenhuma data exata de pagamento foi inventada. A ativação deve ocorrer pela API após informar conta/categoria, primeiro vencimento e tratamento da renda. O modo FOLHA exige confirmação explícita de renda líquida; o modo CONTA registra desembolso na conta indicada.

Amortização registra o desembolso efetivo e preserva o cronograma contratado. Para saldo atualizado, registrar novo saldo informado. A API não estima desconto, juros ou novo prazo. Quitação usa o valor efetivamente informado e cancela apenas parcelas pendentes, com possibilidade de estorno. Pagamento parcial de uma parcela não está disponível; o endpoint exige o valor integral.
