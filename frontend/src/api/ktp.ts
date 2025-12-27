import apiClient from './client'

export interface KtpPlanDTO {
  id: number
  subject_id: number
  group_id: number
  term_id: number
  teacher_user_id: number | null
  name: string | null
  description: string | null
  status: 'draft' | 'active' | 'archived'
  created_by: number
  created_at: string
  updated_at: string
  subject?: {
    id: number
    name: string
  }
  group?: {
    id: number
    name: string
  }
  term?: {
    id: number
    name: string
  }
  teacher?: {
    id: number
    fio: string
  }
  creator?: {
    id: number
    fio: string
  }
  topics?: KtpTopicDTO[]
}

export interface KtpTopicDTO {
  id: number
  curriculum_plan_id: number
  order_no: number
  title: string
  description: string | null
  hours: number | null
  hours_total: number | null
  hours_lecture: number | null
  hours_practice: number | null
  hours_lab: number | null
  control_type: string | null
  planned_date_from: string | null
  planned_date_to: string | null
  created_at: string
  updated_at: string
  links?: KtpTopicLinkDTO[]
}

export interface KtpTopicLinkDTO {
  id: number
  ktp_topic_id: number
  lesson_id: number | null
  assignment_id: number | null
  material_id: number | null
  created_at: string
  updated_at: string
  lesson?: {
    id: number
    title: string
  }
  assignment?: {
    id: number
    title: string
  }
  material?: {
    id: number
    title: string
  }
}

export interface KtpTemplateDTO {
  id: number
  subject_id: number | null
  name: string
  data_json: Record<string, any> | null
  created_by: number
  created_at: string
  updated_at: string
  subject?: {
    id: number
    name: string
  }
  creator?: {
    id: number
    fio: string
  }
}

export interface KtpProgressDTO {
  total_topics: number
  linked_topics_count: number
  percent: number
  alerts: Array<{
    topic_id: number
    topic_title: string
    message: string
  }>
}

export interface KtpListResponse {
  data: KtpPlanDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const ktpApi = {
  // Plans
  async getPlans(params?: {
    termId?: number
    groupId?: number
    subjectId?: number
    status?: string
    per_page?: number
    page?: number
  }): Promise<KtpListResponse> {
    const response = await apiClient.get('/v1/ktp/plans', { params })
    return response.data
  },

  async getPlan(id: number): Promise<KtpPlanDTO> {
    const response = await apiClient.get(`/v1/ktp/plans/${id}`)
    return response.data
  },

  async createPlan(data: {
    subject_id: number
    group_id: number
    term_id: number
    name?: string
    description?: string
  }): Promise<KtpPlanDTO> {
    const response = await apiClient.post('/v1/ktp/plans', data)
    return response.data
  },

  async updatePlan(id: number, data: {
    name?: string
    description?: string
    status?: string
  }): Promise<KtpPlanDTO> {
    const response = await apiClient.patch(`/v1/ktp/plans/${id}`, data)
    return response.data
  },

  // Topics
  async addTopics(planId: number, topics: Array<{
    order_no: number
    title: string
    hours?: number
    control_type?: string
    planned_date_from?: string
    planned_date_to?: string
  }>): Promise<void> {
    await apiClient.post(`/v1/ktp/plans/${planId}/topics`, { topics })
  },

  async updateTopic(id: number, data: {
    order_no?: number
    title?: string
    hours?: number
    control_type?: string
    planned_date_from?: string
    planned_date_to?: string
  }): Promise<KtpTopicDTO> {
    const response = await apiClient.patch(`/v1/ktp/topics/${id}`, data)
    return response.data
  },

  async deleteTopic(id: number): Promise<void> {
    await apiClient.delete(`/v1/ktp/topics/${id}`)
  },

  async linkTopic(topicId: number, data: {
    lessonId?: number
    assignmentId?: number
    materialId?: number
  }): Promise<KtpTopicLinkDTO> {
    const response = await apiClient.post(`/v1/ktp/topics/${topicId}/link`, data)
    return response.data
  },

  // Progress
  async getProgress(planId: number): Promise<KtpProgressDTO> {
    const response = await apiClient.get(`/v1/ktp/plans/${planId}/progress`)
    return response.data
  },

  // Templates
  async getTemplates(params?: {
    subjectId?: number
    per_page?: number
    page?: number
  }): Promise<{ data: KtpTemplateDTO[] }> {
    const response = await apiClient.get('/v1/ktp/templates', { params })
    return response.data
  },

  async applyTemplate(planId: number, templateId: number): Promise<void> {
    await apiClient.post(`/v1/ktp/plans/${planId}/apply-template`, { templateId })
  },

  async copyFrom(planId: number, sourcePlanId: number): Promise<void> {
    await apiClient.post(`/v1/ktp/plans/${planId}/copy-from`, { sourcePlanId })
  },
}

export default ktpApi









