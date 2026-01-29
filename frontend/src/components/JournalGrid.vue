<template>
  <div class="journal-grid-container">
    <div class="journal-grid-toolbar">
      <div class="toolbar-left">
        <v-btn
          v-if="hasChanges"
          @click="saveChanges"
          :loading="saving"
          color="primary"
          prepend-icon="mdi-content-save"
          size="small"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить' }}
        </v-btn>
        <v-btn
          @click="setAllPresent"
          :disabled="saving || loading"
          color="secondary"
          prepend-icon="mdi-check-all"
          size="small"
        >
          Всем присутствовал
        </v-btn>
      </div>
      <div class="toolbar-right">
        <v-chip
          v-if="saveStatus"
          :color="saveStatus.type === 'success' ? 'success' : 'error'"
          size="small"
          prepend-icon="mdi-check-circle"
        >
          {{ saveStatus.message }}
        </v-chip>
        <v-chip
          v-if="hasChanges && !saveStatus"
          color="warning"
          size="small"
          prepend-icon="mdi-alert-circle"
        >
          Есть несохранённые изменения
        </v-chip>
      </div>
    </div>

    <div v-if="loading" class="journal-grid-loading">
      <v-progress-linear indeterminate color="primary"></v-progress-linear>
    </div>

    <div
      v-else
      ref="gridWrapper"
      class="journal-grid-wrapper"
      @keydown="handleKeyDown"
      @paste="handlePaste"
      @copy="handleCopy"
      tabindex="0"
    >
      <table class="journal-grid-table" ref="gridTable">
        <thead>
          <tr>
            <th class="student-column">Студент</th>
            <th class="grade-column">Оценка</th>
            <th class="weight-column">Вес</th>
            <th class="comment-column">Комментарий</th>
            <th class="attendance-column">Посещаемость</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(student, rowIndex) in students"
            :key="student.id"
            :class="{
              'selected-row': selectedCell?.row === rowIndex,
            }"
          >
            <td class="student-column">{{ student.fio }}</td>
            
            <!-- Grade Column -->
            <td
              class="grade-column editable"
              :class="{
                'has-grade': hasGrade(student.id),
                'selected-cell': selectedCell?.row === rowIndex && selectedCell?.field === 'grade',
                'editing': editingCell?.row === rowIndex && editingCell?.field === 'grade',
              }"
              @click="selectCell(rowIndex, 'grade')"
              @dblclick="startEdit(rowIndex, 'grade')"
            >
              <input
                v-if="editingCell?.row === rowIndex && editingCell?.field === 'grade'"
                ref="(el) => { if (el) gradeInputs[rowIndex] = el as HTMLInputElement }"
                :value="getCellData(student.id, 'grade')"
                @input="handleGradeInput(student.id, ($event.target as HTMLInputElement).value)"
                @blur="stopEdit"
                @keydown.enter.prevent="handleEnter(rowIndex, 'grade')"
                @keydown.esc.prevent="stopEdit"
                @keydown.arrow-up.prevent="moveCell(rowIndex - 1, 'grade')"
                @keydown.arrow-down.prevent="moveCell(rowIndex + 1, 'grade')"
                @keydown.tab.prevent="moveCell(rowIndex, 'weight', true)"
                type="number"
                min="1"
                max="5"
                step="1"
                class="cell-input"
              />
              <v-tooltip
                v-else
                :text="getGradeHistoryTooltip(student.id)"
                location="top"
              >
                <template v-slot:activator="{ props }">
                  <span v-bind="props" class="grade-value">
                    {{ getGradeDisplay(student.id) }}
                  </span>
                </template>
              </v-tooltip>
            </td>

            <!-- Weight Column -->
            <td
              class="weight-column editable"
              :class="{
                'selected-cell': selectedCell?.row === rowIndex && selectedCell?.field === 'weight',
                'editing': editingCell?.row === rowIndex && editingCell?.field === 'weight',
              }"
              @click="selectCell(rowIndex, 'weight')"
              @dblclick="startEdit(rowIndex, 'weight')"
            >
              <input
                v-if="editingCell?.row === rowIndex && editingCell?.field === 'weight'"
                ref="(el) => { if (el) weightInputs[rowIndex] = el as HTMLInputElement }"
                :value="getCellData(student.id, 'weight')"
                @input="handleWeightInput(student.id, ($event.target as HTMLInputElement).value)"
                @blur="stopEdit"
                @keydown.enter.prevent="handleEnter(rowIndex, 'weight')"
                @keydown.esc.prevent="stopEdit"
                @keydown.arrow-up.prevent="moveCell(rowIndex - 1, 'weight')"
                @keydown.arrow-down.prevent="moveCell(rowIndex + 1, 'weight')"
                @keydown.tab.prevent="moveCell(rowIndex, 'comment', true)"
                type="number"
                min="0"
                max="100"
                step="0.1"
                class="cell-input"
              />
              <span v-else class="weight-value">{{ getWeightDisplay(student.id) }}</span>
            </td>

            <!-- Comment Column -->
            <td
              class="comment-column editable"
              :class="{
                'selected-cell': selectedCell?.row === rowIndex && selectedCell?.field === 'comment',
                'editing': editingCell?.row === rowIndex && editingCell?.field === 'comment',
              }"
              @click="selectCell(rowIndex, 'comment')"
              @dblclick="startEdit(rowIndex, 'comment')"
            >
              <input
                v-if="editingCell?.row === rowIndex && editingCell?.field === 'comment'"
                ref="(el) => { if (el) commentInputs[rowIndex] = el as HTMLInputElement }"
                :value="getCellData(student.id, 'comment')"
                @input="handleCommentInput(student.id, ($event.target as HTMLInputElement).value)"
                @blur="stopEdit"
                @keydown.enter.prevent="handleEnter(rowIndex, 'comment')"
                @keydown.esc.prevent="stopEdit"
                @keydown.arrow-up.prevent="moveCell(rowIndex - 1, 'comment')"
                @keydown.arrow-down.prevent="moveCell(rowIndex + 1, 'comment')"
                @keydown.tab.prevent="moveCell(rowIndex, 'attendance', true)"
                type="text"
                class="cell-input"
              />
              <span v-else class="comment-value">{{ getCommentDisplay(student.id) }}</span>
            </td>

            <!-- Attendance Column -->
            <td
              class="attendance-column editable"
              :class="{
                'selected-cell': selectedCell?.row === rowIndex && selectedCell?.field === 'attendance',
              }"
              @click="selectCell(rowIndex, 'attendance')"
            >
              <select
                :ref="el => { if (el) attendanceSelects[rowIndex] = el as HTMLSelectElement }"
                :value="getCellData(student.id, 'attendance')"
                @change="handleAttendanceChange(student.id, ($event.target as HTMLSelectElement).value)"
                @keydown.enter.prevent="moveCell(rowIndex + 1, 'grade')"
                @keydown.arrow-up.prevent="moveCell(rowIndex - 1, 'attendance')"
                @keydown.arrow-down.prevent="moveCell(rowIndex + 1, 'attendance')"
                class="attendance-select"
              >
                <option :value="null">—</option>
                <option value="present">Присутствовал</option>
                <option value="absent">Отсутствовал</option>
                <option value="late">Опоздал</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, nextTick, watch, onUnmounted } from 'vue'
