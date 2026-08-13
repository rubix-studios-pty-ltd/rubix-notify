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

export type IpRuleAction = 'ban' | 'unban' | 'whitelist' | 'remove_whitelist'

export type IpStatus = 'observed' | 'banned' | 'whitelisted'

export interface SecuritySummary {
  attempts_last_hour: number
  banned_ips: number
  tracked_ips: number
  whitelisted_ips: number
}

export interface SecurityIp {
  failures_last_hour: number
  first_failed_at_gmt: string | null
  ip_address: string
  last_failed_at_gmt: string | null
  last_notified_at_gmt: string | null
  status: IpStatus
  total_failures: number
}

export interface LoginAttempt {
  attempted_at_gmt: string
  error_code: string
  id: number
  ip_address: string
  username: string
}

export interface SecurityData {
  alert_threshold: number
  alerts_enabled: boolean
  attempts: LoginAttempt[]
  current_ip: string
  ips: SecurityIp[]
  retention_days: number
  summary: SecuritySummary
  window_minutes: number
}

declare global {
  interface Window {
    NTFY_ALERTS: NtfyConfig
  }
}
