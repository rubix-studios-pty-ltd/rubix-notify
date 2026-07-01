import { emptySettings } from '../constants'
import { postEvent, type Post, type Settings } from '../types'

export function updatePosts<K extends keyof Post>(
  settings: Settings,
  key: K,
  value: Post[K]
): Settings {
  const post = settings.post ?? []

  const hasAllPost = post.some((item) => item.event_key === postEvent && item.rule_type === 'all')

  return {
    ...settings,
    post: hasAllPost
      ? post.map((item) =>
          item.event_key === postEvent && item.rule_type === 'all'
            ? {
                ...item,
                [key]: value,
              }
            : item
        )
      : [
          ...post,
          {
            ...emptySettings.post[0],
            [key]: value,
          },
        ],
  }
}
