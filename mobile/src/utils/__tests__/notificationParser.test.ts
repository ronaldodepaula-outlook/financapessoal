import {buildDedupeKey, parseNotification} from '../notificationParser';
import type {RawNotificationEvent} from '../../types/notification';

const baseEvent: RawNotificationEvent = {
  packageName: 'com.nu.production',
  appLabel: 'Nubank',
  title: null,
  text: null,
  bigText: null,
  postedAt: Date.parse('2026-09-16T20:30:00Z'),
  key: 'notif-key-1',
};

function event(overrides: Partial<RawNotificationEvent>): RawNotificationEvent {
  return {...baseEvent, ...overrides};
}

describe('parseNotification', () => {
  it('parses a card-purchase notification with merchant and card digits', () => {
    const result = parseNotification(
      event({
        title: 'Compra aprovada',
        text: 'R$ 152,80\nSUPERMERCADO XYZ\nCartão final 1234',
      }),
    );

    expect(result).not.toBeNull();
    expect(result?.amount).toBe(152.8);
    expect(result?.merchant).toBe('SUPERMERCADO XYZ');
    expect(result?.cardLastDigits).toBe('1234');
    expect(result?.status).toBe('PENDENTE_LAPIDACAO');
    expect(result?.sourceApp).toBe('com.nu.production');
  });

  it('parses "compra no cartão" without a card number', () => {
    const result = parseNotification(
      event({title: 'Compra no cartão', text: 'R$ 49,90\nLOJA ABC'}),
    );

    expect(result).not.toBeNull();
    expect(result?.amount).toBe(49.9);
    expect(result?.merchant).toBe('LOJA ABC');
    expect(result?.cardLastDigits).toBeNull();
  });

  it('handles a thousands-separated amount', () => {
    const result = parseNotification(
      event({title: 'Pagamento aprovado', text: 'R$ 1.250,99\nLOJA GRANDE'}),
    );

    expect(result?.amount).toBe(1250.99);
  });

  it('handles a bare decimal amount without the R$ prefix', () => {
    const result = parseNotification(event({title: 'Compra aprovada', text: '152.80'}));

    expect(result?.amount).toBe(152.8);
  });

  it('leaves merchant null when the notification has no distinguishable establishment line', () => {
    const result = parseNotification(
      event({title: null, text: 'Você realizou uma compra de R$ 89,90'}),
    );

    expect(result).not.toBeNull();
    expect(result?.amount).toBe(89.9);
    expect(result?.merchant).toBeNull();
  });

  it('returns null for a notification with no financial keyword', () => {
    const result = parseNotification(
      event({
        packageName: 'com.whatsapp',
        appLabel: 'WhatsApp',
        title: 'João',
        text: 'Vamos almoçar hoje?',
      }),
    );

    expect(result).toBeNull();
  });

  it('returns null for an empty notification', () => {
    const result = parseNotification(event({title: null, text: null, bigText: null}));

    expect(result).toBeNull();
  });

  it('never throws for unexpected content', () => {
    expect(() =>
      parseNotification(event({title: 'compra', text: 'R$ ,, cartão final abcd'})),
    ).not.toThrow();
  });

  it('respects a custom financial keyword list', () => {
    const withDefaults = parseNotification(event({title: 'Recibo especial', text: 'R$ 10,00'}));
    expect(withDefaults).toBeNull();

    const withCustom = parseNotification(
      event({title: 'Recibo especial', text: 'R$ 10,00'}),
      {financialKeywords: ['recibo']},
    );
    expect(withCustom).not.toBeNull();
  });
});

describe('buildDedupeKey', () => {
  const params = {
    sourceApp: 'com.nu.production',
    amount: 152.8,
    merchant: 'SUPERMERCADO XYZ',
    cardLastDigits: '1234',
    occurredAt: '2026-09-16T20:30:00.000Z',
  };

  it('is stable for identical input', () => {
    expect(buildDedupeKey(params)).toBe(buildDedupeKey({...params}));
  });

  it('is insensitive to seconds within the same minute (repost/update by the bank app)', () => {
    const a = buildDedupeKey({...params, occurredAt: '2026-09-16T20:30:00.000Z'});
    const b = buildDedupeKey({...params, occurredAt: '2026-09-16T20:30:47.000Z'});
    expect(a).toBe(b);
  });

  it('differs for a different amount', () => {
    expect(buildDedupeKey({...params, amount: 10}) === buildDedupeKey(params)).toBe(false);
  });

  it('differs for a different merchant', () => {
    expect(buildDedupeKey({...params, merchant: 'OUTRA LOJA'}) === buildDedupeKey(params)).toBe(
      false,
    );
  });

  it('differs across a minute boundary', () => {
    const a = buildDedupeKey({...params, occurredAt: '2026-09-16T20:30:59.000Z'});
    const b = buildDedupeKey({...params, occurredAt: '2026-09-16T20:31:00.000Z'});
    expect(a === b).toBe(false);
  });
});
