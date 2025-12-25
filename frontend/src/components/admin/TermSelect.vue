<template>
  <v-select
    v-model="modelValue"
    :items="termOptions"
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
import { referencesApi, type TermDTO } from '../../api/references'

interface Props {
  modelValue: number | null
  label?: string
  rules?: any[]
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Семестр',
  rules: () => [],
})

defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const loading = ref(false)
const terms = ref<TermDTO[]>([])
const termOptions = ref<{ title: string; value: number }[]>([])

const loadTerms = async () => {
  loading.value = true
  try {
    terms.value = await referencesApi.listTerms()
    termOptions.value = terms.value.map((t) => ({
      title: t.name,
      value: t.id,
    }))
  } catch (error) {
    console.error('Failed to load terms:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadTerms()
})
</script>

