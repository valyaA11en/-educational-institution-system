import { ref, type Ref } from 'vue'
import { apiClient } from '../api/client'

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface CrudTableOptions<T> {
  resourceUrl: string
  transformItem?: (item: any) => T
  transformPayload?: (payload: any) => any
}

export function useCrudTable<T extends { id: number }>(
  options: CrudTableOptions<T>
) {
  const { resourceUrl, transformItem, transformPayload } = options

  const items = ref<T[]>([]) as Ref<T[]>
  const loading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const search = ref('')
  const pagination = ref({
    current_page: 1,
    per_page: 50,
    total: 0,
  })

  const load = async (params?: Record<string, any>) => {
    loading.value = true
    try {
      const queryParams = {
        q: search.value || undefined,
        per_page: pagination.value.per_page,
        page: pagination.value.current_page,
        ...params,
      }
      const { data } = await apiClient.get<PaginatedResponse<T>>(resourceUrl, {
        params: queryParams,
      })
      items.value = transformItem
        ? data.data.map(transformItem)
        : (data.data as T[])
      pagination.value = {
        current_page: data.current_page,
        per_page: data.per_page,
        total: data.total,
      }
    } catch (error) {
      console.error(`Failed to load ${resourceUrl}:`, error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const create = async (payload: Partial<T>) => {
    saving.value = true
    try {
      const transformedPayload = transformPayload
        ? transformPayload(payload)
        : payload
      const { data } = await apiClient.post<T>(resourceUrl, transformedPayload)
      await load()
      return transformItem ? transformItem(data) : (data as T)
    } catch (error) {
      console.error(`Failed to create ${resourceUrl}:`, error)
      throw error
    } finally {
      saving.value = false
    }
  }

  const update = async (id: number, payload: Partial<T>) => {
    saving.value = true
    try {
      const transformedPayload = transformPayload
        ? transformPayload(payload)
        : payload
      const { data } = await apiClient.patch<T>(
        `${resourceUrl}/${id}`,
        transformedPayload
      )
      await load()
      return transformItem ? transformItem(data) : (data as T)
    } catch (error) {
      console.error(`Failed to update ${resourceUrl}:`, error)
      throw error
    } finally {
      saving.value = false
    }
  }

  const remove = async (id: number) => {
    deleting.value = true
    try {
      await apiClient.delete(`${resourceUrl}/${id}`)
      await load()
    } catch (error) {
      console.error(`Failed to delete ${resourceUrl}:`, error)
      throw error
    } finally {
      deleting.value = false
    }
  }

  const onSearch = (value: string) => {
    search.value = value
    pagination.value.current_page = 1
    load()
  }

  const onPageChange = (page: number) => {
    pagination.value.current_page = page
    load()
  }

  return {
    items,
    loading,
    saving,
    deleting,
    search,
    pagination,
    load,
    create,
    update,
    remove,
    onSearch,
    onPageChange,
  }
}

