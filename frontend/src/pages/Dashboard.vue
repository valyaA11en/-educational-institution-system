<template>
  <div>
    <h1 class="text-h4 mb-4">Панель управления</h1>
    <v-row>
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>Добро пожаловать</v-card-title>
          <v-card-text>
            <p>Добро пожаловать в систему PDO, {{ auth.user?.fio || 'Пользователь' }}!</p>
            <p v-if="auth.roles.length > 0">
              Ваши роли: {{ auth.roles.map(r => r.name).join(', ') }}
            </p>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>Статистика</v-card-title>
          <v-card-text>
            <p>TODO: добавить статистику</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>

