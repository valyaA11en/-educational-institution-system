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
        >
          <v-list-item-title>{{ message.text }}</v-list-item-title>
          <v-list-item-subtitle>{{ message.createdAt }}</v-list-item-subtitle>
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
        @keyup.enter.exact.prevent="onSend"
      />
      <!-- Вложения (каркас, без реальной загрузки) -->
      <v-btn
        icon="mdi-paperclip"
        variant="text"
        class="mr-1"
        @click="onAttachClick"
      />
      <v-btn
        :loading="chat.sending"
        icon="mdi-send"
        color="primary"
        @click="onSend"
      />
    </v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useChatStore } from '../stores/chat'

const chat = useChatStore()

const newMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)

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

