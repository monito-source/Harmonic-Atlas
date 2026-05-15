self.addEventListener('push', (event) => {
  let payload = {}

  try {
    payload = event.data ? event.data.json() : {}
  } catch (error) {
    payload = {}
  }

  const title = String(payload.title || 'Planificador de ensayos')
  const url = String(payload.url || '/')

  event.waitUntil(
    self.registration.showNotification(title, {
      body: String(payload.body || ''),
      tag: String(payload.tag || payload.id || `wpssb-rehearsal-${Date.now()}`),
      requireInteraction: true,
      data: {
        url,
      },
    })
  )
})

self.addEventListener('notificationclick', (event) => {
  const targetUrl = String(event.notification?.data?.url || '/')

  event.notification.close()

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === targetUrl && 'focus' in client) {
          return client.focus()
        }
      }

      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl)
      }

      return undefined
    })
  )
})
