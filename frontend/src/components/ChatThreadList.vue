<template>
  <v-card class="h-100 d-flex flex-column">
    <v-card-title>
      Чаты
    </v-card-title>

    <v-card-text>
      <v-text-field
        v-model="chat.threadsSearch"
        label="Поиск по чатам"
        density="compact"
        prepend-inner-icon="mdi-magnify"
        hide-details
      />
    </v-card-text>

    <v-divider />

    <v-card-text class="flex-grow-1 overflow-y-auto pa-0">
      <v-list density="comfortable">
        <v-list-item
          v-for="thread in chat.filteredThreads"
          :key="thread.id"
          :value="thread.id"
          :active="thread.id === chat.currentThreadId"
          @click="onSelectThread(thread.id)"
        >
          <template #prepend>
            <v-avatar size="32">
              <v-icon>mdi-account-group</v-icon>
            </v-avatar>
          </template>

          <v-list-item-title class="d-flex align-center">
            <span class="text-truncate">{{ thread.name }}</span>
            <v-chip
              v-if="thread.pinned"
              size="x-small"
              color="warning"
              class="ml-1"
              label
            >
              PIN
            </v-chip>
          </v-list-item-title>
          <v-list-item-subtitle class="text-truncate">
            {{ thread.lastMessage || 'Нет сообщений' }}
          </v-list-item-subtitle>

          <template #append>
            <v-badge
              v-if="thread.unreadCount > 0"
              :content="thread.unreadCount"
              color="primary"
            />
          </template>
        </v-list-item>

        <v-list-item v-if="!chat.threadsLoading && chat.filteredThreads.length === 0">
          <v-list-item-title>Нет чатов</v-list-item-title>
        </v-list-item>

        <v-list-item v-if="chat.threadsLoading">
          <v-progress-circular indeterminate size="24" />
          <v-list-item-title class="ml-2">Загрузка...</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useChatStore } from '../stores/chat'

const chat = useChatStore()

const onSelectThread = (id: number) => {
  chat.selectThread(id)
}

onMounted(() => {
  if (!chat.threads.length) {
    chat.loadThreads()
  }
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

