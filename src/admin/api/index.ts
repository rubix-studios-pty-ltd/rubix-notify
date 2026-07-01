import apiFetch from '@wordpress/api-fetch'

import { type Category, type SaveSettings, type Settings, type TestResponse } from '../types'

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

export async function getCategories(): Promise<Category[]> {
  const categories: Category[] = []
  let page = 1
  let totalPages = 1

  do {
    const response = (await apiFetch({
      path: `/wp/v2/categories?per_page=100&page=${page}&hide_empty=false&orderby=name&order=asc`,
      method: 'GET',
      parse: false,
    })) as Response

    if (!response.ok) {
      throw new Error('Unable to load categories.')
    }

    const result = (await response.json()) as Category[]

    categories.push(...result)

    totalPages = Number.parseInt(response.headers.get('X-WP-TotalPages') ?? '1', 10)
    page += 1
  } while (page <= totalPages)

  return categories
}
