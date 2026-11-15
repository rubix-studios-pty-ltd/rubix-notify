import { type Settings } from '../types'

export function removePost(settings: Settings, index: number): Settings {
  const post = settings.post ?? []

  return {
    ...settings,
    post: post.filter((_, currentIndex) => currentIndex !== index),
  }
}
