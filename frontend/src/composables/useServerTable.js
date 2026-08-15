import { ref, watch } from 'vue'

export function useDataTable(fetchFn, extraParams = null) {
  const options = ref({ page: 1, itemsPerPage: 10, sortBy: [] })
  let isFetching = false // ✅ simple flag, no ref needed

  function buildParams() {
    const { page, itemsPerPage, sortBy } = options.value
    const sort = sortBy?.[0]
      ? { sortBy: sortBy[0].key, sortDesc: sortBy[0].order === 'desc' }
      : {}

    return {
      page,
      perPage: itemsPerPage,
      ...sort,
      ...(typeof extraParams === 'function'
        ? extraParams()
        : (extraParams ?? {}))
    }
  }

  async function fetchOnOptions(tableOptions) {
    if (isFetching) return // ✅ prevent concurrent calls

    // ✅ update fields individually, not replace the whole object
    options.value.page = tableOptions.page
    options.value.itemsPerPage = tableOptions.itemsPerPage
    options.value.sortBy = tableOptions.sortBy ?? []

    isFetching = true
    try {
      await fetchFn(buildParams())
    } finally {
      isFetching = false
    }
  }

  async function refresh() {
    if (isFetching) return
    isFetching = true
    try {
      await fetchFn(buildParams())
    } finally {
      isFetching = false
    }
  }

  if (extraParams) {
    let isFirst = true
    watch(
      typeof extraParams === 'function' ? extraParams : () => extraParams,
      () => {
        if (isFirst) {
          isFirst = false
          return
        } // ✅ skip mount trigger
        options.value.page = 1
        refresh()
      },
      { deep: true }
    )
  }

  return { fetchOnOptions, refresh }
}
