import { ref, watch } from 'vue'

export function useDataTable(fetchFn, extraParams = null) {
  const options = ref({ page: 1, itemsPerPage: 10, sortBy: [] })

  function buildParams() {
    const { page, itemsPerPage, sortBy } = options.value
    const sort = sortBy?.[0]
      ? { sort_by: sortBy[0].key, sort_order: sortBy[0].order }
      : {}

    return {
      page,
      per_page: itemsPerPage,
      ...sort,
      ...(typeof extraParams === 'function'
        ? extraParams()
        : (extraParams ?? {}))
    }
  }

  async function fetchOnOptions(tableOptions) {
    options.value = tableOptions
    await fetchFn(buildParams())
  }

  async function refresh() {
    await fetchFn(buildParams())
  }

  // re-fetch when filters change
  if (extraParams) {
    watch(
      typeof extraParams === 'function' ? extraParams : () => extraParams,
      () => {
        options.value.page = 1
        refresh()
      },
      { deep: true }
    )
  }

  return { fetchOnOptions, refresh }
}
