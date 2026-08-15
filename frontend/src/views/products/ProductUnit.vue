<template>
  <div>
    <!-- Header -->
    <AppPageHeader
      :title="t('unit.page_title')"
      show-back
      :breadcrumbs="[
        { title: 'Products', to: '/products' },
        { title: productName }
      ]"
    >
      <template #title-after>
        <v-chip color="success" size="x-small" variant="flat">
          {{ t('unit.manage_hint') }}
        </v-chip>
      </template>

      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('unit.add_unit') }}
        </v-btn>
      </template>
    </AppPageHeader>

    <!-- Info banner -->
    <v-alert
      type="info"
      variant="tonal"
      density="compact"
      rounded="lg"
      class="mb-4"
    >
      <strong>{{ t('unit.how_title') }}</strong>
      {{ t('unit.how_desc') }}
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
              {{ t('unit.base') }}
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
            {{ format(item.retail_price) }}
          </span>
        </template>

        <!-- Wholesale price -->
        <template #item.wholesale_price="{ item }">
          <span
            v-if="item.wholesale_price"
            class="text-body-2 text-success font-weight-bold"
          >
            {{ format(item.wholesale_price) }}
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
              cost {{ format(item.cost_price) }}
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
              {{ t('unit.no_data') }}
            </div>
            <p class="text-caption text-grey mb-4">
              {{ t('unit.no_data_desc') }}
            </p>
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-plus"
              @click="openCreate"
            >
              {{ t('unit.add_first_unit') }}
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
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRoute } from 'vue-router'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'

  import ProductUnitDialog from '@/components/products/ProductUnitDialog.vue'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { format } = useCurrency()
  const route = useRoute()
  const unitStore = useProductUnitStore()
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()

  const productId = computed(() => route.params.id)
  const productName = computed(() => route.query.name ?? 'Product')

  const dialog = ref(false)
  const deleteDialog = ref(false)
  const selectedUnit = ref(null)
  const saving = ref(false)
  const deleting = ref(false)

  const headers = [
    { title: t('unit.headers.unit'), key: 'unit_name' },
    { title: t('unit.headers.qty'), key: 'qty_per_base' },
    { title: t('unit.headers.barcode'), key: 'barcode' },
    { title: t('unit.headers.retail'), key: 'retail_price' },
    { title: t('unit.headers.wholesale'), key: 'wholesale_price' },
    { title: t('unit.headers.margin'), key: 'margin' },
    { title: t('unit.headers.active'), key: 'is_active' },
    { title: '', key: 'actions' }
  ]

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

      dialog.value = false // ✅ IMPORTANT
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

  const confirmDelete = async item => {
    deleting.value = true
    try {
      confirm({
        title: 'Delete Product Unit?',
        message: `Are you sure delete "${item.unit_name}"?`,
        options: { type: 'warning', color: 'warning', width: 400 },
        agree: async () => {
          await unitStore.deleteUnit(productId.value, item.id)
          notif(t('messages.deleted_success'), {
            type: 'success'
          })
          unitStore.fetchUnits(productId.value)
        }
      })
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