import { journalApi, type JournalGridDTO } from '@/api/journal'

interface Props {
  lessonId: number
}

const props = defineProps<Props>()

interface Student {
  id: number
  fio: string
}

interface Grade {
  id?: number
  value: number | null
  weight: number
  comment: string | null
  createdAt?: string
}

interface Attendance {
  status: string | null
  reason: string | null
}

interface GridData {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  [studentId: number]: {
    grades: Grade[]
    attendance: Attendance
  }
}

const students = ref<Student[]>([])
const grades = ref<Record<number, Grade[]>>({})
const attendance = ref<Record<number, Attendance>>({})
const gridData = reactive<GridData>({})
const selectedCell = ref<{ row: number; field: string } | null>(null)
const editingCell = ref<{ row: number; field: string } | null>(null)
const saving = ref(false)
const loading = ref(false)
const saveStatus = ref<{ type: 'success' | 'error'; message: string } | null>(null)
const hasChanges = ref(false)
const saveTimer = ref<ReturnType<typeof setTimeout> | null>(null)

const gridWrapper = ref<HTMLDivElement | null>(null)
const gridTable = ref<HTMLTableElement | null>(null)
const gradeInputs = ref<Record<number, HTMLInputElement>>({})
const weightInputs = ref<Record<number, HTMLInputElement>>({})
const commentInputs = ref<Record<number, HTMLInputElement>>({})
const attendanceSelects = ref<Record<number, HTMLSelectElement>>({})

