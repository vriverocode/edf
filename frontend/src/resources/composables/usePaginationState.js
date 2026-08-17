import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function usePaginationState({ key = 'page', filters = [], pageRef } = {}) {
  const route = useRoute()
  const router = useRouter()

  const page = pageRef || ref(1)
  page.value = Number(route.query[key]) || 1

  const getValue = (entry) => {
    if (entry.ref) return entry.ref.value
    return entry.get()
  }

  const setValue = (entry, value) => {
    if (entry.ref) entry.ref.value = value
    else entry.set(value)
  }

  const restoreFromQuery = () => {
    filters.forEach((entry) => {
      if (route.query[entry.key] === undefined) return
      const raw = route.query[entry.key]
      setValue(entry, entry.parse ? entry.parse(raw) : raw)
    })
  }

  const syncToUrl = (overrides = {}) => {
    const query = { ...route.query }
    filters.forEach((entry) => {
      const value = getValue(entry)
      if (value === '' || value === null || value === undefined) {
        delete query[entry.key]
      } else if (Array.isArray(value)) {
        query[entry.key] = value.map(String)
      } else {
        query[entry.key] = value
      }
    })
    router.replace({ query: { ...query, [key]: page.value, ...overrides } })
  }

  const onPageChange = (fetchFn, overrides = {}) => {
    router.replace({ query: { ...route.query, [key]: page.value, ...overrides } })
    if (fetchFn) fetchFn()
  }

  return { page, restoreFromQuery, syncToUrl, onPageChange }
}