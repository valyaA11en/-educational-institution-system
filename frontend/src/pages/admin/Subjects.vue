<template>
  <div>
    <DataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию"
      create-button-text="Добавить предмет"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать предмет' : 'Создать предмет'"
      :saving="saving"
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
      </template>
    </FormDialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить предмет?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить предмет "{{ deletingItem?.name }}"?</v-card-text>
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
import { ref, onMounted } from 'vue'
import { useCrudTable } from '../../composables/useCrudTable'
import type { SubjectDTO } from '../../api/directory'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Название', key: 'name' },
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
} = useCrudTable<SubjectDTO>({
  resourceUrl: '/v1/admin/directory/subjects',
})

const dialog = ref(false)
const editing = ref(false)
const form = ref({ name: '' })
const deleteDialog = ref(false)
const deletingItem = ref<SubjectDTO | null>(null)

const openDialog = (item?: SubjectDTO) => {
  editing.value = !!item
  form.value = item ? { name: item.name } : { name: '' }
  deletingItem.value = item || null
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
    console.error('Failed to save subject:', error)
  }
}

const confirmDelete = (item: SubjectDTO) => {
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
    console.error('Failed to delete subject:', error)
  }
}

onMounted(() => {
  load()
})
</script>
