<template>
  <div v-if="plan">
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <div>
          <span class="text-h5">{{ plan.name || `КТП: ${plan.subject?.name} - ${plan.group?.name}` }}</span>
          <v-chip :color="getStatusColor(plan.status)" size="small" class="ml-2">
            {{ getStatusText(plan.status) }}
          </v-chip>
        </div>
        <v-btn
          color="primary"
          prepend-icon="mdi-content-save"
          @click="savePlan"
          :loading="saving"
        >
          Сохранить
        </v-btn>
      </v-card-title>
      <v-card-text>
        <!-- Progress and Alerts -->
        <v-row class="mb-4">
          <v-col cols="12" md="4">
            <v-card variant="outlined">
              <v-card-text>
                <div class="text-subtitle-2 text-grey mb-2">Прогресс</div>
                <div class="text-h4">{{ progress.percent }}%</div>
                <div class="text-caption text-grey">
                  {{ progress.linked_topics_count }} / {{ progress.total_topics }} тем связаны
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="8">
            <v-card variant="outlined">
              <v-card-text>
                <div class="text-subtitle-2 text-grey mb-2">Предупреждения</div>
                <v-alert
                  v-if="progress.alerts.length === 0"
                  type="success"
                  variant="tonal"
                  density="compact"
                >
                  Нет предупреждений
                </v-alert>
                <v-list v-else density="compact">
                  <v-list-item
                    v-for="alert in progress.alerts"
                    :key="alert.topic_id"
                    :title="alert.topic_title"
                    :subtitle="alert.message"
                  >
                    <template v-slot:prepend>
                      <v-icon color="warning">mdi-alert</v-icon>
                    </template>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Operations Toolbar -->
        <v-card variant="outlined" class="mb-4">
          <v-card-text>
            <div class="d-flex flex-wrap gap-2">
              <v-btn
                color="primary"
                prepend-icon="mdi-plus"
                @click="showBulkAddDialog = true"
              >
                Добавить темы (bulk)
              </v-btn>
              <v-btn
                color="secondary"
                prepend-icon="mdi-file-document-edit"
                @click="showTemplateDialog = true"
              >
                Применить шаблон
              </v-btn>
              <v-btn
                color="secondary"
                prepend-icon="mdi-content-copy"
                @click="showCopyDialog = true"
              >
                Копировать из прошлого
              </v-btn>
              <v-btn
                color="info"
                prepend-icon="mdi-link"
                @click="showLinksPanel = !showLinksPanel"
              >
                Связи
              </v-btn>
            </div>
          </v-card-text>
        </v-card>

        <!-- Links Panel -->
        <v-expand-transition>
          <v-card v-if="showLinksPanel" variant="outlined" class="mb-4">
            <v-card-title>Панель связей</v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="4">
                  <v-select
                    v-model="selectedTopicForLink"
                    :items="topics"
                    item-title="title"
                    item-value="id"
                    label="Выберите тему"
                    variant="outlined"
                    density="compact"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-select
                    v-model="linkType"
                    :items="linkTypeOptions"
                    label="Тип связи"
                    variant="outlined"
                    density="compact"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-select
                    v-if="linkType === 'lesson'"
                    v-model="selectedLesson"
                    :items="lessons"
                    item-title="title"
                    item-value="id"
                    label="Урок"
                    variant="outlined"
                    density="compact"
                    :loading="loadingLessons"
                  />
                  <v-select
                    v-else-if="linkType === 'assignment'"
                    v-model="selectedAssignment"
                    :items="assignments"
                    item-title="title"
                    item-value="id"
                    label="Задание"
                    variant="outlined"
                    density="compact"
                    :loading="loadingAssignments"
                  />
                  <v-select
                    v-else-if="linkType === 'material'"
                    v-model="selectedMaterial"
                    :items="materials"
                    item-title="title"
                    item-value="id"
                    label="Материал"
                    variant="outlined"
                    density="compact"
                    :loading="loadingMaterials"
                  />
                </v-col>
                <v-col cols="12">
                  <v-btn
                    color="primary"
                    @click="createLink"
                    :disabled="!selectedTopicForLink || !linkType"
                    :loading="linking"
                  >
                    Создать связь
                  </v-btn>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-expand-transition>

        <!-- Topics Table -->
        <v-card variant="outlined">
          <v-card-title>Темы</v-card-title>
          <v-card-text>
            <v-data-table
              :headers="topicHeaders"
              :items="topics"
              :loading="loading"
              item-value="id"
            >
              <template v-slot:item.order_no="{ item, index }">
                <div class="d-flex align-center gap-1">
                  <v-btn
                    icon="mdi-chevron-up"
                    size="x-small"
                    variant="text"
                    :disabled="index === 0"
                    @click="moveTopicUp(item.id)"
                  />
                  <span>{{ item.order_no }}</span>
                  <v-btn
                    icon="mdi-chevron-down"
                    size="x-small"
                    variant="text"
                    :disabled="index === topics.length - 1"
                    @click="moveTopicDown(item.id)"
                  />
                </div>
              </template>
              <template v-slot:item.title="{ item }">
                <v-text-field
                  v-model="item.title"
                  variant="outlined"
                  density="compact"
                  hide-details
                  @blur="updateTopic(item)"
                />
              </template>
              <template v-slot:item.hours="{ item }">
                <v-text-field
                  v-model.number="item.hours"
                  type="number"
                  variant="outlined"
                  density="compact"
                  hide-details
                  @blur="updateTopic(item)"
                />
              </template>
              <template v-slot:item.control_type="{ item }">
                <v-select
                  v-model="item.control_type"
                  :items="controlTypeOptions"
                  variant="outlined"
                  density="compact"
                  hide-details
                  @update:model-value="updateTopic(item)"
                />
              </template>
              <template v-slot:item.planned_date_from="{ item }">
                <v-text-field
                  v-model="item.planned_date_from"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                  @blur="updateTopic(item)"
                />
              </template>
              <template v-slot:item.planned_date_to="{ item }">
                <v-text-field
                  v-model="item.planned_date_to"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                  @blur="updateTopic(item)"
                />
              </template>
              <template v-slot:item.links="{ item }">
                <div class="d-flex flex-column gap-1">
                  <v-chip
                    v-for="link in item.links"
                    :key="link.id"
                    size="x-small"
                    :color="getLinkColor(link)"
                  >
                    {{ getLinkText(link) }}
                  </v-chip>
                </div>
              </template>
              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  @click="deleteTopic(item.id)"
                />
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-card-text>
    </v-card>

    <!-- Bulk Add Dialog -->
    <v-dialog v-model="showBulkAddDialog" max-width="800">
      <v-card>
        <v-card-title>Добавить темы (bulk)</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="bulkTopicsText"
            label="Введите темы (по одной на строку)"
            variant="outlined"
            rows="10"
            hint="Формат: Название темы | Часы | Тип контроля | Дата начала | Дата окончания"
            persistent-hint
          />
          <v-alert type="info" variant="tonal" class="mt-2">
            Пример:<br>
            Тема 1 | 2 | control | 2024-01-15 | 2024-01-20<br>
            Тема 2 | 4 | test | 2024-01-21 | 2024-01-25
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showBulkAddDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="addBulkTopics" :loading="processing">Добавить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Template Dialog -->
    <v-dialog v-model="showTemplateDialog" max-width="600">
      <v-card>
        <v-card-title>Применить шаблон</v-card-title>
        <v-card-text>
          <v-select
            v-model="selectedTemplate"
            :items="templates"
            item-title="name"
            item-value="id"
            label="Выберите шаблон"
            variant="outlined"
            :loading="loadingTemplates"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showTemplateDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="applyTemplate" :loading="processing" :disabled="!selectedTemplate">
            Применить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Copy Dialog -->
    <v-dialog v-model="showCopyDialog" max-width="600">
      <v-card>
        <v-card-title>Копировать из прошлого плана</v-card-title>
        <v-card-text>
          <v-select
            v-model="selectedSourcePlan"
            :items="sourcePlans"
            item-title="name"
            item-value="id"
            label="Выберите план"
            variant="outlined"
            :loading="loadingSourcePlans"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCopyDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="copyFromPlan" :loading="processing" :disabled="!selectedSourcePlan">
            Копировать
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import ktpApi, { type KtpPlanDTO, type KtpTopicDTO, type KtpTopicLinkDTO, type KtpProgressDTO, type KtpTemplateDTO } from '../api/ktp'
import { useToast } from '../composables/useToast'
import apiClient from '../api/client'

