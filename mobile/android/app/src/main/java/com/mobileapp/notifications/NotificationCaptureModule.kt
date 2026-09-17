package com.mobileapp.notifications

import android.content.Intent
import android.content.pm.ApplicationInfo
import android.content.pm.PackageManager
import android.provider.Settings
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.ReadableArray
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.WritableArray
import com.facebook.react.bridge.WritableMap

/**
 * Ponte JS <-> Android para o módulo de captura de notificações. Nomeado
 * "NotificationCaptureModule" no NativeModules do React Native.
 */
class NotificationCaptureModule(reactContext: ReactApplicationContext) :
    ReactContextBaseJavaModule(reactContext) {

    companion object {
        // Referência estática usada pelo NotificationListenerService para emitir
        // eventos ao vivo quando há uma instância JS ativa (ver
        // FinancialNotificationListenerService.emitToJs).
        @Volatile
        var currentReactContext: ReactApplicationContext? = null
    }

    init {
        currentReactContext = reactContext
    }

    override fun getName(): String = "NotificationCaptureModule"

    override fun invalidate() {
        super.invalidate()
        if (currentReactContext === reactApplicationContext) {
            currentReactContext = null
        }
    }

    /**
     * true quando o usuário já concedeu "Acesso a notificações" para este app.
     * Não existe forma de conceder essa permissão programaticamente — só é
     * possível verificar e, se necessário, abrir a tela de configurações.
     */
    @ReactMethod
    fun isServiceEnabled(promise: Promise) {
        try {
            val packageName = reactApplicationContext.packageName
            val enabledListeners = Settings.Secure.getString(
                reactApplicationContext.contentResolver,
                "enabled_notification_listeners",
            )
            val enabled = enabledListeners?.contains(packageName) == true
            promise.resolve(enabled)
        } catch (error: Exception) {
            promise.reject("notification_capture_error", error)
        }
    }

    @ReactMethod
    fun openNotificationSettings() {
        val intent = Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS)
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        reactApplicationContext.startActivity(intent)
    }

    @ReactMethod
    fun setMonitoredPackages(packages: ReadableArray, promise: Promise) {
        try {
            val list = mutableListOf<String>()
            for (i in 0 until packages.size()) {
                packages.getString(i)?.let { list.add(it) }
            }
            NotificationCaptureStore.setMonitoredPackages(reactApplicationContext, list)
            promise.resolve(true)
        } catch (error: Exception) {
            promise.reject("notification_capture_error", error)
        }
    }

    @ReactMethod
    fun getMonitoredPackages(promise: Promise) {
        try {
            val result: WritableArray = Arguments.createArray()
            NotificationCaptureStore.getMonitoredPackages(reactApplicationContext).forEach {
                result.pushString(it)
            }
            promise.resolve(result)
        } catch (error: Exception) {
            promise.reject("notification_capture_error", error)
        }
    }

    /**
     * Devolve (e limpa) os eventos guardados enquanto o app estava fechado e o
     * serviço não tinha uma ponte JS viva para emitir ao vivo. Deve ser chamado
     * na inicialização do app (ver services/notificationService.ts).
     */
    @ReactMethod
    fun drainQueuedEvents(promise: Promise) {
        try {
            val queued = NotificationCaptureStore.drainQueuedEvents(reactApplicationContext)
            val result: WritableArray = Arguments.createArray()
            for (i in 0 until queued.length()) {
                val item = queued.getJSONObject(i)
                val map: WritableMap = Arguments.createMap()
                map.putString("packageName", item.optString("packageName"))
                map.putString("appLabel", if (item.isNull("appLabel")) null else item.optString("appLabel"))
                map.putString("title", if (item.isNull("title")) null else item.optString("title"))
                map.putString("text", if (item.isNull("text")) null else item.optString("text"))
                map.putString("bigText", if (item.isNull("bigText")) null else item.optString("bigText"))
                map.putDouble("postedAt", item.optLong("postedAt").toDouble())
                map.putString("key", item.optString("key"))
                result.pushMap(map)
            }
            promise.resolve(result)
        } catch (error: Exception) {
            promise.reject("notification_capture_error", error)
        }
    }

    /**
     * Lista os apps instalados (nome + rótulo) para a tela "Aplicativos
     * monitorados" — o usuário escolhe explicitamente quais apps o app pode
     * observar, em vez de a gente supor pacotes de bancos "conhecidos"
     * (ver seção 11 do briefing: nada de assumir suporte a apps específicos).
     * Filtra para apps não-sistema por padrão, mas inclui apps de sistema que
     * também tenham uma activity de lançamento (alguns bancos/carteiras vêm
     * pré-instalados como apps de sistema em certos aparelhos).
     */
    @ReactMethod
    fun getInstalledApps(promise: Promise) {
        try {
            val packageManager = reactApplicationContext.packageManager
            val apps = packageManager.getInstalledApplications(PackageManager.GET_META_DATA)
            val result: WritableArray = Arguments.createArray()
            apps.forEach { appInfo ->
                val isSystemApp = (appInfo.flags and ApplicationInfo.FLAG_SYSTEM) != 0
                val hasLauncherIntent =
                    packageManager.getLaunchIntentForPackage(appInfo.packageName) != null
                if (!isSystemApp || hasLauncherIntent) {
                    val map: WritableMap = Arguments.createMap()
                    map.putString("packageName", appInfo.packageName)
                    map.putString("label", packageManager.getApplicationLabel(appInfo).toString())
                    result.pushMap(map)
                }
            }
            promise.resolve(result)
        } catch (error: Exception) {
            promise.reject("notification_capture_error", error)
        }
    }

    // Exigidos pelo contrato do NativeEventEmitter do RN (RCTDeviceEventEmitter);
    // este módulo não precisa fazer nada quando os listeners JS mudam.
    @ReactMethod
    fun addListener(eventName: String) {}

    @ReactMethod
    fun removeListeners(count: Int) {}
}
