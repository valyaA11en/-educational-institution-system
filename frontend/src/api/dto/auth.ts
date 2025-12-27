// Auth DTOs matching backend contracts

export interface LoginRequestDTO {
  emailOrPhone: string
  password: string
}

export interface AuthTokensDTO {
  accessToken: string
  refreshToken: string
  expiresIn: number
}

export interface UserDTO {
  id: number
  fio: string
  email: string | null
  phone: string | null
  status: 'active' | 'blocked'
}

export interface RoleDTO {
  id: number
  name: string
}

export interface PermissionDTO {
  id: number
  code: string
  description: string | null
}

export interface MeDTO {
  user: UserDTO
  roles: RoleDTO[]
  permissions: PermissionDTO[]
}









