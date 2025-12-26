<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1>Создать конкурс</h1>
        <v-card class="mt-4">
          <v-card-text>
            <v-form ref="formRef" v-model="valid">
              <v-text-field
                v-model="contest.title"
                label="Название"
                variant="outlined"
                required
                :rules="[(v) => !!v || 'Обязательное поле']"
              />
              <v-textarea
                v-model="contest.description"
                label="Описание"
                variant="outlined"
                rows="3"
              />
              <v-select
                v-model="contest.visibility_scope"
                :items="[
                  { title: 'Все', value: 'all' },
                  { title: 'Группа', value: 'group' },
                  { title: 'По приглашению', value: 'invite' },
                ]"
                label="Видимость"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="contest.start_at"
                type="datetime-local"
                label="Начало"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="contest.end_at"
                type="datetime-local"
                label="Окончание"
                variant="outlined"
                required
              />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="$router.back()">Отмена</v-btn>
            <v-btn color="primary" :loading="saving" @click="save">Создать</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { contestsApi } from '../api/contests'

const router = useRouter()
const valid = ref(false)
const formRef = ref()
const saving = ref(false)

const contest = ref({
  title: '',
  description: '',
  start_at: '',
  end_at: '',
  visibility_scope: 'all' as 'all' | 'group' | 'invite',
})

const save = async () => {
  const { valid: isValid } = await formRef.value.validate()
  if (!isValid) return

  saving.value = true
  try {
    const res = await contestsApi.create(contest.value)
    router.push({ name: 'contest-detail', params: { id: res.data.id } })
  } catch (error) {
    console.error('Failed to create contest:', error)
  } finally {
    saving.value = false
  }
}
</script>

