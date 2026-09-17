/**
 * A API sempre trabalha com valores decimais em string ("100.00", ponto como
 * separador, exatamente 2 casas — ver backend/app/Helpers/Money.php). Nunca
 * usar float para montante de dinheiro neste app.
 */

const BRL_FORMATTER = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

/** "100.00" -> "R$ 100,00". Aceita null/undefined e devolve um placeholder. */
export function formatCurrency(amount: string | number | null | undefined): string {
  if (amount === null || amount === undefined || amount === '') {
    return '—';
  }
  const value = typeof amount === 'string' ? Number(amount) : amount;
  if (Number.isNaN(value)) {
    return '—';
  }
  return BRL_FORMATTER.format(value);
}

/**
 * Converte um valor digitado ou extraído de notificação ("152,80", "1.250,99",
 * "R$ 89,90", "89.90") para o formato decimal com ponto exigido pela API
 * ("152.80"). Retorna null se não for possível interpretar um número válido.
 */
export function parseAmountToApiDecimal(raw: string): string | null {
  const cleaned = raw.replace(/[^\d,.-]/g, '').trim();
  if (!cleaned) {
    return null;
  }

  const hasComma = cleaned.includes(',');
  const hasDot = cleaned.includes('.');
  let normalized = cleaned;

  if (hasComma && hasDot) {
    // Formato BR com milhar: "1.250,99" -> remove pontos de milhar, vírgula vira ponto.
    normalized = cleaned.replace(/\./g, '').replace(',', '.');
  } else if (hasComma) {
    // "152,80" -> "152.80"
    normalized = cleaned.replace(',', '.');
  }
  // Só ponto ("152.80" ou "1250.99"): já está no formato certo.

  const value = Number(normalized);
  if (Number.isNaN(value) || value < 0) {
    return null;
  }
  return value.toFixed(2);
}
