<template>
  <v-card class="h-100 d-flex flex-column">
    <v-card-title v-if="chat.currentThread">
      {{ chat.currentThread.name }}
      <!-- Индикатор "печатает" (каркас) -->
      <span v-if="typingLabel" class="text-caption text-grey ml-2">
        {{ typingLabel }}
      </span>
    </v-card-title>
    <v-card-title v-else>
      Выберите чат
    </v-card-title>

    <v-divider />

    <v-card-text ref="messagesContainer" class="flex-grow-1 overflow-y-auto">
      <v-list v-if="chat.currentThread">
        <v-list-item
          v-for="message in chat.currentMessages"
          :key="message.id"
          class="message-item"
        >
          <template #prepend>
            <v-avatar size="32">
              <v-icon>mdi-account</v-icon>
            </v-avatar>
          </template>
          <v-list-item-title>{{ message.text }}</v-list-item-title>
          <v-list-item-subtitle>{{ formatDate(message.createdAt) }}</v-list-item-subtitle>
          <template #append>
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                />
              </template>
              <v-list>
                <v-list-item @click="showReportDialog(message)">
                  <v-list-item-title>Пожаловаться</v-list-item-title>
                  <v-list-item-prepend>
                    <v-icon>mdi-alert-circle</v-icon>
                  </v-list-item-prepend>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-list-item>
      </v-list>
      <div v-else class="text-center text-grey mt-4">
        Выберите чат для просмотра сообщений
      </div>
    </v-card-text>

    <v-divider />

    <v-card-actions v-if="chat.currentThread" class="align-center">
      <v-text-field
        v-model="newMessage"
        label="Сообщение"
        hide-details
        density="compact"
        class="flex-grow-1 mr-2"
        :disabled="isQuietHours || isAnnouncementsMode"
        @keyup.enter.exact.prevent="onSend"
      />
      <!-- Вложения (каркас, без реальной загрузки) -->
      <v-btn
        icon="mdi-paperclip"
        variant="text"
        class="mr-1"
        :disabled="!attachmentsEnabled || isQuietHours || isAnnouncementsMode"
        @click="onAttachClick"
      />
      <v-btn
        :loading="chat.sending"
        icon="mdi-send"
        color="primary"
        :disabled="isQuietHours || isAnnouncementsMode"
        @click="onSend"
      />
      <v-btn
        icon="mdi-cog"
        variant="text"
        @click="showSettingsDialog = true"
        v-if="canModerate"
      />
    </v-card-actions>

    <!-- Report Dialog -->
    <v-dialog v-model="showReportDialogVisible" max-width="500">
      <v-card>
        <v-card-title>Пожаловаться на сообщение</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="reportReason"
            label="Причина жалобы"
            variant="outlined"
            rows="4"
            :rules="[(v: string) => !!v || 'Укажите причину жалобы']"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showReportDialogVisible = false">Отмена</v-btn>
          <v-btn color="primary" @click="submitReport" :loading="reporting">Отправить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Settings Dialog -->
    <ChatSettings
      v-model="showSettingsDialog"
      :thread-id="chat.currentThreadId"
      @settings-updated="onSettingsUpdated"
    />
  </v-card>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useChatStore } from '../stores/chat'
import { useAuthStore } from '../stores/auth'
import { chatApi, type ChatMessageDTO, type ChatThreadSettingsDTO } from '../api/chat'
import { useToast } from '../composables/useToast'
import { useEcho } from '../composables/useEcho'
import ChatSettings from './ChatSettings.vue'

const chat = useChatStore()
const auth = useAuthStore()
const { showToast } = useToast()

const newMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)
const showReportDialogVisible = ref(false)
const reportReason = ref('')
const reporting = ref(false)
const selectedMessageForReport = ref<ChatMessageDTO | null>(null)
const showSettingsDialog = ref(false)
const threadSettings = ref<ChatThreadSettingsDTO | null>(null)

const typingLabel = computed(() => {
  const threadId = chat.currentThreadId
  if (!threadId) return ''
  const list = chat.typingUsers[threadId] || []
  if (!list.length) return ''
  // TODO: реальная логика "печатает"
  return `${list.join(', ')} печатает...`
})

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const onSend = async () => {
  if (!newMessage.value.trim()) return
  await chat.sendMessage(newMessage.value)
  newMessage.value = ''
  scrollToBottom()
}

