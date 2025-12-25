<template>
  <div>
    <DataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию"
      create-button-text="Добавить группу"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.term="{ item }">
        {{ getTermName(item.term_id) }}
      </template>
      <template v-slot:item.curator="{ item }">
        {{ getCuratorName(item.curator_user_id) }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать группу' : 'Создать группу'"
      :saving="saving"
      @save="save"
      @cancel="dialog = false"
    >
      <template v-slot:form="{ rules }">
        <TermSelect
          v-model="form.term_id"
          :rules="[rules.required]"
        />
        <v-text-field
          v-model="form.name"
          label="Название"
          :rules="[rules.required]"
          variant="outlined"
        />
        <v-text-field
          v-model.number="form.level"
          label="Курс"
          type="number"
          variant="outlined"
        />
        <CuratorSelect
          v-model="form.curator_user_id"
        />
      </template>
    </FormDialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить группу?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить группу "{{ deletingItem?.name }}"?</v-card-text>
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
import { referencesApi } from '../../api/references'
import type { TermDTO } from '../../api/references'
import type { UserDTO } from '../../api/users'
import type { GroupDTO } from '../../api/directory'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'
import TermSelect from '../../components/admin/TermSelect.vue'
import CuratorSelect from '../../components/admin/CuratorSelect.vue'

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Семестр', key: 'term' },
  { title: 'Название', key: 'name' },
  { title: 'Курс', key: 'level' },
  { title: 'Куратор', key: 'curator' },
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
} = useCrudTable<GroupDTO & { term_id?: number; level?: number; curator_user_id?: number }>({
  resourceUrl: '/v1/admin/directory/groups',
})

const dialog = ref(false)
const editing = ref(false)
const form = ref({
  term_id: null as number | null,
  name: '',
  level: null as number | null,
  curator_user_id: null as number | null,
})
const deleteDialog = ref(false)
const deletingItem = ref<GroupDTO | null>(null)

const terms = ref<TermDTO[]>([])
const curators = ref<UserDTO[]>([])

const getTermName = (termId?: number) => {
  if (!termId) return '-'
  const term = terms.value.find((t) => t.id === termId)
  return term?.name || '-'
}

const getCuratorName = (curatorId?: number) => {
  if (!curatorId) return '-'
  const curator = curators.value.find((c) => c.id === curatorId)
  return curator?.fio || '-'
}

const loadReferences = async () => {
  terms.value = await referencesApi.listTerms()
  curators.value = await referencesApi.listCurators()
}

const openDialog = (item?: GroupDTO) => {
  editing.value = !!item
  form.value = item
    ? {
        term_id: (item as any).term_id || null,
        name: item.name,
        level: (item as any).level || null,
        curator_user_id: (item as any).curator_user_id || null,
      }
    : {
        term_id: null,
        name: '',
        level: null,
        curator_user_id: null,
      }
  deletingItem.value = item || null
  dialog.value = true
}

const save = async () => {
  try {
    const payload: any = {
      name: form.value.name,
      term_id: form.value.term_id,
      level: form.value.level || undefined,
      curator_user_id: form.value.curator_user_id || undefined,
    }

    if (editing.value && deletingItem.value) {
      await update(deletingItem.value.id, payload)
    } else {
      await create(payload)
    }
    dialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to save group:', error)
  }
}

const confirmDelete = (item: GroupDTO) => {
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
    console.error('Failed to delete group:', error)
  }
}

onMounted(async () => {
  await loadReferences()
  load()
})
</script>
