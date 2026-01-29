import apiClient from './client'

export interface AuditLogDTO {
  id: number
  tenant_id: number | null
  user_id: number | null
  action: string
  entity: string
  entity_id: number | null
  before_json: Record<string, any> | null
  after_json: Record<string, any> | null
  ip: string | null
  created_at: string
  user_fio: string | null
  user_email: string | null
}

export interface AuditLogListResponse {
  data: AuditLogDTO[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface AuditLogFilters {
  entity?: string
  action?: string
  userId?: number
  dateFrom?: string
  dateTo?: string
  search?: string
  per_page?: number
  page?: number
}

export const auditApi = {
  /**
   * Get audit logs with filtering
   */
  async list(filters: AuditLogFilters = {}): Promise<AuditLogListResponse> {
    const params: Record<string, string | number> = {}
    
    if (filters.entity) params.entity = filters.entity
    if (filters.action) params.action = filters.action
    if (filters.userId) params.userId = String(filters.userId)
    if (filters.dateFrom) params.dateFrom = filters.dateFrom
    if (filters.dateTo) params.dateTo = filters.dateTo
    if (filters.search) params.search = filters.search
    if (filters.per_page) params.per_page = filters.per_page
    if (filters.page) params.page = filters.page

    const response = await apiClient.get<AuditLogListResponse>('/admin/audit', { params })
    return response.data
  },

  /**
   * Get single audit log by ID
   */
  async get(id: number): Promise<AuditLogDTO> {
    const response = await apiClient.get<AuditLogDTO>(`/admin/audit/${id}`)
    return response.data
  },

  /**
   * Export audit logs to XLSX
   */
  async export(filters: AuditLogFilters = {}): Promise<void> {
    const params: Record<string, string | number> = {
      format: 'xlsx',
    }
    
    if (filters.entity) params.entity = filters.entity
    if (filters.action) params.action = filters.action
    if (filters.userId) params.userId = String(filters.userId)
    if (filters.dateFrom) params.dateFrom = filters.dateFrom
    if (filters.dateTo) params.dateTo = filters.dateTo
    if (filters.search) params.search = filters.search

    const response = await apiClient.get('/admin/audit/export', {
      params,
      responseType: 'blob',
    })

    // Create download link
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url

    // Get filename from Content-Disposition header or use default
    const contentDisposition = response.headers['content-disposition']
    let filename = 'audit_logs_export.xlsx'
    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="?(.+)"?/i)
      if (filenameMatch) {
        filename = filenameMatch[1]
      }
    }

    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  },
}

