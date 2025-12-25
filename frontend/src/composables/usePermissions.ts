import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

/**
 * Composable for permission checks in components
 */
export function usePermissions() {
  const auth = useAuthStore()

  const can = (permissionCode: string): boolean => {
    return auth.hasPermission(permissionCode)
  }

  const canAny = (permissionCodes: string[]): boolean => {
    return permissionCodes.some((code) => can(code))
  }

  const canAll = (permissionCodes: string[]): boolean => {
    return permissionCodes.every((code) => can(code))
  }

  const hasRole = (roleName: string): boolean => {
    return auth.hasRole(roleName)
  }

  const hasAnyRole = (roleNames: string[]): boolean => {
    return roleNames.some((name) => hasRole(name))
  }

  return {
    can,
    canAny,
    canAll,
    hasRole,
    hasAnyRole,
    isAuthenticated: computed(() => auth.isAuthenticated),
    user: computed(() => auth.user),
    roles: computed(() => auth.roles),
    permissions: computed(() => auth.permissions),
  }
}

