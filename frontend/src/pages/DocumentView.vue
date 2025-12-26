<template>
  <div>
    <v-card v-if="document">
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h5">Документ {{ document.number || 'без номера' }}</span>
        <div>
          <v-chip :color="getStatusColor(document.status)" size="small" class="mr-2">
            {{ getStatusText(document.status) }}
          </v-chip>
          <v-btn
            v-if="document.status === 'draft' && !document.number"
            color="primary"
            prepend-icon="mdi-file-document-edit"
            @click="registerNumber"
            :loading="processing"
            class="mr-2"
          >
            Зарегистрировать номер
          </v-btn>
        </div>
      </v-card-title>
      <v-card-text>
        <v-tabs v-model="activeTab">
          <v-tab value="data">Данные</v-tab>
          <v-tab value="route">Маршрут</v-tab>
          <v-tab value="approval">Согласование</v-tab>
          <v-tab value="sign">Подпись</v-tab>
          <v-tab value="ack">Ознакомление</v-tab>
          <v-tab value="export">Экспорт</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
          <!-- Данные -->
          <v-window-item value="data">
            <v-card variant="outlined" class="mt-4">
              <v-card-title class="text-subtitle-1">Редактирование данных</v-card-title>
              <v-card-text>
                <v-form v-if="document.status === 'draft'">
                  <div v-for="(value, key) in editData" :key="key" class="mb-3">
                    <v-text-field
                      v-model="editData[key]"
                      :label="formatKey(key)"
                      variant="outlined"
                      density="compact"
                    />
                  </div>
                  <v-btn
                    color="primary"
                    @click="updateDocument"
                    :loading="processing"
                    :disabled="document.status !== 'draft'"
                  >
                    Сохранить
                  </v-btn>
                </v-form>
                <v-list v-else>
                  <v-list-item
                    v-for="(value, key) in document.data_json"
                    :key="key"
                  >
                    <v-list-item-title>{{ formatKey(key) }}</v-list-item-title>
                    <v-list-item-subtitle>{{ formatValue(value) }}</v-list-item-subtitle>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- Маршрут -->
          <v-window-item value="route">
            <v-card variant="outlined" class="mt-4">
              <v-card-title class="d-flex justify-space-between align-center">
                <span class="text-subtitle-1">Маршрут согласования</span>
                <v-btn
                  v-if="document.status === 'draft'"
                  color="primary"
                  prepend-icon="mdi-play"
                  @click="showRouteDialog = true"
                >
                  Отправить на согласование
                </v-btn>
              </v-card-title>
              <v-card-text>
                <v-timeline v-if="document.routes && document.routes.length > 0">
                  <v-timeline-item
                    v-for="step in document.routes"
                    :key="step.id"
                    :dot-color="getStepColor(step.status)"
                    size="small"
                  >
                    <v-card>
                      <v-card-text>
                        <div class="font-weight-bold">{{ getStepApprover(step) }}</div>
                        <div class="text-caption text-grey">
                          Шаг {{ step.step_no }} - {{ getStepStatusText(step.status) }}
                        </div>
                        <div v-if="step.comment" class="text-caption mt-1">
                          {{ step.comment }}
                        </div>
                        <div v-if="step.decided_at" class="text-caption text-grey mt-1">
                          {{ formatDateTime(step.decided_at) }}
                        </div>
                      </v-card-text>
                    </v-card>
                  </v-timeline-item>
                </v-timeline>
                <v-alert v-else type="info">Маршрут не настроен</v-alert>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- Согласование -->
          <v-window-item value="approval">
            <v-card variant="outlined" class="mt-4">
              <v-card-title class="text-subtitle-1">Согласование</v-card-title>
              <v-card-text>
                <div v-if="canApprove">
                  <v-textarea
                    v-model="approveComment"
                    label="Комментарий (необязательно)"
                    variant="outlined"
                    class="mb-3"
                  />
                  <v-btn
                    color="success"
                    prepend-icon="mdi-check"
                    @click="approveDocument"
                    :loading="processing"
                    class="mr-2"
                  >
                    Одобрить
                  </v-btn>
                  <v-btn
                    color="error"
                    prepend-icon="mdi-close"
                    @click="showRejectDialog = true"
                    :loading="processing"
                  >
                    Отклонить
                  </v-btn>
                </div>
                <v-alert v-else type="info">
                  Вы не являетесь текущим согласующим для этого документа
                </v-alert>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- Подпись -->
          <v-window-item value="sign">
            <v-card variant="outlined" class="mt-4">
              <v-card-title class="text-subtitle-1">Подпись</v-card-title>
              <v-card-text>
                <div v-if="document.status === 'approved' && canSign">
                  <v-textarea
                    v-model="signComment"
                    label="Комментарий (необязательно)"
                    variant="outlined"
                    class="mb-3"
                  />
                  <v-btn
                    color="success"
                    prepend-icon="mdi-pen"
                    @click="signDocument"
                    :loading="processing"
                  >
                    Подписать
                  </v-btn>
                </div>
                <div v-else-if="document.status === 'signed'">
                  <v-alert type="success">
                    Документ подписан
                    <div v-if="document.signer" class="mt-2">
                      Подписант: {{ document.signer.fio }}
                    </div>
                    <div v-if="document.signed_at" class="mt-1">
                      Дата: {{ formatDateTime(document.signed_at) }}
                    </div>
                  </v-alert>
                </div>
                <v-alert v-else type="info">
                  Документ должен быть утвержден перед подписанием
                </v-alert>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- Ознакомление -->
          <v-window-item value="ack">
            <v-card variant="outlined" class="mt-4">
              <v-card-title class="d-flex justify-space-between align-center">
                <span class="text-subtitle-1">Ознакомление</span>
                <v-btn
                  v-if="document.status === 'signed'"
                  color="primary"
                  prepend-icon="mdi-account-plus"
                  @click="showAckTargetsDialog = true"
                >
                  Добавить адресатов
                </v-btn>
              </v-card-title>
              <v-card-text>
                <v-list v-if="document.acks && document.acks.length > 0">
                  <v-list-item
                    v-for="ack in document.acks"
                    :key="ack.id"
                  >
                    <v-list-item-title>{{ ack.user?.fio || `ID: ${ack.user_id}` }}</v-list-item-title>
                    <v-list-item-subtitle>
                      <v-chip :color="ack.status === 'confirmed' ? 'success' : 'info'" size="small">
                        {{ ack.status === 'confirmed' ? 'Подтверждено' : 'Прочитано' }}
                      </v-chip>
                      <span v-if="ack.confirmed_at" class="ml-2 text-caption">
                        {{ formatDateTime(ack.confirmed_at) }}
                      </span>
                    </v-list-item-subtitle>
                  </v-list-item>
                </v-list>
                <v-alert v-else type="info">Адресаты не назначены</v-alert>
                <v-btn
                  v-if="canConfirmAck"
                  color="primary"
                  prepend-icon="mdi-check"
                  @click="confirmAck"
                  :loading="processing"
                  class="mt-4"
                >
                  Подтвердить ознакомление
                </v-btn>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- Экспорт -->
          <v-window-item value="export">
            <v-card variant="outlined" class="mt-4">
              <v-card-title class="text-subtitle-1">Экспорт</v-card-title>
              <v-card-text>
                <v-btn
                  color="primary"
                  prepend-icon="mdi-download"
                  @click="exportDocument('docx')"
                  :loading="exporting === 'docx'"
                  class="mr-2"
                >
                  Экспорт DOCX
                </v-btn>
                <v-btn
                  color="primary"
                  prepend-icon="mdi-file-pdf-box"
                  @click="exportDocument('pdf')"
                  :loading="exporting === 'pdf'"
                >
                  Экспорт PDF
                </v-btn>
                <v-alert v-if="pdfNotConfigured" type="warning" class="mt-4">
                  Конвертация в PDF не настроена
                </v-alert>
              </v-card-text>
            </v-card>
          </v-window-item>
        </v-window>
      </v-card-text>
    </v-card>

    <!-- Route Dialog -->
    <v-dialog v-model="showRouteDialog" max-width="600">
      <v-card>
        <v-card-title>Отправить на согласование</v-card-title>
        <v-card-text>
          <div v-for="(step, index) in routeSteps" :key="index" class="mb-4">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-2">Шаг {{ step.stepNo }}</v-card-title>
              <v-card-text>
                <v-select
                  v-model="step.approverRoleId"
                  :items="roleOptions"
                  label="Роль"
                  clearable
                  variant="outlined"
                  density="compact"
                />
                <v-select
                  v-model="step.approverUserId"
                  :items="userOptions"
                  label="Пользователь"
                  clearable
                  variant="outlined"
                  density="compact"
                  class="mt-2"
                />
              </v-card-text>
            </v-card>
          </div>
          <v-btn color="primary" @click="addRouteStep">Добавить шаг</v-btn>
          <v-btn color="error" @click="removeRouteStep" :disabled="routeSteps.length <= 1" class="ml-2">
            Удалить шаг
          </v-btn>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showRouteDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="sendToApproval" :loading="processing">Отправить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="showRejectDialog" max-width="500">
      <v-card>
        <v-card-title>Отклонить документ</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="rejectComment"
            label="Комментарий"
            variant="outlined"
            required
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showRejectDialog = false">Отмена</v-btn>
          <v-btn color="error" @click="rejectDocument" :loading="processing" :disabled="!rejectComment">
            Отклонить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Ack Targets Dialog -->
    <v-dialog v-model="showAckTargetsDialog" max-width="500">
      <v-card>
        <v-card-title>Добавить адресатов</v-card-title>
        <v-card-text>
          <v-select
            v-model="ackUserIds"
            :items="userOptions"
            label="Пользователи"
            multiple
            chips
            variant="outlined"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAckTargetsDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="setAckTargets" :loading="processing" :disabled="ackUserIds.length === 0">
            Добавить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-card v-else-if="loading">
      <v-card-text>
        <v-progress-linear indeterminate />
      </v-card-text>
    </v-card>

    <v-card v-else>
      <v-card-text>
        <v-alert type="error">Документ не найден</v-alert>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { documentsApi, type DocumentDTO } from '../api/documents'
