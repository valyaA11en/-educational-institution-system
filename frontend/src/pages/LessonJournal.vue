<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <div>
          <div class="text-h6">Журнал урока</div>
          <div v-if="lessonInfo" class="text-caption text-grey">
            {{ formatDate(lessonInfo.date) }} • Группа: {{ lessonInfo.groupId }} • Предмет: {{ lessonInfo.subjectId }}
          </div>
        </div>
        <v-btn
          icon="mdi-arrow-left"
          variant="text"
          @click="$router.back()"
        >
        </v-btn>
      </v-card-title>

      <v-card-text>
        <JournalGrid :lesson-id="lessonId" />
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import JournalGrid from '@/components/JournalGrid.vue'
import { journalApi, type JournalGridDTO } from '@/api/journal'

const route = useRoute()

const lessonId = computed(() => Number(route.params.id))
const lessonInfo = ref<JournalGridDTO['lesson'] | null>(null)

const loadLessonInfo = async () => {
  try {
    const data = await journalApi.getGrid(lessonId.value)
    lessonInfo.value = data.lesson
  } catch (error) {
    console.error('Failed to load lesson info:', error)
  }
}

const formatDate = (dateStr: string) => {
  const date = new Date(dateStr)
  return date.toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

onMounted(() => {
  loadLessonInfo()
})
</script>

<style scoped>
.text-caption {
  margin-top: 4px;
}
</style>
