import { emptySettings, postEvent } from '../constants'
import { type Post, type Settings } from '../types'

export function updatePosts<K extends keyof Post>(
  settings: Settings,
  key: K,
  value: Post[K]
): Settings {
  let updated = false

  const post = (settings.post ?? []).map((item) => {
    if (item.event_key !== postEvent || item.rule_type !== 'all') {
      return item
    }

    updated = true
    return { ...item, [key]: value }
  })

  if (!updated) {
    post.push({ ...emptySettings.post[0], [key]: value })
  }

  return { ...settings, post }
}
