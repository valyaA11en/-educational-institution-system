<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <h1>Портфолио</h1>
            <p v-if="student" class="text-subtitle-1">{{ student.fio }}</p>
          </div>
          <div class="d-flex" style="gap: 8px;">
            <v-btn
              color="primary"
              :loading="exporting"
              @click="exportPDF"
              prepend-icon="mdi-file-pdf-box"
            >
              Экспорт портфолио
            </v-btn>
            <v-btn
              color="primary"
              variant="outlined"
              :loading="exportingAll"
              @click="exportAll"
              prepend-icon="mdi-file-document-multiple"
            >
              Экспорт всего
            </v-btn>
          </div>
        </div>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-title>Фильтры</v-card-title>
          <v-card-text>
            <v-select
              v-model="filters.type"
              :items="typeOptions"
              label="Тип"
              clearable
              chips
            ></v-select>
            <v-checkbox
              v-model="filters.is_featured"
              label="Только избранное"
              class="mt-4"
            ></v-checkbox>
            <v-btn
              color="primary"
              block
              class="mt-4"
              @click="loadPortfolio"
            >
              Применить
            </v-btn>
            <v-btn
              variant="outlined"
              block
              class="mt-2"
              @click="resetFilters"
            >
              Сбросить
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="9">
        <div v-if="loading" class="text-center py-8">
          <v-progress-circular indeterminate size="64"></v-progress-circular>
        </div>

        <div v-else-if="portfolio.length === 0" class="text-center py-8">
          <v-alert type="info">Портфолио пусто</v-alert>
        </div>

        <v-row v-else>
          <v-col
            v-for="item in portfolio"
            :key="item.id"
            cols="12"
            sm="6"
            md="4"
          >
            <v-card
              :class="{ 'featured-card': item.is_featured }"
              @click="viewItem(item)"
              style="cursor: pointer"
            >
              <v-card-title class="d-flex align-center">
                <v-icon :color="getTypeColor(item.type)" class="mr-2">
                  {{ getTypeIcon(item.type) }}
                </v-icon>
                <span class="text-truncate">{{ item.title }}</span>
                <v-spacer></v-spacer>
                <v-btn
                  v-if="canToggleFeatured"
                  icon
                  size="small"
                  :color="item.is_featured ? 'amber' : 'grey'"
                  @click.stop="toggleFeatured(item)"
                >
                  <v-icon>{{ item.is_featured ? 'mdi-star' : 'mdi-star-outline' }}</v-icon>
                </v-btn>
              </v-card-title>
              <v-card-subtitle>
                <v-chip size="small" :color="getTypeColor(item.type)">
                  {{ getTypeLabel(item.type) }}
                </v-chip>
                <span class="ml-2 text-caption">
                  {{ formatDate(item.created_at) }}
                </span>
              </v-card-subtitle>
              <v-card-text>
                <p v-if="item.description" class="text-body-2">
                  {{ truncateText(item.description, 100) }}
                </p>
                <v-chip v-if="item.is_featured" size="small" color="amber" class="mt-2">
                  Избранное
                </v-chip>
              </v-card-text>
              <v-card-actions v-if="item.file_id">
                <v-btn
                  size="small"
                  prepend-icon="mdi-file"
                  @click.stop="viewFile(item)"
                >
                  Просмотр файла
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </v-col>
    </v-row>

    <!-- Диалог просмотра элемента -->
    <v-dialog v-model="viewDialog" max-width="800">
      <v-card v-if="selectedItem">
        <v-card-title class="d-flex align-center">
          <v-icon :color="getTypeColor(selectedItem.type)" class="mr-2">
            {{ getTypeIcon(selectedItem.type) }}
          </v-icon>
          {{ selectedItem.title }}
        </v-card-title>
        <v-card-subtitle>
          <v-chip size="small" :color="getTypeColor(selectedItem.type)">
            {{ getTypeLabel(selectedItem.type) }}
          </v-chip>
          <span class="ml-2">{{ formatDate(selectedItem.created_at) }}</span>
        </v-card-subtitle>
        <v-card-text>
          <p v-if="selectedItem.description" class="text-body-1 mb-4">
            {{ selectedItem.description }}
          </p>
          <div v-if="selectedItem.file_id" class="mt-4">
            <v-btn
              color="primary"
              prepend-icon="mdi-download"
              @click="downloadFile(selectedItem)"
            >
              Скачать файл
            </v-btn>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="viewDialog = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { studentPortfolioApi, type StudentPortfolioItem } from '@/api/studentPortfolio'
