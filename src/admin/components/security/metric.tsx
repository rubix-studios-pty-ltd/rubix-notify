type Props = {
  label: string
  value: number
}

export function Metric({ label, value }: Props) {
  return (
    <div className="rx-metric">
      <span className="rx-metric__label">{label}</span>
      <strong className="rx-metric__value">{value.toLocaleString()}</strong>
    </div>
  )
}
