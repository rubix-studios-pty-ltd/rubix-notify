import { Button, Card, CardBody, CardHeader, Notice, TextControl } from '@wordpress/components'
import { useState } from '@wordpress/element'

import { type IpRuleAction, type SecurityData, type SecurityIp } from '../types'
import { formatGmt } from '../utils/format-date'
import { getError } from '../utils/get-error'

interface SecurityProps {
  busyAction: string
  data: SecurityData
  loading: boolean
  onRefresh: () => Promise<void>
  onRule: (ip: string, action: IpRuleAction) => Promise<boolean>
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rx-metric">
      <span className="rx-metric__label">{label}</span>
      <strong className="rx-metric__value">{value.toLocaleString()}</strong>
    </div>
  )
}

function IpActions({
  busyAction,
  currentIp,
  ip,
  onRule,
}: {
  busyAction: string
  currentIp: string
  ip: SecurityIp
  onRule: (address: string, action: IpRuleAction) => Promise<boolean>
}) {
  const isBusy = (action: IpRuleAction) => busyAction === `${action}:${ip.ip_address}`
  const actionsDisabled = busyAction !== ''

  async function apply(action: IpRuleAction) {
    await onRule(ip.ip_address, action)
  }

  if (ip.status === 'whitelisted') {
    return (
      <Button
        disabled={actionsDisabled}
        isBusy={isBusy('remove_whitelist')}
        onClick={() => void apply('remove_whitelist')}
        size="small"
        variant="secondary"
      >
        Remove
      </Button>
    )
  }

  if (ip.status === 'banned') {
    return (
      <>
        <Button
          disabled={actionsDisabled}
          isBusy={isBusy('unban')}
          onClick={() => void apply('unban')}
          size="small"
          variant="secondary"
        >
          Unban
        </Button>

        <Button
          disabled={actionsDisabled}
          isBusy={isBusy('whitelist')}
          onClick={() => void apply('whitelist')}
          size="small"
          variant="secondary"
        >
          Whitelist
        </Button>
      </>
    )
  }

  return (
    <>
      <Button
        disabled={actionsDisabled || ip.ip_address === currentIp}
        isBusy={isBusy('ban')}
        isDestructive
        onClick={() => void apply('ban')}
        size="small"
        variant="secondary"
      >
        Ban
      </Button>

      <Button
        disabled={actionsDisabled}
        isBusy={isBusy('whitelist')}
        onClick={() => void apply('whitelist')}
        size="small"
        variant="secondary"
      >
        Whitelist
      </Button>
    </>
  )
}

export function SecurityPanel({ busyAction, data, loading, onRefresh, onRule }: SecurityProps) {
  const [whitelistIp, setWhitelistIp] = useState('')

  async function addWhitelist() {
    const updated = await onRule(whitelistIp.trim(), 'whitelist')

    if (updated) {
      setWhitelistIp('')
    }
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      {!data.alerts_enabled && (
        <Notice isDismissible={false} status="warning">
          Threshold alerts are currently off. Enable <strong>Repeated failed logins</strong> under
          Templates to send ntfy alerts. Attempt tracking and IP rules remain active.
        </Notice>
      )}

      <div className="rx-security-metrics">
        <Metric label="Failures in the last hour" value={data.summary.attempts_last_hour} />
        <Metric label="Tracked IPs" value={data.summary.tracked_ips} />
        <Metric label="Banned IPs" value={data.summary.banned_ips} />
        <Metric label="Whitelisted IPs" value={data.summary.whitelisted_ips} />
      </div>

      <Card>
        <CardHeader>
          <div className="rx-card-heading">
            <h2>Whitelist</h2>
            <p>
              Failed login notications are trigged after {data.alert_threshold} attempts within{' '}
              {data.window_minutes} minutes.
            </p>
          </div>
        </CardHeader>

        <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div className="rx-rule-form">
            <TextControl
              onChange={setWhitelistIp}
              placeholder="203.0.113.10 or 2001:db8::1"
              value={whitelistIp}
            />

            <Button
              disabled={busyAction !== '' || whitelistIp.trim() === ''}
              isBusy={busyAction === `whitelist:${whitelistIp.trim()}`}
              onClick={() => void addWhitelist()}
              variant="primary"
            >
              Whitelist
            </Button>
          </div>

          <p className="rx-security-note">
            Detected IP: <code>{data.current_ip || 'Unavailable'}</code>.
          </p>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <div className="rx-card-heading rx-card-heading--row">
            <div>
              <h2>Activity</h2>
              <p>Active bans and whitelist entries stay pinned above recent activity.</p>
            </div>

            <Button
              disabled={loading || busyAction !== ''}
              isBusy={loading}
              onClick={() => void onRefresh()}
              variant="secondary"
            >
              Refresh
            </Button>
          </div>
        </CardHeader>

        <CardBody>
          <div className="rx-table-wrap">
            <table className="rx-table">
              <thead>
                <tr>
                  <th>IP address</th>
                  <th>Status</th>
                  <th>Last hour</th>
                  <th>Total failures</th>
                  <th>Last failure</th>
                  <th>Last alert</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {data.ips.length === 0 ? (
                  <tr>
                    <td className="rx-empty" colSpan={7}>
                      No IP activity has been recorded yet.
                    </td>
                  </tr>
                ) : (
                  data.ips.map((ip) => (
                    <tr key={ip.ip_address}>
                      <td>
                        <code>{ip.ip_address}</code>
                        {ip.ip_address === data.current_ip && (
                          <span className="rx-current-ip">Current session</span>
                        )}
                      </td>
                      <td>
                        <span className={`rx-badge rx-badge--${ip.status}`}>
                          {ip.status === 'observed' ? 'Monitored' : ip.status}
                        </span>
                      </td>
                      <td>{ip.failures_last_hour.toLocaleString()}</td>
                      <td>{ip.total_failures.toLocaleString()}</td>
                      <td>{formatGmt(ip.last_failed_at_gmt)}</td>
                      <td>{formatGmt(ip.last_notified_at_gmt)}</td>
                      <td>
                        <div className="rx-actions">
                          <IpActions
                            busyAction={busyAction}
                            currentIp={data.current_ip}
                            ip={ip}
                            onRule={onRule}
                          />
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <div className="rx-card-heading">
            <h2>Failed logins</h2>
            <p>Latest 50 attempts. Detailed events are retained for {data.retention_days} days.</p>
          </div>
        </CardHeader>

        <CardBody>
          <div className="rx-table-wrap">
            <table className="rx-table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>IP address</th>
                  <th>Username or email</th>
                  <th>Result</th>
                </tr>
              </thead>
              <tbody>
                {data.attempts.length === 0 ? (
                  <tr>
                    <td className="rx-empty" colSpan={4}>
                      No failed logins have been recorded.
                    </td>
                  </tr>
                ) : (
                  data.attempts.map((attempt) => (
                    <tr key={attempt.id}>
                      <td>{formatGmt(attempt.attempted_at_gmt)}</td>
                      <td>
                        <code>{attempt.ip_address || 'Unavailable'}</code>
                      </td>
                      <td>{attempt.username || 'Not supplied'}</td>
                      <td style={{ textTransform: 'capitalize' }}>
                        {getError(attempt.error_code)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>
    </div>
  )
}
