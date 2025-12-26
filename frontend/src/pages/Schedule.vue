<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h4">Расписание</span>
        <div class="d-flex align-center gap-2">
          <!-- Version Selector -->
          <v-select
            v-model="selectedVersionId"
            :items="versionOptions"
            label="Версия"
            variant="outlined"
            density="compact"
            style="min-width: 250px"
            @update:model-value="onVersionChange"
          >
            <template v-slot:item="{ item, props }">
              <v-list-item v-bind="props">
                <template v-slot:prepend>
                  <v-chip :color="getStatusColor(item.raw.status)" size="small" class="mr-2">
                    {{ getStatusText(item.raw.status) }}
                  </v-chip>
                </template>
                <v-list-item-title>
                  Версия #{{ item.raw.id }}
                  <span v-if="item.raw.status === 'published'" class="text-caption text-grey">
                    (Опубликовано)
                  </span>
                </v-list-item-title>
              </v-list-item>
            </template>
          </v-select>

          <!-- Action Buttons -->
          <v-btn
            v-if="auth.hasPermission('schedule.create')"
            color="primary"
            prepend-icon="mdi-plus"
            @click="showCreateDialog = true"
            :loading="processing"
          >
            Создать черновик
          </v-btn>
          <v-btn
            v-if="selectedVersion?.status === 'draft' && auth.hasPermission('schedule.create')"
            color="success"
            prepend-icon="mdi-publish"
            @click="publishVersion"
            :loading="processing"
          >
            Опубликовать
          </v-btn>
          <v-btn
            v-if="selectedVersion?.status === 'published' && auth.hasPermission('schedule.create')"
            color="warning"
            prepend-icon="mdi-archive"
            @click="archiveVersion"
            :loading="processing"
          >
            Архивировать
          </v-btn>
        </div>
      </v-card-title>

      <v-card-text>
        <v-tabs v-model="activeTab" class="mb-4">
          <v-tab value="schedule">Расписание</v-tab>
          <v-tab value="changelog">История изменений</v-tab>
          <v-tab value="compare">Сравнение версий</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
          <v-window-item value="schedule">
            <ScheduleGrid :version-id="selectedVersionId" />
          </v-window-item>

          <v-window-item value="changelog">
            <ScheduleChangelog :version-id="selectedVersionId" />
          </v-window-item>

          <v-window-item value="compare">
            <ScheduleCompare />
          </v-window-item>
        </v-window>
      </v-card-text>
    </v-card>

    <!-- Create Version Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="500">
      <v-card>
        <v-card-title>Создать черновик версии</v-card-title>
        <v-card-text>
          <v-select
            v-model="newVersion.term_id"
            :items="termOptions"
            item-title="name"
            item-value="id"
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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { scheduleApi, type ScheduleVersionDTO } from '../api/schedule'
import { referencesApi } from '../api/references'
import { useAuthStore } from '../stores/auth'
import { useToast } from '../composables/useToast'
import ScheduleGrid from '../components/ScheduleGrid.vue'
import ScheduleChangelog from '../components/ScheduleChangelog.vue'
import ScheduleCompare from '../components/ScheduleCompare.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { showToast } = useToast()

const activeTab = ref('schedule')
const loading = ref(false)
const processing = ref(false)
const versions = ref<ScheduleVersionDTO[]>([])
const selectedVersionId = ref<number | null>(null)
const showCreateDialog = ref(false)
const newVersion = ref({ term_id: null as number | null })
const termOptions = ref<Array<{ id: number; name: string }>>([])

const selectedVersion = computed(() => {
  return versions.value.find(v => v.id === selectedVersionId.value) || null
})

const versionOptions = computed(() => {
  return versions.value.map(v => ({
    title: `Версия #${v.id} (${getStatusText(v.status)})`,
    value: v.id,
    raw: v,
  }))
})

const loadVersions = async () => {
  loading.value = true
  try {
    const response = await scheduleApi.getVersions({ per_page: 100 })
    versions.value = response.data

    // Set default version from query or published version
    if (!selectedVersionId.value) {
      const versionIdFromQuery = route.query.version_id ? Number(route.query.version_id) : null
      if (versionIdFromQuery) {
        selectedVersionId.value = versionIdFromQuery
      } else {
        // Find published version or latest draft
        const published = versions.value.find(v => v.status === 'published')
        const latestDraft = versions.value.find(v => v.status === 'draft')
        selectedVersionId.value = published?.id || latestDraft?.id || versions.value[0]?.id || null
      }
    }
  } catch (error) {
    console.error('Failed to load versions:', error)
    showToast('Ошибка при загрузке версий', 'error')
  } finally {
    loading.value = false
  }
}

const loadTerms = async () => {
  try {
    const terms = await referencesApi.listTerms()
    termOptions.value = terms.map(t => ({ id: t.id, name: t.name }))
  } catch (error) {
    console.error('Failed to load terms:', error)
  }
}

const createVersion = async () => {
  if (!newVersion.value.term_id) {
    showToast('Выберите семестр', 'warning')
    return
  }

  processing.value = true
  try {
    const version = await scheduleApi.createVersion({ term_id: newVersion.value.term_id })
    showToast('Черновик версии создан', 'success')
    showCreateDialog.value = false
    newVersion.value.term_id = null
    await loadVersions()
    selectedVersionId.value = version.id
    router.replace({ query: { version_id: version.id } })
  } catch (error: any) {
    console.error('Failed to create version:', error)
    showToast(error.response?.data?.message || 'Ошибка при создании версии', 'error')
  } finally {
    processing.value = false
  }
}

const publishVersion = async () => {
  if (!selectedVersionId.value) return

  processing.value = true
  try {
    const version = await scheduleApi.publishVersion(selectedVersionId.value)
    showToast('Версия опубликована', 'success')
    await loadVersions()
    // Switch to published version
    selectedVersionId.value = version.id
    router.replace({ query: { version_id: version.id } })
  } catch (error: any) {
    console.error('Failed to publish version:', error)
    showToast(error.response?.data?.message || 'Ошибка при публикации', 'error')
  } finally {
    processing.value = false
  }
}

const archiveVersion = async () => {
  if (!selectedVersionId.value) return

  if (!confirm('Вы уверены, что хотите архивировать эту версию?')) {
    return
  }

  processing.value = true
  try {
    await scheduleApi.archiveVersion(selectedVersionId.value)
    showToast('Версия архивирована', 'info')
    await loadVersions()
    // Switch to published version or latest draft
    const published = versions.value.find(v => v.status === 'published')
    const latestDraft = versions.value.find(v => v.status === 'draft')
    selectedVersionId.value = published?.id || latestDraft?.id || versions.value[0]?.id || null
    if (selectedVersionId.value) {
      router.replace({ query: { version_id: selectedVersionId.value } })
    }
  } catch (error: any) {
    console.error('Failed to archive version:', error)
    showToast(error.response?.data?.message || 'Ошибка при архивировании', 'error')
  } finally {
    processing.value = false
  }
}

const onVersionChange = () => {
  if (selectedVersionId.value) {
    router.replace({ query: { version_id: selectedVersionId.value } })
  }
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

// Watch route query for version_id changes
watch(() => route.query.version_id, (newVersionId) => {
  if (newVersionId) {
    const versionId = Number(newVersionId)
    if (versionId && versionId !== selectedVersionId.value) {
      selectedVersionId.value = versionId
    }
  }
}, { immediate: true })

onMounted(async () => {
  await loadTerms()
  await loadVersions()
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
