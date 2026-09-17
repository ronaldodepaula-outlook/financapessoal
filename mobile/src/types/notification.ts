import type {TransactionFormInput} from './transaction';

/**
 * Evento cru emitido pelo módulo nativo Android (NotificationListenerService)
 * via DeviceEventEmitter. Ver android/app/src/main/java/.../notifications/.
 */
export interface RawNotificationEvent {
  packageName: string;
  appLabel: string | null;
  title: string | null;
  text: string | null;
  bigText: string | null;
  /** epoch millis, timestamp original da notificação (StatusBarNotification#getPostTime). */
  postedAt: number;
  /** StatusBarNotification#getKey(), usado só como dica adicional de dedupe. */
  key: string;
}

/**
 * Status local (existe apenas no armazenamento do app; nunca é enviado à API).
 * PENDENTE_LAPIDACAO -> aguardando revisão do usuário.
 * AGUARDANDO_SINCRONIZACAO -> usuário já lapidou, falta enviar (offline) via POST /transactions.
 * PROCESSADO -> já lapidado e confirmado como Transaction real via POST /transactions.
 * IGNORADA -> descartado pelo usuário sem virar lançamento.
 */
export type CapturedTransactionStatus =
  | 'PENDENTE_LAPIDACAO'
  | 'AGUARDANDO_SINCRONIZACAO'
  | 'PROCESSADO'
  | 'IGNORADA';

/**
 * Registro genérico resultante do parser (src/utils/notificationParser.ts).
 * Campos incertos ficam null propositalmente — a lapidação é responsabilidade
 * do usuário, o parser não deve "adivinhar" categoria/cartão/tipo.
 */
export interface CapturedTransaction {
  id: string;
  sourceApp: string;
  sourceAppLabel: string | null;
  title: string;
  content: string;
  amount: number | null;
  merchant: string | null;
  cardLastDigits: string | null;
  occurredAt: string;
  rawPayload: string;
  dedupeKey: string;
  status: CapturedTransactionStatus;
  createdAt: string;
  /** preenchido quando o POST /transactions da lapidação é confirmado pela API. */
  syncedAt: string | null;
  transactionId: number | null;
  /** dados informados na lapidação, presentes enquanto o envio ainda não foi confirmado. */
  pendingSync: LapidacaoInput | null;
  /** mensagem amigável quando uma tentativa de sincronização falhou (não-rede). */
  syncError: string | null;
}

/** Dados que o usuário informa na tela de lapidação (ver screens/Notifications/LapidacaoScreen). */
export type LapidacaoInput = TransactionFormInput;

export interface MonitoredApp {
  packageName: string;
  label: string;
  enabled: boolean;
}
