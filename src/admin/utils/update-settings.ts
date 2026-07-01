import { type Settings } from '../types'

export function updateSetting<K extends keyof Settings>(
  settings: Settings,
  key: K,
  value: Settings[K]
): Settings {
  return {
    ...settings,
    [key]: value,
  }
}
