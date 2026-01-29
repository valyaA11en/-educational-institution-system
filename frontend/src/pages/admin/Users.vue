<template>
  <div>
    <DataTable
      :headers="headers"
      :items="users"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по ФИО, email или телефону"
      create-button-text="Добавить пользователя"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.status="{ item }">
        <v-chip :color="item.status === 'active' ? 'success' : 'error'" size="small">
          {{ item.status === 'active' ? 'Активен' : 'Заблокирован' }}
        </v-chip>
      </template>
      <template v-slot:item.roles="{ item }">
        <v-chip
          v-for="role in item.roles"
          :key="role.id"
          size="small"
          class="mr-1"
        >
          {{ role.name }}
        </v-chip>
      </template>
      <template v-slot:item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать пользователя' : 'Создать пользователя'"
      :saving="saving"
      max-width="600"
      @save="save"
      @cancel="dialog = false"
    >
      <template v-slot:form="{ rules }">
        <v-text-field
          v-model="form.fio"
          label="ФИО"
          :rules="[rules.required]"
          variant="outlined"
        />
        <v-text-field
          v-model="form.email"
          label="Email"
          type="email"
          variant="outlined"
        />
        <v-text-field
          v-if="!editing"
          v-model="form.password"
          label="Пароль"
          type="password"
          :rules="[rules.required]"
          variant="outlined"
        />
        <v-select
          v-model="form.status"
          :items="statusOptions"
          label="Статус"
          variant="outlined"
        />
        <v-select
          v-model="form.role_ids"
          :items="roleOptions"
          label="Роли"
          multiple
          chips
          variant="outlined"
        />
      </template>
    </FormDialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить пользователя?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить пользователя "{{ deletingItem?.fio }}"?</v-card-text>
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
import { usersApi, type UserDTO } from '../../api/users'
import { rolesApi } from '../../api/roles'
import type { RoleDTO } from '../../api/dto'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'

const search = ref('')
const loading = ref(false)
const users = ref<UserDTO[]>([])
const roles = ref<RoleDTO[]>([])
const pagination = ref({ current_page: 1, per_page: 50, total: 0 })

const roleOptions = ref<{ title: string; value: number }[]>([])
const statusOptions = [
  { title: 'Активен', value: 'active' },
  { title: 'Заблокирован', value: 'blocked' },
]

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'ФИО', key: 'fio' },
  { title: 'Email', key: 'email' },
  { title: 'Роли', key: 'roles' },
  { title: 'Статус', key: 'status' },
  { title: 'Создан', key: 'created_at' },
  { title: 'Действия', key: 'actions', sortable: false, width: '120px' },
]

const dialog = ref(false)
const editing = ref(false)
const saving = ref(false)
const form = ref({
  fio: '',
  email: '',
  password: '',
  status: 'active' as 'active' | 'blocked',
  role_ids: [] as number[],
})

const deleteDialog = ref(false)
const deleting = ref(false)
const deletingItem = ref<UserDTO | null>(null)

const formatDate = (date?: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ru-RU')
}

const loadRoles = async () => {
  try {
    const data = await rolesApi.list()
    roles.value = data
    roleOptions.value = data.map((r) => ({ title: r.name, value: r.id }))
  } catch (error) {
    console.error('Failed to load roles:', error)
  }
}

const loadUsers = async () => {
  loading.value = true
  try {
    const response = await usersApi.list({
      q: search.value || undefined,
      per_page: pagination.value.per_page,
      page: pagination.value.current_page,
    })
    users.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
    }
  } catch (error) {
    console.error('Failed to load users:', error)
  } finally {
    loading.value = false
  }
}

const onSearch = (value: string) => {
  search.value = value
  pagination.value.current_page = 1
  loadUsers()
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadUsers()
}

const openDialog = (item?: UserDTO) => {
  editing.value = !!item
  if (item) {
    form.value = {
      fio: item.fio,
      email: item.email || '',
      password: '',
      status: item.status,
      role_ids: item.roles?.map((r) => r.id) || [],
    }
    deletingItem.value = item
  } else {
    form.value = {
      fio: '',
      email: '',
      password: '',
      status: 'active',
      role_ids: [],
    }
    deletingItem.value = null
  }
  dialog.value = true
}

const save = async () => {
  saving.value = true
  try {
    if (editing.value && deletingItem.value) {
      const payload: any = {
        fio: form.value.fio,
        email: form.value.email || undefined,
        status: form.value.status,
        role_ids: form.value.role_ids,
      }
      await usersApi.update(deletingItem.value.id, payload)
    } else {
      const payload = {
        fio: form.value.fio,
        email: form.value.email || undefined,
        password: form.value.password,
        status: form.value.status,
        role_ids: form.value.role_ids,
      }
      await usersApi.create(payload)
    }
    dialog.value = false
    deletingItem.value = null
    await loadUsers()
  } catch (error) {
    console.error('Failed to save user:', error)
  } finally {
    saving.value = false
  }
}

const confirmDelete = (item: UserDTO) => {
  deletingItem.value = item
  deleteDialog.value = true
}

const deleteItem = async () => {
  if (!deletingItem.value) return

  deleting.value = true
  try {
    await usersApi.delete(deletingItem.value.id)
    deleteDialog.value = false
    await loadUsers()
  } catch (error) {
    console.error('Failed to delete user:', error)
  } finally {
    deleting.value = false
    deletingItem.value = null
  }
}

onMounted(async () => {
  await loadRoles()
  await loadUsers()
})
</script>
