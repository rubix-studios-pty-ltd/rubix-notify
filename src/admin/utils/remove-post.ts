import { type Settings } from '../types'

export function removePost(settings: Settings, index: number): Settings {
  return {
    ...settings,
    post: (settings.post ?? []).filter((_, currentIndex) => currentIndex !== index),
  }
}