import { rolesApi } from '../api/roles'
import { usersApi } from '../api/users'
import { useRealtime } from '../composables/useRealtime'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const document = ref<DocumentDTO | null>(null)
const processing = ref(false)
const exporting = ref<'docx' | 'pdf' | null>(null)
const pdfNotConfigured = ref(false)
const activeTab = ref('data')
const editData = ref<Record<string, any>>({})
const showRouteDialog = ref(false)
const routeSteps = ref<Array<{ stepNo: number; approverRoleId: number | null; approverUserId: number | null }>>([
  { stepNo: 1, approverRoleId: null, approverUserId: null }
])
const roleOptions = ref<Array<{ title: string; value: number }>>([])
const userOptions = ref<Array<{ title: string; value: number }>>([])
const approveComment = ref('')
const rejectComment = ref('')
const showRejectDialog = ref(false)
const signComment = ref('')
const showAckTargetsDialog = ref(false)
const ackUserIds = ref<number[]>([])

const echo = useEcho()

const showToast = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') => {
  // TODO: integrate with Vuetify snackbar
  console.log(`[${type}] ${message}`)
}

const canApprove = computed(() => {
  if (!document.value || document.value.status !== 'on_review') return false
  const currentStep = document.value.routes?.find(r => r.status === 'pending')
  if (!currentStep) return false
  // TODO: check if current user matches approver
  return true
})

