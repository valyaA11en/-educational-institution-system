<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h5">Версии расписания</span>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="showCreateDialog = true"
        >
          Создать версию
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Статус"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadVersions"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="versions"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
        >
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ getStatusText(item.status) }}
            </v-chip>
          </template>
          <template v-slot:item.published_at="{ item }">
            {{ formatDateTime(item.published_at) }}
          </template>
          <template v-slot:item.created_at="{ item }">
            {{ formatDateTime(item.created_at) }}
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              @click="viewVersion(item.id)"
            />
            <v-btn
              v-if="item.status === 'draft'"
              icon="mdi-publish"
              size="small"
              variant="text"
              color="success"
              @click="publishVersion(item.id)"
              :loading="processing === item.id"
            />
            <v-btn
              v-if="item.status === 'published'"
              icon="mdi-archive"
              size="small"
              variant="text"
              color="warning"
              @click="archiveVersion(item.id)"
              :loading="processing === item.id"
            />
            <v-btn
              icon="mdi-history"
              size="small"
              variant="text"
              @click="viewChangelog(item.id)"
            />
            <v-btn
              icon="mdi-compare"
              size="small"
              variant="text"
              @click="openCompareDialog(item.id)"
            />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Create Version Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="500">
      <v-card>
        <v-card-title>Создать версию</v-card-title>
        <v-card-text>
          <v-select
            v-model="newVersion.term_id"
            :items="termOptions"
            label="Семестр"
            variant="outlined"
            required
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCreateDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="createVersion" :loading="processing">Создать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Changelog Dialog -->
    <v-dialog v-model="showChangelogDialog" max-width="800">
      <v-card>
        <v-card-title>Журнал изменений</v-card-title>
        <v-card-text>
          <v-timeline v-if="changelog.length > 0">
            <v-timeline-item
              v-for="entry in changelog"
              :key="entry.id"
              :dot-color="getActionColor(entry.action)"
            >
              <v-card>
                <v-card-text>
                  <div class="font-weight-bold">{{ getActionText(entry.action) }}</div>
                  <div class="text-caption text-grey">
                    {{ entry.changer?.fio || `ID: ${entry.changed_by}` }} - {{ formatDateTime(entry.created_at) }}
                  </div>
                  <div v-if="entry.schedule_item" class="text-caption mt-1">
                    Элемент: {{ entry.schedule_item.date }} ({{ entry.schedule_item.group?.name }})
                  </div>
                </v-card-text>
              </v-card>
            </v-timeline-item>
          </v-timeline>
          <v-alert v-else type="info">Нет изменений</v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showChangelogDialog = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Compare Dialog -->
    <v-dialog v-model="showCompareDialog" max-width="900">
      <v-card>
        <v-card-title>Сравнение версий</v-card-title>
        <v-card-text>
          <v-row class="mb-3">
            <v-col cols="6">
              <v-select
                v-model="compareVersion1"
                :items="versionOptions"
                label="Версия 1"
                variant="outlined"
              />
            </v-col>
            <v-col cols="6">
              <v-select
                v-model="compareVersion2"
                :items="versionOptions"
                label="Версия 2"
                variant="outlined"
              />
            </v-col>
          </v-row>
          <v-btn color="primary" @click="compareVersions" :loading="comparing" :disabled="!compareVersion1 || !compareVersion2">
            Сравнить
          </v-btn>

          <v-card v-if="comparison" variant="outlined" class="mt-4">
            <v-card-title class="text-subtitle-1">Добавлено: {{ comparison.added.length }}</v-card-title>
            <v-card-text v-if="comparison.added.length > 0">
              <v-list>
                <v-list-item
                  v-for="item in comparison.added"
                  :key="item.id"
                >
                  {{ formatItem(item) }}
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>

          <v-card v-if="comparison" variant="outlined" class="mt-4">
            <v-card-title class="text-subtitle-1">Удалено: {{ comparison.removed.length }}</v-card-title>
            <v-card-text v-if="comparison.removed.length > 0">
              <v-list>
                <v-list-item
                  v-for="item in comparison.removed"
                  :key="item.id"
                >
                  {{ formatItem(item) }}
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>

          <v-card v-if="comparison" variant="outlined" class="mt-4">
            <v-card-title class="text-subtitle-1">Изменено: {{ comparison.modified.length }}</v-card-title>
            <v-card-text v-if="comparison.modified.length > 0">
              <v-list>
                <v-list-item
                  v-for="(mod, index) in comparison.modified"
                  :key="index"
                >
                  <v-expansion-panels>
                    <v-expansion-panel>
                      <v-expansion-panel-title>{{ formatItem(mod.item) }}</v-expansion-panel-title>
                      <v-expansion-panel-text>
                        <div class="text-caption">Было:</div>
                        <pre class="text-caption">{{ JSON.stringify(mod.before, null, 2) }}</pre>
                        <div class="text-caption mt-2">Стало:</div>
                        <pre class="text-caption">{{ JSON.stringify(mod.after, null, 2) }}</pre>
                      </v-expansion-panel-text>
                    </v-expansion-panel>
                  </v-expansion-panels>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCompareDialog = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { scheduleApi } from '../api/schedule'

