<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center mb-4">
          <h1>Конкурсы</h1>
          <v-btn
            v-if="canCreate"
            color="primary"
            @click="showCreateDialog = true"
          >
            Создать конкурс
          </v-btn>
        </div>

        <v-card>
          <v-card-text>
            <v-row>
              <v-col cols="12" md="4">
                <v-select
                  v-model="filters.active"
                  :items="[
                    { title: 'Все', value: null },
                    { title: 'Активные', value: true },
                    { title: 'Архив', value: false },
                  ]"
                  label="Статус"
                  variant="outlined"
                  density="compact"
                  @update:model-value="loadContests"
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-data-table
            :headers="headers"
            :items="contests"
            :loading="loading"
            :items-per-page="20"
            @click:row="onRowClick"
          >
            <template #item.start_at="{ item }">
              {{ formatDate(item.start_at) }}
            </template>
            <template #item.end_at="{ item }">
              {{ formatDate(item.end_at) }}
            </template>
            <template #item.visibility_scope="{ item }">
              {{
                item.visibility_scope === 'all'
                  ? 'Все'
                  : item.visibility_scope === 'group'
                    ? 'Группа'
                    : 'По приглашению'
              }}
            </template>
            <template #item.actions="{ item }">
              <v-btn
                icon="mdi-eye"
                variant="text"
                size="small"
                @click.stop="onRowClick({ item })"
              />
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="showCreateDialog" max-width="600">
      <v-card>
        <v-card-title>Создать конкурс</v-card-title>
        <v-card-text>
          <v-form ref="formRef" v-model="valid">
            <v-text-field
              v-model="newContest.title"
              label="Название"
              variant="outlined"
              required
              :rules="[(v) => !!v || 'Обязательное поле']"
            />
            <v-textarea
              v-model="newContest.description"
              label="Описание"
              variant="outlined"
              rows="3"
            />
            <v-select
              v-model="newContest.visibility_scope"
              :items="[
                { title: 'Все', value: 'all' },
                { title: 'Группа', value: 'group' },
                { title: 'По приглашению', value: 'invite' },
              ]"
              label="Видимость"
              variant="outlined"
              required
            />
            <v-text-field
              v-model="newContest.start_at"
              type="datetime-local"
              label="Начало"
              variant="outlined"
              required
            />
            <v-text-field
              v-model="newContest.end_at"
              type="datetime-local"
              label="Окончание"
              variant="outlined"
              required
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCreateDialog = false">Отмена</v-btn>
          <v-btn color="primary" :loading="creating" @click="createContest">Создать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { contestsApi, type ContestDTO } from '../api/contests'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const creating = ref(false)
const contests = ref<ContestDTO[]>([])
const showCreateDialog = ref(false)
const valid = ref(false)
const formRef = ref()

const filters = ref({
  active: null as boolean | null,
})

const newContest = ref({
  title: '',
  description: '',
  start_at: '',
  end_at: '',
  visibility_scope: 'all' as 'all' | 'group' | 'invite',
})

const canCreate = computed(() => {
  return auth.hasPermission('contests.manage') || auth.hasRole('admin')
})

const headers = [
  { title: 'Название', key: 'title' },
  { title: 'Начало', key: 'start_at' },
  { title: 'Окончание', key: 'end_at' },
  { title: 'Видимость', key: 'visibility_scope' },
  { title: 'Действия', key: 'actions', sortable: false },
]

const onRowClick = ({ item }: { item: ContestDTO }) => {
  router.push({ name: 'contest-detail', params: { id: item.id } })
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

const loadContests = async () => {
  loading.value = true
  try {
    const params: any = {}
    if (filters.value.active !== null) {
      params.active = filters.value.active
    }
    const res = await contestsApi.list(params)
    contests.value = res.data.data || []
  } catch (error) {
    console.error('Failed to load contests:', error)
  } finally {
    loading.value = false
  }
}

const createContest = async () => {
  const { valid: isValid } = await formRef.value.validate()
  if (!isValid) return

  creating.value = true
  try {
    await contestsApi.create(newContest.value)
    showCreateDialog.value = false
    newContest.value = {
      title: '',
      description: '',
      start_at: '',
      end_at: '',
      visibility_scope: 'all',
    }
    await loadContests()
  } catch (error) {
    console.error('Failed to create contest:', error)
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  loadContests()
})
</script>
