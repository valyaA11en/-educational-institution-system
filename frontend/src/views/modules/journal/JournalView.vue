<template>
  <v-card>
    <v-card-title class="d-flex justify-space-between align-center">
      <span>Журнал</span>
      <v-btn
        color="success"
        prepend-icon="mdi-file-excel"
        @click="exportGradeChanges"
        :loading="exporting"
      >
        Export Grade Changes
      </v-btn>
    </v-card-title>
    <v-card-text>
      <!-- TODO: реализовать модуль журнала -->
      <p>Journal module placeholder.</p>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { journalApi } from '../../../api/journal'

const exporting = ref(false)

const exportGradeChanges = async () => {
  exporting.value = true
  try {
    await journalApi.exportGradeChanges()
  } catch (error) {
    console.error('Failed to export grade changes:', error)
  } finally {
    exporting.value = false
  }
}
</script>