const canSign = computed(() => {
  // TODO: check if user has documents.sign permission
  return document.value?.status === 'approved'
})

const canConfirmAck = computed(() => {
  if (!document.value) return false
  const ack = document.value.acks?.find(a => a.user_id === getCurrentUserId())
  return ack && ack.status !== 'confirmed'
})

const getCurrentUserId = () => {
  // TODO: get from auth store
  return 1
}

const loadDocument = async () => {
  const id = parseInt(route.params.id as string)
  if (!id) return

  loading.value = true
  try {
    document.value = await documentsApi.get(id)
    editData.value = { ...document.value.data_json }
  } catch (error) {
    console.error('Failed to load document:', error)
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const roles = await rolesApi.list()
    roleOptions.value = roles.map(r => ({ title: r.name, value: r.id }))
  } catch (error) {
    console.error('Failed to load roles:', error)
  }
}

const loadUsers = async () => {
  try {
    const response = await usersApi.list({ per_page: 1000 })
    userOptions.value = response.data.map(u => ({ title: u.fio, value: u.id }))
  } catch (error) {
    console.error('Failed to load users:', error)
  }
}

const updateDocument = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.update(document.value.id, { data_json: editData.value })
    await loadDocument()
    showToast('Документ обновлен', 'success')
  } catch (error) {
    console.error('Failed to update document:', error)
    showToast('Ошибка при обновлении', 'error')
  } finally {
    processing.value = false
  }
}

const addRouteStep = () => {
  routeSteps.value.push({
    stepNo: routeSteps.value.length + 1,
    approverRoleId: null,
    approverUserId: null,
  })
}

const removeRouteStep = () => {
  if (routeSteps.value.length > 1) {
    routeSteps.value.pop()
  }
}

