import {parseAmountToApiDecimal} from './currency';
import {epochMillisToIso} from './date';
import type {CapturedTransaction, RawNotificationEvent} from '../types/notification';

/**
 * Palavras que indicam que uma notificação provavelmente é financeira.
 * Configurável pelo usuário (ver screens/Settings + MonitoredApps) — esta
 * lista é só o padrão inicial, nunca a única fonte de verdade em runtime.
 */
export const DEFAULT_FINANCIAL_KEYWORDS = [
  'compra',
  'compras',
  'cartão',
  'cartao',
  'pagamento',
  'pagou',
  'aprovada',
  'aprovado',
  'débito',
  'debito',
  'crédito',
  'credito',
  'pix',
  'recebido',
  'recebeu',
  'fatura',
  'boleto',
  'saque',
];

export interface ParserOptions {
  /** Substitui a lista padrão de palavras-chave (nunca concatenar, o chamador decide). */
  financialKeywords?: string[];
}

const AMOUNT_WITH_PREFIX = /R\$\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2}|\d+\.\d{2}|\d+)/i;
const AMOUNT_BARE = /\b(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2}|\d+\.\d{2})\b/;
const CARD_DIGITS_NAMED = /(?:final|cart[aã]o)\D{0,12}(\d{4})\b/i;
const CARD_DIGITS_MASKED = /(?:•{2,}|\*{2,}|x{2,})\s*(\d{4})\b/i;

function containsFinancialKeyword(haystack: string, keywords: string[]): boolean {
  const normalized = haystack.toLowerCase();
  return keywords.some(keyword => normalized.includes(keyword.toLowerCase()));
}

function extractAmount(text: string): {amount: number | null; matchedText: string | null} {
  const prefixed = text.match(AMOUNT_WITH_PREFIX);
  if (prefixed) {
    const parsed = parseAmountToApiDecimal(prefixed[1]);
    return {amount: parsed ? Number(parsed) : null, matchedText: prefixed[0]};
  }
  const bare = text.match(AMOUNT_BARE);
  if (bare) {
    const parsed = parseAmountToApiDecimal(bare[0]);
    return {amount: parsed ? Number(parsed) : null, matchedText: bare[0]};
  }
  return {amount: null, matchedText: null};
}

function extractCardLastDigits(text: string): string | null {
  return text.match(CARD_DIGITS_NAMED)?.[1] ?? text.match(CARD_DIGITS_MASKED)?.[1] ?? null;
}

/**
 * Extração best-effort do estabelecimento. Quando não há uma linha claramente
 * distinta do valor/rótulos genéricos, devolve null de propósito — o parser
 * não deve "adivinhar" o estabelecimento, isso é papel da lapidação.
 */
function extractMerchant(lines: string[], amountText: string | null): string | null {
  const candidates = lines
    .map(line => line.trim())
    .filter(Boolean)
    .filter(line => !amountText || !line.includes(amountText))
    .filter(line => !/cart[aã]o\s+final/i.test(line))
    .filter(line => !/^(compra|compras|pagamento|transfer[eê]ncia|pix)\b\s*(aprovad|realizad|no cart)/i.test(line))
    .filter(line => !/^(compra|compras|pagamento|transfer[eê]ncia|pix)$/i.test(line));

  if (candidates.length === 0) {
    return null;
  }
  const mostlyUpper = candidates.find(
    line => line === line.toUpperCase() && /[A-ZÀ-Ú]/.test(line),
  );
  return mostlyUpper ?? candidates[0];
}

/**
 * Chave de deduplicação combinando app de origem, valor, estabelecimento,
 * final do cartão e um bucket de minuto do horário — evita que a mesma compra
 * (ex.: notificação atualizada/reenviada pelo banco) vire dois registros.
 */
export function buildDedupeKey(input: {
  sourceApp: string;
  amount: number | null;
  merchant: string | null;
  cardLastDigits: string | null;
  occurredAt: string;
}): string {
  const minuteBucket = input.occurredAt.slice(0, 16);
  return [
    input.sourceApp,
    input.amount !== null ? input.amount.toFixed(2) : 'na',
    (input.merchant ?? 'na').toLowerCase().trim(),
    input.cardLastDigits ?? 'na',
    minuteBucket,
  ].join('|');
}

/**
 * Converte uma notificação crua do Android em um CapturedTransaction genérico,
 * ou null quando ela não parece ser financeira. Nunca lança exceção — entradas
 * inesperadas resultam em campos incertos como null, nunca em erro.
 */
export function parseNotification(
  event: RawNotificationEvent,
  options: ParserOptions = {},
): CapturedTransaction | null {
  const keywords = options.financialKeywords ?? DEFAULT_FINANCIAL_KEYWORDS;
  const title = (event.title ?? '').trim();
  const body = (event.bigText || event.text || '').trim();
  const haystack = `${title}\n${body}`;

  if (!containsFinancialKeyword(haystack, keywords)) {
    return null;
  }

  const {amount, matchedText} = extractAmount(haystack);
  const cardLastDigits = extractCardLastDigits(haystack);
  const lines = [title, ...body.split('\n')];
  const merchant = extractMerchant(lines, matchedText);
  const occurredAt = epochMillisToIso(event.postedAt);
  const dedupeKey = buildDedupeKey({sourceApp: event.packageName, amount, merchant, cardLastDigits, occurredAt});

  return {
    id: `${event.packageName}-${event.postedAt}-${Math.random().toString(36).slice(2, 8)}`,
    sourceApp: event.packageName,
    sourceAppLabel: event.appLabel,
    title,
    content: body,
    amount,
    merchant,
    cardLastDigits,
    occurredAt,
    rawPayload: JSON.stringify(event),
    dedupeKey,
    status: 'PENDENTE_LAPIDACAO',
    createdAt: new Date().toISOString(),
    syncedAt: null,
    transactionId: null,
    pendingSync: null,
    syncError: null,
  };
}
