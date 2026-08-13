export function formatGmt(value: string | null): string {
  if (!value) {
    return 'Never'
  }

  const date = new Date(`${value.replace(' ', 'T')}Z`)

  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}