const loadData = async () => {
  loading.value = true
  saveStatus.value = null
  try {
    const data: JournalGridDTO = await journalApi.getGrid(props.lessonId)

    students.value = data.students || []
    grades.value = data.grades || {}
    attendance.value = data.attendance || {}

    // Initialize gridData
    students.value.forEach((student) => {
      const studentGrades = grades.value[student.id] || []
      gridData[student.id] = {
        grades: studentGrades.length > 0
          ? [{ ...studentGrades[0] }]
          : [{ value: null, weight: 1.0, comment: null }],
        attendance: attendance.value[student.id] || { status: null, reason: null },
      }
    })

    hasChanges.value = false
  } catch (error: any) {
    console.error('Failed to load journal grid:', error)
    saveStatus.value = {
      type: 'error',
      message: error.response?.data?.message || 'Ошибка загрузки данных',
    }
  } finally {
    loading.value = false
  }
}

const saveChanges = async () => {
  if (saving.value || !hasChanges.value) return

  if (saveTimer.value) {
    clearTimeout(saveTimer.value)
    saveTimer.value = null
  }

  saving.value = true
  saveStatus.value = null

  try {
    const gradesToSave: any[] = []
    const attendanceToSave: any[] = []

    students.value.forEach((student) => {
      const data = gridData[student.id]
      const grade = data.grades[0]

      if (grade.value !== null && grade.value !== undefined) {
        gradesToSave.push({
          studentId: student.id,
          value: grade.value,
          weight: grade.weight ?? 1.0,
          comment: grade.comment ?? null,
          gradeId: grade.id,
        })
      }

      if (data.attendance.status) {
        attendanceToSave.push({
          studentId: student.id,
          status: data.attendance.status,
          reason: data.attendance.reason ?? null,
        })
      }
    })

    await journalApi.saveGrid(props.lessonId, {
      grades: gradesToSave,
      attendance: attendanceToSave,
    })

    saveStatus.value = { type: 'success', message: 'Сохранено' }
    hasChanges.value = false

    // Reload data to get updated IDs
    await loadData()

    setTimeout(() => {
      saveStatus.value = null
    }, 3000)
  } catch (error: any) {
    console.error('Failed to save journal grid:', error)
    saveStatus.value = {
      type: 'error',
      message: error.response?.data?.message || 'Ошибка сохранения',
    }
  } finally {
    saving.value = false
  }
}

const scheduleAutoSave = () => {
  if (saveTimer.value) {
    clearTimeout(saveTimer.value)
  }
  saveTimer.value = setTimeout(() => {
    saveChanges()
  }, 1000)
}

const markChanged = () => {
  hasChanges.value = true
  scheduleAutoSave()
}

const selectCell = (row: number, field: string) => {
  selectedCell.value = { row, field }
  editingCell.value = null
  if (gridWrapper.value) {
    gridWrapper.value.focus()
  }
}

const startEdit = (row: number, field: string) => {
  editingCell.value = { row, field }
  selectedCell.value = { row, field }
  nextTick(() => {
    if (field === 'grade' && gradeInputs.value[row]) {
      const input = gradeInputs.value[row]
      input.focus()
      input.select()
    } else if (field === 'weight' && weightInputs.value[row]) {
      const input = weightInputs.value[row]
      input.focus()
      input.select()
    } else if (field === 'comment' && commentInputs.value[row]) {
      const input = commentInputs.value[row]
      input.focus()
      input.select()
    }
  })
}

const handleGradeInput = (studentId: number, value: string) => {
  const data = gridData[studentId]
  if (data) {
    const numValue = parseFloat(value)
    data.grades[0].value = isNaN(numValue) ? null : numValue
  }
}

const handleWeightInput = (studentId: number, value: string) => {
  const data = gridData[studentId]
  if (data) {
    const numValue = parseFloat(value)
    data.grades[0].weight = isNaN(numValue) ? 1.0 : numValue
  }
}

