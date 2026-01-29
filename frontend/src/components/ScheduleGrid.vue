<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span>Расписание</span>
        <div class="d-flex gap-2">
          <v-select
            v-model="viewMode"
            :items="viewModes"
            label="Вид"
            variant="outlined"
            density="compact"
            style="min-width: 150px"
            @update:model-value="applyViewMode"
          />
          <v-select
            v-model="selectedGroup"
            :items="groupOptions"
            label="Группа"
            variant="outlined"
            density="compact"
            clearable
            style="min-width: 200px"
            @update:model-value="filterByGroup"
          />
          <v-menu>
            <template v-slot:activator="{ props }">
              <v-text-field
                v-bind="props"
                v-model="dateRangeText"
                label="Период"
                variant="outlined"
                density="compact"
                readonly
                prepend-inner-icon="mdi-calendar"
                style="min-width: 200px"
              />
            </template>
            <v-date-picker
              v-model="dateRange"
              range
              @update:model-value="filterByDateRange"
            />
          </v-menu>
          <v-btn
            v-if="auth.hasPermission('schedule.create')"
            color="primary"
            prepend-icon="mdi-plus"
            @click="showCreateDialog = true"
            :disabled="readOnly.isEnabled"
          >
            Добавить пару
          </v-btn>
        </div>
      </v-card-title>
      <v-card-text>
        <div v-if="loading" class="text-center py-8">
          <v-progress-circular indeterminate color="primary" />
        </div>
        <div v-else-if="filteredItems.length === 0" class="text-center py-8 text-grey">
          <p>Расписание пусто</p>
          <p v-if="versionId" class="text-caption">Выберите версию или создайте новую</p>
        </div>
        <div v-else>
          <!-- Grid View -->
          <div v-if="viewMode === 'grid'" class="schedule-grid">
            <v-table density="compact" class="schedule-table">
              <thead>
                <tr>
                  <th class="text-left schedule-time-col">Время</th>
                  <th
                    v-for="day in weekDays"
                    :key="day.date"
                    class="text-center schedule-day-col"
                    :class="{ 'bg-primary-lighten-5': isToday(day.date) }"
                  >
                    <div class="text-caption font-weight-bold">{{ formatDayName(day.date) }}</div>
                    <div class="text-caption text-grey">{{ formatDateShort(day.date) }}</div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="timeSlot in timeSlots" :key="timeSlot.id">
                  <td class="font-weight-bold schedule-time-cell">
                    {{ formatTime(timeSlot.start_time) }}<br>
                    <span class="text-caption text-grey">{{ formatTime(timeSlot.end_time) }}</span>
                  </td>
                  <td
                    v-for="day in weekDays"
                    :key="`${timeSlot.id}-${day.date}`"
                    class="schedule-cell"
                    :class="{ 'bg-primary-lighten-5': isToday(day.date) }"
                  >
                    <div
                      v-for="item in getCellItems(timeSlot.id, day.date)"
                      :key="item.id"
                      class="schedule-item"
                      :class="getItemClass(item)"
                      @click="editItem(item)"
                    >
                      <div class="schedule-item-subject font-weight-bold">{{ item.subject?.name || '-' }}</div>
                      <div class="schedule-item-group text-caption">{{ item.group?.name || '-' }}</div>
                      <div class="schedule-item-teacher text-caption">{{ item.teacher?.fio || '-' }}</div>
                      <div class="schedule-item-room text-caption">{{ item.room?.name || '-' }}</div>
                    </div>
                    <div v-if="getCellItems(timeSlot.id, day.date).length === 0" class="schedule-empty-cell">
                      <v-btn
                        v-if="auth.hasPermission('schedule.create')"
                        icon="mdi-plus"
                        size="x-small"
                        variant="text"
                        @click="createItemForCell(timeSlot.id, day.date)"
                        :disabled="readOnly.isEnabled"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </div>

          <!-- Table View -->
          <v-data-table
            v-else
            :headers="tableHeaders"
            :items="filteredItems"
            :loading="loading"
            :items-per-page="50"
          >
            <template v-slot:item.date="{ item }">
              {{ formatDate(item.date) }}
            </template>
            <template v-slot:item.time_slot="{ item }">
              {{ formatTimeSlot(item.time_slot) }}
            </template>
            <template v-slot:item.group="{ item }">
              {{ item.group?.name || '-' }}
            </template>
            <template v-slot:item.subject="{ item }">
              {{ item.subject?.name || '-' }}
            </template>
            <template v-slot:item.teacher="{ item }">
              {{ item.teacher?.fio || '-' }}
            </template>
            <template v-slot:item.room="{ item }">
              {{ item.room?.name || '-' }}
            </template>
            <template v-slot:item.actions="{ item }" v-if="auth.hasPermission('schedule.create')">
              <v-btn
                icon="mdi-pencil"
                size="small"
                variant="text"
                @click="editItem(item)"
                :disabled="readOnly.isEnabled"
              />
              <v-btn
                icon="mdi-delete"
                size="small"
                variant="text"
                color="error"
                @click="deleteItem(item)"
                :disabled="readOnly.isEnabled"
              />
            </template>
          </v-data-table>
        </div>
      </v-card-text>
    </v-card>

    <!-- Create/Edit Item Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="600">
      <v-card>
        <v-card-title>{{ editingItem ? 'Редактировать пару' : 'Добавить пару' }}</v-card-title>
        <v-card-text>
          <v-form ref="formRef" v-model="formValid">
            <v-select
              v-model="formData.date"
              :items="dateOptions"
              label="Дата"
              variant="outlined"
              required
            />
            <v-select
              v-model="formData.time_slot_id"
              :items="timeSlotOptions"
              item-title="label"
              item-value="id"
              label="Время"
              variant="outlined"
              required
            />
            <v-select
              v-model="formData.group_id"
              :items="groupOptions"
              item-title="name"
              item-value="id"
              label="Группа"
              variant="outlined"
              required
            />
            <v-select
              v-model="formData.subject_id"
              :items="subjectOptions"
              item-title="name"
              item-value="id"
              label="Предмет"
              variant="outlined"
              required
            />
            <v-select
              v-model="formData.teacher_user_id"
              :items="teacherOptions"
              item-title="fio"
              item-value="id"
              label="Преподаватель"
              variant="outlined"
              required
            />
            <v-select
              v-model="formData.room_id"
              :items="roomOptions"
              item-title="name"
              item-value="id"
              label="Кабинет"
              variant="outlined"
              required
            />
            <v-textarea
              v-model="formData.override_reason"
              label="Причина переопределения (если есть конфликт)"
              variant="outlined"
              rows="2"
            />
            <v-checkbox
              v-model="formData.force"
              label="Принудительно создать (игнорировать конфликты)"
              color="warning"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCreateDialog = false">Отмена</v-btn>
          <v-btn
            color="primary"
            @click="saveItem"
            :loading="saving"
            :disabled="!formValid || readOnly.isEnabled"
          >
            {{ editingItem ? 'Сохранить' : 'Создать' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { scheduleApi, type ScheduleItemDTO } from '../api/schedule'
import { useAuthStore } from '../stores/auth'
import { useReadOnlyStore } from '../stores/readOnly'
import { useToast } from '../composables/useToast'
import { referencesApi } from '../api/references'

interface Props {
  versionId?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  versionId: null,
})

const auth = useAuthStore()
const readOnly = useReadOnlyStore()
const { showToast } = useToast()

const loading = ref(false)
const saving = ref(false)
const items = ref<ScheduleItemDTO[]>([])
const timeSlots = ref<Array<{ id: number; start_time: string; end_time: string }>>([])
const viewMode = ref<'grid' | 'table'>('grid')
const selectedGroup = ref<number | null>(null)
const dateRange = ref<string[]>([])
const showCreateDialog = ref(false)
const editingItem = ref<ScheduleItemDTO | null>(null)
const formValid = ref(false)
const formRef = ref()

const formData = ref({
  date: '',
  time_slot_id: null as number | null,
  group_id: null as number | null,
  subject_id: null as number | null,
  teacher_user_id: null as number | null,
  room_id: null as number | null,
  override_reason: '',
  force: false,
})

const groupOptions = ref<Array<{ id: number; name: string }>>([])
const subjectOptions = ref<Array<{ id: number; name: string }>>([])
const teacherOptions = ref<Array<{ id: number; fio: string }>>([])
const roomOptions = ref<Array<{ id: number; name: string }>>([])

const viewModes = [
  { title: 'Сетка', value: 'grid' },
  { title: 'Таблица', value: 'table' },
]

const tableHeaders = [
  { title: 'Дата', key: 'date' },
  { title: 'Время', key: 'time_slot' },
  { title: 'Группа', key: 'group' },
  { title: 'Предмет', key: 'subject' },
  { title: 'Преподаватель', key: 'teacher' },
  { title: 'Кабинет', key: 'room' },
  ...(auth.hasPermission('schedule.create') ? [{ title: 'Действия', key: 'actions', sortable: false }] : []),
]

const weekDays = computed(() => {
  if (dateRange.value.length === 0) {
    // Default: current week
    const today = new Date()
    const startOfWeek = new Date(today)
    startOfWeek.setDate(today.getDate() - today.getDay() + 1) // Monday
    
    const days = []
    for (let i = 0; i < 7; i++) {
      const date = new Date(startOfWeek)
      date.setDate(startOfWeek.getDate() + i)
      days.push({ date: date.toISOString().split('T')[0] })
    }
    return days
  }

  if (dateRange.value.length === 1) {
    const date = new Date(dateRange.value[0])
    const days = []
    for (let i = 0; i < 7; i++) {
      const d = new Date(date)
      d.setDate(date.getDate() + i)
      days.push({ date: d.toISOString().split('T')[0] })
    }
    return days
  }

  // Range
  const start = new Date(dateRange.value[0])
  const end = new Date(dateRange.value[1])
  const days = []
  const current = new Date(start)
  while (current <= end) {
    days.push({ date: current.toISOString().split('T')[0] })
    current.setDate(current.getDate() + 1)
  }
  return days
})

const dateRangeText = computed(() => {
  if (dateRange.value.length === 0) return 'Текущая неделя'
  if (dateRange.value.length === 1) return formatDate(dateRange.value[0])
  return `${formatDate(dateRange.value[0])} - ${formatDate(dateRange.value[1])}`
})

const dateOptions = computed(() => {
  return weekDays.value.map(day => ({
    title: formatDate(day.date),
    value: day.date,
  }))
})

const timeSlotOptions = computed(() => {
  return timeSlots.value.map(slot => ({
    id: slot.id,
    label: `${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}`,
  }))
})

const filteredItems = computed(() => {
  let filtered = items.value

  if (selectedGroup.value) {
    filtered = filtered.filter(item => item.group_id === selectedGroup.value)
  }

  if (dateRange.value.length > 0) {
    if (dateRange.value.length === 1) {
      filtered = filtered.filter(item => item.date === dateRange.value[0])
    } else {
      filtered = filtered.filter(item => {
        const itemDate = new Date(item.date)
        const start = new Date(dateRange.value[0])
        const end = new Date(dateRange.value[1])
        return itemDate >= start && itemDate <= end
      })
    }
  }

  return filtered
})

const loadItems = async () => {
  if (!props.versionId) {
    items.value = []
    return
  }

  loading.value = true
  try {
    const response = await scheduleApi.getItems({
      version_id: props.versionId,
      per_page: 1000,
    })
    items.value = response.data
  } catch (error) {
    console.error('Failed to load schedule items:', error)
    showToast('Ошибка при загрузке расписания', 'error')
  } finally {
    loading.value = false
  }
}

const loadTimeSlots = async () => {
  try {
    const slots = await referencesApi.listTimeSlots({ per_page: 1000 })
    timeSlots.value = slots.map((slot: any) => ({
      id: slot.id,
      start_time: slot.start_time,
      end_time: slot.end_time,
    }))
  } catch (error) {
    console.error('Failed to load time slots:', error)
  }
}

const loadReferences = async () => {
  try {
    const [groups, subjects, teachers, rooms] = await Promise.all([
      referencesApi.listGroups({ per_page: 1000 }),
      referencesApi.listSubjects({ per_page: 1000 }),
      referencesApi.listTeachers({ per_page: 1000 }),
      referencesApi.listRooms({ per_page: 1000 }),
    ])

    groupOptions.value = groups.map((g: any) => ({ id: g.id, name: g.name }))
    subjectOptions.value = subjects.map((s: any) => ({ id: s.id, name: s.name }))
    teacherOptions.value = teachers.map((t: any) => ({ id: t.id, fio: t.fio || t.name }))
    roomOptions.value = rooms.map((r: any) => ({ id: r.id, name: r.name }))
  } catch (error) {
    console.error('Failed to load references:', error)
  }
}

const getCellItems = (timeSlotId: number, date: string): ScheduleItemDTO[] => {
  return filteredItems.value.filter(
    item => item.time_slot_id === timeSlotId && item.date === date
  )
}

const getItemClass = (): string => {
  return 'schedule-item-clickable'
}

const isToday = (date: string): boolean => {
  const today = new Date().toISOString().split('T')[0]
  return date === today
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const formatDateShort = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
  })
}

