import { emptySettings } from '../constants'
import { type Post, type Settings } from '../types'

export const postEvent = 'post_published' as const

export function getPosts(settings: Settings): Post {
  const rule = settings.post?.find(
    (post) => post.event_key === postEvent && post.rule_type === 'all'
  )

  return rule ?? emptySettings.post[0]
}
