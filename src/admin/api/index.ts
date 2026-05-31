import apiFetch from '@wordpress/api-fetch'

import { type Settings, type SaveSettings, type TestResponse } from '../types'

const config = window.NTFY_ALERTS

apiFetch.use(apiFetch.createRootURLMiddleware(config.root))
apiFetch.use(apiFetch.createNonceMiddleware(config.nonce))

const route = (path: string) => `/${config.namespace}${path}`

export async function getSettings(): Promise<Settings> {
  return apiFetch({
    path: route('/settings'),
    method: 'GET',
  }) as Promise<Settings>
}

export async function saveSettings(payload: SaveSettings): Promise<Settings> {
  return apiFetch({
    path: route('/settings'),
    method: 'POST',
    data: payload,
  }) as Promise<Settings>
}

export async function sendTest(): Promise<TestResponse> {
  return apiFetch({
    path: route('/test'),
    method: 'POST',
  }) as Promise<TestResponse>
}