const formatDayName = (date: string) => {
  const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']
  const dayIndex = new Date(date).getDay()
  return days[dayIndex === 0 ? 6 : dayIndex - 1]
}

const formatTime = (time: string) => {
  return time.substring(0, 5) // HH:mm
}

const formatTimeSlot = (item: ScheduleItemDTO) => {
  const slot = timeSlots.value.find(s => s.id === item.time_slot_id)
  if (!slot) return '-'
  return `${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}`
}

const applyViewMode = () => {
  // View mode changed
}

const filterByGroup = () => {
  // Group filter applied
}

const filterByDateRange = () => {
  // Date range filter applied
}

const createItemForCell = (timeSlotId: number, date: string) => {
  formData.value = {
    date,
    time_slot_id: timeSlotId,
    group_id: selectedGroup.value,
    subject_id: null,
    teacher_user_id: null,
    room_id: null,
    override_reason: '',
    force: false,
  }
  editingItem.value = null
  showCreateDialog.value = true
}

const editItem = (item: ScheduleItemDTO) => {
  editingItem.value = item
  formData.value = {
    date: item.date,
    time_slot_id: item.time_slot_id,
    group_id: item.group_id,
    subject_id: item.subject_id,
    teacher_user_id: item.teacher_user_id,
    room_id: item.room_id,
    override_reason: item.override_reason || '',
    force: false,
  }
  showCreateDialog.value = true
}

