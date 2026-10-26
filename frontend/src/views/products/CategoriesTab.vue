<template>
  <div>
    <div class="d-flex align-center justify-end flex-wrap ga-2 mb-4">
      <v-btn
        :color="reorderMode ? 'primary' : 'default'"
        :variant="reorderMode ? 'flat' : 'outlined'"
        rounded="lg"
        class="text-none"
        :prepend-icon="reorderMode ? 'mdi-check' : 'mdi-sort'"
        @click="toggleReorderMode"
      >
        {{ reorderMode ? t('products.reorder.done') : t('products.reorder.toggle') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        class="text-none"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        {{ t('categories.tab.add_category') }}
      </v-btn>
    </div>

    <!-- Drag-reorder — toggled explicitly, same pattern as the Products
         tab's own reorder mode: a full, unpaginated, sort_order-sorted list
         swapped in for AppTable's paginated/searchable view. -->
    <v-card v-if="reorderMode" rounded="lg" elevation="0" border class="pa-4">
      <v-alert type="info" variant="tonal" density="compact" rounded="lg" class="mb-3">
        {{ t('products.reorder.hint') }}
      </v-alert>

      <v-progress-linear v-if="reorderLoading" indeterminate class="mb-3" />

      <draggable
        v-model="allCategories"
        item-key="id"
        handle=".drag-handle"
        tag="div"
        :disabled="reorderLoading || reorderSaving"
        @end="onReorderEnd"
      >
        <template #item="{ element: item }">
          <div
            class="category-row d-flex align-center ga-3 py-2 px-2"
            :class="isHidden(item) ? 'opacity-60' : ''"
          >
            <v-icon icon="mdi-drag-vertical" class="drag-handle" style="cursor: grab" />
            <div class="flex-grow-1" style="min-width: 0">
              <div class="font-weight-medium text-truncate">{{ item.name }}</div>
              <div v-if="item.name_km" class="text-caption text-medium-emphasis text-truncate">
                {{ item.name_km }}
              </div>
            </div>
          </div>
        </template>
      </draggable>

      <div v-if="!reorderLoading && !allCategories.length" class="text-center py-10">
        <p class="text-medium-emphasis">{{ t('categories.tab.empty') }}</p>
      </div>
    </v-card>

    <!-- Table view — same AppTable contract used across the rest of the
         app (headers + fetch-fn -> { items, total }). -->
    <v-card v-else rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchCategoriesForTable"
        :show-search="true"
        :item-label="t('categories.title')"
      >
        <template #item.name="{ item }">
          <div :class="isHidden(item) ? 'opacity-60' : ''">
            <div class="font-weight-medium">{{ item.name }}</div>
            <div v-if="item.name_km" class="text-caption text-medium-emphasis">
              {{ item.name_km }}
            </div>
          </div>
        </template>

        <template #item.products_count="{ item }">
          <v-btn
            variant="text"
            density="compact"
            class="text-none"
            color="primary"
            @click="$emit('view-products', item.id)"
          >
            {{ t('categories.tab.products_count', item.products_count ?? 0) }}
          </v-btn>
        </template>

        <template #item.show_on_pos="{ item }">
          <v-switch
            :model-value="item.show_on_pos"
            color="success"
            hide-details
            @update:model-value="val => toggleField(item, 'show_on_pos', val)"
          />
        </template>

        <template #item.show_on_emenu="{ item }">
          <v-switch
            :model-value="item.show_on_emenu"
            color="success"
            hide-details
            @update:model-value="val => toggleField(item, 'show_on_emenu', val)"
          />
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openEdit(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="openDeleteFlow(item)"
            />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon icon="mdi-shape-outline" size="48" color="grey-lighten-1" />
            <p class="text-medium-emphasis mt-3">{{ t('categories.tab.empty') }}</p>
            <v-btn color="primary" variant="tonal" class="mt-3 text-none" prepend-icon="mdi-plus" @click="openCreate">
              {{ t('categories.tab.add_category') }}
            </v-btn>
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- Rendered at this component's root, not nested in the table/card, so
         the drawer overlay always covers the full screen. -->
    <CategoryDrawer
      v-model="drawerOpen"
      :category="selectedCategory"
      :loading="saving"
      @saved="handleSaved"
    />

    <!-- Delete with no products is a plain confirm() (see openDeleteFlow);
         this dialog only ever opens for the has-products case, where a
         destination category must be picked first. -->
    <v-dialog v-model="reassignDeleteOpen" max-width="460">
      <v-card rounded="lg">
        <v-card-title class="text-body-1 font-weight-bold">
          {{ t('categories.tab.delete_title') }}
        </v-card-title>
        <v-card-text>
          <div class="mb-3">
            {{ deleteMessageUsed }}
          </div>
          <v-select
            v-model="reassignTo"
            :items="otherCategories"
            item-title="name"
            item-value="id"
            :label="t('categories.tab.reassign_label') + ' *'"
            variant="outlined"
            rounded="lg"
            density="comfortable"
            hide-details
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="reassignDeleteOpen = false">
            {{ t('btn.cancel') }}
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            class="text-none"
            :disabled="!reassignTo"
            :loading="deleting"
            @click="confirmReassignDelete"
          >
            {{ t('categories.tab.delete_category') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppTable, useAppUtils } from '@nong-official-dev/core'
  import draggable from 'vuedraggable'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { getAllCategoriesApi } from '@/api/categoryService'
  import CategoryDrawer from '@/components/products/CategoryDrawer.vue'

  const { t } = useI18n()
  const { notif, confirm } = useAppUtils()
  const categoryStore = useCategoryStore()

  defineEmits(['view-products'])

  const tableRef = ref(null)

  const headers = [
    { title: t('categories.tab.header_name'), key: 'name', sortable: true },
    { title: t('categories.tab.header_products'), key: 'products_count', sortable: false },
    { title: t('categories.tab.show_on_pos'), key: 'show_on_pos', sortable: false },
    { title: t('categories.tab.show_on_emenu'), key: 'show_on_emenu', sortable: false },
    { title: t('categories.tab.header_actions'), key: 'actions', sortable: false, align: 'end' }
  ]

  // Deliberately bypasses categoryStore.fetchCategories() — that action
  // writes its result into the shared categoryStore.categories array, which
  // ProductManagement.vue's own category filter (and this tab's reassign
  // dropdown) expect to always hold the FULL unpaginated list. Routing
  // AppTable's page-sized requests through it would stomp that shared state
  // with whatever 10-ish-row page was last fetched here.
  const fetchCategoriesForTable = async params => {
    const { data } = await getAllCategoriesApi(params)
    return { items: data.data, total: data.meta.total }
  }

  function isHidden(item) {
    return !item.show_on_pos && !item.show_on_emenu
  }

  // The full, unpaginated, sort_order-sorted list — used both as the
  // reorder-mode draggable source and as the reassignment drawer's "move
  // products to" options. Kept entirely separate from categoryStore's own
  // `categories`/`pagination` state (which other pages, e.g.
  // ProductManagement.vue's filter, rely on holding THEIR OWN full list) so
  // this tab's table pagination and reorder fetches never stomp on it.
  const allCategories = ref([])
  const reorderLoading = ref(false)

  async function loadAllCategories() {
    reorderLoading.value = true
    try {
      const { data } = await getAllCategoriesApi({
        perPage: -1,
        sortBy: 'sort_order',
        sortDesc: false
      })
      allCategories.value = data.data
    } finally {
      reorderLoading.value = false
    }
  }

  // ── Reorder mode ──────────────────────────────────────────────────────────
  const reorderMode = ref(false)
  const reorderSaving = ref(false)

  function toggleReorderMode() {
    reorderMode.value = !reorderMode.value
    // No manual refresh needed on exit — AppTable is freshly (re)mounted by
    // the v-if/v-else swap and loads itself immediately (see its own
    // `watch(options, load, { immediate: true })`), same as
    // ProductManagement.vue's own reorder-mode toggle.
    if (reorderMode.value) loadAllCategories()
  }

  async function onReorderEnd() {
    reorderSaving.value = true
    try {
      await categoryStore.reorderCategories(allCategories.value.map(c => c.id))
      notif(t('categories.tab.reorder_saved'), { type: 'success' })
    } catch {
      notif(t('categories.tab.reorder_failed'), { type: 'error' })
      await loadAllCategories() // resync to the server's actual order
    } finally {
      reorderSaving.value = false
    }
  }

  // ── Toggle a visibility switch — optimistic, reverts on failure ──────────
  async function toggleField(item, field, value) {
    const previous = item[field]
    item[field] = value
    try {
      await categoryStore.updateCategory(item.id, { [field]: value })
    } catch {
      item[field] = previous
      notif(t('categories.tab.update_failed'), { type: 'error' })
    }
  }

  // ── Drawer ────────────────────────────────────────────────────────────────
  const drawerOpen = ref(false)
  const selectedCategory = ref(null)
  const saving = ref(false)

  function openCreate() {
    selectedCategory.value = null
    drawerOpen.value = true
  }
  function openEdit(item) {
    selectedCategory.value = { ...item }
    drawerOpen.value = true
  }

  async function handleSaved(payload, callbacks) {
    saving.value = true
    try {
      if (payload.id) {
        await categoryStore.updateCategory(payload.id, payload)
      } else {
        await categoryStore.createCategory(payload)
      }
      tableRef.value?.refresh()
      await loadAllCategories()
      notif(
        payload.id ? t('categories.tab.updated') : t('categories.tab.created'),
        { type: 'success' }
      )
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      if (err?.response?.status !== 422) {
        notif(t('categories.tab.save_failed'), { type: 'error' })
      }
    } finally {
      saving.value = false
    }
  }

  // ── Delete — simple confirm when empty, reassignment dialog when the
  // category still has products (products.category_id is restrictOnDelete
  // at the DB level, so a plain delete would otherwise just 500). ─────────
  const deleting = ref(false)
  const categoryToDelete = ref(null)
  const reassignDeleteOpen = ref(false)
  const reassignTo = ref(null)

  const otherCategories = computed(() =>
    allCategories.value.filter(c => c.id !== categoryToDelete.value?.id)
  )

  // vue-i18n's t(key, count, namedObject) 3-arg overload does NOT actually
  // wire namedObject into message interpolation (only `count`'s own
  // reserved `{n}` resolves) — see parseTranslateArgs, the plain-object 3rd
  // arg gets flat-merged onto the options bag, never assigned to
  // `options.named`. So `{name}` silently rendered empty. Picking the
  // one/other key in JS and calling t(key, namedObject) (the 2-arg form)
  // sidesteps that entirely.
  const deleteMessageUsed = computed(() => {
    const n = categoryToDelete.value?.products_count ?? 0
    const key = n === 1 ? 'categories.tab.delete_message_used_one' : 'categories.tab.delete_message_used_other'
    return t(key, { n, name: categoryToDelete.value?.name })
  })

  function openDeleteFlow(item) {
    categoryToDelete.value = item
    if ((item.products_count ?? 0) > 0) {
      reassignTo.value = null
      reassignDeleteOpen.value = true
      return
    }
    confirm({
      title: t('categories.tab.delete_title'),
      message: t('categories.tab.delete_message_unused', { name: item.name }),
      options: { type: 'warning', color: 'warning', width: 420 },
      agree: async () => {
        deleting.value = true
        try {
          await categoryStore.deleteCategory(item.id)
          await afterDelete()
        } catch {
          notif(t('categories.tab.delete_failed'), { type: 'error' })
        } finally {
          deleting.value = false
        }
      }
    })
  }

  async function confirmReassignDelete() {
    if (!reassignTo.value) return
    deleting.value = true
    try {
      await categoryStore.deleteCategoryWithReassignment(categoryToDelete.value.id, reassignTo.value)
      reassignDeleteOpen.value = false
      await afterDelete()
    } catch {
      notif(t('categories.tab.delete_failed'), { type: 'error' })
    } finally {
      deleting.value = false
    }
  }

  async function afterDelete() {
    tableRef.value?.refresh()
    await loadAllCategories()
    notif(t('messages.deleted_success'), { type: 'success' })
  }

  onMounted(() => {
    loadAllCategories()
  })
</script>

<style scoped>
  .category-row + .category-row {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
</style>
