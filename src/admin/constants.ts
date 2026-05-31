import { type Priority, type Settings, type TemplateEvent } from './types'

export const priorityOptions: Array<{ label: string; value: Priority }> = [
  { label: 'Minimum', value: 'min' },
  { label: 'Low', value: 'low' },
  { label: 'Default', value: 'default' },
  { label: 'High', value: 'high' },
  { label: 'Urgent', value: 'urgent' },
]

export const templateLabels: Record<TemplateEvent, string> = {
  login_success: 'Successful login',
  login_failure: 'Failed login',
}

export const templateDescriptions: Record<TemplateEvent, string> = {
  login_success: 'Send a notification when a user logs in successfully.',
  login_failure: 'Send a notification when a login attempt fails.',
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
  templates: {
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
      title: 'Failed WordPress login {username}',
      message: 'Failed login attempt for {username} on {site_name} from {ip}.',
      priority: 'high',
      tags: 'warning',
    },
  },
}