const saveItem = async () => {
  if (!formRef.value?.validate()) {
    return
  }

  if (!props.versionId) {
    showToast('Выберите версию расписания', 'warning')
    return
  }

  saving.value = true
  try {
    if (editingItem.value) {
      await scheduleApi.updateItem(editingItem.value.id, {
        ...formData.value,
      })
      showToast('Пара обновлена', 'success')
    } else {
      await scheduleApi.createItem({
        version_id: props.versionId,
        ...formData.value,
      })
      showToast('Пара создана', 'success')
    }
    showCreateDialog.value = false
    await loadItems()
  } catch (error: any) {
    console.error('Failed to save item:', error)
    if (error.response?.status === 409) {
      // Conflicts detected
      const conflicts = error.response.data.conflicts || []
      showToast(`Обнаружены конфликты: ${conflicts.map((c: any) => c.message).join(', ')}`, 'warning')
    } else {
      showToast(error.response?.data?.message || 'Ошибка при сохранении', 'error')
    }
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item: ScheduleItemDTO) => {
  if (!confirm(`Удалить пару "${item.subject?.name}" от ${formatDate(item.date)}?`)) {
    return
  }

  try {
    await scheduleApi.deleteItem(item.id)
    showToast('Пара удалена', 'success')
    await loadItems()
  } catch (error: any) {
    console.error('Failed to delete item:', error)
    showToast(error.response?.data?.message || 'Ошибка при удалении', 'error')
  }
}

watch(() => props.versionId, () => {
  loadItems()
}, { immediate: true })

onMounted(async () => {
  await Promise.all([
    loadTimeSlots(),
    loadReferences(),
  ])
  
  // Set default date range to current week
  const today = new Date()
  const startOfWeek = new Date(today)
  startOfWeek.setDate(today.getDate() - today.getDay() + 1)
  const endOfWeek = new Date(startOfWeek)
  endOfWeek.setDate(startOfWeek.getDate() + 6)
  dateRange.value = [
    startOfWeek.toISOString().split('T')[0],
    endOfWeek.toISOString().split('T')[0],
  ]
})
</script>

<style scoped>
.schedule-grid {
  overflow-x: auto;
}

.schedule-table {
  min-width: 100%;
}

.schedule-time-col {
  width: 100px;
  position: sticky;
  left: 0;
  background: white;
  z-index: 2;
}

.schedule-day-col {
  min-width: 150px;
}

.schedule-time-cell {
  position: sticky;
  left: 0;
  background: white;
  z-index: 1;
  border-right: 2px solid rgba(0, 0, 0, 0.12);
}

.schedule-cell {
  min-height: 80px;
  vertical-align: top;
  padding: 4px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  position: relative;
}

.schedule-item {
  background: #e3f2fd;
  border: 1px solid #90caf9;
  border-radius: 4px;
  padding: 6px;
  margin-bottom: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.schedule-item:hover {
  background: #bbdefb;
  transform: scale(1.02);
}

.schedule-item-subject {
  font-size: 0.875rem;
  margin-bottom: 2px;
}

.schedule-item-group,
.schedule-item-teacher,
.schedule-item-room {
  font-size: 0.75rem;
  color: rgba(0, 0, 0, 0.7);
}

.schedule-empty-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60px;
}

.gap-2 {
  gap: 8px;
}
</style>
