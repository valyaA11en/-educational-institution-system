<template>
  <div>
    <DataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию"
      create-button-text="Добавить кабинет"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.type="{ item }">
        {{ item.attributes?.type || '-' }}
      </template>
      <template v-slot:item.attributes="{ item }">
        <v-chip v-if="item.attributes?.pc" size="small" color="info" class="mr-1">ПК</v-chip>
        <v-chip v-if="item.attributes?.lab" size="small" color="warning">Лаборатория</v-chip>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать кабинет' : 'Создать кабинет'"
      :saving="saving"
      max-width="600"
      @save="save"
      @cancel="dialog = false"
    >
      <template v-slot:form="{ rules }">
        <v-text-field
          v-model="form.name"
          label="Название"
          :rules="[rules.required]"
          variant="outlined"
        />
        <v-select
          v-model="form.type"
          :items="typeOptions"
          label="Тип"
          variant="outlined"
        />
        <v-text-field
          v-model.number="form.capacity"
          label="Вместимость"
          type="number"
          variant="outlined"
        />
        <v-textarea
          v-model="attributesJson"
          label="Атрибуты (JSON)"
          variant="outlined"
          rows="4"
          hint='Пример: {"pc": true, "lab": false}'
        />
      </template>
    </FormDialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить кабинет?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить кабинет "{{ deletingItem?.name }}"?</v-card-text>
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
import type { RoomDTO } from '../../api/directory'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Название', key: 'name' },
  { title: 'Тип', key: 'type' },
  { title: 'Вместимость', key: 'capacity' },
  { title: 'Атрибуты', key: 'attributes' },
  { title: 'Действия', key: 'actions', sortable: false, width: '120px' },
]

interface RoomFormDTO {
  name: string
  type?: string
  capacity?: number | null
  attributes?: Record<string, any>
}

const typeOptions = [
  { title: 'Обычный', value: 'regular' },
  { title: 'Компьютерный', value: 'computer' },
  { title: 'Лаборатория', value: 'lab' },
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
} = useCrudTable<RoomDTO>({
  resourceUrl: '/v1/admin/directory/rooms',
})

const dialog = ref(false)
const editing = ref(false)
const form = ref<RoomFormDTO>({ name: '', type: '', capacity: null })
const attributesJson = ref('{}')
const deleteDialog = ref(false)
const deletingItem = ref<RoomDTO | null>(null)

const attributes = computed(() => {
  try {
    const parsed = JSON.parse(attributesJson.value || '{}')
    if (form.value.type) {
      parsed.type = form.value.type
    }
    return parsed
  } catch {
    return form.value.type ? { type: form.value.type } : {}
  }
})

const openDialog = (item?: RoomDTO) => {
  editing.value = !!item
  if (item) {
    form.value = {
      name: item.name,
      type: item.attributes?.type || '',
      capacity: item.capacity || null,
    }
    attributesJson.value = JSON.stringify(item.attributes || {}, null, 2)
    deletingItem.value = item
  } else {
    form.value = { name: '', type: '', capacity: null }
    attributesJson.value = '{}'
    deletingItem.value = null
  }
  dialog.value = true
}

const save = async () => {
  try {
    const payload: any = {
      name: form.value.name,
      capacity: form.value.capacity || undefined,
      attributes: Object.keys(attributes.value).length > 0 ? attributes.value : undefined,
    }

    if (editing.value && deletingItem.value) {
      await update(deletingItem.value.id, payload)
    } else {
      await create(payload)
    }
    dialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to save room:', error)
  }
}

const confirmDelete = (item: RoomDTO) => {
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
    console.error('Failed to delete room:', error)
  }
}

onMounted(() => {
  load()
})
</script>
