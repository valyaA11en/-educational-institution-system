<template>
  <div class="d-flex h-100">
    <v-col cols="12" md="4" class="pa-0">
      <ChatThreadList />
    </v-col>
    <v-col cols="12" md="8" class="pa-0">
      <ChatMessages />
    </v-col>
  </div>
</template>

<script setup lang="ts">
import ChatThreadList from '../components/ChatThreadList.vue'
import ChatMessages from '../components/ChatMessages.vue'
</script>

<style scoped>
.h-100 {
  height: 100%;
}
</style>


