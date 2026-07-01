import { type Settings, type Template, type TemplateEvent } from '../types'

export function updateTemplate<K extends keyof Template>(
  settings: Settings,
  eventKey: TemplateEvent,
  key: K,
  value: Template[K]
): Settings {
  return {
    ...settings,
    templates: {
      ...settings.templates,
      [eventKey]: {
        ...settings.templates[eventKey],
        [key]: value,
      },
    },
  }
}
