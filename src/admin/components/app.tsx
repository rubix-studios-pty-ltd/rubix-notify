import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Notice,
  SelectControl,
  TabPanel,
  TextareaControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components'
import { useEffect, useState } from '@wordpress/element'

import {
  getCategories,
  getSecurity,
  getSettings,
  saveSettings,
  sendTest,
  updateIpRule,
} from '../api'
import {
  emptySecurity,
  emptySettings,
  type NoticeState,
  priorityOptions,
  templateDescriptions,
  templateLabels,
} from '../constants'
import {
  type Category,
  type IpRuleAction,
  type Priority,
  postEvent,
  type Settings,
  type TemplateEvent,
  templateEvents,
} from '../types'
import { addPostCategory } from '../utils/add-category'
import { getPosts } from '../utils/get-posts'
import { removePost } from '../utils/remove-post'
import { updatePost } from '../utils/update-post'
import { updatePosts } from '../utils/update-posts'
import { updateSetting } from '../utils/update-settings'
import { updateTemplate } from '../utils/update-template'
import { SecurityPanel } from './security'

export function App() {
  const [settings, setSettings] = useState<Settings>(emptySettings)
  const [authToken, setAuthToken] = useState('')
  const [clearAuthToken, setClearAuthToken] = useState(false)
  const [notice, setNotice] = useState<NoticeState | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [categories, setCategories] = useState<Category[]>([])
  const [security, setSecurity] = useState(emptySecurity)
  const [securityLoading, setSecurityLoading] = useState(true)
  const [securityAction, setSecurityAction] = useState('')

  useEffect(() => {
    void loadSettings()
  }, [])

  async function loadSettings() {
    setLoading(true)

    try {
      const [result, categoryResult, securityResult] = await Promise.all([
        getSettings(),
        getCategories(),
        getSecurity(),
      ])

      setSettings(result)
      setCategories(categoryResult)
      setSecurity(securityResult)
    } catch (error) {
      setNotice({
        status: 'error',
        message: error instanceof Error ? error.message : 'Unable to load settings.',
      })
    } finally {
      setLoading(false)
      setSecurityLoading(false)
    }
  }

  function update<K extends keyof Settings>(key: K, value: Settings[K]) {
    setSettings((current) => updateSetting(current, key, value))
  }

  function setPosts<K extends keyof ReturnType<typeof getPosts>>(
    key: K,
    value: ReturnType<typeof getPosts>[K]
  ) {
    setSettings((current) => updatePosts(current, key, value))
  }

  function addCategory() {
    setSettings((current) => addPostCategory(current))
  }

  function setPost<K extends keyof ReturnType<typeof getPosts>>(
    index: number,
    key: K,
    value: ReturnType<typeof getPosts>[K]
  ) {
    setSettings((current) => updatePost(current, index, key, value))
  }

  function deletePost(index: number) {
    setSettings((current) => removePost(current, index))
  }

  function setTemplate<K extends keyof Settings['templates'][TemplateEvent]>(
    eventKey: TemplateEvent,
    key: K,
    value: Settings['templates'][TemplateEvent][K]
  ) {
    setSettings((current) => updateTemplate(current, eventKey, key, value))
  }

  async function refreshSecurity() {
    setSecurityLoading(true)

    try {
      setSecurity(await getSecurity())
    } catch (error) {
      setNotice({
        status: 'error',
        message: error instanceof Error ? error.message : 'Unable to refresh login security.',
      })
    } finally {
      setSecurityLoading(false)
    }
  }

  async function handleIpRule(ip: string, action: IpRuleAction): Promise<boolean> {
    const actionKey = `${action}:${ip}`

    setSecurityAction(actionKey)
    setNotice(null)

    try {
      setSecurity(await updateIpRule(ip, action))
      setNotice({
        status: 'success',
        message: 'IP rule updated.',
      })

      return true
    } catch (error) {
      setNotice({
        status: 'error',
        message: error instanceof Error ? error.message : 'Unable to update the IP rule.',
      })

      return false
    } finally {
      setSecurityAction('')
    }
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
        post: settings.post,
        templates: settings.templates,
      })

      setSettings(saved)
      setSecurity((current) => ({
        ...current,
        alerts_enabled: saved.templates.login_failure.enabled,
      }))
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

  const defaultPost = getPosts(settings)

  const categoryOptions = [
    { label: 'Select category', value: '0' },
    ...categories.map((category) => ({
      label: `${category.name} (#${category.id})`,
      value: String(category.id),
    })),
  ]

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 16 }}>
      {notice && (
        <Notice isDismissible onRemove={() => setNotice(null)} status={notice.status}>
          {notice.message}
        </Notice>
      )}

      <TabPanel
        activeClass="is-active"
        className="wp-ntfy-tabs"
        tabs={[
          { name: 'settings', title: 'Settings' },
          { name: 'security', title: 'Security' },
          { name: 'posts', title: 'Posts' },
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
                    onChange={(value) => update('server_url', value)}
                    placeholder="https://ntfy.sh"
                    value={settings.server_url}
                  />

                  <TextControl
                    help={
                      settings.has_auth_token
                        ? 'There is currently an access token saved and encrypted.'
                        : 'Use this when your ntfy topic or server requires authentication.'
                    }
                    label="Access token"
                    onChange={(value) => setAuthToken(value)}
                    placeholder={
                      settings.has_auth_token
                        ? 'Leave blank to keep existing token.'
                        : 'Optional bearer token'
                    }
                    type="password"
                    value={authToken}
                  />

                  <ToggleControl
                    checked={clearAuthToken}
                    label="Clear saved access token"
                    onChange={(value) => setClearAuthToken(Boolean(value))}
                  />

                  <ToggleControl
                    checked={settings.include_user_agent}
                    help="Disabled by default to reduce noisy output."
                    label="Include user agent"
                    onChange={(value) => update('include_user_agent', Boolean(value))}
                  />
                </CardBody>
              </Card>
            ) : tab.name === 'security' ? (
              <SecurityPanel
                busyAction={securityAction}
                data={security}
                loading={securityLoading}
                onRefresh={refreshSecurity}
                onRule={handleIpRule}
              />
            ) : tab.name === 'posts' ? (
              <>
                <Card>
                  <CardHeader>
                    <h2 style={{ margin: 0 }}>Posts</h2>
                  </CardHeader>

                  <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <ToggleControl
                      checked={defaultPost.enabled}
                      help="Used when no category-specific setting matches."
                      label="Enable all post notifications"
                      onChange={(value) => setPosts('enabled', Boolean(value))}
                    />

                    <TextControl
                      help="Default ntfy topic for published posts."
                      label="Default topic"
                      onChange={(value) => setPosts('topic', value)}
                      placeholder="wordpress-{site_slug}"
                      value={defaultPost.topic}
                    />

                    <ToggleControl
                      checked={defaultPost.include_children}
                      label="Include child categories"
                      onChange={(value) => setPosts('include_children', Boolean(value))}
                    />
                  </CardBody>
                </Card>

                <Card>
                  <CardHeader>
                    <div>
                      <h2 style={{ margin: 0 }}>Category topics</h2>
                      <p style={{ margin: '6px 0 0' }}>
                        Override default post topic for selected categories.
                      </p>
                    </div>
                  </CardHeader>

                  <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {(settings.post ?? [])
                      .map((post, index) => ({ post, index }))
                      .filter(
                        ({ post }) =>
                          post.event_key === postEvent && post.rule_type === 'taxonomy_term'
                      )
                      .map(({ post, index }) => (
                        <div
                          key={`${post.id ?? 'new'}-${index}`}
                          style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                            padding: 12,
                            border: '1px solid #ddd',
                          }}
                        >
                          <ToggleControl
                            checked={post.enabled}
                            label="Enabled"
                            onChange={(value) => setPost(index, 'enabled', Boolean(value))}
                          />

                          <SelectControl
                            help="Select the WordPress category that should override the default topic."
                            label="Category"
                            onChange={(value) =>
                              setPost(index, 'term_id', Number.parseInt(value, 10) || 0)
                            }
                            options={categoryOptions}
                            value={String(post.term_id)}
                          />

                          <TextControl
                            label="Topic"
                            onChange={(value) => setPost(index, 'topic', value)}
                            placeholder="wordpress-news"
                            value={post.topic}
                          />

                          <ToggleControl
                            checked={post.include_children}
                            label="Include child categories"
                            onChange={(value) => setPost(index, 'include_children', Boolean(value))}
                          />

                          <Button
                            isDestructive
                            onClick={() => deletePost(index)}
                            variant="secondary"
                          >
                            Remove
                          </Button>
                        </div>
                      ))}

                    <Button onClick={addCategory} variant="secondary">
                      Add category
                    </Button>
                  </CardBody>
                </Card>
              </>
            ) : (
              <>
                {templateEvents.map((eventKey) => {
                  const template = settings.templates[eventKey]
                  const isPost = eventKey === 'post_published'

                  return (
                    <Card key={eventKey}>
                      <CardHeader>
                        <div>
                          <h2 style={{ margin: 0 }}>{templateLabels[eventKey]}</h2>

                          <p style={{ margin: '6px 0 0' }}>{templateDescriptions[eventKey]}</p>
                        </div>
                      </CardHeader>

                      <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        {!isPost && (
                          <>
                            <ToggleControl
                              checked={template.enabled}
                              label="Enabled"
                              onChange={(value) => setTemplate(eventKey, 'enabled', Boolean(value))}
                            />

                            <TextControl
                              label="Topic"
                              onChange={(value) => setTemplate(eventKey, 'topic', value)}
                              value={template.topic}
                            />
                          </>
                        )}

                        <TextControl
                          label="Title"
                          onChange={(value) => setTemplate(eventKey, 'title', value)}
                          value={template.title}
                        />

                        <TextareaControl
                          label="Message"
                          onChange={(value) => setTemplate(eventKey, 'message', value)}
                          rows={6}
                          value={template.message}
                        />

                        <SelectControl
                          label="Priority"
                          onChange={(value) => setTemplate(eventKey, 'priority', value as Priority)}
                          options={priorityOptions}
                          value={template.priority}
                        />

                        <TextControl
                          help="Comma-separated ntfy tags."
                          label="Tags"
                          onChange={(value) => setTemplate(eventKey, 'tags', value)}
                          placeholder="key,warning"
                          value={template.tags}
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
          disabled={saving}
          isBusy={saving}
          onClick={() => void handleSave(false)}
          variant="primary"
        >
          Save
        </Button>

        <Button
          disabled={saving}
          isBusy={saving}
          onClick={() => void handleSave(true)}
          variant="secondary"
        >
          Test and Save
        </Button>
      </div>
    </div>
  )
}