const route = useRoute()
const { showToast } = useToast()

const loading = ref(false)
const saving = ref(false)
const processing = ref(false)
const linking = ref(false)
const plan = ref<KtpPlanDTO | null>(null)
const topics = ref<KtpTopicDTO[]>([])
const progress = ref<KtpProgressDTO>({
  total_topics: 0,
  linked_topics_count: 0,
  percent: 0,
  alerts: [],
})

const showBulkAddDialog = ref(false)
const showTemplateDialog = ref(false)
const showCopyDialog = ref(false)
const showLinksPanel = ref(false)

const bulkTopicsText = ref('')
const selectedTemplate = ref<number | null>(null)
const templates = ref<KtpTemplateDTO[]>([])
const loadingTemplates = ref(false)

const selectedSourcePlan = ref<number | null>(null)
const sourcePlans = ref<KtpPlanDTO[]>([])
const loadingSourcePlans = ref(false)

const selectedTopicForLink = ref<number | null>(null)
const linkType = ref<string | null>(null)
const selectedLesson = ref<number | null>(null)
const selectedAssignment = ref<number | null>(null)
const selectedMaterial = ref<number | null>(null)

const lessons = ref<any[]>([])
const assignments = ref<any[]>([])
const materials = ref<any[]>([])
const loadingLessons = ref(false)
const loadingAssignments = ref(false)
const loadingMaterials = ref(false)

