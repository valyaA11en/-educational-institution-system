<template>
  <div>
    <v-card v-if="document">
      <v-card-title>
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Документ №{{ document.number }}</span>
          <div>
            <v-btn
              color="primary"
              prepend-icon="mdi-download"
              @click="exportDocument('docx')"
              :loading="exporting === 'docx'"
            >
              Экспорт DOCX
            </v-btn>
            <v-btn
              color="primary"
              prepend-icon="mdi-file-pdf-box"
              @click="exportDocument('pdf')"
              :loading="exporting === 'pdf'"
              class="ml-2"
            >
              Экспорт PDF
            </v-btn>
          </div>
        </div>
      </v-card-title>
      <v-card-text>
        <v-alert v-if="pdfNotConfigured" type="warning" class="mb-4">
          Конвертация в PDF не настроена
        </v-alert>

        <v-row>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1">Основная информация</v-card-title>
              <v-card-text>
                <v-list>
                  <v-list-item>
                    <v-list-item-title>Тип</v-list-item-title>
                    <v-list-item-subtitle>{{ document.type }}</v-list-item-subtitle>
                  </v-list-item>
                  <v-list-item>
                    <v-list-item-title>Номер</v-list-item-title>
                    <v-list-item-subtitle>{{ document.number }}</v-list-item-subtitle>
                  </v-list-item>
                  <v-list-item>
                    <v-list-item-title>Дата</v-list-item-title>
                    <v-list-item-subtitle>{{ formatDate(document.date) }}</v-list-item-subtitle>
                  </v-list-item>
                  <v-list-item>
                    <v-list-item-title>Статус</v-list-item-title>
                    <v-list-item-subtitle>
                      <v-chip :color="getStatusColor(document.status)" size="small">
                        {{ getStatusText(document.status) }}
                      </v-chip>
                    </v-list-item-subtitle>
                  </v-list-item>
                  <v-list-item v-if="document.creator">
                    <v-list-item-title>Создатель</v-list-item-title>
                    <v-list-item-subtitle>{{ document.creator.fio }}</v-list-item-subtitle>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1">Данные документа</v-card-title>
              <v-card-text>
                <v-list v-if="document.data_json && Object.keys(document.data_json).length > 0">
                  <v-list-item
                    v-for="(value, key) in document.data_json"
                    :key="key"
                  >
                    <v-list-item-title>{{ formatKey(key) }}</v-list-item-title>
                    <v-list-item-subtitle>{{ formatValue(value) }}</v-list-item-subtitle>
                  </v-list-item>
                </v-list>
                <v-alert v-else type="info">Нет данных</v-alert>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card v-else-if="loading">
      <v-card-text>
        <v-progress-linear indeterminate />
      </v-card-text>
    </v-card>

    <v-card v-else>
      <v-card-text>
        <v-alert type="error">Документ не найден</v-alert>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { documentsApi, type DocumentDTO } from '../api/documents'

const route = useRoute()
const loading = ref(false)
const document = ref<DocumentDTO | null>(null)
const exporting = ref<'docx' | 'pdf' | null>(null)
const pdfNotConfigured = ref(false)

const loadDocument = async () => {
  const id = parseInt(route.params.id as string)
  if (!id) return

  loading.value = true
  try {
    document.value = await documentsApi.get(id)
  } catch (error) {
    console.error('Failed to load document:', error)
  } finally {
    loading.value = false
  }
}

const exportDocument = async (format: 'docx' | 'pdf') => {
  if (!document.value) return

  exporting.value = format
  pdfNotConfigured.value = false

  try {
    const blob = await documentsApi.export(document.value.id, format)
    
    // Create download link
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `document_${document.value.number}.${format}`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    console.error('Failed to export document:', error)
    if (error.response?.status === 501) {
      pdfNotConfigured.value = true
    } else {
      alert('Ошибка при экспорте документа')
    }
  } finally {
    exporting.value = null
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU')
}

const formatKey = (key: string) => {
  // Convert snake_case to Title Case
  return key
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const formatValue = (value: any) => {
  if (value === null || value === undefined) return '-'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    on_review: 'orange',
    approved: 'blue',
    signed: 'green',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    on_review: 'На проверке',
    approved: 'Утвержден',
    signed: 'Подписан',
    archived: 'Архив',
  }
  return texts[status] || status
}

onMounted(() => {
  loadDocument()
})
</script>

