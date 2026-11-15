export function formatGmt(value: string | null): string {
  if (!value) {
    return 'Never'
  }

  const timestamp = value.trim().replace(' ', 'T')
  const timezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(timestamp)
  const date = new Date(timezone ? timestamp : `${timestamp}Z`)

  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}
