<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center gap-3 mb-6">
      <v-btn
        icon="mdi-arrow-left"
        variant="text"
        rounded="lg"
        @click="router.back()"
      />
      <div class="flex-grow-1">
        <div class="d-flex align-center gap-2">
          <h1 class="text-h5 font-weight-bold">{{ productName }}</h1>
          <v-chip size="small" color="primary" variant="tonal" rounded="lg">
            Units
          </v-chip>
        </div>
        <p class="text-caption text-medium-emphasis mt-1">
          Manage selling units — can, pack, box, pallet etc.
        </p>
      </div>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        Add Unit
      </v-btn>
    </div>

    <!-- Info banner -->
    <v-alert
      type="info"
      variant="tonal"
      density="compact"
      rounded="lg"
      class="mb-4"
    >
      <strong>How units work:</strong>
      Stock is always tracked in the
      <strong>base unit</strong>
      (e.g. "can"). Selling a box of 24 deducts 24 cans from stock.
    </v-alert>

    <!-- Units table -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table
        :headers="headers"
        :items="unitStore.units"
        :loading="unitStore.loading"
        item-value="id"
      >
        <!-- Unit name -->
        <template #item.unit_name="{ item }">
          <div class="d-flex align-center gap-2">
            <div>
              <div class="text-body-2 font-weight-bold">
                {{ item.unit_label || item.unit_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.unit_name }}
              </div>
            </div>
            <v-chip
              v-if="item.is_base_unit"
              size="x-small"
              color="success"
              variant="tonal"
              rounded="lg"
            >
              BASE
            </v-chip>
          </div>
        </template>

        <!-- Qty per base -->
        <template #item.qty_per_base="{ item }">
          <v-chip size="small" variant="tonal" rounded="lg">
            × {{ item.qty_per_base }}
          </v-chip>
        </template>

        <!-- Barcode -->
        <template #item.barcode="{ item }">
          <div v-if="item.barcode" class="d-flex align-center gap-1">
            <v-icon icon="mdi-barcode" size="14" />
            <span class="text-caption font-weight-medium">
              {{ item.barcode }}
            </span>
          </div>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Retail price -->
        <template #item.retail_price="{ item }">
          <span class="font-weight-bold text-body-2">
            {{ fmt(item.retail_price) }}
          </span>
        </template>

        <!-- Wholesale price -->
        <template #item.wholesale_price="{ item }">
          <span
            v-if="item.wholesale_price"
            class="text-body-2 text-success font-weight-bold"
          >
            {{ fmt(item.wholesale_price) }}
          </span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Margin -->
        <template #item.margin="{ item }">
          <div v-if="item.cost_price">
            <div class="text-body-2 font-weight-bold text-primary">
              {{ marginPct(item.retail_price, item.cost_price) }}%
            </div>
            <div class="text-caption text-medium-emphasis">
              cost {{ fmt(item.cost_price) }}
            </div>
          </div>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Active -->
        <template #item.is_active="{ item }">
          <v-switch
            :model-value="item.is_active"
            color="success"
            density="compact"
            hide-details
            @change="toggleActive(item)"
          />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
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

        <!-- Empty -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon
              icon="mdi-package-variant-remove"
              size="64"
              color="grey-lighten-1"
              class="mb-3"
            />
            <div class="text-body-1 font-weight-medium text-grey">
              No units yet
            </div>
            <p class="text-caption text-grey mb-4">
              Add units like Can, Pack, Box to enable multi-unit selling
            </p>
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-plus"
              @click="openCreate"
            >
              Add First Unit
            </v-btn>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Unit Dialog -->
    <ProductUnitDialog
      v-model="dialog"
      :unit="selectedUnit"
      :loading="saving"
      @save="handleSave"
    />

    <!-- Delete Confirm -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl" border elevation="0">
        <v-card-title class="pa-5">
          <div class="d-flex align-center gap-3">
            <v-avatar color="error" variant="tonal" size="40" rounded="lg">
              <v-icon icon="mdi-delete-outline" />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">Delete Unit?</div>
              <div class="text-caption text-medium-emphasis">
                {{ deleteTarget?.unit_label || deleteTarget?.unit_name }}
              </div>
            </div>
          </div>
        </v-card-title>
        <v-card-actions class="pa-5 gap-3">
          <v-btn variant="tonal" rounded="lg" @click="deleteDialog = false">
            Cancel
          </v-btn>
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
  import { useRoute, useRouter } from 'vue-router'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import ProductUnitDialog from '@/components/products/ProductUnitDialog.vue'

  const route = useRoute()
  const router = useRouter()
  const unitStore = useProductUnitStore()
  const { notif } = useAppUtils()

  const productId = computed(() => route.params.id)
  const productName = computed(() => route.query.name ?? 'Product')

  const dialog = ref(false)
  const deleteDialog = ref(false)
  const selectedUnit = ref(null)
  const deleteTarget = ref(null)
  const saving = ref(false)
  const deleting = ref(false)

  const headers = [
    { title: 'Unit', key: 'unit_name', sortable: false },
    { title: 'Qty / Base', key: 'qty_per_base', sortable: false },
    { title: 'Barcode', key: 'barcode', sortable: false },
    { title: 'Retail Price', key: 'retail_price', sortable: false },
    { title: 'Wholesale Price', key: 'wholesale_price', sortable: false },
    { title: 'Margin', key: 'margin', sortable: false },
    { title: 'Active', key: 'is_active', sortable: false },
    { title: '', key: 'actions', sortable: false, width: '80' }
  ]

  const fmt = v =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(v ?? 0)
  const marginPct = (sell, cost) => (((sell - cost) / sell) * 100).toFixed(1)

  const openCreate = () => {
    selectedUnit.value = null
    dialog.value = true
  }
  const openEdit = u => {
    selectedUnit.value = u
    dialog.value = true
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await unitStore.updateUnit(productId.value, payload.id, payload)
        notif('Unit updated', { type: 'success' })
      } else {
        await unitStore.createUnit(productId.value, payload)
        notif('Unit added', { type: 'success' })
      }
      dialog.value = false
    } catch (err) {
      const msg = err.response?.data?.message ?? 'Failed to save unit'
      notif(msg, { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const toggleActive = async unit => {
    await unitStore.updateUnit(productId.value, unit.id, {
      is_active: !unit.is_active
    })
  }

  const confirmDelete = u => {
    deleteTarget.value = u
    deleteDialog.value = true
  }
  const doDelete = async () => {
    deleting.value = true
    try {
      await unitStore.deleteUnit(productId.value, deleteTarget.value.id)
      notif('Unit deleted', { type: 'success' })
      deleteDialog.value = false
    } catch (err) {
      notif(err.response?.data?.message ?? 'Failed to delete', {
        type: 'error'
      })
    } finally {
      deleting.value = false
    }
  }

  onMounted(() => unitStore.fetchUnits(productId.value))
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
