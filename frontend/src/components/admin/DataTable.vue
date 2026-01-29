<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" :md="additionalFilters ? 6 : 6">
        <v-text-field
          v-model="searchQuery"
          :label="searchLabel || 'Поиск'"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          clearable
          @update:model-value="onSearch"
        />
      </v-col>
      <v-col v-if="additionalFilters" cols="12" md="3">
        <slot name="filters" />
      </v-col>
      <v-col cols="12" :md="additionalFilters ? 3 : 6" class="text-right">
        <v-btn v-if="showCreateButton" color="primary" @click="$emit('create')">
          <v-icon start>mdi-plus</v-icon>
          {{ createButtonText || 'Создать' }}
        </v-btn>
      </v-col>
    </v-row>

    <v-data-table
      :headers="headers"
      :items="items"
      :loading="loading"
      :items-per-page="pagination.per_page"
      :page="pagination.current_page"
      :server-items-length="pagination.total"
      @update:page="onPageChange"
    >
      <template v-for="(_, slot) in $slots" :key="slot" v-slot:[slot]="props">
        <slot :name="slot" v-bind="props" />
      </template>
    </v-data-table>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  headers: any[]
  items: any[]
  loading?: boolean
  pagination: {
    current_page: number
    per_page: number
    total: number
  }
  searchLabel?: string
  showCreateButton?: boolean
  createButtonText?: string
  additionalFilters?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
  showCreateButton: true,
  additionalFilters: false,
})

const emit = defineEmits<{
  search: [value: string]
  pageChange: [page: number]
  create: []
}>()

const searchQuery = ref('')

let searchTimeout: ReturnType<typeof setTimeout> | null = null

const onSearch = (value: string) => {
  searchQuery.value = value || ''
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  searchTimeout = setTimeout(() => {
    emit('search', searchQuery.value)
  }, 300)
}

const onPageChange = (page: number) => {
  emit('pageChange', page)
}
</script>

