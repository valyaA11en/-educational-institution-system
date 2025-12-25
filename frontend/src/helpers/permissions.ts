import { useAuthStore } from '../stores/auth'

/**
 * Check if user has permission
 * @param permissionCode - Permission code to check
 * @returns boolean
 */
export function can(permissionCode: string): boolean {
  const auth = useAuthStore()
  return auth.hasPermission(permissionCode)
}

/**
 * Check if user has any of the permissions
 * @param permissionCodes - Array of permission codes
 * @returns boolean
 */
export function canAny(permissionCodes: string[]): boolean {
  return permissionCodes.some((code) => can(code))
}

/**
 * Check if user has all of the permissions
 * @param permissionCodes - Array of permission codes
 * @returns boolean
 */
export function canAll(permissionCodes: string[]): boolean {
  return permissionCodes.every((code) => can(code))
}

/**
 * Check if user has role
 * @param roleName - Role name to check
 * @returns boolean
 */
export function hasRole(roleName: string): boolean {
  const auth = useAuthStore()
  return auth.hasRole(roleName)
}

/**
 * Check if user has any of the roles
 * @param roleNames - Array of role names
 * @returns boolean
 */
export function hasAnyRole(roleNames: string[]): boolean {
  return roleNames.some((name) => hasRole(name))
}

