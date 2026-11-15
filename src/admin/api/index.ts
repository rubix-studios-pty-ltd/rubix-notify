import apiFetch from '@wordpress/api-fetch'

import {
  type Category,
  type IpRuleAction,
  type SaveSettings,
  type SecurityData,
  type Settings,
  type TestResponse,
} from '../types'

const config = window.NTFY_ALERTS

apiFetch.use(apiFetch.createRootURLMiddleware(config.root))
apiFetch.use(apiFetch.createNonceMiddleware(config.nonce))

const route = (path: string) => `/${config.namespace}${path}`

export function getSettings(): Promise<Settings> {
  return apiFetch<Settings>({
    path: route('/settings'),
    method: 'GET',
  })
}

export function saveSettings(payload: SaveSettings): Promise<Settings> {
  return apiFetch<Settings>({
    path: route('/settings'),
    method: 'POST',
    data: payload,
  })
}

export function sendTest(): Promise<TestResponse> {
  return apiFetch<TestResponse>({
    path: route('/test'),
    method: 'POST',
  })
}

export function getSecurity(): Promise<SecurityData> {
  return apiFetch<SecurityData>({ path: route('/security') })
}

export function updateIpRule(ip: string, action: IpRuleAction): Promise<SecurityData> {
  return apiFetch<SecurityData>({
    path: route('/security/ip'),
    method: 'POST',
    data: { ip, action },
  })
}

export async function getCategories(): Promise<Category[]> {
  const categories: Category[] = []
  let page = 1
  let total = 1

  do {
    const response = await apiFetch({
      path: `/wp/v2/categories?per_page=100&page=${page}&hide_empty=false&orderby=name&order=asc`,
      parse: false,
    })

    if (!response.ok) {
      throw new Error('Unable to load categories.')
    }

    const result: Category[] = await response.json()
    categories.push(...result)

    total = Number.parseInt(response.headers.get('X-WP-TotalPages') ?? '1', 10)
    page += 1
  } while (page <= total)

  return categories
}
