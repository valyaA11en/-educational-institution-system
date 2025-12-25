<template>
  <v-app>
    <router-view />

    <!-- Global notifications toast -->
    <v-snackbar
      v-model="notifications.toastVisible"
      :timeout="4000"
      color="primary"
      location="bottom right"
    >
      {{ notifications.toastMessage }}
    </v-snackbar>
  </v-app>
</template>

<script setup lang="ts">
import { useNotificationsStore } from './stores/notifications'

const notifications = useNotificationsStore()
</script>

<style>
html,
body,
#app {
  margin: 0;
  padding: 0;
  height: 100%;
}
</style>

