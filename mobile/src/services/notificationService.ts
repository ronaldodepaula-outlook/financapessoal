import {NativeEventEmitter, NativeModules} from 'react-native';
import type {RawNotificationEvent} from '../types/notification';

const {NotificationCaptureModule} = NativeModules;
const EVENT_NAME = 'FinancasPessoais:NotificationCaptured';

/**
 * Camada fina sobre o módulo nativo Android (ver
 * android/.../notifications/NotificationCaptureModule.kt). Em qualquer
 * ambiente sem o módulo nativo linkado (ex.: iOS, ou build ainda não
 * recompilado), todas as funções degradam graciosamente em vez de lançar.
 */

export async function isNotificationAccessEnabled(): Promise<boolean> {
  if (!NotificationCaptureModule) {
    return false;
  }
  return NotificationCaptureModule.isServiceEnabled();
}

export function openNotificationAccessSettings(): void {
  NotificationCaptureModule?.openNotificationSettings();
}

export async function setMonitoredPackages(packages: string[]): Promise<void> {
  if (!NotificationCaptureModule) {
    return;
  }
  await NotificationCaptureModule.setMonitoredPackages(packages);
}

export async function getMonitoredPackages(): Promise<string[]> {
  if (!NotificationCaptureModule) {
    return [];
  }
  return NotificationCaptureModule.getMonitoredPackages();
}

export interface InstalledApp {
  packageName: string;
  label: string;
}

export async function getInstalledApps(): Promise<InstalledApp[]> {
  if (!NotificationCaptureModule) {
    return [];
  }
  return NotificationCaptureModule.getInstalledApps();
}

/** Deve ser chamado na inicialização do app para não perder capturas feitas com o app fechado. */
export async function drainQueuedEvents(): Promise<RawNotificationEvent[]> {
  if (!NotificationCaptureModule) {
    return [];
  }
  return NotificationCaptureModule.drainQueuedEvents();
}

let emitter: NativeEventEmitter | null = null;

function getEmitter(): NativeEventEmitter | null {
  if (!NotificationCaptureModule) {
    return null;
  }
  if (!emitter) {
    emitter = new NativeEventEmitter(NotificationCaptureModule);
  }
  return emitter;
}

/** Assina eventos ao vivo enquanto o app está aberto. Devolve a função de cancelamento. */
export function subscribeToNotificationEvents(
  listener: (event: RawNotificationEvent) => void,
): () => void {
  const instance = getEmitter();
  if (!instance) {
    return () => undefined;
  }
  const subscription = instance.addListener(EVENT_NAME, (event: object) => {
    listener(event as RawNotificationEvent);
  });
  return () => subscription.remove();
}
