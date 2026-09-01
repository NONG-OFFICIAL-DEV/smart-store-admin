<template>
  <div>
    <custom-title
      icon="mdi-tree"
      :title="$t('ingredients.title')"
      :subtitle="$t('ingredients.subtitle')"
    >
      <template #right>
        <v-btn
          :color="showFilters ? 'primary' : 'default'"
          :variant="showFilters ? 'flat' : 'tonal'"
          rounded="lg"
          :prepend-icon="
            showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
          "
          @click="showFilters = !showFilters"
        >
          {{ $t('btn.filter') }}
          <!-- badge shows how many filters are active -->
          <v-badge
            v-if="activeFilterCount > 0"
            :content="activeFilterCount"
            color="error"
            floating
          />
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          class="ms-2"
          @click="openCreate"
        >
          {{ $t('ingredients.add') }}
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
      <v-col v-for="s in statCards" :key="s.label" cols="6" sm="3">
        <v-card rounded="lg" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ s.label }}</span>
            <v-icon :icon="s.icon" :color="s.color" size="18" />
          </div>
          <div class="text-h6 font-weight-bold" :class="`text-${s.color}`">
            {{ s.value }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="lg" border elevation="0" class="mb-4">
        <v-card-text class="pa-4">
          <v-row dense align="center">
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="filters.search"
                :placeholder="$t('ingredients.search_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                prepend-inner-icon="mdi-magnify"
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="6" sm="2">
              <v-select
                v-model="filters.category"
                :items="categoryOptions"
                :placeholder="$t('form.category')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
              />
            </v-col>
            <v-col cols="6" sm="2">
              <v-select
                v-model="filters.is_active"
                :items="activeOptions"
                item-title="label"
                item-value="value"
                :placeholder="$t('form.status')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
              />
            </v-col>
            <v-col cols="6" sm="2">
              <v-select
                v-model="filters.low_stock"
                :items="[{ label: $t('ingredients.low_stock_only'), value: true }]"
                item-title="label"
                item-value="value"
                :placeholder="$t('ingredients.stock_alert')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4">
          <v-spacer />
          <v-btn
            v-if="hasActiveFilters"
            rounded="lg"
            variant="tonal"
            color="error"
            prepend-icon="mdi-close"
            @click="resetFilters"
          >
            {{ $t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ $t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="ingredientStore.ingredients"
        :items-length="ingredientStore.pagination?.total ?? 0"
        :loading="ingredientStore.loading"
        :items-per-page="filters.perPage"
        :page="filters.page"
        item-value="id"
        @update:page="
          p => {
            filters.page = p
            load()
          }
        "
        @update:items-per-page="
          p => {
            filters.perPage = p
            filters.page = 1
            load()
          }
        "
      >
        <!-- Name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar color="primary" variant="tonal" size="32" rounded="md">
              <v-icon icon="mdi-flask-outline" size="16" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
              <div
                v-if="item.barcode"
                class="text-caption text-medium-emphasis"
              >
                {{ item.barcode }}
              </div>
            </div>
          </div>
        </template>

        <!-- Category -->
        <template #item.category="{ item }">
          <v-chip
            v-if="item.category"
            size="x-small"
            rounded="lg"
            variant="tonal"
            color="info"
          >
            {{ item.category }}
          </v-chip>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Unit -->
        <template #item.unit="{ item }">
          <v-chip size="x-small" rounded="lg" variant="outlined">
            {{ item.unit }}
          </v-chip>
        </template>

        <!-- Unit Cost -->
        <template #item.unit_cost="{ item }">
          <span class="text-body-2">
            {{ item.unit_cost ? fmt(item.unit_cost) : '—' }}
          </span>
        </template>

        <!-- Reorder Point -->
        <template #item.reorder_point="{ item }">
          <div v-if="item.reorder_point">
            <span class="text-body-2">
              {{ item.reorder_point }} {{ item.unit }}
            </span>
            <div class="text-caption text-medium-emphasis">
              {{
                $t('ingredients.reorder_qty', {
                  qty: item.reorder_quantity ?? '—'
                })
              }}
            </div>
          </div>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Supplier -->
        <template #item.preferred_supplier="{ item }">
          <span v-if="item.preferred_supplier" class="text-body-2">
            {{ item.preferred_supplier.name }}
          </span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Status -->
        <template #item.is_active="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="small" />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
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
              @click="confirmDelete(item)"
            />
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- ── Create/Edit Dialog ─────────────────────────────────────────────── -->
    <IngredientDialog
      v-model="dialog"
      :ingredient="selected"
      :loading="saving"
      @save="handleSave"
    />

    <!-- ── Delete Confirm ─────────────────────────────────────────────────── -->
    <AppDialog
      v-model="deleteDialog"
      :max-width="400"
      :persistent="false"
      :scrollable="false"
      :title="$t('ingredients.delete_title')"
      :subtitle="deleteTarget?.name"
      icon="mdi-delete-outline"
      color="error"
      :loading="deleting"
      :submit-text="$t('btn.delete')"
      @submit="doDelete"
    >
      {{ $t('ingredients.delete_body') }}
    </AppDialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useIngredientStore } from '@/stores/ingredientStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { AppStatusChip } from '@nong-official-dev/core'
  import IngredientDialog from '@/components/ingredients/IngredientDialog.vue'
  import AppDialog from '@/components/common/AppDialog.vue'

  const ingredientStore = useIngredientStore()
  const { notif } = useAppUtils()
  const { t } = useI18n()

  const dialog = ref(false)
  const deleteDialog = ref(false)
  const selected = ref(null)
  const deleteTarget = ref(null)
  const saving = ref(false)
  const deleting = ref(false)

  const filters = ref({
    search: '',
    category: null,
    is_active: null,
    low_stock: null,
    perPage: 15,
    page: 1
  })
  const showFilters = ref(false)

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (filters.value.search?.trim()) count++
    if (filters.value.category) count++
    if (filters.value.is_active !== null && filters.value.is_active !== undefined)
      count++
    if (filters.value.low_stock) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const statCards = computed(() => {
    const items = ingredientStore.ingredients
    return [
      {
        label: t('common.total'),
        value: ingredientStore.pagination?.total ?? 0,
        color: 'primary',
        icon: 'mdi-flask-outline'
      },
      {
        label: t('status.active'),
        value: items.filter(i => i.is_active).length,
        color: 'success',
        icon: 'mdi-check-circle-outline'
      },
      {
        label: t('status.inactive'),
        value: items.filter(i => !i.is_active).length,
        color: 'grey',
        icon: 'mdi-close-circle-outline'
      },
      {
        label: t('inventory.low_stock'),
        value: items.filter(
          i => i.reorder_point && i.stock_quantity <= i.reorder_point
        ).length,
        color: 'warning',
        icon: 'mdi-alert-outline'
      }
    ]
  })

  // ── Headers ───────────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('form.name'), key: 'name', sortable: true },
    { title: t('form.category'), key: 'category', sortable: true },
    { title: t('form.unit'), key: 'unit', sortable: false },
    { title: t('ingredients.unit_cost'), key: 'unit_cost', sortable: true },
    { title: t('ingredients.reorder'), key: 'reorder_point', sortable: false },
    { title: t('form.supplier'), key: 'preferred_supplier', sortable: false },
    { title: t('form.status'), key: 'is_active', sortable: false },
    { title: '', key: 'actions', sortable: false, width: '80' }
  ])

  // ── Options ───────────────────────────────────────────────────────────────────
  const categoryOptions = computed(() => {
    const cats = [
      ...new Set(
        ingredientStore.ingredients.map(i => i.category).filter(Boolean)
      )
    ]
    return cats
  })

  const activeOptions = computed(() => [
    { label: t('status.active'), value: 1 },
    { label: t('status.inactive'), value: 0 }
  ])

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const fmt = v =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 4
    }).format(v ?? 0)

  const load = () => ingredientStore.fetchIngredients(filters.value)

  // ── Reset to page 1 and reload when filters change ───────────────────────────
  const onFilterChange = () => {
    filters.value.page = 1
    load()
  }

  const resetFilters = () => {
    filters.value = {
      search: '',
      category: null,
      is_active: null,
      low_stock: null,
      perPage: 15,
      page: 1
    }
    load()
  }

  // ── CRUD ──────────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selected.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selected.value = item
    dialog.value = true
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await ingredientStore.updateIngredient(payload.id, payload)
        notif(t('ingredients.updated_success'), { type: 'success' })
      } else {
        await ingredientStore.createIngredient(payload)
        notif(t('ingredients.created_success'), { type: 'success' })
      }
      dialog.value = false
    } catch {
      notif(t('ingredients.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = item => {
    deleteTarget.value = item
    deleteDialog.value = true
  }
  const doDelete = async () => {
    deleting.value = true
    try {
      await ingredientStore.deleteIngredient(deleteTarget.value.id)
      notif(t('ingredients.deleted_success'), { type: 'success' })
      deleteDialog.value = false
    } catch {
      notif(t('ingredients.delete_failed'), { type: 'error' })
    } finally {
      deleting.value = false
    }
  }

  onMounted(() => load())
</script>
