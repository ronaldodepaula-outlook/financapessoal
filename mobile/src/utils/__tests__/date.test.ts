import {formatMonthYear, shiftCompetence} from '../date';

describe('formatMonthYear', () => {
  it('formats a competence as "Mês Ano"', () => {
    expect(formatMonthYear(2026, 9)).toBe('Setembro 2026');
    expect(formatMonthYear(2027, 1)).toBe('Janeiro 2027');
    expect(formatMonthYear(2027, 12)).toBe('Dezembro 2027');
  });
});

describe('shiftCompetence', () => {
  it('moves forward within the same year', () => {
    expect(shiftCompetence(2026, 9, 1)).toEqual({year: 2026, month: 10});
  });

  it('moves backward within the same year', () => {
    expect(shiftCompetence(2026, 9, -1)).toEqual({year: 2026, month: 8});
  });

  it('rolls over to the next year from December', () => {
    expect(shiftCompetence(2026, 12, 1)).toEqual({year: 2027, month: 1});
  });

  it('rolls back to the previous year from January', () => {
    expect(shiftCompetence(2026, 1, -1)).toEqual({year: 2025, month: 12});
  });

  it('handles multi-month jumps across a year boundary', () => {
    expect(shiftCompetence(2026, 11, 3)).toEqual({year: 2027, month: 2});
    expect(shiftCompetence(2026, 2, -5)).toEqual({year: 2025, month: 9});
  });

  it('is a no-op with delta zero', () => {
    expect(shiftCompetence(2026, 6, 0)).toEqual({year: 2026, month: 6});
  });
});