const handleCommentInput = (studentId: number, value: string) => {
  const data = gridData[studentId]
  if (data) {
    data.grades[0].comment = value || null
  }
}

const stopEdit = () => {
  if (editingCell.value) {
    const studentId = students.value[editingCell.value.row]?.id
    if (studentId) {
      markChanged()
    }
  }
  editingCell.value = null
}

const moveCell = (row: number, field: string, startEditMode = false) => {
  if (row < 0 || row >= students.value.length) return
  if (field === 'attendance') {
    selectCell(row, field)
    nextTick(() => {
      attendanceSelects.value[row]?.focus()
    })
  } else if (startEditMode) {
    selectCell(row, field)
    nextTick(() => startEdit(row, field))
  } else {
    selectCell(row, field)
  }
}

const handleEnter = (row: number, field: string) => {
  stopEdit()
  const nextRow = row + 1
  if (nextRow < students.value.length) {
    moveCell(nextRow, field, true)
  } else {
    moveCell(row, field === 'grade' ? 'weight' : field === 'weight' ? 'comment' : 'attendance', true)
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (editingCell.value) return

  if (!selectedCell.value) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Enter') {
      e.preventDefault()
      selectCell(0, 'grade')
    }
    return
  }

  const { row, field } = selectedCell.value

  switch (e.key) {
    case 'ArrowDown':
      e.preventDefault()
      moveCell(row + 1, field)
      break
    case 'ArrowUp':
      e.preventDefault()
      moveCell(row - 1, field)
      break
    case 'ArrowRight':
      e.preventDefault()
      if (field === 'grade') moveCell(row, 'weight')
      else if (field === 'weight') moveCell(row, 'comment')
      else if (field === 'comment') moveCell(row, 'attendance')
      break
    case 'ArrowLeft':
      e.preventDefault()
      if (field === 'attendance') moveCell(row, 'comment')
      else if (field === 'comment') moveCell(row, 'weight')
      else if (field === 'weight') moveCell(row, 'grade')
      break
    case 'Enter':
      e.preventDefault()
      startEdit(row, field)
      break
    case 'Delete':
    case 'Backspace':
      e.preventDefault()
      {
        const studentId = students.value[row]?.id
        if (studentId && field === 'grade') {
          const data = gridData[studentId]
          if (data) {
            data.grades[0].value = null
            markChanged()
          }
        }
      }
      break
  }
}

const handlePaste = async (e: ClipboardEvent) => {
  e.preventDefault()
  const text = e.clipboardData?.getData('text')
  if (!text || !selectedCell.value) return

  const lines = text.split('\n').filter(line => line.trim())
  if (lines.length === 0) return

  let currentRow = selectedCell.value.row
  const field = selectedCell.value.field

  for (const line of lines) {
    if (currentRow >= students.value.length) break

    const values = line.split('\t').filter(v => v.trim())
    if (values.length === 0) continue

    const studentId = students.value[currentRow]?.id
    if (!studentId) continue

    const data = gridData[studentId]
    if (!data) continue

    if (field === 'grade') {
      const value = parseFloat(values[0])
      if (!isNaN(value) && value >= 1 && value <= 5) {
        data.grades[0].value = value
        markChanged()
      }
      if (values.length > 1 && field === 'grade') {
        const weight = parseFloat(values[1])
        if (!isNaN(weight) && weight >= 0 && weight <= 100) {
          data.grades[0].weight = weight
        }
      }
    }

    currentRow++
  }

  if (currentRow < students.value.length) {
    selectCell(currentRow, field)
  }
}

const handleCopy = (e: ClipboardEvent) => {
  if (!selectedCell.value) return

  const studentId = students.value[selectedCell.value.row]?.id
  if (!studentId) return

  const data = gridData[studentId]
  if (!data) return

  const grade = data.grades[0]
  const text = grade.value?.toString() || ''

  e.clipboardData?.setData('text/plain', text)
}

const handleAttendanceChange = (studentId: number, status: string | null) => {
  const data = gridData[studentId]
  if (data) {
    data.attendance.status = status
    markChanged()
  }
}

const setAllPresent = () => {
  students.value.forEach((student) => {
    const data = gridData[student.id]
    if (data) {
      data.attendance.status = 'present'
      markChanged()
    }
  })
}

