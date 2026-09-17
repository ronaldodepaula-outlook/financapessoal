import {formatCurrency, parseAmountToApiDecimal} from '../currency';

describe('parseAmountToApiDecimal', () => {
  it('converts a plain comma-decimal value', () => {
    expect(parseAmountToApiDecimal('152,80')).toBe('152.80');
  });

  it('converts a value with thousands separator and comma decimals', () => {
    expect(parseAmountToApiDecimal('1.250,99')).toBe('1250.99');
  });

  it('accepts a value already using a dot as the decimal separator', () => {
    expect(parseAmountToApiDecimal('152.80')).toBe('152.80');
  });

  it('strips a currency prefix and whitespace', () => {
    expect(parseAmountToApiDecimal('R$ 89,90')).toBe('89.90');
  });

  it('returns null for garbage input', () => {
    expect(parseAmountToApiDecimal('não é um valor')).toBeNull();
  });

  it('returns null for a negative amount', () => {
    expect(parseAmountToApiDecimal('-10,00')).toBeNull();
  });
});

describe('formatCurrency', () => {
  it('formats a decimal string as BRL', () => {
    expect(formatCurrency('152.80')).toContain('152,80');
  });

  it('returns a placeholder for null/undefined', () => {
    expect(formatCurrency(null)).toBe('—');
    expect(formatCurrency(undefined)).toBe('—');
  });
});
