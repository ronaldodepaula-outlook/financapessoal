package com.mobileapp.notifications

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

/**
 * Persistência simples (SharedPreferences) compartilhada entre o
 * NotificationListenerService (que pode rodar sem o app/JS em primeiro plano)
 * e o NativeModule que a ponte React Native usa. Guarda:
 *  - a lista de pacotes monitorados (configurada em Settings > Aplicativos monitorados);
 *  - uma fila de eventos capturados enquanto não havia um ReactContext vivo para
 *    emitir via DeviceEventEmitter, para não perder nada com o app fechado.
 */
object NotificationCaptureStore {
    private const val PREFS_NAME = "notification_capture_prefs"
    private const val KEY_MONITORED_PACKAGES = "monitored_packages"
    private const val KEY_QUEUED_EVENTS = "queued_events"
    private const val MAX_QUEUE_SIZE = 200

    @Synchronized
    fun getMonitoredPackages(context: Context): Set<String> {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val raw = prefs.getString(KEY_MONITORED_PACKAGES, null) ?: return emptySet()
        return try {
            val array = JSONArray(raw)
            (0 until array.length()).map { array.getString(it) }.toSet()
        } catch (error: Exception) {
            emptySet()
        }
    }

    @Synchronized
    fun setMonitoredPackages(context: Context, packages: List<String>) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val array = JSONArray()
        packages.forEach { array.put(it) }
        prefs.edit().putString(KEY_MONITORED_PACKAGES, array.toString()).apply()
    }

    @Synchronized
    fun enqueueEvent(context: Context, event: JSONObject) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val raw = prefs.getString(KEY_QUEUED_EVENTS, null)
        val array = try {
            if (raw != null) JSONArray(raw) else JSONArray()
        } catch (error: Exception) {
            JSONArray()
        }
        array.put(event)
        // Nunca deixa a fila crescer sem limite se o app ficar muito tempo sem abrir.
        val trimmed = if (array.length() > MAX_QUEUE_SIZE) {
            val start = array.length() - MAX_QUEUE_SIZE
            val next = JSONArray()
            for (i in start until array.length()) {
                next.put(array.get(i))
            }
            next
        } else {
            array
        }
        prefs.edit().putString(KEY_QUEUED_EVENTS, trimmed.toString()).apply()
    }

    @Synchronized
    fun drainQueuedEvents(context: Context): JSONArray {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val raw = prefs.getString(KEY_QUEUED_EVENTS, null)
        prefs.edit().remove(KEY_QUEUED_EVENTS).apply()
        return try {
            if (raw != null) JSONArray(raw) else JSONArray()
        } catch (error: Exception) {
            JSONArray()
        }
    }
}