const getCellData = (studentId: number, field: string): any => {
  const data = gridData[studentId]
  if (!data) return null

  if (field === 'grade') return data.grades[0]?.value ?? null
  if (field === 'weight') return data.grades[0]?.weight ?? 1.0
  if (field === 'comment') return data.grades[0]?.comment ?? null
  if (field === 'attendance') return data.attendance?.status ?? null

  return null
}

const hasGrade = (studentId: number): boolean => {
  return gridData[studentId]?.grades[0]?.value !== null && gridData[studentId]?.grades[0]?.value !== undefined
}

const getGradeDisplay = (studentId: number): string => {
  const value = gridData[studentId]?.grades[0]?.value
  return value !== null && value !== undefined ? value.toString() : '—'
}

const getWeightDisplay = (studentId: number): string => {
  const weight = gridData[studentId]?.grades[0]?.weight
  return weight !== null && weight !== undefined ? weight.toFixed(1) : '1.0'
}

const getCommentDisplay = (studentId: number): string => {
  const comment = gridData[studentId]?.grades[0]?.comment
  return comment || '—'
}

const getGradeHistoryTooltip = (studentId: number): string => {
  const grade = grades.value[studentId]?.[0]
  if (!grade || !grade.id || !grade.createdAt) return ''
  
  const date = new Date(grade.createdAt).toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
  
  return `Оценка создана: ${date}`
}

watch(() => props.lessonId, () => {
  loadData()
}, { immediate: true })

onMounted(() => {
  loadData()
  if (gridWrapper.value) {
    gridWrapper.value.focus()
  }
})

onUnmounted(() => {
  if (saveTimer.value) {
    clearTimeout(saveTimer.value)
  }
})
</script>

<style scoped>
.journal-grid-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.journal-grid-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
  background: #fafafa;
}

.toolbar-left,
.toolbar-right {
  display: flex;
  gap: 8px;
  align-items: center;
}

.journal-grid-loading {
  padding: 20px;
}

.journal-grid-wrapper {
  flex: 1;
  overflow: auto;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  background: white;
  outline: none;
}

.journal-grid-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.journal-grid-table thead {
  background-color: #f5f5f5;
  position: sticky;
  top: 0;
  z-index: 10;
}

.journal-grid-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid rgba(0, 0, 0, 0.12);
  border-right: 1px solid rgba(0, 0, 0, 0.12);
  background-color: #f5f5f5;
}

.journal-grid-table td {
  padding: 8px 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
  border-right: 1px solid rgba(0, 0, 0, 0.12);
  position: relative;
}

.journal-grid-table tr.selected-row {
  background-color: #e3f2fd;
}

.journal-grid-table tr:hover {
  background-color: #f5f5f5;
}

.student-column {
  min-width: 200px;
  font-weight: 500;
  position: sticky;
  left: 0;
  background: white;
  z-index: 5;
}

.journal-grid-table tr.selected-row .student-column {
  background-color: #e3f2fd;
}

.grade-column,
.weight-column {
  min-width: 80px;
  text-align: center;
}

.comment-column {
  min-width: 200px;
}

.attendance-column {
  min-width: 150px;
}

.editable {
  cursor: cell;
  position: relative;
  user-select: none;
}

.editable.selected-cell {
  background-color: #bbdefb;
  outline: 2px solid #1976d2;
  outline-offset: -2px;
}

.editable.editing {
  background-color: #e3f2fd;
}

.editable:hover:not(.editing) {
  background-color: #f0f0f0;
}

.grade-column.has-grade .grade-value {
  font-weight: 600;
  color: #1976d2;
}

.cell-input {
  width: 100%;
  border: 2px solid #1976d2;
  border-radius: 2px;
  padding: 4px 8px;
  font-size: 14px;
  text-align: center;
  background: white;
  outline: none;
}

.cell-input:focus {
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2);
}

.attendance-select {
  width: 100%;
  padding: 4px 8px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  background: white;
  outline: none;
}

.attendance-select:focus {
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2);
}

.grade-value,
.weight-value {
  display: inline-block;
  min-width: 40px;
}

.comment-value {
  color: #666;
  font-size: 13px;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>

