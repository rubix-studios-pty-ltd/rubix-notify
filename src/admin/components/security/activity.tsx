import { Button, Card, CardBody, CardHeader } from '@wordpress/components'

import { type IpRuleAction, type SecurityData } from '../../types'
import { formatGmt } from '../../utils/format-date'
import { IpActions } from '../ip'

type Props = {
  busyAction: string
  currentIp: string
  ips: SecurityData['ips']
  loading: boolean
  onRefresh: () => Promise<void>
  onRule: (ip: string, action: IpRuleAction) => Promise<boolean>
}

export function Activity({ busyAction, currentIp, ips, loading, onRefresh, onRule }: Props) {
  return (
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
              {ips.length === 0 ? (
                <tr>
                  <td className="rx-empty" colSpan={7}>
                    No IP activity has been recorded yet.
                  </td>
                </tr>
              ) : (
                ips.map((ip) => (
                  <tr key={ip.ip_address}>
                    <td>
                      <code>{ip.ip_address}</code>
                      {ip.ip_address === currentIp && (
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
                          currentIp={currentIp}
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
  )
}
