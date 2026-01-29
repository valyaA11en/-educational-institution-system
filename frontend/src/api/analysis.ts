import api from './client'

export interface FailedTopic {
  id: number
  subject_id: number
  subject_name: string | null
  topic_name: string
  failed_attempts: number
  average_grade: number
  last_attempt_date: string | null
  details: {
    grades: Array<{
      value: number
      date: string
      type: string
    }>
  } | null
}

export interface ComplexTopic {
  topic_name: string
  ktp_topic_id: number | null
  total_failures: number
  average_grade: number
  affected_students: number
}

export const analysisApi = {
  getFailedTopics(studentId: number, subjectId?: number) {
    return api.get<{ data: FailedTopic[] }>(`/v1/analysis/failed-topics/${studentId}`, {
      params: subjectId ? { subject_id: subjectId } : undefined,
    })
  },
  getComplexTopics(subjectId: number, limit = 10) {
    return api.get<{ data: ComplexTopic[] }>('/v1/analysis/complex-topics', {
      params: { subject_id: subjectId, limit },
    })
  },
}


