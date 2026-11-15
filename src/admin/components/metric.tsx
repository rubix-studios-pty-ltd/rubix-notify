export function Metric({ label, value }: { label: string; value: number }) {
  return (
    <div className="rx-metric">
      <span className="rx-metric__label">{label}</span>
      <strong className="rx-metric__value">{value.toLocaleString()}</strong>
    </div>
  )
}
