import { Card, CardBody, CardHeader } from '@wordpress/components'

import { type SecurityData } from '../../types'
import { formatGmt } from '../../utils/format-date'
import { getError } from '../../utils/get-error'

type Props = {
  attempts: SecurityData['attempts']
  retentionDays: number
}

export function Attempts({ attempts, retentionDays }: Props) {
  return (
    <Card>
      <CardHeader>
        <div className="rx-card-heading">
          <h2>Failed logins</h2>
          <p>Latest 50 attempts. Detailed events are retained for {retentionDays} days.</p>
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
              {attempts.length === 0 ? (
                <tr>
                  <td className="rx-empty" colSpan={4}>
                    No failed logins have been recorded.
                  </td>
                </tr>
              ) : (
                attempts.map((attempt) => (
                  <tr key={attempt.id}>
                    <td>{formatGmt(attempt.attempted_at_gmt)}</td>
                    <td>
                      <code>{attempt.ip_address || 'Unavailable'}</code>
                    </td>
                    <td>{attempt.username || 'Not supplied'}</td>
                    <td style={{ textTransform: 'capitalize' }}>{getError(attempt.error_code)}</td>
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
