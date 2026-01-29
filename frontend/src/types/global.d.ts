/* eslint-disable @typescript-eslint/no-unused-vars */
import { ComponentCustomProperties } from 'vue'

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $can: (permissionCode: string) => boolean
    $canAny: (permissionCodes: string[]) => boolean
    $canAll: (permissionCodes: string[]) => boolean
    $hasRole: (roleName: string) => boolean
    $hasAnyRole: (roleNames: string[]) => boolean
  }
}









