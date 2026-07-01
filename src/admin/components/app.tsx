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

import { getCategories, getSettings, saveSettings, sendTest } from '../api'
import {
  priorityOptions,
  templateDescriptions,
  templateLabels,
  emptySettings,
  type NoticeState,
} from '../constants'
import {
  type Category,
  type Priority,
  type Settings,
  postEvent,
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

export function App() {
  const [settings, setSettings] = useState<Settings>(emptySettings)
  const [authToken, setAuthToken] = useState('')
  const [clearAuthToken, setClearAuthToken] = useState(false)
  const [notice, setNotice] = useState<NoticeState | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [categories, setCategories] = useState<Category[]>([])

  useEffect(() => {
    void loadSettings()
  }, [])

  async function loadSettings() {
    setLoading(true)

    try {
      const [result, categoryResult] = await Promise.all([getSettings(), getCategories()])

      setSettings(result)
      setCategories(categoryResult)
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
        <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <TabPanel
        className="wp-ntfy-tabs"
        activeClass="is-active"
        tabs={[
          { name: 'settings', title: 'Settings' },
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
            ) : tab.name === 'posts' ? (
              <>
                <Card>
                  <CardHeader>
                    <h2 style={{ margin: 0 }}>Posts</h2>
                  </CardHeader>

                  <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <ToggleControl
                      label="Enable all post notifications"
                      checked={defaultPost.enabled}
                      help="Used when no category-specific setting matches."
                      onChange={(value) => setPosts('enabled', Boolean(value))}
                    />

                    <TextControl
                      label="Default topic"
                      value={defaultPost.topic}
                      placeholder="wordpress-{site_slug}"
                      help="Default ntfy topic for published posts."
                      onChange={(value) => setPosts('topic', value)}
                    />

                    <ToggleControl
                      label="Include child categories"
                      checked={defaultPost.include_children}
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
                            label="Enabled"
                            checked={post.enabled}
                            onChange={(value) => setPost(index, 'enabled', Boolean(value))}
                          />

                          <SelectControl
                            label="Category"
                            value={String(post.term_id)}
                            options={categoryOptions}
                            help="Select the WordPress category that should override the default topic."
                            onChange={(value) =>
                              setPost(index, 'term_id', Number.parseInt(value, 10) || 0)
                            }
                          />

                          <TextControl
                            label="Topic"
                            value={post.topic}
                            placeholder="wordpress-news"
                            onChange={(value) => setPost(index, 'topic', value)}
                          />

                          <ToggleControl
                            label="Include child categories"
                            checked={post.include_children}
                            onChange={(value) => setPost(index, 'include_children', Boolean(value))}
                          />

                          <Button
                            variant="secondary"
                            isDestructive
                            onClick={() => deletePost(index)}
                          >
                            Remove
                          </Button>
                        </div>
                      ))}

                    <Button variant="secondary" onClick={addCategory}>
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
                              label="Enabled"
                              checked={template.enabled}
                              onChange={(value) => setTemplate(eventKey, 'enabled', Boolean(value))}
                            />

                            <TextControl
                              label="Topic"
                              value={template.topic}
                              onChange={(value) => setTemplate(eventKey, 'topic', value)}
                            />
                          </>
                        )}

                        <TextControl
                          label="Title"
                          value={template.title}
                          onChange={(value) => setTemplate(eventKey, 'title', value)}
                        />

                        <TextareaControl
                          label="Message"
                          rows={6}
                          value={template.message}
                          onChange={(value) => setTemplate(eventKey, 'message', value)}
                        />

                        <SelectControl
                          label="Priority"
                          value={template.priority}
                          options={priorityOptions}
                          onChange={(value) => setTemplate(eventKey, 'priority', value as Priority)}
                        />

                        <TextControl
                          label="Tags"
                          value={template.tags}
                          placeholder="key,warning"
                          help="Comma-separated ntfy tags."
                          onChange={(value) => setTemplate(eventKey, 'tags', value)}
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
