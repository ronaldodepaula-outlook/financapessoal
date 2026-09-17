function pad(value: number): string {
  return String(value).padStart(2, '0');
}

export const MONTH_NAMES = [
  'Janeiro',
  'Fevereiro',
  'Março',
  'Abril',
  'Maio',
  'Junho',
  'Julho',
  'Agosto',
  'Setembro',
  'Outubro',
  'Novembro',
  'Dezembro',
];

/** "Setembro 2026". `month` é 1-12. */
export function formatMonthYear(year: number, month: number): string {
  return `${MONTH_NAMES[month - 1]} ${year}`;
}

/** Soma (ou subtrai, com delta negativo) meses a uma competência, ajustando o ano. */
export function shiftCompetence(year: number, month: number, delta: number): {year: number; month: number} {
  const zeroBased = month - 1 + delta;
  const nextYear = year + Math.floor(zeroBased / 12);
  const nextMonth = ((zeroBased % 12) + 12) % 12;
  return {year: nextYear, month: nextMonth + 1};
}

/** Data local do aparelho no formato exigido pela API ("Y-m-d"). */
export function todayApiDate(): string {
  const now = new Date();
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export function toApiDate(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function currentCompetence(): {year: number; month: number} {
  const now = new Date();
  return {year: now.getFullYear(), month: now.getMonth() + 1};
}

/** "2027-01-10" -> "10/01/2027". Tolerante a strings já formatadas incorretamente. */
export function formatApiDate(apiDate: string | null | undefined): string {
  if (!apiDate) {
    return '—';
  }
  const [year, month, day] = apiDate.split('-');
  if (!year || !month || !day) {
    return apiDate;
  }
  return `${day}/${month}/${year}`;
}

/** Rótulo relativo curto usado nas listas ("Hoje", "Ontem" ou "10/01"). */
export function formatRelativeShort(apiDate: string | null | undefined): string {
  if (!apiDate) {
    return '—';
  }
  const today = todayApiDate();
  if (apiDate === today) {
    return 'Hoje';
  }
  const yesterday = toApiDate(new Date(Date.now() - 86400000));
  if (apiDate === yesterday) {
    return 'Ontem';
  }
  const [, month, day] = apiDate.split('-');
  return month && day ? `${day}/${month}` : apiDate;
}

/** epoch millis (StatusBarNotification#getPostTime) -> ISO 8601 local. */
export function epochMillisToIso(epochMillis: number): string {
  return new Date(epochMillis).toISOString();
}
