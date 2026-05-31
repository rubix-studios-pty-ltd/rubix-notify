import { useEffect, useState } from '@wordpress/element'
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Notice,
  TabPanel,
  SelectControl,
  TextareaControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components'

import { getSettings, saveSettings, sendTest } from '../api'
import {
  priorityOptions,
  templateDescriptions,
  templateLabels,
  emptySettings,
  type NoticeState,
} from '../constants'
import { type Priority, type Settings, type TemplateEvent } from '../types'

const templateEvents: TemplateEvent[] = ['login_success', 'login_failure']

export function App() {
  const [settings, setSettings] = useState<Settings>(emptySettings)
  const [authToken, setAuthToken] = useState('')
  const [clearAuthToken, setClearAuthToken] = useState(false)
  const [notice, setNotice] = useState<NoticeState | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    void loadSettings()
  }, [])

  async function loadSettings() {
    setLoading(true)

    try {
      const result = await getSettings()

      setSettings(result)
    } catch (error) {
      setNotice({
        status: 'error',
        message: error instanceof Error ? error.message : 'Unable to load settings.',
      })
    } finally {
      setLoading(false)
    }
  }

  function update<K extends keyof Settings>(key: K, value: Settings[K]) {
    setSettings((current) => ({
      ...current,
      [key]: value,
    }))
  }

  function updateTemplate<K extends keyof Settings['templates'][TemplateEvent]>(
    eventKey: TemplateEvent,
    key: K,
    value: Settings['templates'][TemplateEvent][K]
  ) {
    setSettings((current) => ({
      ...current,
      templates: {
        ...current.templates,
        [eventKey]: {
          ...current.templates[eventKey],
          [key]: value,
        },
      },
    }))
  }

  async function handleSave(shouldSendTest: boolean) {
    setSaving(true)
    setNotice(null)

    try {
      const saved = await saveSettings({
        server_url: settings.server_url,
        auth_token: authToken,
        clear_auth_token: clearAuthToken,
        include_user_agent: settings.include_user_agent,
        templates: settings.templates,
      })

      setSettings(saved)
      setAuthToken('')
      setClearAuthToken(false)

      if (shouldSendTest) {
        const test = await sendTest()

        setNotice({
          status: test.success ? 'success' : 'error',
          message: test.message,
        })

        return
      }

      setNotice({
        status: 'success',
        message: 'Settings saved.',
      })
    } catch (error) {
      setNotice({
        status: 'error',
        message: error instanceof Error ? error.message : 'Unable to save settings.',
      })
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <p>Loading settings…</p>
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 16 }}>
      {notice && (
        <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <TabPanel
        className="wp-ntfy-tabs"
        activeClass="is-active"
        tabs={[
          { name: 'settings', title: 'Settings' },
          { name: 'templates', title: 'Templates' },
        ]}
      >
        {(tab) => (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {tab.name === 'settings' ? (
              <Card>
                <CardHeader>
                  <h2 style={{ margin: 0 }}>Settings</h2>
                </CardHeader>

                <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                  <TextControl
                    label="Server URL"
                    value={settings.server_url}
                    placeholder="https://ntfy.sh"
                    onChange={(value) => update('server_url', value)}
                  />

                  <TextControl
                    label="Access token"
                    value={authToken}
                    type="password"
                    placeholder={
                      settings.has_auth_token
                        ? 'Leave blank to keep existing token.'
                        : 'Optional bearer token'
                    }
                    help={
                      settings.has_auth_token
                        ? 'There is currently an access token saved and encrypted.'
                        : 'Use this when your ntfy topic or server requires authentication.'
                    }
                    onChange={(value) => setAuthToken(value)}
                  />

                  <ToggleControl
                    label="Clear saved access token"
                    checked={clearAuthToken}
                    onChange={(value) => setClearAuthToken(Boolean(value))}
                  />

                  <ToggleControl
                    label="Include user agent"
                    checked={settings.include_user_agent}
                    help="Disabled by default to reduce noisy output."
                    onChange={(value) => update('include_user_agent', Boolean(value))}
                  />
                </CardBody>
              </Card>
            ) : (
              <>
                {templateEvents.map((eventKey) => {
                  const template = settings.templates[eventKey]

                  return (
                    <Card key={eventKey}>
                      <CardHeader>
                        <div>
                          <h2 style={{ margin: 0 }}>{templateLabels[eventKey]}</h2>

                          <p style={{ margin: '6px 0 0' }}>{templateDescriptions[eventKey]}</p>
                        </div>
                      </CardHeader>

                      <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <ToggleControl
                          label="Enabled"
                          checked={template.enabled}
                          onChange={(value) => updateTemplate(eventKey, 'enabled', Boolean(value))}
                        />

                        <TextControl
                          label="Topic"
                          value={template.topic}
                          onChange={(value) => updateTemplate(eventKey, 'topic', value)}
                        />

                        <TextControl
                          label="Title"
                          value={template.title}
                          onChange={(value) => updateTemplate(eventKey, 'title', value)}
                        />

                        <TextareaControl
                          label="Message"
                          rows={6}
                          value={template.message}
                          onChange={(value) => updateTemplate(eventKey, 'message', value)}
                        />

                        <SelectControl
                          label="Priority"
                          value={template.priority}
                          options={priorityOptions}
                          onChange={(value) =>
                            updateTemplate(eventKey, 'priority', value as Priority)
                          }
                        />

                        <TextControl
                          label="Tags"
                          value={template.tags}
                          placeholder="key,warning"
                          help="Comma-separated ntfy tags."
                          onChange={(value) => updateTemplate(eventKey, 'tags', value)}
                        />
                      </CardBody>
                    </Card>
                  )
                })}

                <Card>
                  <CardHeader>
                    <h2 style={{ margin: 0 }}>Variables</h2>
                  </CardHeader>

                  <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                      {settings.available_variables.map((variable) => (
                        <code key={variable}>{variable}</code>
                      ))}
                    </div>
                  </CardBody>
                </Card>
              </>
            )}
          </div>
        )}
      </TabPanel>

      <div style={{ height: 16 }} />

      <div style={{ display: 'flex', gap: 8 }}>
        <Button
          variant="primary"
          isBusy={saving}
          disabled={saving}
          onClick={() => void handleSave(false)}
        >
          Save
        </Button>

        <Button
          variant="secondary"
          isBusy={saving}
          disabled={saving}
          onClick={() => void handleSave(true)}
        >
          Test and Save
        </Button>
      </div>
    </div>
  )
}