const sendToApproval = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.sendToApproval(document.value.id, { route: routeSteps.value })
    showRouteDialog.value = false
    await loadDocument()
    showToast('Документ отправлен на согласование', 'success')
  } catch (error) {
    console.error('Failed to send to approval:', error)
    showToast('Ошибка при отправке', 'error')
  } finally {
    processing.value = false
  }
}

const approveDocument = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.approve(document.value.id, { comment: approveComment.value || undefined })
    approveComment.value = ''
    await loadDocument()
    showToast('Документ одобрен', 'success')
  } catch (error) {
    console.error('Failed to approve document:', error)
    showToast('Ошибка при одобрении', 'error')
  } finally {
    processing.value = false
  }
}

const rejectDocument = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.reject(document.value.id, { comment: rejectComment.value })
    showRejectDialog.value = false
    rejectComment.value = ''
    await loadDocument()
    showToast('Документ отклонен', 'info')
  } catch (error) {
    console.error('Failed to reject document:', error)
    showToast('Ошибка при отклонении', 'error')
  } finally {
    processing.value = false
  }
}

const signDocument = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.sign(document.value.id, { comment: signComment.value || undefined })
    signComment.value = ''
    await loadDocument()
    showToast('Документ подписан', 'success')
  } catch (error) {
    console.error('Failed to sign document:', error)
    showToast('Ошибка при подписании', 'error')
  } finally {
    processing.value = false
  }
}

const setAckTargets = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.setAckTargets(document.value.id, { userIds: ackUserIds.value })
    showAckTargetsDialog.value = false
    ackUserIds.value = []
    await loadDocument()
    showToast('Адресаты добавлены', 'success')
  } catch (error) {
    console.error('Failed to set ack targets:', error)
    showToast('Ошибка при добавлении адресатов', 'error')
  } finally {
    processing.value = false
  }
}

const confirmAck = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.confirmAck(document.value.id)
    await loadDocument()
    showToast('Ознакомление подтверждено', 'success')
  } catch (error) {
    console.error('Failed to confirm ack:', error)
    showToast('Ошибка при подтверждении', 'error')
  } finally {
    processing.value = false
  }
}

const registerNumber = async () => {
  if (!document.value) return

  processing.value = true
  try {
    await documentsApi.registerNumber(document.value.id)
    await loadDocument()
    showToast('Номер зарегистрирован', 'success')
  } catch (error) {
    console.error('Failed to register number:', error)
    showToast('Ошибка при регистрации номера', 'error')
  } finally {
    processing.value = false
  }
}

const exportDocument = async (format: 'docx' | 'pdf') => {
  if (!document.value) return

  exporting.value = format
  pdfNotConfigured.value = false

  try {
    const blob = await documentsApi.export(document.value.id, format)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `document_${document.value.number || document.value.id}.${format}`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    console.error('Failed to export document:', error)
    if (error.response?.status === 501) {
      pdfNotConfigured.value = true
    } else {
      showToast('Ошибка при экспорте', 'error')
    }
  } finally {
    exporting.value = null
  }
}

const getStepApprover = (step: any) => {
  if (step.approver_role) return `Роль: ${step.approver_role.name}`
  if (step.approver_user) return step.approver_user.fio
  return 'Не назначен'
}

const getStepColor = (status: string) => {
  const colors: Record<string, string> = {
    pending: 'grey',
    approved: 'success',
    rejected: 'error',
  }
  return colors[status] || 'default'
}

const getStepStatusText = (status: string) => {
  const texts: Record<string, string> = {
    pending: 'Ожидает',
    approved: 'Одобрено',
    rejected: 'Отклонено',
  }
  return texts[status] || status
}

const formatKey = (key: string) => {
  return key
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const formatValue = (value: any) => {
  if (value === null || value === undefined) return '-'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU')
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    on_review: 'orange',
    approved: 'blue',
    signed: 'green',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    on_review: 'На проверке',
    approved: 'Утвержден',
    signed: 'Подписан',
    archived: 'Архив',
  }
  return texts[status] || status
}

onMounted(async () => {
  await loadDocument()
  await loadRoles()
  await loadUsers()

  // Subscribe to document status changes
  if (document.value && echo) {
    echo.private(`document.${document.value.id}`)
      .listen('.document.status_changed', (event: any) => {
        showToast('Статус документа изменен', 'info')
        loadDocument()
      })
  }
})

onUnmounted(() => {
  if (document.value && echo) {
    echo.leave(`document.${document.value.id}`)
  }
})
</script>
