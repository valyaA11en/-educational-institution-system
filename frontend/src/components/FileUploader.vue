<template>
  <div>
    <v-file-input
      v-model="files"
      :accept="accept"
      :multiple="maxFiles > 1"
      :show-size="true"
      label="Выберите файлы"
      variant="outlined"
      density="compact"
      @update:model-value="handleFilesChange"
    />
    <div v-if="uploadedFiles.length > 0" class="mt-2">
      <div
        v-for="file in uploadedFiles"
        :key="file.id"
        class="d-flex align-center justify-space-between pa-2 mb-1"
        style="background-color: rgba(0, 0, 0, 0.02); border-radius: 4px;"
      >
        <div class="d-flex align-center">
          <v-icon class="mr-2">mdi-file</v-icon>
          <span>{{ file.name }}</span>
          <span class="text-caption text-grey ml-2">({{ formatSize(file.size) }})</span>
        </div>
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          @click="removeFile(file.id)"
        />
      </div>
    </div>
    <div v-if="uploading" class="mt-2">
      <v-progress-linear indeterminate />
      <div class="text-caption text-center mt-1">Загрузка файлов...</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { filesApi } from '../api/files'

interface Props {
  modelValue: number[]
  maxFiles?: number
  maxSize?: number // in bytes
  accept?: string
  ticketId?: number
  assignmentId?: number
}

const props = withDefaults(defineProps<Props>(), {
  maxFiles: 5,
  maxSize: 10485760, // 10MB
  accept: '*/*',
})

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const files = ref<File[]>([])
const uploadedFiles = ref<Array<{ id: number; name: string; size: number }>>([])
const uploading = ref(false)

const handleFilesChange = async (newFiles: File[] | null) => {
  if (!newFiles || newFiles.length === 0) return

  // Validate file count
  const totalFiles = uploadedFiles.value.length + newFiles.length
  if (totalFiles > props.maxFiles) {
    alert(`Максимальное количество файлов: ${props.maxFiles}`)
    files.value = []
    return
  }

  // Validate file sizes
  for (const file of newFiles) {
    if (file.size > props.maxSize) {
      alert(`Файл "${file.name}" превышает максимальный размер ${formatSize(props.maxSize)}`)
      files.value = []
      return
    }
  }

  uploading.value = true
  try {
    const fileIds: number[] = []

    for (const file of newFiles) {
      try {
        // Get presigned URL
        // Note: For tickets, ticket_id should be passed as a prop
        const presignedResponse = await filesApi.getPresignedUploadUrl({
          filename: file.name,
          content_type: file.type,
          size: file.size,
          ticket_id: props.ticketId,
          assignment_id: props.assignmentId,
        })

        // Upload file to S3
        const uploadResponse = await fetch(presignedResponse.upload_url, {
          method: 'PUT',
          body: file,
          headers: {
            'Content-Type': file.type,
          },
        })

        if (!uploadResponse.ok) {
          throw new Error('Failed to upload file to S3')
        }

        // Confirm upload
        const fileResponse = await filesApi.confirmUpload(presignedResponse.file_id, {
          filename: file.name,
          size: file.size,
          content_type: file.type,
        })

        fileIds.push(fileResponse.id)
        uploadedFiles.value.push({
          id: fileResponse.id,
          name: fileResponse.filename || file.name,
          size: fileResponse.size || file.size,
        })
      } catch (error) {
        console.error(`Failed to upload file ${file.name}:`, error)
        alert(`Ошибка загрузки файла "${file.name}"`)
      }
    }

    // Update model value
    const newValue = [...props.modelValue, ...fileIds]
    emit('update:modelValue', newValue)
  } catch (error) {
    console.error('Failed to upload files:', error)
    alert('Ошибка загрузки файлов')
  } finally {
    uploading.value = false
    files.value = []
  }
}

const removeFile = (fileId: number) => {
  uploadedFiles.value = uploadedFiles.value.filter((f) => f.id !== fileId)
  const newValue = props.modelValue.filter((id) => id !== fileId)
  emit('update:modelValue', newValue)
}

const formatSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// Watch for external changes to modelValue (e.g., form reset)
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue.length === 0) {
      uploadedFiles.value = []
    }
  }
)
</script>

