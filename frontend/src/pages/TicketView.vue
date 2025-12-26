<template>
  <div v-if="ticket" class="d-flex flex-column h-100">
    <v-card class="mb-3">
      <v-card-title>
        <div class="d-flex justify-space-between align-center w-100">
          <div>
            <span class="text-h5">#{{ ticket.id }} - {{ ticket.title }}</span>
            <div class="d-flex align-center mt-2">
              <v-chip :color="getStatusColor(ticket.status)" size="small" class="mr-2">
                {{ getStatusLabel(ticket.status) }}
              </v-chip>
              <v-chip :color="getPriorityColor(ticket.priority)" size="small" class="mr-2">
                {{ getPriorityLabel(ticket.priority) }}
              </v-chip>
              <v-chip v-if="ticket.is_overdue" color="error" size="small">
                Просрочен
              </v-chip>
            </div>
          </div>
          <v-btn icon="mdi-arrow-left" variant="text" @click="goBack" />
        </div>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="6">
            <div class="mb-2">
              <strong>Категория:</strong> {{ ticket.category }}
            </div>
            <div class="mb-2">
              <strong>Создан:</strong> {{ formatDate(ticket.created_at) }}
            </div>
            <div class="mb-2" v-if="ticket.assignee">
              <strong>Назначен:</strong> {{ ticket.assignee.fio }}
            </div>
          </v-col>
          <v-col cols="12" md="6">
            <div class="mb-2">
              <strong>SLA:</strong>
              <div class="mt-1">
                <SlaTimer
                  v-if="ticket.first_response_due_at"
                  :due-at="ticket.first_response_due_at"
                  :responded-at="ticket.first_response_at"
                  label="Первый ответ"
                />
                <SlaTimer
                  v-if="ticket.resolution_due_at"
                  :due-at="ticket.resolution_due_at"
                  :resolved-at="ticket.resolved_at"
                  label="Решение"
                  class="mt-2"
                />
              </div>
            </div>
          </v-col>
        </v-row>
        <v-divider class="my-3" />
        <div>
          <strong>Описание:</strong>
          <p class="mt-2">{{ ticket.description }}</p>
        </div>
        <div v-if="canManage" class="mt-3">
          <v-select
            v-model="manageForm.status"
            :items="statusOptions"
            label="Статус"
            variant="outlined"
            density="compact"
            class="mb-2"
            @update:model-value="updateTicket"
          />
        </div>
      </v-card-text>
    </v-card>

    <!-- Messages -->
    <v-card class="flex-grow-1 d-flex flex-column">
      <v-card-title>Переписка</v-card-title>
      <v-card-text class="flex-grow-1 overflow-y-auto" style="max-height: 500px;">
        <div v-if="loadingMessages" class="text-center py-4">
          <v-progress-circular indeterminate />
        </div>
        <div v-else-if="messages.length === 0" class="text-center py-4 text-grey">
          Нет сообщений
        </div>
        <div v-else class="messages-container">
          <div
            v-for="message in messages"
            :key="message.id"
            :class="['message-item', { 'message-internal': message.is_internal }]"
          >
            <div class="message-header">
              <strong>{{ message.user?.fio || 'Пользователь' }}</strong>
              <span class="text-caption text-grey ml-2">
                {{ formatDateTime(message.created_at) }}
              </span>
              <v-chip v-if="message.is_internal" size="x-small" color="warning" class="ml-2">
                Внутреннее
              </v-chip>
            </div>
            <div class="message-text">{{ message.text }}</div>
          </div>
        </div>
      </v-card-text>
      <v-divider />
      <v-card-text>
        <v-form @submit.prevent="sendMessage">
          <v-textarea
            v-model="messageText"
            label="Сообщение"
            variant="outlined"
            rows="3"
            :disabled="sending"
            class="mb-2"
          />
          <div class="d-flex justify-space-between align-center">
            <v-checkbox
              v-model="isInternal"
              label="Внутреннее сообщение"
              density="compact"
              :disabled="sending"
            />
            <v-btn
              type="submit"
              color="primary"
              :loading="sending"
              :disabled="!messageText.trim()"
            >
              Отправить
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
  <div v-else class="text-center py-8">
    <v-progress-circular indeterminate />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ticketsApi, type TicketDTO, type TicketMessageDTO } from '../api/tickets'
import { useWsStore } from '../stores/ws'
import SlaTimer from '../components/SlaTimer.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ws = useWsStore()