import { usersApi } from '@/api/users'
import { studentExportApi } from '@/api/studentExport'

const route = useRoute()
const studentId = computed(() => parseInt(route.params.id as string))

const portfolio = ref<StudentPortfolioItem[]>([])
const student = ref<any>(null)
const loading = ref(false)
const exporting = ref(false)
const exportingAll = ref(false)
const viewDialog = ref(false)
const selectedItem = ref<StudentPortfolioItem | null>(null)

const typeOptions = [
  { title: 'Задание', value: 'assignment' },
  { title: 'Конкурс', value: 'contest' },
  { title: 'Сертификат', value: 'certificate' },
  { title: 'Достижение', value: 'achievement' },
]

const canToggleFeatured = computed(() => {
  // Студент может изменять is_featured для своих элементов
  // TODO: Проверить права доступа
  return true
})

onMounted(async () => {
  await Promise.all([loadStudent(), loadPortfolio()])
})

async function loadStudent() {
  try {
    const response = await usersApi.get(studentId.value)
    student.value = response
  } catch (error) {
    console.error('Failed to load student:', error)
  }
}

async function loadPortfolio() {
  loading.value = true
  try {
    const params: any = {}
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.is_featured) params.is_featured = true

    const response = await studentPortfolioApi.getPortfolio(studentId.value, params)
    portfolio.value = response.data.data
  } catch (error) {
    console.error('Failed to load portfolio:', error)
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = {
    type: null,
    is_featured: false,
  }
  loadPortfolio()
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function truncateText(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text
  return text.substring(0, maxLength) + '...'
}

function getTypeIcon(type: string): string {
  const icons: Record<string, string> = {
    assignment: 'mdi-file-document',
    contest: 'mdi-trophy',
    certificate: 'mdi-certificate',
    achievement: 'mdi-medal',
  }
  return icons[type] || 'mdi-file'
}

function getTypeColor(type: string): string {
  const colors: Record<string, string> = {
    assignment: 'blue',
    contest: 'amber',
    certificate: 'green',
    achievement: 'purple',
  }
  return colors[type] || 'grey'
}

function getTypeLabel(type: string): string {
  const item = typeOptions.find((o) => o.value === type)
  return item?.title || type
}

function viewItem(item: StudentPortfolioItem) {
  selectedItem.value = item
  viewDialog.value = true
}

async function toggleFeatured(item: StudentPortfolioItem) {
  try {
    await studentPortfolioApi.updateItem(studentId.value, item.id, {
      is_featured: !item.is_featured,
    })
    item.is_featured = !item.is_featured
  } catch (error) {
    console.error('Failed to toggle featured:', error)
  }
}

function viewFile(item: StudentPortfolioItem) {
  if (item.file_id) {
    // TODO: Реализовать просмотр файла
    window.open(`/api/v1/files/${item.file_id}`, '_blank')
  }
}

function downloadFile(item: StudentPortfolioItem) {
  if (item.file_id) {
    window.open(`/api/v1/files/${item.file_id}/download`, '_blank')
  }
}

async function exportPDF() {
  exporting.value = true
  try {
    await studentPortfolioApi.exportPortfolio(studentId.value)
  } catch (error) {
    console.error('Failed to export portfolio PDF:', error)
  } finally {
    exporting.value = false
  }
}

async function exportAll() {
  exportingAll.value = true
  try {
    await studentExportApi.exportStudent(studentId.value)
  } catch (error) {
    console.error('Failed to export all:', error)
  } finally {
    exportingAll.value = false
  }
}
</script>

<style scoped>
.featured-card {
  border: 2px solid rgb(255, 193, 7);
}
</style>

