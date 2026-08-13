export function getError(errorCode: string): string {
  if (!errorCode) {
    return 'Authentication failed'
  }

  return errorCode.replace(/_/g, ' ')
}
