export type Priority = 'min' | 'low' | 'default' | 'high' | 'urgent'

export type PostEvent = 'post_published'

export type PostType = 'all' | 'taxonomy_term'

export type TemplateEvent = 'login_success' | 'login_failure' | 'post_published'

export const postEvent = 'post_published'

export const templateEvents: TemplateEvent[] = ['login_success', 'login_failure', 'post_published']

export interface NtfyConfig {
  namespace: string
  nonce: string
  root: string
}

export interface Settings {
  available_variables: string[]
  has_auth_token: boolean
  include_user_agent: boolean
  post: Post[]
  server_url: string
  templates: Record<TemplateEvent, Template>
}

export interface SaveSettings {
  auth_token: string
  clear_auth_token: boolean
  include_user_agent: boolean
  post: Post[]
  server_url: string
  templates: Record<TemplateEvent, Template>
}

export interface Category {
  id: number
  name: string
  parent: number
  slug: string
}

export interface Post {
  enabled: boolean
  event_key: PostEvent
  id?: number
  include_children: boolean
  post_type: string
  rule_type: PostType
  taxonomy: string
  term_id: number
  topic: string
}

export interface Template {
  enabled: boolean
  message: string
  priority: Priority
  tags: string
  title: string
  topic: string
}

export interface TestResponse {
  message: string
  success: boolean
}

declare global {
  interface Window {
    NTFY_ALERTS: NtfyConfig
  }
}
