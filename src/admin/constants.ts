import { type Priority, type SecurityData, type Settings, type TemplateEvent } from './types'

export const priorityOptions: Array<{ label: string; value: Priority }> = [
  { label: 'Minimum', value: 'min' },
  { label: 'Low', value: 'low' },
  { label: 'Default', value: 'default' },
  { label: 'High', value: 'high' },
  { label: 'Urgent', value: 'urgent' },
]

export const templateLabels: Record<TemplateEvent, string> = {
  post_published: 'Post published',
  login_success: 'Successful login',
  login_failure: 'Repeated failed logins',
}

export const templateDescriptions: Record<TemplateEvent, string> = {
  post_published: 'Send a notification when a post is published.',
  login_success: 'Send a notification when a user logs in successfully.',
  login_failure: 'Send a notification when per IP fail count reaches 10 over 60 minutes.',
}

export type NoticeState = {
  status: 'success' | 'error'
  message: string
}

export const emptySettings: Settings = {
  server_url: 'https://ntfy.sh',
  include_user_agent: false,
  has_auth_token: false,
  available_variables: [],
  post: [
    {
      id: 0,
      event_key: 'post_published',
      enabled: false,
      rule_type: 'all',
      post_type: 'post',
      taxonomy: 'category',
      term_id: 0,
      topic: '',
      include_children: true,
    },
  ],
  templates: {
    post_published: {
      enabled: false,
      topic: 'wordpress-{site_slug}',
      title: 'New post published: {post_title}',
      message: '{post_title} was published on {site_name}.\nAuthor {post_author}\nURL {post_url}',
      priority: 'default',
      tags: 'newspaper',
    },
    login_success: {
      enabled: true,
      topic: 'wordpress-{site_slug}',
      title: 'WordPress login {username}',
      message: '{username} logged into {site_name} from {ip} at {time}.',
      priority: 'default',
      tags: 'key',
    },
    login_failure: {
      enabled: false,
      topic: 'wordpress-{site_slug}',
      title: 'Repeated WordPress login failures from {ip}',
      message:
        '{failure_count} failed logins were recorded from {ip} within {window_minutes} minutes on {site_name}.\nLatest username {username}\nTime {time}\nUser Agent {user_agent}',
      priority: 'high',
      tags: 'warning',
    },
  },
}

export const emptySecurity: SecurityData = {
  alert_threshold: 10,
  alerts_enabled: false,
  attempts: [],
  current_ip: '',
  ips: [],
  retention_days: 90,
  summary: {
    attempts_last_hour: 0,
    banned_ips: 0,
    tracked_ips: 0,
    whitelisted_ips: 0,
  },
  window_minutes: 60,
}
