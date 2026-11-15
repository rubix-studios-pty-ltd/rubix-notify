import { Notice } from '@wordpress/components'

import { type IpRuleAction, type SecurityData } from '../../types'
import { Metric } from '../metric'
import { Activity } from './activity'
import { Attempts } from './attempts'
import { Whitelist } from './whitelist'

type Props = {
  busyAction: string
  data: SecurityData
  loading: boolean
  onRefresh: () => Promise<void>
  onRule: (ip: string, action: IpRuleAction) => Promise<boolean>
}

export function Security({ busyAction, data, loading, onRefresh, onRule }: Props) {
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

      <Whitelist
        alertThreshold={data.alert_threshold}
        busyAction={busyAction}
        currentIp={data.current_ip}
        onRule={onRule}
        windowMinutes={data.window_minutes}
      />

      <Activity
        busyAction={busyAction}
        currentIp={data.current_ip}
        ips={data.ips}
        loading={loading}
        onRefresh={onRefresh}
        onRule={onRule}
      />

      <Attempts attempts={data.attempts} retentionDays={data.retention_days} />
    </div>
  )
}
