import { ref } from 'vue'

export function useButtonLoader() {
  const loading = ref(false)

  async function withLoading(asyncFn) {
    loading.value = true
    try {
      return await asyncFn()
    } finally {
      loading.value = false
    }
  }

  return { loading, withLoading }
}
