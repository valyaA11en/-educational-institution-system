<template>
  <v-card>
    <v-card-title class="d-flex flex-wrap align-center gap-2">
      <span>Материалы</span>
      <v-spacer />
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        Добавить
      </v-btn>
    </v-card-title>

    <v-card-text>
      <v-form class="mb-4">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select
              v-model="filters.subject_id"
              :items="subjectOptions"
              item-title="name"
              item-value="id"
              label="Предмет"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2" class="d-flex align-center">
            <v-btn
              color="primary"
              prepend-icon="mdi-refresh"
              :loading="loading"
              @click="load"
            >
              Загрузить
            </v-btn>
          </v-col>
        </v-row>
      </v-form>

      <div v-if="loading && items.length === 0" class="text-center py-8">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <div v-else-if="items.length === 0" class="text-center py-8 text-medium-emphasis">
        <p>Нет материалов. Добавьте материал или измените фильтры.</p>
      </div>
      <v-table v-else density="compact">
        <thead>
          <tr>
            <th class="text-left">Предмет</th>
            <th class="text-left">Название</th>
            <th class="text-left">Доступ</th>
            <th class="text-right">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in items" :key="m.id">
            <td>{{ subjectName(m.subject_id) }}</td>
            <td>{{ m.title }}</td>
            <td>
              <v-chip size="small" variant="tonal">
                {{ scopeLabel(m.visibility_scope) }}
              </v-chip>
            </td>
            <td class="text-right">
              <v-btn
                icon="mdi-eye"
                variant="text"
                size="small"
                @click="openView(m)"
              />
              <v-btn
                icon="mdi-pencil"
                variant="text"
                size="small"
                @click="openEdit(m)"
              />
              <v-btn
                icon="mdi-download"
                variant="text"
                size="small"
                :loading="downloadingId === m.id"
                @click="downloadOne(m)"
              />
              <v-btn
                icon="mdi-check"
                variant="text"
                size="small"
                title="Отметить прочтение"
                @click="markRead(m.id)"
              />
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                @click="confirmDelete(m)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>

    <!-- View dialog -->
    <v-dialog v-model="viewOpen" max-width="600" persistent>
      <v-card>
        <v-card-title>{{ viewing?.title }}</v-card-title>
        <v-card-subtitle v-if="viewing">
          {{ subjectName(viewing.subject_id) }} · {{ scopeLabel(viewing.visibility_scope) }}
        </v-card-subtitle>
        <v-card-text>
          <pre class="material-content">{{ viewing?.content || '—' }}</pre>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="viewOpen = false">Закрыть</v-btn>
          <v-btn color="primary" variant="tonal" @click="viewing && downloadOne(viewing); viewOpen = false">
            Скачать
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Create / Edit dialog -->
    <v-dialog v-model="formOpen" max-width="560" persistent>
      <v-form @submit.prevent="submitForm">
        <v-card>
          <v-card-title>{{ editing ? 'Редактировать материал' : 'Новый материал' }}</v-card-title>
          <v-card-text>
            <v-select
              v-model="form.subject_id"
              :items="subjectOptions"
              item-title="name"
              item-value="id"
              label="Предмет *"
              variant="outlined"
              density="compact"
              :disabled="!!editing"
              class="mb-2"
            />
            <v-text-field
              v-model="form.title"
              label="Название *"
              variant="outlined"
              density="compact"
              class="mb-2"
            />
            <v-textarea
              v-model="form.content"
              label="Содержание"
              variant="outlined"
              density="compact"
              rows="4"
              class="mb-2"
            />
            <v-select
              v-model="form.visibility_scope"
              :items="scopeOptions.filter((o) => o.value === 'all')"
              item-title="label"
              item-value="value"
              label="Доступ"
              variant="outlined"
              density="compact"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeForm">Отмена</v-btn>
            <v-btn type="submit" color="primary" :loading="saving">Сохранить</v-btn>
          </v-card-actions>
        </v-card>
      </v-form>
    </v-dialog>

    <!-- Delete confirm -->
    <v-dialog v-model="deleteConfirmOpen" max-width="400" persistent>
      <v-card>
        <v-card-title>Удалить материал?</v-card-title>
        <v-card-text>
          «{{ deleting?.title }}» будет удалён без возможности восстановления.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="deleteConfirmOpen = false">Отмена</v-btn>
          <v-btn color="error" :loading="deletingBusy" @click="doDelete">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { materialsApi, type MaterialDTO } from '../../../api/materials'
import { referencesApi } from '../../../api/references'
import { useToast } from '../../../composables/useToast'

