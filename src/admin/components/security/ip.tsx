import { Button } from '@wordpress/components'

import { type IpRuleAction, type SecurityIp } from '../../types'

type Props = {
  busyAction: string
  currentIp: string
  ip: SecurityIp
  onRule: (address: string, action: IpRuleAction) => Promise<boolean>
}

export function IpActions({ busyAction, currentIp, ip, onRule }: Props) {
  const isBusy = (action: IpRuleAction) => busyAction === `${action}:${ip.ip_address}`
  const action = busyAction !== ''

  async function apply(action: IpRuleAction) {
    await onRule(ip.ip_address, action)
  }

  if (ip.status === 'whitelisted') {
    return (
      <Button
        disabled={action}
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
          disabled={action}
          isBusy={isBusy('unban')}
          onClick={() => void apply('unban')}
          size="small"
          variant="secondary"
        >
          Unban
        </Button>

        <Button
          disabled={action}
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
        disabled={action || ip.ip_address === currentIp}
        isBusy={isBusy('ban')}
        isDestructive
        onClick={() => void apply('ban')}
        size="small"
        variant="secondary"
      >
        Ban
      </Button>

      <Button
        disabled={action}
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