const linkTypeOptions = [
  { title: 'Урок', value: 'lesson' },
  { title: 'Задание', value: 'assignment' },
  { title: 'Материал', value: 'material' },
]

const controlTypeOptions = [
  { title: 'Контроль', value: 'control' },
  { title: 'Тест', value: 'test' },
  { title: 'Зачет', value: 'credit' },
  { title: 'Экзамен', value: 'exam' },
  { title: 'Нет', value: null },
]

const topicHeaders = [
  { title: '№', key: 'order_no', width: '80px' },
  { title: 'Название', key: 'title' },
  { title: 'Часы', key: 'hours', width: '100px' },
  { title: 'Тип контроля', key: 'control_type', width: '150px' },
  { title: 'Дата начала', key: 'planned_date_from', width: '150px' },
  { title: 'Дата окончания', key: 'planned_date_to', width: '150px' },
  { title: 'Связи', key: 'links', width: '200px' },
  { title: 'Действия', key: 'actions', width: '100px', sortable: false },
]

const loadPlan = async () => {
  loading.value = true
  try {
    const planId = parseInt(route.params.id as string)
    plan.value = await ktpApi.getPlan(planId)
    topics.value = (plan.value.topics || []).map(topic => ({
      ...topic,
      links: topic.links || [],
    }))
    await loadProgress()
  } catch (error) {
    console.error('Failed to load plan:', error)
    showToast('Ошибка загрузки плана', 'error')
  } finally {
    loading.value = false
  }
}

const loadProgress = async () => {
  try {
    const planId = parseInt(route.params.id as string)
    progress.value = await ktpApi.getProgress(planId)
  } catch (error) {
    console.error('Failed to load progress:', error)
  }
}

const loadTemplates = async () => {
  loadingTemplates.value = true
  try {
    const response = await ktpApi.getTemplates()
    templates.value = response.data || []
  } catch (error) {
    console.error('Failed to load templates:', error)
  } finally {
    loadingTemplates.value = false
  }
}