const { showToast } = useToast()
const loading = ref(false)
const saving = ref(false)
const downloadingId = ref<number | null>(null)
const deletingBusy = ref(false)

const items = ref<MaterialDTO[]>([])
const subjectOptions = ref<{ id: number; name: string }[]>([])

const filters = ref({ subject_id: null as number | null })

const viewOpen = ref(false)
const viewing = ref<MaterialDTO | null>(null)

const formOpen = ref(false)
const editing = ref<MaterialDTO | null>(null)
const form = ref({
  subject_id: null as number | null,
  title: '',
  content: '' as string | null,
  visibility_scope: 'all' as 'group' | 'subgroup' | 'individual' | 'all',
})

const scopeOptions = [
  { value: 'all', label: 'Все' },
  { value: 'group', label: 'Группа' },
  { value: 'subgroup', label: 'Подгруппа' },
  { value: 'individual', label: 'Индивидуально' },
]

const deleteConfirmOpen = ref(false)
const deleting = ref<MaterialDTO | null>(null)

function scopeLabel(s: string): string {
  return scopeOptions.find((o) => o.value === s)?.label ?? s
}

function subjectName(id: number): string {
  return subjectOptions.value.find((s) => s.id === id)?.name ?? String(id)
}

async function load() {
  loading.value = true
  try {
    const params = filters.value.subject_id ? { subject_id: filters.value.subject_id } : undefined
    items.value = await materialsApi.list(params)
  } catch (e) {
    console.error(e)
    showToast('Ошибка загрузки материалов', 'error')
  } finally {
    loading.value = false
  }
}

async function loadSubjects() {
  try {
    const list = await referencesApi.listSubjects({ per_page: 1000 })
    subjectOptions.value = Array.isArray(list) ? list : (list as { id: number; name: string }[]) ?? []
  } catch (e) {
    console.error('Failed to load subjects', e)
  }
}

function openCreate() {
  editing.value = null
  form.value = {
    subject_id: null,
    title: '',
    content: null,
    visibility_scope: 'all',
  }
  formOpen.value = true
}

function openEdit(m: MaterialDTO) {
  editing.value = m
  form.value = {
    subject_id: m.subject_id,
    title: m.title,
    content: m.content ?? null,
    visibility_scope: m.visibility_scope as 'group' | 'subgroup' | 'individual' | 'all',
  }
  formOpen.value = true
}

function openView(m: MaterialDTO) {
  viewing.value = m
  viewOpen.value = true
}

function closeForm() {
  formOpen.value = false
  editing.value = null
}

async function submitForm() {
  if (!form.value.subject_id || !form.value.title.trim()) {
    showToast('Укажите предмет и название', 'warning')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await materialsApi.update(editing.value.id, {
        title: form.value.title.trim(),
        content: form.value.content?.trim() || null,
      })
      showToast('Материал обновлён', 'success')
    } else {
      await materialsApi.create({
        subject_id: form.value.subject_id,
        title: form.value.title.trim(),
        content: form.value.content?.trim() || null,
        visibility_scope: 'all',
        targets: undefined,
      })
      showToast('Материал создан', 'success')
    }
    closeForm()
    await load()
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? 'Ошибка сохранения'
    showToast(msg, 'error')
  } finally {
    saving.value = false
  }
}

async function downloadOne(m: MaterialDTO) {
  downloadingId.value = m.id
  try {
    const blob = await materialsApi.download(m.id)
    const name = (m.title || 'material').replace(/[/\\?*"<>|]/g, '-') + '.txt'
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = name
    a.click()
    URL.revokeObjectURL(a.href)
  } catch (e) {
    console.error(e)
    showToast('Ошибка скачивания', 'error')
  } finally {
    downloadingId.value = null
  }
}

async function markRead(id: number) {
  try {
    await materialsApi.read(id)
    showToast('Отмечено как прочитано', 'success')
  } catch (e) {
    console.error(e)
    showToast('Ошибка', 'error')
  }
}

function confirmDelete(m: MaterialDTO) {
  deleting.value = m
  deleteConfirmOpen.value = true
}

async function doDelete() {
  if (!deleting.value) return
  deletingBusy.value = true
  try {
    await materialsApi.delete(deleting.value.id)
    showToast('Материал удалён', 'success')
    deleteConfirmOpen.value = false
    deleting.value = null
    await load()
  } catch (e) {
    console.error(e)
    showToast('Ошибка удаления', 'error')
  } finally {
    deletingBusy.value = false
  }
}

onMounted(() => {
  loadSubjects()
  load()
})
</script>

<style scoped>
.material-content {
  white-space: pre-wrap;
  word-break: break-word;
  font-family: inherit;
  font-size: 0.9rem;
}
</style>
