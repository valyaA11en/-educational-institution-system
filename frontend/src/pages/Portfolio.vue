<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1>Портфолио</h1>
        <v-btn color="primary" @click="showDialog = true">Добавить</v-btn>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12" md="4" v-for="item in items" :key="item.id">
        <v-card>
          <v-card-title>{{ item.title }}</v-card-title>
          <v-card-subtitle>{{ formatDate(item.date) }} - {{ item.type }}</v-card-subtitle>
          <v-card-text>
            <p>{{ item.description }}</p>
            <v-chip v-if="item.is_public" color="success" small>Публичное</v-chip>
          </v-card-text>
          <v-card-actions>
            <v-btn @click="editItem(item)">Редактировать</v-btn>
            <v-btn @click="deleteItem(item.id)" color="error">Удалить</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="showDialog" max-width="600">
      <v-card>
        <v-card-title>Добавить в портфолио</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.title" label="Название" required></v-text-field>
          <v-textarea v-model="form.description" label="Описание"></v-textarea>
          <v-select v-model="form.type" :items="types" label="Тип"></v-select>
          <v-date-picker v-model="form.date"></v-date-picker>
          <v-file-input v-model="form.file" label="Файл"></v-file-input>
          <v-checkbox v-model="form.is_public" label="Публичное"></v-checkbox>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDialog = false">Отмена</v-btn>
          <v-btn @click="saveItem" color="primary">Сохранить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { portfolioApi, type PortfolioItem, type CreatePortfolioItem } from '@/api/portfolio'

const items = ref<PortfolioItem[]>([])
const showDialog = ref(false)
const form = ref<CreatePortfolioItem & { file?: File }>({
  type: 'achievement',
  title: '',
  description: '',
  date: new Date().toISOString().split('T')[0],
  is_public: false,
})
const types = ['achievement', 'project', 'certificate', 'contest', 'other']

onMounted(async () => {
  await loadItems()
})

async function loadItems() {
  try {
    const response = await portfolioApi.getItems()
    items.value = response.data.data
  } catch (error) {
    console.error('Failed to load portfolio:', error)
  }
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('ru-RU')
}

async function saveItem() {
  try {
    await portfolioApi.createItem(form.value)
    showDialog.value = false
    form.value = {
      type: 'achievement',
      title: '',
      description: '',
      date: new Date().toISOString().split('T')[0],
      is_public: false,
    }
    await loadItems()
  } catch (error) {
    console.error('Failed to save item:', error)
  }
}

function editItem(item: PortfolioItem) {
  form.value = {
    type: item.type,
    title: item.title,
    description: item.description || '',
    date: item.date,
    is_public: item.is_public,
  }
  showDialog.value = true
}

async function deleteItem(id: number) {
  if (!confirm('Удалить элемент?')) return
  try {
    await portfolioApi.deleteItem(id)
    await loadItems()
  } catch (error) {
    console.error('Failed to delete item:', error)
  }
}
</script>


