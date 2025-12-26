import apiClient from './client'

export interface ExamDTO {
  id: number
  term_id: number
  type: 'exam' | 'test' | 'attestation'
  title: string
  subject_id: number | null
  group_id: number | null
  date_at: string
  room_id: number | null
  created_by: number
  subject?: { id: number; name: string }
  group?: { id: number; name: string }
  term?: { id: number; name: string }
  room?: { id: number; name: string }
  creator?: { id: number; fio: string }
  commissions?: ExamCommissionDTO[]
  registrations?: ExamRegistrationDTO[]
  results?: ExamResultDTO[]
}

export interface ExamCommissionDTO {
  id: number
  exam_id: number
  user_id: number
  role: 'chair' | 'member'
  user?: { id: number; fio: string }
}

export interface ExamRegistrationDTO {
  id: number
  exam_id: number
  student_user_id: number
  status: 'registered' | 'admitted' | 'not_admitted' | 'passed' | 'failed'
  reason: string | null
  student?: { id: number; fio: string }
}

export interface ExamResultDTO {
  id: number
  exam_id: number
  student_user_id: number
  score: number | null
  grade_value: number | null
  comment: string | null
  student?: { id: number; fio: string }
}

export interface ExamCommissionDTO {
  id: number
  exam_id: number
  user_id: number
  role: 'chairman' | 'member' | 'secretary'
  user?: { id: number; fio: string }
}

export interface ExamAdmissionDTO {
  id: number
  exam_id: number
  user_id: number
  status: 'admitted' | 'not_admitted' | 'pending'
  reason: string | null
  user?: { id: number; fio: string }
}

export const examsApi = {
  async list(params?: {
    termId?: number
    groupId?: number
    from?: string
    to?: string
    per_page?: number
  }): Promise<{ data: ExamDTO[]; current_page: number; total: number }> {
    const response = await apiClient.get('/v1/exams', { params })
    return response.data
  },

  async create(payload: {
    term_id: number
    type: string
    title: string
    subject_id?: number
    group_id?: number
    date_at: string
    room_id?: number
  }): Promise<ExamDTO> {
    const response = await apiClient.post('/v1/exams', payload)
    return response.data
  },

  async get(id: number): Promise<ExamDTO> {
    const response = await apiClient.get(`/v1/exams/${id}`)
    return response.data
  },

  async update(id: number, payload: Partial<ExamDTO>): Promise<ExamDTO> {
    const response = await apiClient.patch(`/v1/exams/${id}`, payload)
    return response.data
  },

  async delete(id: number): Promise<void> {
    await apiClient.delete(`/v1/exams/${id}`)
  },

  async setCommission(examId: number, members: Array<{ user_id: number; role: string }>): Promise<{ members: ExamCommissionDTO[] }> {
    const response = await apiClient.post(`/v1/exams/${examId}/commission`, { members })
    return response.data
  },

  async getCommission(examId: number): Promise<{ members: ExamCommissionDTO[] }> {
    const response = await apiClient.get(`/v1/exams/${examId}/commission`)
    return response.data
  },

  async seedRegistrations(examId: number): Promise<{ message: string }> {
    const response = await apiClient.post(`/v1/exams/${examId}/registrations/seed`)
    return response.data
  },

  async evaluateAdmission(examId: number, studentId: number): Promise<{ status: string; reason: string | null; metrics: any }> {
    const response = await apiClient.post(`/v1/exams/${examId}/registrations/${studentId}/evaluate-admission`)
    return response.data
  },

  async getAdmissionReport(examId: number): Promise<{ registrations: ExamRegistrationDTO[] }> {
    const response = await apiClient.get(`/v1/exams/${examId}/admission-report`)
    return response.data
  },

  async setResult(examId: number, payload: { student_user_id: number; score?: number; grade_value?: number; comment?: string }): Promise<ExamResultDTO> {
    const response = await apiClient.post(`/v1/exams/${examId}/results`, payload)
    return response.data
  },

  async getResults(examId: number): Promise<{ results: ExamResultDTO[] }> {
    const response = await apiClient.get(`/v1/exams/${examId}/results`)
    return response.data
  },

  async generateSheet(examId: number): Promise<{ document_id: number; document: any }> {
    const response = await apiClient.post(`/v1/exams/${examId}/generate-sheet`)
    return response.data
  },
}

