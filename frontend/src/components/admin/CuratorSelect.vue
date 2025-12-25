<template>
  <v-select
    v-model="modelValue"
    :items="curatorOptions"
    :label="label"
    :rules="rules"
    variant="outlined"
    :loading="loading"
    clearable
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { referencesApi } from '../../api/references'
import type { UserDTO } from '../../api/users'

interface Props {
  modelValue: number | null
  label?: string
  rules?: any[]
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Куратор',
  rules: () => [],
})

defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const loading = ref(false)
const curators = ref<UserDTO[]>([])
const curatorOptions = ref<{ title: string; value: number }[]>([])

const loadCurators = async () => {
  loading.value = true
  try {
    curators.value = await referencesApi.listCurators()
    curatorOptions.value = curators.value.map((c) => ({
      title: c.fio,
      value: c.id,
    }))
  } catch (error) {
    console.error('Failed to load curators:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadCurators()
})
</script>

