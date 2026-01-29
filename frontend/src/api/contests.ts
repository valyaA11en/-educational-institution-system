import apiClient from './client'

export interface ContestDTO {
  id: number
  title: string
  description: string | null
  start_at: string
  end_at: string
  visibility_scope: 'all' | 'group' | 'invite'
  created_by: number
  created_at: string
  updated_at: string
  creator?: {
    id: number
    name: string
    fio: string
  }
  targets?: Array<{
    id: number
    group_id: number | null
    user_id: number | null
    group?: { id: number; name: string }
    user?: { id: number; name: string; fio: string }
  }>
  jury?: Array<{
    id: number
    user_id: number
    role: 'chair' | 'member'
    user?: { id: number; name: string; fio: string }
  }>
  rubrics?: Array<{
    id: number
    title: string
    criteria_json: Array<{
      key: string
      title: string
      maxScore: number
      weight?: number
    }>
  }>
  submissions?: ContestSubmissionDTO[]
  results?: ContestResultDTO[]
}

export interface ContestTargetDTO {
  id: number
  contest_id: number
  group_id: number | null
  user_id: number | null
  group?: { id: number; name: string }
  user?: { id: number; name: string; fio: string }
}

export interface ContestSubmissionDTO {
  id: number
  contest_id: number
  participant_user_id: number
  title: string | null
  description: string | null
  created_at: string
  updated_at: string
  participant?: { id: number; name: string; fio: string }
  files?: Array<{ id: number; name: string; url: string }>
  scores?: ContestScoreDTO[]
  result?: ContestResultDTO
}

export interface ContestScoreDTO {
  id: number
  contest_id: number
  submission_id: number
  jury_user_id: number
  rubric_json: Record<string, number>
  total_score: number
  comment: string | null
  created_at: string
  updated_at: string
  juryUser?: { id: number; name: string; fio: string }
}

export interface ContestResultDTO {
  id: number
  contest_id: number
  submission_id: number
  place: number | null
  final_score: number
  published_at: string | null
  created_at: string
  updated_at: string
  submission?: ContestSubmissionDTO
}

export interface ContestRubricDTO {
  id: number
  contest_id: number
  title: string
  criteria_json: Array<{
    key: string
    title: string
    maxScore: number
    weight?: number
  }>
  created_at: string
  updated_at: string
}

export interface ContestJuryDTO {
  id: number
  contest_id: number
  user_id: number
  role: 'chair' | 'member'
  user?: { id: number; name: string; fio: string }
}

const base = '/v1/contests'

export const contestsApi = {
  list: (params?: { active?: boolean; visibility_scope?: string }) => {
    return apiClient.get<{ data: ContestDTO[]; total: number }>(base, { params })
  },

  get: (id: number) => {
    return apiClient.get<ContestDTO>(`${base}/${id}`)
  },

  create: (data: {
    title: string
    description?: string
    start_at: string
    end_at: string
    visibility_scope: 'all' | 'group' | 'invite'
    targets?: Array<{ group_id?: number; user_id?: number }>
  }) => {
    return apiClient.post<ContestDTO>(base, data)
  },

  update: (id: number, data: {
    title?: string
    description?: string
    start_at?: string
    end_at?: string
    visibility_scope?: 'all' | 'group' | 'invite'
  }) => {
    return apiClient.patch<ContestDTO>(`${base}/${id}`, data)
  },

  delete: (id: number) => {
    return apiClient.delete(`${base}/${id}`)
  },

  setTargets: (id: number, targets: Array<{ group_id?: number; user_id?: number }>) => {
    return apiClient.post(`${base}/${id}/targets`, { targets })
  },

  addJury: (id: number, data: { user_id: number; role: 'chair' | 'member' }) => {
    return apiClient.post<ContestJuryDTO>(`${base}/${id}/jury`, data)
  },

  addRubric: (id: number, data: {
    title: string
    criteria_json: Array<{ key: string; title: string; maxScore: number; weight?: number }>
  }) => {
    return apiClient.post<ContestRubricDTO>(`${base}/${id}/rubric`, data)
  },

  submit: (id: number, data: {
    title?: string
    description?: string
    fileIds: number[]
  }) => {
    return apiClient.post<ContestSubmissionDTO>(`${base}/${id}/submit`, data)
  },

  getSubmissions: (id: number) => {
    return apiClient.get<ContestSubmissionDTO[]>(`${base}/${id}/submissions`)
  },

  getSubmission: (id: number, sid: number) => {
    return apiClient.get<ContestSubmissionDTO>(`${base}/${id}/submissions/${sid}`)
  },

  score: (id: number, sid: number, data: {
    rubric_json: Record<string, number>
    comment?: string
  }) => {
    return apiClient.post<ContestScoreDTO>(`${base}/${id}/submissions/${sid}/score`, data)
  },

  getScores: (id: number, sid: number) => {
    return apiClient.get<ContestScoreDTO[]>(`${base}/${id}/submissions/${sid}/scores`)
  },

  publishResults: (id: number) => {
    return apiClient.post(`${base}/${id}/publish-results`)
  },

  getResults: (id: number) => {
    return apiClient.get<ContestResultDTO[]>(`${base}/${id}/results`)
  },

  generateCertificates: (id: number) => {
    return apiClient.post<Array<{ userId: number; documentId: number }>>(`${base}/${id}/generate-certificates`)
  },
}
