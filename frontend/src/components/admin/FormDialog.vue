<template>
  <v-dialog v-model="dialog" :max-width="maxWidth" persistent>
    <v-card>
      <v-card-title>{{ title }}</v-card-title>
      <v-card-text>
        <v-form ref="formRef" v-model="valid">
          <slot name="form" :rules="rules" />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="cancel">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
  modelValue: boolean
  title: string
  maxWidth?: string | number
  saving?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  maxWidth: 500,
  saving: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  save: []
  cancel: []
}>()

const dialog = ref(props.modelValue)
const formRef = ref()
const valid = ref(false)

const rules = {
  required: (v: any) => !!v || 'Обязательное поле',
}

watch(() => props.modelValue, (val) => {
  dialog.value = val
})

watch(dialog, (val) => {
  emit('update:modelValue', val)
})

const save = () => {
  if (formRef.value?.validate()) {
    emit('save')
  }
}

const cancel = () => {
  dialog.value = false
  emit('cancel')
}
</script>

