package com.mobileapp.notifications

import android.app.Notification
import android.content.pm.PackageManager
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import com.facebook.react.modules.core.DeviceEventManagerModule
import org.json.JSONObject

/**
 * Serviço de escuta de notificações do Android. O usuário precisa conceder
 * este acesso manualmente (Configurações > Apps > Acesso especial >
 * Acesso a notificações) — não existe forma de conceder isso programaticamente
 * (ver screens/NotificationAccess.tsx).
 *
 * Por privacidade (seção 11 do briefing), NENHUM conteúdo é encaminhado para o
 * JS/API antes de passar pelo filtro de pacotes monitorados configurado pelo
 * usuário em Configurações > Aplicativos monitorados.
 */
class FinancialNotificationListenerService : NotificationListenerService() {

    companion object {
        private const val TAG = "NotificationCapture"
        const val EVENT_NAME = "FinancasPessoais:NotificationCaptured"
    }

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        try {
            handleNotification(sbn)
        } catch (error: Exception) {
            // Uma notificação malformada nunca pode derrubar o listener do sistema.
            Log.w(TAG, "Falha ao processar notificação capturada", error)
        }
    }

    private fun handleNotification(sbn: StatusBarNotification) {
        val packageName = sbn.packageName ?: return

        // Ignora silenciosamente qualquer app fora da lista configurada pelo usuário —
        // o serviço nunca encaminha conteúdo de apps não monitorados.
        val monitored = NotificationCaptureStore.getMonitoredPackages(applicationContext)
        if (packageName !in monitored) {
            return
        }
        // Ignora notificações do próprio app (evita eco).
        if (packageName == applicationContext.packageName) {
            return
        }

        val extras = sbn.notification.extras
        val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString()
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()
        val bigText = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString()
        val appLabel = resolveAppLabel(packageName)

        val payload = JSONObject().apply {
            put("packageName", packageName)
            put("appLabel", appLabel)
            put("title", title)
            put("text", text)
            put("bigText", bigText)
            put("postedAt", sbn.postTime)
            put("key", sbn.key ?: "$packageName-${sbn.postTime}")
        }

        val reactContext = NotificationCaptureModule.currentReactContext
        if (reactContext != null && reactContext.hasActiveReactInstance()) {
            emitToJs(reactContext, payload)
        } else {
            // Sem bridge viva (app fechado): guarda para o próximo drainQueuedEvents().
            NotificationCaptureStore.enqueueEvent(applicationContext, payload)
        }
    }

    private fun emitToJs(
        reactContext: com.facebook.react.bridge.ReactContext,
        payload: JSONObject,
    ) {
        val map = com.facebook.react.bridge.Arguments.createMap().apply {
            putString("packageName", payload.optString("packageName"))
            putString("appLabel", payload.optString("appLabel", null))
            putString("title", payload.optString("title", null))
            putString("text", payload.optString("text", null))
            putString("bigText", payload.optString("bigText", null))
            putDouble("postedAt", payload.optLong("postedAt").toDouble())
            putString("key", payload.optString("key"))
        }
        reactContext
            .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
            .emit(EVENT_NAME, map)
    }

    private fun resolveAppLabel(packageName: String): String? {
        return try {
            val packageManager = applicationContext.packageManager
            val appInfo = packageManager.getApplicationInfo(packageName, 0)
            packageManager.getApplicationLabel(appInfo).toString()
        } catch (error: PackageManager.NameNotFoundException) {
            null
        }
    }
}
