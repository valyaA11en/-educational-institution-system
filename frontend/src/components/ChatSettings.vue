<template>
  <v-dialog :model-value="modelValue" max-width="600" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title>Настройки чата</v-card-title>
      <v-card-text>
        <v-form ref="formRef">
          <v-select
            v-model="settings.mode"
            :items="modeOptions"
            label="Режим чата"
            variant="outlined"
            class="mb-3"
          />

          <v-switch
            v-model="settings.attachments_enabled"
            label="Разрешить вложения"
            class="mb-3"
          />

          <div class="mb-3">
            <div class="text-subtitle-2 mb-2">Тихие часы</div>
            <div v-for="(period, index) in quietHours" :key="index" class="d-flex align-center gap-2 mb-2">
              <v-text-field
                v-model="period.start"
                label="Начало"
                type="time"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 150px"
              />
              <v-text-field
                v-model="period.end"
                label="Конец"
                type="time"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 150px"
              />
              <v-btn
                icon="mdi-delete"
                size="small"
                variant="text"
                color="error"
                @click="removeQuietHour(index)"
              />
            </div>
            <v-btn
              prepend-icon="mdi-plus"
              variant="outlined"
              size="small"
              @click="addQuietHour"
            >
              Добавить период
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="$emit('update:modelValue', false)">Отмена</v-btn>
        <v-btn color="primary" @click="saveSettings" :loading="saving">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { chatApi, type ChatThreadSettingsDTO } from '../api/chat'
import { useToast } from '../composables/useToast'

const props = defineProps<{
  modelValue: boolean
  threadId: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'settings-updated': [settings: ChatThreadSettingsDTO]
}>()

const { showToast } = useToast()

const formRef = ref()
const saving = ref(false)
const settings = ref<{
  mode: 'standard' | 'announcements'
  attachments_enabled: boolean
  quiet_hours: Array<{ start: string; end: string }> | null
}>({
  mode: 'standard',
  attachments_enabled: true,
  quiet_hours: null,
})

const quietHours = computed({
  get: () => {
    if (!settings.value.quiet_hours || settings.value.quiet_hours.length === 0) {
      return [{ start: '22:00', end: '08:00' }]
    }
    return settings.value.quiet_hours
  },
  set: (value) => {
    settings.value.quiet_hours = value.length > 0 ? value : null
  },
})

const modeOptions = [
  { title: 'Стандартный', value: 'standard' },
  { title: 'Объявления', value: 'announcements' },
]

const loadSettings = async () => {
  if (!props.threadId) return

  try {
    const loaded = await chatApi.getSettings(props.threadId)
    if (loaded) {
      settings.value = {
        mode: loaded.mode,
        attachments_enabled: loaded.attachments_enabled,
        quiet_hours: loaded.quiet_hours,
      }
    } else {
      // Default settings
      settings.value = {
        mode: 'standard',
        attachments_enabled: true,
        quiet_hours: null,
      }
    }
  } catch (error) {
    console.error('Failed to load settings:', error)
    settings.value = {
      mode: 'standard',
      attachments_enabled: true,
      quiet_hours: null,
    }
  }
}

const addQuietHour = () => {
  if (!settings.value.quiet_hours) {
    settings.value.quiet_hours = []
  }
  settings.value.quiet_hours.push({ start: '22:00', end: '08:00' })
}

const removeQuietHour = (index: number) => {
  if (settings.value.quiet_hours) {
    settings.value.quiet_hours.splice(index, 1)
    if (settings.value.quiet_hours.length === 0) {
      settings.value.quiet_hours = null
    }
  }
}

const saveSettings = async () => {
  if (!props.threadId) return

  saving.value = true
  try {
    const updated = await chatApi.updateSettings(props.threadId, {
      mode: settings.value.mode,
      attachments_enabled: settings.value.attachments_enabled,
      quiet_hours: settings.value.quiet_hours,
    })
    showToast('Настройки сохранены', 'success')
    emit('settings-updated', updated)
    emit('update:modelValue', false)
  } catch (error: any) {
    console.error('Failed to save settings:', error)
    showToast(error.response?.data?.message || 'Ошибка сохранения настроек', 'error')
  } finally {
    saving.value = false
  }
}

watch(() => props.modelValue, (show) => {
  if (show) {
    loadSettings()
  }
})

watch(() => props.threadId, () => {
  if (props.modelValue) {
    loadSettings()
  }
})
</script>









