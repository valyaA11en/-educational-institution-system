<template>
  <v-card>
    <v-card-title class="d-flex justify-space-between align-center">
      <span>Журнал</span>
      <div class="d-flex gap-2">
      <v-btn
        color="success"
        prepend-icon="mdi-file-excel"
        @click="exportGradeChanges"
        :loading="exporting"
        :disabled="readOnly.isEnabled"
      >
        Export Grade Changes
      </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-printer"
          @click="printJournal"
          :loading="printing"
        >
          Печать журнала
        </v-btn>
      </div>
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
import { printApi } from '../../../api/print'
import { useReadOnlyStore } from '../../../stores/readOnly'
import { useToast } from '../../../composables/useToast'

const { showToast } = useToast()
const readOnly = useReadOnlyStore()
const exporting = ref(false)
const printing = ref(false)

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

const printJournal = async () => {
  // TODO: Get current filter values (groupId, subjectId, termId)
  // For now, prompt user for groupId
  const groupId = prompt('Введите ID группы:')
  if (!groupId) return

  printing.value = true
  try {
    const blob = await printApi.journal({
      groupId: Number(groupId),
      format: 'pdf',
    })

    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `journal_group_${groupId}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
    showToast('PDF сгенерирован', 'success')
  } catch (error: any) {
    console.error('Failed to print journal:', error)
    showToast(error.response?.data?.message || 'Ошибка при генерации PDF', 'error')
  } finally {
    printing.value = false
  }
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>


