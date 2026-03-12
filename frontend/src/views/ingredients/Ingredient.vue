<template>
  <div>
    <custom-title
      icon="mdi-tree"
      title="Ingredients"
      subtitle="Manage raw materials and stock levels"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          Add Ingredient
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
    <v-card rounded="lg" border elevation="0" class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="filters.search"
              placeholder="Search ingredients..."
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-magnify"
              @update:model-value="onSearch"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="filters.category"
              :items="categoryOptions"
              placeholder="Category"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="filters.is_active"
              :items="activeOptions"
              item-title="label"
              item-value="value"
              placeholder="Status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="filters.low_stock"
              :items="[{ label: 'Low Stock Only', value: true }]"
              item-title="label"
              item-value="value"
              placeholder="Stock Alert"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>
          <v-col cols="6" sm="2" class="d-flex justify-end">
            <v-btn
              variant="tonal"
              rounded="lg"
              size="small"
              icon="mdi-filter-off-outline"
              @click="resetFilters"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="ingredientStore.ingredients"
        :items-length="ingredientStore.pagination?.total ?? 0"
        :loading="ingredientStore.loading"
        :items-per-page="filters.per_page"
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
            filters.per_page = p
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
              Reorder qty: {{ item.reorder_quantity ?? '—' }}
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
          <v-chip
            size="small"
            rounded="lg"
            variant="tonal"
            :color="item.is_active ? 'success' : 'error'"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
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
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl" border elevation="0">
        <v-card-title class="pa-5">
          <div class="d-flex align-center gap-3">
            <v-avatar color="error" variant="tonal" size="40" rounded="lg">
              <v-icon icon="mdi-delete-outline" />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">Delete Ingredient?</div>
              <div class="text-caption text-medium-emphasis">
                {{ deleteTarget?.name }}
              </div>
            </div>
          </div>
        </v-card-title>
        <v-card-text class="px-5 pb-2 text-body-2">
          This cannot be undone. Ingredients used in recipes or purchase orders
          cannot be deleted.
        </v-card-text>
        <v-card-actions class="pa-5 gap-3">
          <v-btn variant="tonal" rounded="lg" @click="deleteDialog = false">
            Cancel
          </v-btn>
          <v-spacer />
          <v-btn
            color="error"
            variant="flat"
            rounded="lg"
            :loading="deleting"
            @click="doDelete"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useIngredientStore } from '@/stores/ingredientStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import IngredientDialog from '@/components/ingredients/IngredientDialog.vue'

  const ingredientStore = useIngredientStore()
  const { notif } = useAppUtils()

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
    per_page: 15,
    page: 1
  })

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const statCards = computed(() => {
    const items = ingredientStore.ingredients
    return [
      {
        label: 'Total',
        value: ingredientStore.pagination?.total ?? 0,
        color: 'primary',
        icon: 'mdi-flask-outline'
      },
      {
        label: 'Active',
        value: items.filter(i => i.is_active).length,
        color: 'success',
        icon: 'mdi-check-circle-outline'
      },
      {
        label: 'Inactive',
        value: items.filter(i => !i.is_active).length,
        color: 'grey',
        icon: 'mdi-close-circle-outline'
      },
      {
        label: 'Low Stock',
        value: items.filter(
          i => i.reorder_point && i.stock_quantity <= i.reorder_point
        ).length,
        color: 'warning',
        icon: 'mdi-alert-outline'
      }
    ]
  })

  // ── Headers ───────────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Name', key: 'name', sortable: true },
    { title: 'Category', key: 'category', sortable: true },
    { title: 'Unit', key: 'unit', sortable: false },
    { title: 'Unit Cost', key: 'unit_cost', sortable: true },
    { title: 'Reorder', key: 'reorder_point', sortable: false },
    { title: 'Supplier', key: 'preferred_supplier', sortable: false },
    { title: 'Status', key: 'is_active', sortable: false },
    { title: '', key: 'actions', sortable: false, width: '80' }
  ]

  // ── Options ───────────────────────────────────────────────────────────────────
  const categoryOptions = computed(() => {
    const cats = [
      ...new Set(
        ingredientStore.ingredients.map(i => i.category).filter(Boolean)
      )
    ]
    return cats
  })

  const activeOptions = [
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 }
  ]

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const fmt = v =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 4
    }).format(v ?? 0)

  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => load(), 400)
  }

  const load = () => ingredientStore.fetchIngredients(filters.value)

  const resetFilters = () => {
    filters.value = {
      search: '',
      category: null,
      is_active: null,
      low_stock: null,
      per_page: 15,
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
        notif('Ingredient updated', { type: 'success' })
      } else {
        await ingredientStore.createIngredient(payload)
        notif('Ingredient created', { type: 'success' })
      }
      dialog.value = false
    } catch {
      notif('Failed to save ingredient', { type: 'error' })
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
      notif('Ingredient deleted', { type: 'success' })
      deleteDialog.value = false
    } catch {
      notif('Cannot delete — ingredient may be in use', { type: 'error' })
    } finally {
      deleting.value = false
    }
  }

  onMounted(() => load())
</script>
