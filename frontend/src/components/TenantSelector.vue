<template>
  <v-select
    v-if="auth.hasRole('admin') && auth.userTenants.length > 1"
    v-model="selectedTenantId"
    :items="tenantItems"
    item-title="name"
    item-value="id"
    density="compact"
    variant="outlined"
    hide-details
    class="mr-2"
    style="max-width: 200px"
    @update:model-value="onTenantChange"
  >
    <template v-slot:prepend-inner>
      <v-icon size="small">mdi-domain</v-icon>
    </template>
  </v-select>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import type { TenantDTO } from '../api/tenants'

const auth = useAuthStore()

const selectedTenantId = ref<number | null>(auth.currentTenant?.id || null)

const tenantItems = computed(() => {
  return auth.userTenants.map((tenant: TenantDTO) => ({
    id: tenant.id,
    name: tenant.name,
    slug: tenant.slug,
  }))
})

watch(() => auth.currentTenant, (newTenant) => {
  if (newTenant) {
    selectedTenantId.value = newTenant.id
  }
}, { immediate: true })

const onTenantChange = async (tenantId: number) => {
  if (tenantId === auth.currentTenant?.id) {
    return
  }

  try {
    await auth.switchTenant(tenantId)
    // Reload page to refresh all data with new tenant context
    window.location.reload()
  } catch (error) {
    console.error('Failed to switch tenant:', error)
    // Revert selection
    selectedTenantId.value = auth.currentTenant?.id || null
  }
}
</script>


