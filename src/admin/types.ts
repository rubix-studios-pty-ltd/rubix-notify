export type Priority = 'min' | 'low' | 'default' | 'high' | 'urgent'

export type TemplateEvent = 'login_success' | 'login_failure'

export interface NotificationTemplate {
  enabled: boolean
  topic: string
  title: string
  message: string
  priority: Priority
  tags: string
}

export interface Settings {
  server_url: string
  include_user_agent: boolean
  has_auth_token: boolean
  available_variables: string[]
  templates: Record<TemplateEvent, NotificationTemplate>
}

export interface SaveSettings {
  server_url: string
  auth_token: string
  clear_auth_token: boolean
  include_user_agent: boolean
  templates: Record<TemplateEvent, NotificationTemplate>
}

export interface TestResponse {
  success: boolean
  message: string
}

export interface NtfyConfig {
  root: string
  namespace: string
  nonce: string
}

declare global {
  interface Window {
    NTFY_ALERTS: NtfyConfig
  }
}
