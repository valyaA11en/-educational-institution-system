<template>
  <div>
    <v-card>
      <v-card-title>Сравнение версий</v-card-title>
      <v-card-text>
        <v-row class="mb-4">
          <v-col cols="12" md="5">
            <v-select
              v-model="fromVersionId"
              :items="versionOptions"
              label="Версия 1 (от)"
              variant="outlined"
              clearable
            >
              <template v-slot:item="{ item, props }">
                <v-list-item v-bind="props">
                  <template v-slot:prepend>
                    <v-chip :color="getStatusColor(item.raw.status)" size="small" class="mr-2">
                      {{ getStatusText(item.raw.status) }}
                    </v-chip>
                  </template>
                  <v-list-item-title>Версия #{{ item.raw.id }}</v-list-item-title>
                </v-list-item>
              </template>
            </v-select>
          </v-col>
          <v-col cols="12" md="5">
            <v-select
              v-model="toVersionId"
              :items="versionOptions"
              label="Версия 2 (до)"
              variant="outlined"
              clearable
            >
              <template v-slot:item="{ item, props }">
                <v-list-item v-bind="props">
                  <template v-slot:prepend>
                    <v-chip :color="getStatusColor(item.raw.status)" size="small" class="mr-2">
                      {{ getStatusText(item.raw.status) }}
                    </v-chip>
                  </template>
                  <v-list-item-title>Версия #{{ item.raw.id }}</v-list-item-title>
                </v-list-item>
              </template>
            </v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-btn
              color="primary"
              block
              @click="compareVersions"
              :loading="loading"
              :disabled="!fromVersionId || !toVersionId"
            >
              Сравнить
            </v-btn>
          </v-col>
        </v-row>

        <v-alert v-if="!fromVersionId || !toVersionId" type="info">
          Выберите две версии для сравнения
        </v-alert>

        <div v-if="diff">
          <!-- Added Items -->
          <v-card variant="outlined" class="mb-4">
            <v-card-title class="text-subtitle-1 bg-success-lighten-5">
              Добавлено: {{ diff.added.length }}
            </v-card-title>
            <v-card-text>
              <v-list v-if="diff.added.length > 0">
                <v-list-item
                  v-for="item in diff.added"
                  :key="item.id"
                  class="border-b"
                >
                  <v-list-item-title>{{ formatItem(item) }}</v-list-item-title>
                  <v-list-item-subtitle>
                    {{ item.date }} - {{ item.group?.name }} - {{ item.subject?.name }}
                    <span v-if="item.teacher"> - {{ item.teacher.fio }}</span>
                    <span v-if="item.room"> - {{ item.room.name }}</span>
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
              <v-alert v-else type="info" variant="tonal">Нет добавленных элементов</v-alert>
            </v-card-text>
          </v-card>

          <!-- Removed Items -->
          <v-card variant="outlined" class="mb-4">
            <v-card-title class="text-subtitle-1 bg-error-lighten-5">
              Удалено: {{ diff.removed.length }}
            </v-card-title>
            <v-card-text>
              <v-list v-if="diff.removed.length > 0">
                <v-list-item
                  v-for="item in diff.removed"
                  :key="item.id"
                  class="border-b"
                >
                  <v-list-item-title>{{ formatItem(item) }}</v-list-item-title>
                  <v-list-item-subtitle>
                    {{ item.date }} - {{ item.group?.name }} - {{ item.subject?.name }}
                    <span v-if="item.teacher"> - {{ item.teacher.fio }}</span>
                    <span v-if="item.room"> - {{ item.room.name }}</span>
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
              <v-alert v-else type="info" variant="tonal">Нет удаленных элементов</v-alert>
            </v-card-text>
          </v-card>

          <!-- Changed Items -->
          <v-card variant="outlined">
            <v-card-title class="text-subtitle-1 bg-warning-lighten-5">
              Изменено: {{ diff.changed.length }}
            </v-card-title>
            <v-card-text>
              <v-list v-if="diff.changed.length > 0">
                <v-list-item
                  v-for="(change, index) in diff.changed"
                  :key="index"
                  class="border-b"
                >
                  <v-list-item-title>{{ formatItem(change.to) }}</v-list-item-title>
                  <v-expansion-panels>
                    <v-expansion-panel>
                      <v-expansion-panel-title>Показать изменения</v-expansion-panel-title>
                      <v-expansion-panel-text>
                        <v-row>
                          <v-col cols="6">
                            <div class="text-caption font-weight-bold mb-2">Было:</div>
                            <pre class="text-caption pa-2 bg-grey-lighten-4 rounded">{{ JSON.stringify(change.from, null, 2) }}</pre>
                          </v-col>
                          <v-col cols="6">
                            <div class="text-caption font-weight-bold mb-2">Стало:</div>
                            <pre class="text-caption pa-2 bg-grey-lighten-4 rounded">{{ JSON.stringify(change.to, null, 2) }}</pre>
                          </v-col>
                        </v-row>
                      </v-expansion-panel-text>
                    </v-expansion-panel>
                  </v-expansion-panels>
                </v-list-item>
              </v-list>
              <v-alert v-else type="info" variant="tonal">Нет измененных элементов</v-alert>
            </v-card-text>
          </v-card>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { scheduleApi, type ScheduleVersionDTO, type ScheduleItemDTO } from '../api/schedule'

const loading = ref(false)
const versions = ref<ScheduleVersionDTO[]>([])
const fromVersionId = ref<number | null>(null)
const toVersionId = ref<number | null>(null)
const diff = ref<{
  added: ScheduleItemDTO[]
  removed: ScheduleItemDTO[]
  changed: Array<{ from: ScheduleItemDTO; to: ScheduleItemDTO }>
} | null>(null)

const versionOptions = computed(() => {
  return versions.value.map(v => ({
    title: `Версия #${v.id} (${getStatusText(v.status)})`,
    value: v.id,
    raw: v,
  }))
})

const loadVersions = async () => {
  try {
    const response = await scheduleApi.getVersions({ per_page: 100 })
    versions.value = response.data
  } catch (error) {
    console.error('Failed to load versions:', error)
  }
}

const compareVersions = async () => {
  if (!fromVersionId.value || !toVersionId.value) return

  loading.value = true
  try {
    const result = await scheduleApi.getDiff({
      fromVersionId: fromVersionId.value,
      toVersionId: toVersionId.value,
    })
    diff.value = result
  } catch (error) {
    console.error('Failed to compare versions:', error)
  } finally {
    loading.value = false
  }
}

const formatItem = (item: ScheduleItemDTO) => {
  const parts = []
  if (item.date) parts.push(item.date)
  if (item.group?.name) parts.push(item.group.name)
  if (item.subject?.name) parts.push(item.subject.name)
  return parts.join(' - ')
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    published: 'success',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    published: 'Опубликовано',
    archived: 'Архив',
  }
  return texts[status] || status
}

onMounted(() => {
  loadVersions()
})
</script>

<style scoped>
.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}
</style>