const loadSourcePlans = async () => {
  loadingSourcePlans.value = true
  try {
    const response = await ktpApi.getPlans({ per_page: 100 })
    sourcePlans.value = response.data.filter(p => p.id !== plan.value?.id)
  } catch (error) {
    console.error('Failed to load source plans:', error)
  } finally {
    loadingSourcePlans.value = false
  }
}

const loadLessons = async () => {
  if (lessons.value.length > 0) return
  loadingLessons.value = true
  try {
    const response = await apiClient.get('/v1/journal/lessons', { params: { per_page: 100 } })
    lessons.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Failed to load lessons:', error)
  } finally {
    loadingLessons.value = false
  }
}

const loadAssignments = async () => {
  if (assignments.value.length > 0) return
  loadingAssignments.value = true
  try {
    const response = await apiClient.get('/v1/assignments', { params: { per_page: 100 } })
    assignments.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Failed to load assignments:', error)
  } finally {
    loadingAssignments.value = false
  }
}

const loadMaterials = async () => {
  if (materials.value.length > 0) return
  loadingMaterials.value = true
  try {
    const response = await apiClient.get('/v1/materials', { params: { per_page: 100 } })
    materials.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Failed to load materials:', error)
  } finally {
    loadingMaterials.value = false
  }
}

const updateTopic = async (topic: KtpTopicDTO) => {
  try {
    await ktpApi.updateTopic(topic.id, {
      order_no: topic.order_no,
      title: topic.title,
      hours: topic.hours || undefined,
      control_type: topic.control_type || undefined,
      planned_date_from: topic.planned_date_from || undefined,
      planned_date_to: topic.planned_date_to || undefined,
    })
    await loadProgress()
  } catch (error) {
    console.error('Failed to update topic:', error)
    showToast('Ошибка обновления темы', 'error')
  }
}

const deleteTopic = async (topicId: number) => {
  if (!confirm('Удалить тему?')) return
  try {
    await ktpApi.deleteTopic(topicId)
    showToast('Тема удалена', 'success')
    await loadPlan()
  } catch (error) {
    console.error('Failed to delete topic:', error)
    showToast('Ошибка удаления темы', 'error')
  }
}

const moveTopicUp = async (topicId: number) => {
  const index = topics.value.findIndex(t => t.id === topicId)
  if (index <= 0) return
  
  const topic = topics.value[index]
  const prevTopic = topics.value[index - 1]
  
  const tempOrder = topic.order_no
  topic.order_no = prevTopic.order_no
  prevTopic.order_no = tempOrder
  
  topics.value.sort((a, b) => a.order_no - b.order_no)
  
  await Promise.all([
    updateTopic(topic),
    updateTopic(prevTopic),
  ])
}

const moveTopicDown = async (topicId: number) => {
  const index = topics.value.findIndex(t => t.id === topicId)
  if (index >= topics.value.length - 1) return
  
  const topic = topics.value[index]
  const nextTopic = topics.value[index + 1]
  
  const tempOrder = topic.order_no
  topic.order_no = nextTopic.order_no
  nextTopic.order_no = tempOrder
  
  topics.value.sort((a, b) => a.order_no - b.order_no)
  
  await Promise.all([
    updateTopic(topic),
    updateTopic(nextTopic),
  ])
}

const addBulkTopics = async () => {
  if (!bulkTopicsText.value.trim()) return
  
  processing.value = true
  try {
    const lines = bulkTopicsText.value.split('\n').filter(l => l.trim())
    const maxOrder = topics.value.length > 0 ? Math.max(...topics.value.map(t => t.order_no)) : 0
    
    const newTopics = lines.map((line, index) => {
      const parts = line.split('|').map(p => p.trim())
      return {
        order_no: maxOrder + index + 1,
        title: parts[0] || `Тема ${index + 1}`,
        hours: parts[1] ? parseInt(parts[1]) : undefined,
        control_type: parts[2] || undefined,
        planned_date_from: parts[3] || undefined,
        planned_date_to: parts[4] || undefined,
      }
    })
    
    await ktpApi.addTopics(plan.value!.id, newTopics)
    showToast('Темы добавлены', 'success')
    showBulkAddDialog.value = false
    bulkTopicsText.value = ''
    await loadPlan()
  } catch (error) {
    console.error('Failed to add bulk topics:', error)
    showToast('Ошибка добавления тем', 'error')
  } finally {
    processing.value = false
  }
}

