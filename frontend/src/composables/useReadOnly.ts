import { computed } from 'vue'
import { useReadOnlyStore } from '../stores/readOnly'

export function useReadOnly() {
  const readOnly = useReadOnlyStore()

  const isReadOnly = computed(() => readOnly.isEnabled)

  const disableIfReadOnly = (action: () => void) => {
    if (readOnly.isEnabled) {
      return
    }
    action()
  }

  return {
    isReadOnly,
    disableIfReadOnly,
  }
}