const onAttachClick = () => {
  // TODO: открыть диалог выбора файлов и отправить вместе с сообщением
  // Пока только заглушка
  console.log('Attach files: TODO')
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const showReportDialog = (message: ChatMessageDTO) => {
  selectedMessageForReport.value = message
  reportReason.value = ''
  showReportDialogVisible.value = true
}

const submitReport = async () => {
  if (!selectedMessageForReport.value || !chat.currentThreadId || !reportReason.value.trim()) {
    return
  }

  reporting.value = true
  try {
    await chatApi.reportMessage(
      chat.currentThreadId,
      selectedMessageForReport.value.id,
      reportReason.value,
    )
    showToast('Жалоба отправлена', 'success')
    showReportDialogVisible.value = false
    reportReason.value = ''
    selectedMessageForReport.value = null
  } catch (error: any) {
    console.error('Failed to report message:', error)
    showToast(error.response?.data?.message || 'Ошибка отправки жалобы', 'error')
  } finally {
    reporting.value = false
  }
}

const canModerate = computed(() => {
  if (!chat.currentThreadId) return false
  // Check if user has chat.moderate permission or is moderator in thread
  return auth.hasPermission('chat.moderate')
})

const isQuietHours = computed(() => {
  if (!threadSettings.value || !threadSettings.value.quiet_hours) return false
  if (canModerate.value) return false // Moderators can always write

  const now = new Date()
  const currentTime = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`

  for (const period of threadSettings.value.quiet_hours) {
    const start = period.start
    const end = period.end

    if (start <= end) {
      if (currentTime >= start && currentTime <= end) {
        return true
      }
    } else {
      // Crosses midnight
      if (currentTime >= start || currentTime <= end) {
        return true
      }
    }
  }

  return false
})

const isAnnouncementsMode = computed(() => {
  if (!threadSettings.value) return false
  return threadSettings.value.mode === 'announcements' && !canModerate.value
})

const attachmentsEnabled = computed(() => {
  return threadSettings.value?.attachments_enabled !== false
})

const loadSettings = async () => {
  if (!chat.currentThreadId) {
    threadSettings.value = null
    return
  }

  try {
    threadSettings.value = await chatApi.getSettings(chat.currentThreadId)
  } catch (error) {
    console.error('Failed to load settings:', error)
    threadSettings.value = null
  }
}

const onSettingsUpdated = (settings: ChatThreadSettingsDTO) => {
  threadSettings.value = settings
}

watch(() => chat.currentThreadId, () => {
  loadSettings()
})

// Realtime subscription for settings changes
const echo = useEcho()
let settingsChannel: any = null

const subscribeToSettings = () => {
  if (!echo || !chat.currentThreadId) return

  settingsChannel = echo.private(`user.${auth.user?.id}`)
    .listen('.chat.thread_settings_changed', (event: any) => {
      const payload = event.payload || {}
      if (payload.thread_id === chat.currentThreadId) {
        // Update settings from event
        if (payload.after) {
          threadSettings.value = {
            id: payload.after.id || 0,
            thread_id: payload.thread_id,
            mode: payload.after.mode || 'standard',
            quiet_hours: payload.after.quiet_hours || null,
            attachments_enabled: payload.after.attachments_enabled !== false,
            created_at: payload.after.created_at || new Date().toISOString(),
            updated_at: payload.after.updated_at || new Date().toISOString(),
          }
        } else {
          // Reload settings
          loadSettings()
        }
      }
    })
}

const unsubscribeFromSettings = () => {
  if (settingsChannel) {
    settingsChannel.stopListening('.chat.thread_settings_changed')
    settingsChannel = null
  }
}

watch(() => chat.currentThreadId, () => {
  unsubscribeFromSettings()
  subscribeToSettings()
})

onMounted(() => {
  loadSettings()
  subscribeToSettings()
})

onUnmounted(() => {
  unsubscribeFromSettings()
})

watch(
  () => chat.currentMessages.length,
  () => {
    scrollToBottom()
  },
)

onMounted(() => {
  scrollToBottom()
})
</script>

<style scoped>
.h-100 {
  height: 100%;
}
.overflow-y-auto {
  overflow-y: auto;
}
</style>

