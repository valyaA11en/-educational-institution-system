<template>
  <div>
    <DataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию"
      create-button-text="Добавить слот"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.start_time="{ item }">
        {{ formatTime(item.start_time) }}
      </template>
      <template v-slot:item.end_time="{ item }">
        {{ formatTime(item.end_time) }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать слот' : 'Создать слот'"
      :saving="saving"
      @save="save"
      @cancel="dialog = false"
    >
      <template v-slot:form="{ rules: baseRules }">
        <v-text-field
          v-model="form.name"
          label="Название"
          :rules="[baseRules.required]"
          variant="outlined"
        />
        <v-text-field
          v-model="form.start_time"
          label="Время начала (HH:MM)"
          :rules="[baseRules.required, rules.time]"
          variant="outlined"
          placeholder="08:00"
        />
        <v-text-field
          v-model="form.end_time"
          label="Время окончания (HH:MM)"
          :rules="[baseRules.required, rules.time, rules.endAfterStart]"
          variant="outlined"
          placeholder="09:30"
        />
      </template>
    </FormDialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить слот?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить слот "{{ deletingItem?.name }}"?</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Отмена</v-btn>
          <v-btn color="error" :loading="deleting" @click="deleteItem">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useCrudTable } from '../../composables/useCrudTable'
import type { TimeSlotDTO } from '../../api/directory'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Название', key: 'name' },
  { title: 'Время начала', key: 'start_time' },
  { title: 'Время окончания', key: 'end_time' },
  { title: 'Действия', key: 'actions', sortable: false, width: '120px' },
]

const {
  items,
  loading,
  saving,
  deleting,
  pagination,
  load,
  create,
  update,
  remove,
  onSearch,
  onPageChange,
} = useCrudTable<TimeSlotDTO>({
  resourceUrl: '/v1/admin/directory/time-slots',
  transformPayload: (payload) => ({
    ...payload,
    start_time: payload.start_time ? payload.start_time + ':00' : undefined,
    end_time: payload.end_time ? payload.end_time + ':00' : undefined,
  }),
})

const dialog = ref(false)
const editing = ref(false)
const form = ref({ name: '', start_time: '', end_time: '' })
const deleteDialog = ref(false)
const deletingItem = ref<TimeSlotDTO | null>(null)

const rules = computed(() => ({
  time: (v: string) => /^([0-1][0-9]|2[0-3]):[0-5][0-9]$/.test(v) || 'Формат: HH:MM',
  endAfterStart: (v: string) => {
    if (!form.value.start_time || !v) return true
    return strtotime(v) > strtotime(form.value.start_time) || 'Время окончания должно быть позже времени начала'
  },
}))

const strtotime = (time: string): number => {
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

const formatTime = (time: string) => {
  if (!time) return '-'
  const parts = time.split(':')
  return `${parts[0]}:${parts[1]}`
}

const openDialog = (item?: TimeSlotDTO) => {
  editing.value = !!item
  if (item) {
    form.value = {
      name: item.name,
      start_time: formatTime(item.start_time),
      end_time: formatTime(item.end_time),
    }
    deletingItem.value = item
  } else {
    form.value = { name: '', start_time: '', end_time: '' }
    deletingItem.value = null
  }
  dialog.value = true
}

const save = async () => {
  try {
    if (editing.value && deletingItem.value) {
      await update(deletingItem.value.id, form.value)
    } else {
      await create(form.value)
    }
    dialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to save time slot:', error)
  }
}

const confirmDelete = (item: TimeSlotDTO) => {
  deletingItem.value = item
  deleteDialog.value = true
}

const deleteItem = async () => {
  if (!deletingItem.value) return
  try {
    await remove(deletingItem.value.id)
    deleteDialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to delete time slot:', error)
  }
}

onMounted(() => {
  load()
})
</script>
