import { Button, Card, CardBody, CardHeader, TextControl } from '@wordpress/components'
import { useState } from '@wordpress/element'

import { type IpRuleAction } from '../../types'

type Props = {
  alertThreshold: number
  busyAction: string
  currentIp: string
  onRule: (ip: string, action: IpRuleAction) => Promise<boolean>
  windowMinutes: number
}

export function Whitelist({ alertThreshold, busyAction, currentIp, onRule, windowMinutes }: Props) {
  const [whitelist, setWhitelist] = useState('')
  const ip = whitelist.trim()

  async function addWhitelist() {
    const updated = await onRule(ip, 'whitelist')

    if (updated) {
      setWhitelist('')
    }
  }

  return (
    <Card>
      <CardHeader>
        <div className="rx-card-heading">
          <h2>Whitelist</h2>
          <p>
            Failed login notifications are triggered after {alertThreshold} attempts within{' '}
            {windowMinutes} minutes.
          </p>
        </div>
      </CardHeader>

      <CardBody style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div className="rx-rule-form">
          <TextControl
            onChange={setWhitelist}
            placeholder="203.0.113.10 or 2001:db8::1"
            value={whitelist}
          />

          <Button
            disabled={busyAction !== '' || ip === ''}
            isBusy={busyAction === `whitelist:${ip}`}
            onClick={() => void addWhitelist()}
            variant="primary"
          >
            Whitelist
          </Button>
        </div>

        <p className="rx-security-note">
          Detected IP: <code>{currentIp || 'Unavailable'}</code>.
        </p>
      </CardBody>
    </Card>
  )
}