const applyTemplate = async () => {
  if (!selectedTemplate.value) return
  
  processing.value = true
  try {
    await ktpApi.applyTemplate(plan.value!.id, selectedTemplate.value)
    showToast('Шаблон применен', 'success')
    showTemplateDialog.value = false
    selectedTemplate.value = null
    await loadPlan()
  } catch (error) {
    console.error('Failed to apply template:', error)
    showToast('Ошибка применения шаблона', 'error')
  } finally {
    processing.value = false
  }
}

const copyFromPlan = async () => {
  if (!selectedSourcePlan.value) return
  
  processing.value = true
  try {
    await ktpApi.copyFrom(plan.value!.id, selectedSourcePlan.value)
    showToast('Темы скопированы', 'success')
    showCopyDialog.value = false
    selectedSourcePlan.value = null
    await loadPlan()
  } catch (error) {
    console.error('Failed to copy from plan:', error)
    showToast('Ошибка копирования тем', 'error')
  } finally {
    processing.value = false
  }
}

const createLink = async () => {
  if (!selectedTopicForLink.value || !linkType.value) return
  
  linking.value = true
  try {
    const data: any = {}
    if (linkType.value === 'lesson' && selectedLesson.value) {
      data.lessonId = selectedLesson.value
    } else if (linkType.value === 'assignment' && selectedAssignment.value) {
      data.assignmentId = selectedAssignment.value
    } else if (linkType.value === 'material' && selectedMaterial.value) {
      data.materialId = selectedMaterial.value
    } else {
      showToast('Выберите элемент для связи', 'warning')
      return
    }
    
    await ktpApi.linkTopic(selectedTopicForLink.value, data)
    showToast('Связь создана', 'success')
    selectedTopicForLink.value = null
    linkType.value = null
    selectedLesson.value = null
    selectedAssignment.value = null
    selectedMaterial.value = null
    await loadPlan()
  } catch (error) {
    console.error('Failed to create link:', error)
    showToast('Ошибка создания связи', 'error')
  } finally {
    linking.value = false
  }
}

const savePlan = async () => {
  saving.value = true
  try {
    await ktpApi.updatePlan(plan.value!.id, {
      name: plan.value.name || undefined,
      description: plan.value.description || undefined,
      status: plan.value.status,
    })
    showToast('План сохранен', 'success')
  } catch (error) {
    console.error('Failed to save plan:', error)
    showToast('Ошибка сохранения плана', 'error')
  } finally {
    saving.value = false
  }
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    active: 'green',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    active: 'Активный',
    archived: 'Архив',
  }
  return texts[status] || status
}

const getLinkColor = (link: KtpTopicLinkDTO) => {
  if (link.lesson_id) return 'primary'
  if (link.assignment_id) return 'success'
  if (link.material_id) return 'info'
  return 'default'
}

const getLinkText = (link: KtpTopicLinkDTO) => {
  if (link.lesson_id) return `Урок: ${link.lesson?.title || link.lesson_id}`
  if (link.assignment_id) return `Задание: ${link.assignment?.title || link.assignment_id}`
  if (link.material_id) return `Материал: ${link.material?.title || link.material_id}`
  return 'Связь'
}

// Watch for link type changes to load appropriate data
import { watch } from 'vue'
watch(linkType, (newType) => {
  if (newType === 'lesson') {
    loadLessons()
  } else if (newType === 'assignment') {
    loadAssignments()
  } else if (newType === 'material') {
    loadMaterials()
  }
})

watch(() => showTemplateDialog, (show) => {
  if (show) {
    loadTemplates()
  }
})

watch(() => showCopyDialog, (show) => {
  if (show) {
    loadSourcePlans()
  }
})

onMounted(() => {
  loadPlan()
})
</script>

