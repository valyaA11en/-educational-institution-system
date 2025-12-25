<template>
  <div>
    <DataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию"
      create-button-text="Добавить подгруппу"
      :additional-filters="true"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:filters>
        <v-select
          v-model="selectedGroupId"
          :items="groupOptions"
          label="Группа"
          variant="outlined"
          density="compact"
          clearable
          @update:model-value="loadWithFilter"
        />
      </template>
      <template v-slot:item.group="{ item }">
        {{ getGroupName(item.group_id) }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать подгруппу' : 'Создать подгруппу'"
      :saving="saving"
      @save="save"
      @cancel="dialog = false"
    >
      <template v-slot:form="{ rules }">
        <v-select
          v-model="form.group_id"
          :items="groupOptions"
          label="Группа"
          :rules="[rules.required]"
          variant="outlined"
        />
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
        <v-card-title>Удалить подгруппу?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить подгруппу "{{ deletingItem?.name }}"?</v-card-text>
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
import { directoryApi } from '../../api/directory'
import type { GroupDTO, SubgroupDTO } from '../../api/directory'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Группа', key: 'group' },
  { title: 'Название', key: 'name' },
  { title: 'Действия', key: 'actions', sortable: false, width: '120px' },
]

const selectedGroupId = ref<number | null>(null)
const groups = ref<GroupDTO[]>([])
const groupOptions = ref<{ title: string; value: number }[]>([])

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
} = useCrudTable<SubgroupDTO>({
  resourceUrl: '/v1/admin/directory/subgroups',
})

const dialog = ref(false)
const editing = ref(false)
const form = ref({ group_id: null as number | null, name: '' })
const deleteDialog = ref(false)
const deletingItem = ref<SubgroupDTO | null>(null)

const getGroupName = (groupId: number) => {
  const group = groups.value.find((g) => g.id === groupId)
  return group?.name || '-'
}

const loadGroups = async () => {
  try {
    const response = await directoryApi.listGroups({ per_page: 1000 })
    groups.value = response.data
    groupOptions.value = response.data.map((g) => ({ title: g.name, value: g.id }))
  } catch (error) {
    console.error('Failed to load groups:', error)
  }
}

const loadWithFilter = () => {
  load({ group_id: selectedGroupId.value || undefined })
}

const openDialog = (item?: SubgroupDTO) => {
  editing.value = !!item
  form.value = item
    ? { group_id: item.group_id, name: item.name }
    : { group_id: null, name: '' }
  deletingItem.value = item || null
  dialog.value = true
}

const save = async () => {
  try {
    if (editing.value && deletingItem.value) {
      await update(deletingItem.value.id, {
        group_id: form.value.group_id!,
        name: form.value.name,
      })
    } else {
      await create({
        group_id: form.value.group_id!,
        name: form.value.name,
      })
    }
    dialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to save subgroup:', error)
  }
}

const confirmDelete = (item: SubgroupDTO) => {
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
    console.error('Failed to delete subgroup:', error)
  }
}

onMounted(async () => {
  await loadGroups()
  load()
})
</script>