const ticketId = parseInt(route.params.id as string)
const ticket = ref<TicketDTO | null>(null)
const messages = ref<TicketMessageDTO[]>([])
const loading = ref(false)
const loadingMessages = ref(false)
const sending = ref(false)
const messageText = ref('')
const isInternal = ref(false)

const canManage = ref(false) // TODO: Check permission

const statusOptions = [
  { title: 'Открыт', value: 'open' },
  { title: 'В работе', value: 'in_progress' },
  { title: 'Решен', value: 'resolved' },
  { title: 'Закрыт', value: 'closed' },
]

const manageForm = ref({
  status: 'open' as string,
})

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    open: 'info',
    in_progress: 'warning',
    resolved: 'success',
    closed: 'grey',
  }
  return colors[status] || 'grey'
}

const getStatusLabel = (status: string) => {
  return statusOptions.find((s) => s.value === status)?.title || status
}

const getPriorityColor = (priority: string) => {
  const colors: Record<string, string> = {
    low: 'grey',
    normal: 'blue',
    medium: 'orange',
    high: 'red',
    critical: 'error',
  }
  return colors[priority] || 'grey'
}

const getPriorityLabel = (priority: string) => {
  const labels: Record<string, string> = {
    low: 'Низкий',
    normal: 'Обычный',
    medium: 'Средний',
    high: 'Высокий',
    critical: 'Критический',
  }
  return labels[priority] || priority
}

const formatDate = (date?: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ru-RU')
}

const formatDateTime = (date?: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const loadTicket = async () => {
  loading.value = true
  try {
    ticket.value = await ticketsApi.get(ticketId)
    if (ticket.value) {
      manageForm.value.status = ticket.value.status
      canManage.value = auth.hasPermission('tickets.manage')
    }
  } catch (error) {
    console.error('Failed to load ticket:', error)
  } finally {
    loading.value = false
  }
}

const loadMessages = async () => {
  loadingMessages.value = true
  try {
    messages.value = await ticketsApi.getMessages(ticketId)
  } catch (error) {
    console.error('Failed to load messages:', error)
  } finally {
    loadingMessages.value = false
  }
}

const sendMessage = async () => {
  if (!messageText.value.trim()) return

  sending.value = true
  try {
    const newMessage = await ticketsApi.sendMessage(ticketId, {
      text: messageText.value,
      is_internal: isInternal.value,
    })
    messages.value.push(newMessage)
    messageText.value = ''
    isInternal.value = false
  } catch (error) {
    console.error('Failed to send message:', error)
  } finally {
    sending.value = false
  }
}

const updateTicket = async () => {
  if (!ticket.value) return

  try {
    ticket.value = await ticketsApi.update(ticketId, {
      status: manageForm.value.status as any,
    })
  } catch (error) {
    console.error('Failed to update ticket:', error)
  }
}

const goBack = () => {
  router.push({ name: 'tickets' })
}

// Realtime subscriptions
let messageHandler: ((data: any) => void) | null = null
let statusHandler: ((data: any) => void) | null = null

const setupRealtime = () => {
  if (!ws.isConnected) return

  // Subscribe to ticket channel
  ws.subscribe(`ticket.${ticketId}`)

  // Listen for ticket.message_created events
  messageHandler = (data: any) => {
    if (data.message && data.message.ticket_id === ticketId) {
      messages.value.push(data.message)
    }
  }
  ws.on('ticket.message_created', messageHandler)

  // Listen for ticket.status_changed events
  statusHandler = (data: any) => {
    if (data.ticket && data.ticket.id === ticketId && ticket.value) {
      ticket.value.status = data.ticket.status
      manageForm.value.status = data.ticket.status
    }
  }
  ws.on('ticket.status_changed', statusHandler)
}

onMounted(async () => {
  await loadTicket()
  await loadMessages()
  setupRealtime()
})

onUnmounted(() => {
  if (messageHandler) {
    ws.off('ticket.message_created', messageHandler)
  }
  if (statusHandler) {
    ws.off('ticket.status_changed', statusHandler)
  }
  ws.unsubscribe(`ticket.${ticketId}`)
})
</script>

<style scoped>
.messages-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.message-item {
  padding: 12px;
  border-radius: 8px;
  background-color: rgba(0, 0, 0, 0.02);
}

.message-internal {
  background-color: rgba(255, 193, 7, 0.1);
  border-left: 3px solid #ffc107;
}

.message-header {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}

.message-text {
  white-space: pre-wrap;
  word-break: break-word;
}

.h-100 {
  height: 100%;
}
</style>

