export function getError(errorCode: string): string {
  const code = errorCode.trim()

  if (!code) {
    return 'Authentication failed'
  }

  return code.replace(/_/g, ' ')
}
