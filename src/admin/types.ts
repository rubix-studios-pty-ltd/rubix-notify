export type Priority = 'min' | 'low' | 'default' | 'high' | 'urgent'

export type PostEvent = 'post_published'

export type PostType = 'all' | 'taxonomy_term'

export type TemplateEvent = 'login_success' | 'login_failure' | 'post_published'

export const postEvent = 'post_published'

export const templateEvents: TemplateEvent[] = ['login_success', 'login_failure', 'post_published']

export interface NtfyConfig {
  root: string
  namespace: string
  nonce: string
}

export interface Settings {
  server_url: string
  include_user_agent: boolean
  has_auth_token: boolean
  available_variables: string[]
  post: Post[]
  templates: Record<TemplateEvent, Template>
}

export interface SaveSettings {
  server_url: string
  auth_token: string
  clear_auth_token: boolean
  include_user_agent: boolean
  post: Post[]
  templates: Record<TemplateEvent, Template>
}

export interface Category {
  id: number
  name: string
  slug: string
  parent: number
}

export interface Post {
  id?: number
  event_key: PostEvent
  enabled: boolean
  rule_type: PostType
  post_type: string
  taxonomy: string
  term_id: number
  topic: string
  include_children: boolean
}

export interface Template {
  enabled: boolean
  topic: string
  title: string
  message: string
  priority: Priority
  tags: string
}

export interface TestResponse {
  success: boolean
  message: string
}

declare global {
  interface Window {
    NTFY_ALERTS: NtfyConfig
  }
}
