<template>
  <v-banner
    v-if="readOnly.isEnabled"
    color="warning"
    icon="mdi-lock"
    sticky
    single-line
    class="read-only-banner"
  >
    <v-banner-text>
      <strong>Read-only mode:</strong> System is currently in read-only mode. All write operations are disabled.
    </v-banner-text>
  </v-banner>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useReadOnlyStore } from '../stores/readOnly'

const readOnly = useReadOnlyStore()

onMounted(() => {
  // Check read-only mode on mount
  readOnly.checkReadOnlyMode()
})
</script>

<style scoped>
.read-only-banner {
  z-index: 1000;
}
</style>

