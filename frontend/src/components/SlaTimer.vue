<template>
  <div :class="['sla-timer', size]">
    <v-chip
      :color="timerColor"
      :size="size"
      :variant="isOverdue ? 'flat' : 'tonal'"
    >
      <v-icon start :size="size === 'small' ? '14' : '16'">
        {{ isOverdue ? 'mdi-alert-circle' : 'mdi-clock-outline' }}
      </v-icon>
      {{ label }}: {{ displayTime }}
    </v-chip>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'

interface Props {
  dueAt: string | null
  respondedAt?: string | null
  resolvedAt?: string | null
  label: string
  size?: 'small' | 'default'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'default',
})

const now = ref(new Date())

let interval: number | null = null

const updateNow = () => {
  now.value = new Date()
}

onMounted(() => {
  interval = window.setInterval(updateNow, 60000) // Update every minute
})

onUnmounted(() => {
  if (interval) clearInterval(interval)
})

const dueDate = computed(() => {
  if (!props.dueAt) return null
  return new Date(props.dueAt)
})

const isOverdue = computed(() => {
  if (!dueDate.value) return false
  if (props.respondedAt || props.resolvedAt) return false // Already responded/resolved
  return now.value > dueDate.value
})

const timeRemaining = computed(() => {
  if (!dueDate.value) return null
  if (props.respondedAt || props.resolvedAt) {
    // Show time taken
    const completedAt = props.resolvedAt ? new Date(props.resolvedAt) : new Date(props.respondedAt!)
    const diff = dueDate.value.getTime() - completedAt.getTime()
    const wasOnTime = diff >= 0
    return {
      value: Math.abs(diff),
      wasOnTime,
    }
  }
  // Show time remaining
  const diff = dueDate.value.getTime() - now.value.getTime()
  return {
    value: diff,
    wasOnTime: null,
  }
})

const displayTime = computed(() => {
  if (!timeRemaining.value) return '-'
  
  const { value, wasOnTime } = timeRemaining.value
  const absValue = Math.abs(value)
  
  const hours = Math.floor(absValue / (1000 * 60 * 60))
  const minutes = Math.floor((absValue % (1000 * 60 * 60)) / (1000 * 60))
  
  if (wasOnTime === null) {
    // Time remaining
    if (isOverdue.value) {
      return `+${hours}ч ${minutes}м`
    }
    return `${hours}ч ${minutes}м`
  } else if (wasOnTime) {
    // Was on time
    return `✓ ${hours}ч ${minutes}м`
  } else {
    // Was late
    return `+${hours}ч ${minutes}м`
  }
})

const timerColor = computed(() => {
  if (!timeRemaining.value) return 'grey'
  
  const { wasOnTime } = timeRemaining.value
  
  if (wasOnTime === null) {
    // Time remaining
    if (isOverdue.value) return 'error'
    const hours = Math.abs(timeRemaining.value.value) / (1000 * 60 * 60)
    if (hours < 2) return 'error'
    if (hours < 4) return 'warning'
    return 'success'
  } else if (wasOnTime) {
    // Was on time
    return 'success'
  } else {
    // Was late
    return 'error'
  }
})
</script>

<style scoped>
.sla-timer {
  display: inline-block;
}
</style>


