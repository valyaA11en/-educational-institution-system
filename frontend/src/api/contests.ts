import api from './index'

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

export const contestsApi = {
  list: (params?: { active?: boolean; visibility_scope?: string }) => {
    return api.get<{ data: ContestDTO[]; total: number }>('/contests', { params })
  },

  get: (id: number) => {
    return api.get<ContestDTO>(`/contests/${id}`)
  },

  create: (data: {
    title: string
    description?: string
    start_at: string
    end_at: string
    visibility_scope: 'all' | 'group' | 'invite'
    targets?: Array<{ group_id?: number; user_id?: number }>
  }) => {
    return api.post<ContestDTO>('/contests', data)
  },

  update: (id: number, data: {
    title?: string
    description?: string
    start_at?: string
    end_at?: string
    visibility_scope?: 'all' | 'group' | 'invite'
  }) => {
    return api.patch<ContestDTO>(`/contests/${id}`, data)
  },

  delete: (id: number) => {
    return api.delete(`/contests/${id}`)
  },

  setTargets: (id: number, targets: Array<{ group_id?: number; user_id?: number }>) => {
    return api.post(`/contests/${id}/targets`, { targets })
  },

  addJury: (id: number, data: { user_id: number; role: 'chair' | 'member' }) => {
    return api.post<ContestJuryDTO>(`/contests/${id}/jury`, data)
  },

  addRubric: (id: number, data: {
    title: string
    criteria_json: Array<{ key: string; title: string; maxScore: number; weight?: number }>
  }) => {
    return api.post<ContestRubricDTO>(`/contests/${id}/rubric`, data)
  },

  submit: (id: number, data: {
    title?: string
    description?: string
    fileIds: number[]
  }) => {
    return api.post<ContestSubmissionDTO>(`/contests/${id}/submit`, data)
  },

  getSubmissions: (id: number) => {
    return api.get<ContestSubmissionDTO[]>(`/contests/${id}/submissions`)
  },

  getSubmission: (id: number, sid: number) => {
    return api.get<ContestSubmissionDTO>(`/contests/${id}/submissions/${sid}`)
  },

  score: (id: number, sid: number, data: {
    rubric_json: Record<string, number>
    comment?: string
  }) => {
    return api.post<ContestScoreDTO>(`/contests/${id}/submissions/${sid}/score`, data)
  },

  getScores: (id: number, sid: number) => {
    return api.get<ContestScoreDTO[]>(`/contests/${id}/submissions/${sid}/scores`)
  },

  publishResults: (id: number) => {
    return api.post(`/contests/${id}/publish-results`)
  },

  getResults: (id: number) => {
    return api.get<ContestResultDTO[]>(`/contests/${id}/results`)
  },

  generateCertificates: (id: number) => {
    return api.post<Array<{ userId: number; documentId: number }>>(`/contests/${id}/generate-certificates`)
  },
}
