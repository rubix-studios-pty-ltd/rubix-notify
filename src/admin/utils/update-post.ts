import { type Post, type Settings } from '../types'

export function updatePost<K extends keyof Post>(
  settings: Settings,
  index: number,
  key: K,
  value: Post[K]
): Settings {
  const post = settings.post ?? []

  return {
    ...settings,
    post: post.map((item, currentIndex) =>
      currentIndex === index ? { ...item, [key]: value } : item
    ),
  }
}
