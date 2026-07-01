import { postEvent, type Settings } from '../types'

export function addPostCategory(settings: Settings): Settings {
  return {
    ...settings,
    post: [
      ...(settings.post ?? []),
      {
        event_key: postEvent,
        enabled: true,
        rule_type: 'taxonomy_term',
        post_type: 'post',
        taxonomy: 'category',
        term_id: 0,
        topic: '',
        include_children: true,
      },
    ],
  }
}