const router = useRouter()
const loading = ref(false)
const processing = ref<number | null>(null)
const versions = ref<any[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  status: null as string | null,
})

const statusOptions = [
  { title: 'Черновик', value: 'draft' },
  { title: 'Опубликовано', value: 'published' },
  { title: 'Архив', value: 'archived' },
]

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Статус', key: 'status' },
  { title: 'Опубликовано', key: 'published_at' },
  { title: 'Создано', key: 'created_at' },
  { title: 'Создатель', key: 'creator.fio' },
  { title: 'Действия', key: 'actions', sortable: false, width: '200px' },
]

const showCreateDialog = ref(false)
const newVersion = ref({ term_id: null as number | null })
const termOptions = ref<Array<{ title: string; value: number }>>([])

const showChangelogDialog = ref(false)
const changelog = ref<any[]>([])
const currentChangelogVersionId = ref<number | null>(null)

const showCompareDialog = ref(false)
const compareVersion1 = ref<number | null>(null)
const compareVersion2 = ref<number | null>(null)
const comparing = ref(false)
const comparison = ref<any>(null)
const versionOptions = computed(() => versions.value.map(v => ({ title: `Версия ${v.id} (${getStatusText(v.status)})`, value: v.id })))

const loadVersions = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    }
    if (filters.value.status) params.status = filters.value.status

    const response = await scheduleApi.getVersions(params)
    versions.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load versions:', error)
  } finally {
    loading.value = false
  }
}

const loadTerms = async () => {
  try {
    // TODO: load terms from API
    termOptions.value = []
  } catch (error) {
    console.error('Failed to load terms:', error)
  }
}

const createVersion = async () => {
  if (!newVersion.value.term_id) return

  processing.value = -1
  try {
    await scheduleApi.createVersion({ term_id: newVersion.value.term_id })
    showCreateDialog.value = false
    await loadVersions()
  } catch (error) {
    console.error('Failed to create version:', error)
    alert('Ошибка при создании версии')
  } finally {
    processing.value = null
  }
}

const publishVersion = async (id: number) => {
  processing.value = id
  try {
    await scheduleApi.publishVersion(id)
    await loadVersions()
  } catch (error) {
    console.error('Failed to publish version:', error)
    alert('Ошибка при публикации')
  } finally {
    processing.value = null
  }
}

const archiveVersion = async (id: number) => {
  processing.value = id
  try {
    await scheduleApi.archiveVersion(id)
    await loadVersions()
  } catch (error) {
    console.error('Failed to archive version:', error)
    alert('Ошибка при архивировании')
  } finally {
    processing.value = null
  }
}

const viewVersion = (id: number) => {
  router.push({ name: 'schedule', query: { version_id: id } })
}

const viewChangelog = async (id: number) => {
  currentChangelogVersionId.value = id
  showChangelogDialog.value = true
  try {
    const response = await scheduleApi.getChangelog(id)
    changelog.value = response.data
  } catch (error) {
    console.error('Failed to load changelog:', error)
  }
}

const openCompareDialog = (id: number) => {
  compareVersion1.value = id
  compareVersion2.value = null
  comparison.value = null
  showCompareDialog.value = true
}

const compareVersions = async () => {
  if (!compareVersion1.value || !compareVersion2.value) return

  comparing.value = true
  try {
    const response = await scheduleApi.compareVersions({
      version1_id: compareVersion1.value,
      version2_id: compareVersion2.value,
    })
    comparison.value = response
  } catch (error) {
    console.error('Failed to compare versions:', error)
    alert('Ошибка при сравнении')
  } finally {
    comparing.value = false
  }
}

const formatItem = (item: any) => {
  return `${item.date} - ${item.group?.name || ''} - ${item.subject?.name || ''}`
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    published: 'success',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    published: 'Опубликовано',
    archived: 'Архив',
  }
  return texts[status] || status
}

const getActionColor = (action: string) => {
  const colors: Record<string, string> = {
    created: 'success',
    updated: 'warning',
    deleted: 'error',
  }
  return colors[action] || 'default'
}

const getActionText = (action: string) => {
  const texts: Record<string, string> = {
    created: 'Создано',
    updated: 'Изменено',
    deleted: 'Удалено',
  }
  return texts[action] || action
}

const formatDateTime = (date?: string | null) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('ru-RU')
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadVersions()
}

onMounted(async () => {
  await loadVersions()
  await loadTerms()
})
</script>








