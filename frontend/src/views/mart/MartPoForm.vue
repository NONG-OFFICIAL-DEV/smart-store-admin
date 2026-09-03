<template>
  <div>
    <!-- Breadcrumb -->
    <AppPageHeader
      :title="isEdit ? t('po.edit') : t('po.new')"
      show-back
      :breadcrumbs="[
        { title: t('po.title'), to: '/mart/purchase-order' },
        { title: isEdit ? po?.po_number : t('po.subtitle') }
      ]"
    >
      <template #title-after>
        <!-- <v-chip
          v-if="store.branch?.is_active"
          color="success"
          size="x-small"
          variant="flat"
        >
          Active
        </v-chip> -->
      </template>

      <template #right>
        <div class="d-flex gap-2">
          <v-btn
            variant="tonal"
            rounded="lg"
            :disabled="saving"
            @click="router.back()"
          >
            {{ t('btn.cancel') }}
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-content-save"
            :loading="saving"
            @click="save"
          >
            {{ isEdit ? t('btn.save_changes') : t('btn.create_po') }}
          </v-btn>
        </div>
      </template>
    </AppPageHeader>

    <v-form ref="formRef">
      <!-- ── Left: PO Details ───────────────────────────────────────────── -->
      <v-card rounded="xl" border elevation="0" class="mb-4">
        <v-card-title class="pa-5 pb-3">
          <div class="text-body-1 font-weight-bold">{{ t('po.order_details') }}</div>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <v-row dense>
            <v-col cols="3">
              <v-select
                v-model="form.branch_id"
                :items="branchList"
                item-title="name"
                item-value="id"
                :label="t('po.field.branch')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
              />
            </v-col>

            <!-- Supplier -->
            <v-col cols="3">
              <v-select
                v-model="form.supplier_id"
                :items="supplierList"
                item-title="name"
                item-value="id"
                :label="t('po.field.supplier')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
              >
                <template #no-data>
                  <div class="pa-3 text-caption text-medium-emphasis">
                    {{ t('po.no_suppliers_found') }}
                  </div>
                </template>
              </v-select>
            </v-col>
            <!-- Expected delivery -->
            <v-col cols="3">
              <AppDatePicker
                v-model="form.expected_delivery"
                :label="t('po.field.date')"
              />
            </v-col>
            <!-- Status (edit only) -->
            <v-col v-if="isEdit" cols="3">
              <v-select
                v-model="form.status"
                :items="editableStatuses"
                item-title="label"
                item-value="value"
                :label="t('po.field.status')"
                variant="outlined"
                rounded="lg"
              />
            </v-col>
            <v-col cols="3" lg="3">
              <v-textarea
                v-model="form.notes"
                :label="t('po.field.note')"
                variant="outlined"
                rounded="lg"
                rows="1"
                auto-grow
                :placeholder="t('po.optional_remarks')"
              />
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
      <v-row dense>
        <!-- ── Right: Line Items ──────────────────────────────────────────── -->
        <v-col cols="12" lg="9">
          <v-card rounded="xl" border elevation="0">
            <div class="d-flex align-center justify-space-between pa-5 pb-3">
              <div class="text-body-1 font-weight-bold">
                {{ t('po.order_items') }}
                <v-chip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                  class="ml-2"
                >
                  {{ form.items.length }}
                </v-chip>
              </div>
              <v-btn
                size="small"
                color="primary"
                variant="tonal"
                rounded="lg"
                prepend-icon="mdi-plus"
                @click="addItem"
              >
                {{t('po.add_product')}}
              </v-btn>
            </div>
            <v-divider />

            <!-- Empty state -->
            <div v-if="form.items.length === 0" class="text-center py-16">
              <v-icon
                icon="mdi-package-variant-plus"
                size="56"
                color="grey-lighten-1"
                class="mb-3"
              />
              <div class="text-body-1 font-weight-medium text-grey">
                {{ t('po.no_items_yet') }}
              </div>
              <p class="text-caption text-grey mb-4">
                {{ t('po.no_items_hint') }}
              </p>
              <v-btn
                color="primary"
                variant="flat"
                rounded="lg"
                prepend-icon="mdi-plus"
                @click="addItem"
              >
                {{ t('po.add_first_product') }}
              </v-btn>
            </div>

            <!-- Items -->
            <div v-else class="pa-4">
              <!-- Table header -->
              <v-row dense class="px-2 mb-2">
                <v-col cols="12" sm="4">
                  <span
                    class="text-caption font-weight-bold text-medium-emphasis"
                  >
                    {{t('po.header.product')}}
                  </span>
                </v-col>
                <v-col cols="6" sm="2">
                  <span
                    class="text-caption font-weight-bold text-medium-emphasis"
                  >
                    {{t('po.header.unit')}}
                  </span>
                </v-col>
                <v-col cols="6" sm="2">
                  <span
                    class="text-caption font-weight-bold text-medium-emphasis"
                  >
                    {{t('po.header.qty')}}
                  </span>
                </v-col>
                <v-col cols="6" sm="2">
                  <span
                    class="text-caption font-weight-bold text-medium-emphasis"
                  >
                    {{t('po.header.unit_cost')}}
                  </span>
                </v-col>
                <v-col cols="6" sm="2" class="text-right">
                  <span
                    class="text-caption font-weight-bold text-medium-emphasis"
                  >
                    {{t('po.header.total')}}
                  </span>
                </v-col>
              </v-row>

              <v-divider class="mb-3" />

              <!-- Item row -->
              <div
                v-for="(item, i) in form.items"
                :key="i"
                class="item-row mb-3 pa-3 rounded-lg border"
              >
                <v-row dense align="center">
                  <!-- Product autocomplete -->
                  <v-col cols="12" sm="4">
                    <v-autocomplete
                      v-model="item.product_id"
                      :items="productList"
                      item-title="name"
                      item-value="id"
                      :label="t('po.field.product')"
                      variant="outlined"
                      rounded="lg"
                      hide-details
                      :rules="[r.required]"
                      @update:model-value="onProductSelect(item)"
                    >
                      <template #item="{ item: p, props: iProps }">
                        <v-list-item v-bind="iProps">
                          <template #prepend>
                            <v-avatar
                              size="32"
                              rounded="lg"
                              class="bg-grey-lighten-4 border mr-2"
                            >
                              <v-img :src="p.raw?.image_url" cover />
                            </v-avatar>
                          </template>
                          <template #subtitle>
                            {{ t('stock.adjust.current_stock') }}: {{ p.raw?.stock_quantity ?? 0 }}
                            {{ p.raw?.unit ?? 'pcs' }}
                          </template>
                        </v-list-item>
                      </template>
                    </v-autocomplete>
                  </v-col>

                  <!-- Unit -->
                  <v-col cols="6" sm="2">
                    <v-select
                      v-model="item.product_unit_id"
                      :items="unitsFor(item.product_id)"
                      item-title="unit_label"
                      item-value="id"
                      :label="t('po.field.unit')"
                      variant="outlined"
                      rounded="lg"
                      hide-details
                      clearable
                      :no-data-text="t('unit.no_data')"
                      @update:model-value="onUnitSelect(item)"
                    />
                  </v-col>

                  <!-- Qty -->
                  <v-col cols="6" sm="2">
                    <v-text-field
                      v-model.number="item.quantity_ordered"
                      type="number"
                      :label="t('po.field.qty')"
                      min="0.001"
                      variant="outlined"
                      rounded="lg"
                      hide-details
                    />
                  </v-col>

                  <!-- Unit cost -->
                  <v-col cols="6" sm="2">
                    <v-text-field
                      v-model.number="item.unit_cost"
                      type="number"
                      :label="t('po.field.unit_cost')"
                      min="0"
                      variant="outlined"
                      rounded="lg"
                      hide-details
                      :prefix="currencySymbol()"
                      />
                  </v-col>

                  <!-- Line total + remove -->
                  <v-col cols="6" sm="2">
                    <div class="d-flex align-center justify-end gap-2">
                      <div class="text-body-2 font-weight-black text-primary">
                        {{ format(item.quantity_ordered * item.unit_cost) }}
                      </div>
                      <v-btn
                        icon="mdi-delete-outline"
                        size="x-small"
                        variant="text"
                        color="error"
                        :disabled="form.items.length === 1"
                        @click="removeItem(i)"
                      />
                    </div>
                  </v-col>
                </v-row>

                <!-- Stock info row -->
                <div v-if="productOf(item.product_id)" class="stock-info mt-2">
                  <v-icon
                    icon="mdi-information-outline"
                    size="12"
                    class="mr-1"
                  />
                  {{ t('stock.adjust.current_stock') }}:
                  <strong>
                    {{ productOf(item.product_id)?.stock_quantity ?? 0 }}
                    {{ productOf(item.product_id)?.unit ?? 'pcs' }}
                  </strong>
                  <span v-if="item.quantity_ordered">
                    · {{ t('stock.adjust.after_receive') }}:
                    <strong class="text-success">
                      {{
                        (
                          parseFloat(
                            productOf(item.product_id)?.stock_quantity ?? 0
                          ) +
                          item.quantity_ordered *
                            (unitOf(item)?.qty_per_base ?? 1)
                        ).toFixed(2)
                      }}
                    </strong>
                  </span>
                </div>
              </div>

              <!-- Add row button -->
              <v-btn
                block
                variant="tonal"
                rounded="lg"
                color="primary"
                prepend-icon="mdi-plus"
                class="mt-2"
                @click="addItem"
              >
                {{ t('btn.add_product') }}
              </v-btn>
            </div>
          </v-card>
        </v-col>
        <v-col>
          <!-- Order Summary card -->
          <v-card rounded="xl" border elevation="0">
            <v-card-title class="pa-5 pb-3">
              <div class="text-body-1 font-weight-bold">{{ t('po.summary') }}</div>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-5">
              <div class="d-flex justify-space-between mb-2">
                <span class="text-body-2 text-medium-emphasis">
                  {{ t('po.total_items') }}
                </span>
                <span class="text-body-2 font-weight-bold">
                  {{ form.items.length }}
                </span>
              </div>
              <div class="d-flex justify-space-between mb-2">
                <span class="text-body-2 text-medium-emphasis">
                  {{ t('po.total_units') }}
                </span>
                <span class="text-body-2 font-weight-bold">
                  {{
                    form.items.reduce(
                      (s, i) => s + (i.quantity_ordered || 0),
                      0
                    )
                  }}
                </span>
              </div>
              <v-divider class="my-3" />
              <div class="d-flex justify-space-between align-center">
                <span class="text-body-1 font-weight-bold">{{ t('po.grand_total') }}</span>
                <span class="text-h6 font-weight-black text-primary">
                  {{ format(orderTotal) }}
                </span>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-form>
  </div>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useRouter, useRoute } from 'vue-router'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useProductStore } from '@/stores/productStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useMartPurchaseOrderStore } from '@/stores/martPurchaseOrderStore'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import { AppDatePicker } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const router = useRouter()
  const route = useRoute()
  const { notif } = useAppUtils()
  const { t } = useI18n()
  const { format ,currencySymbol} = useCurrency()

  const supplierStore = useSupplierStore()
  const branchStore = useBranchStore()
  const productStore = useProductStore()
  const authStore = useAuthStore()
  const poStore = useMartPurchaseOrderStore()
  const productUnit = useProductUnitStore()

  const formRef = ref(null)
  const saving = ref(false)
  const po = ref(null)
  const isEdit = computed(() => !!route.params.id)

  // Units cache: { productId: [units] }
  const unitsCache = reactive({})

  // ── Computed lists ────────────────────────────────────────────────────────
  const supplierList = computed(() => {
    const s = supplierStore.suppliers
    return Array.isArray(s) ? s : (s?.data ?? [])
  })
  const branchList = computed(() => {
    const b = branchStore.branches
    return Array.isArray(b) ? b : (b?.data ?? [])
  })
  const productList = computed(() => {
    const p = productStore.products
    return Array.isArray(p) ? p : (p?.data ?? [])
  })

  const unitsFor = productId => {
    if (!productId) return []
    return (unitsCache[productId] ?? []).map(u => ({
      ...u,
      unit_label: u.unit_label || u.unit_name
    }))
  }

  const productOf = productId =>
    productId ? productList.value.find(p => p.id === productId) : null

  const unitOf = item =>
    item.product_unit_id
      ? (unitsCache[item.product_id] ?? []).find(
          u => u.id === item.product_unit_id
        )
      : null

  // ── Form ──────────────────────────────────────────────────────────────────
  const defaultItem = () => ({
    product_id: null,
    product_unit_id: null,
    quantity_ordered: 1,
    unit_cost: 0
  })

  const form = reactive({
    branch_id: null,
    supplier_id: null,
    expected_delivery: null,
    status: 'draft',
    notes: '',
    items: [defaultItem()]
  })

  const orderTotal = computed(() =>
    form.items.reduce(
      (s, i) => s + (i.quantity_ordered || 0) * (i.unit_cost || 0),
      0
    )
  )

  const editableStatuses = computed(() => [
    { value: 'draft', label: t('po.status.draft') },
    { value: 'submitted', label: t('po.status.submitted') },
    { value: 'confirmed', label: t('po.status.confirmed') },
    { value: 'cancelled', label: t('po.status.cancelled') }
  ])

  // ── Product / unit select handlers ────────────────────────────────────────
  const onProductSelect = async item => {
    item.product_unit_id = null
    item.unit_cost = 0
    if (!item.product_id) return

    if (!unitsCache[item.product_id]) {
      try {
        const res = await productUnit.fetchUnits(item.product_id)
        unitsCache[item.product_id] = res.data.data ?? []
      } catch {
        unitsCache[item.product_id] = []
      }
    }

    // Auto-select base unit
    const baseUnit = unitsCache[item.product_id]?.find(u => u.is_base_unit)
    if (baseUnit) {
      item.product_unit_id = baseUnit.id
      item.unit_cost = parseFloat(baseUnit.cost_price ?? 0)
    }
  }

  const onUnitSelect = item => {
    const unit = unitOf(item)
    if (unit?.cost_price) item.unit_cost = parseFloat(unit.cost_price)
  }

  // ── Actions ───────────────────────────────────────────────────────────────
  const addItem = () => form.items.push(defaultItem())
  const removeItem = i => form.items.splice(i, 1)

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    saving.value = true
    try {
      if (isEdit.value) {
        await poStore.updateOrder(route.params.id, form)
        notif(t('po.messages.updated'), { type: 'success' })
      } else {
        await poStore.createOrder(form)
        notif(t('po.messages.created'), { type: 'success' })
      }
      router.push({ name: 'MartPurchaseOrders' })
    } catch (e) {
      notif(e.response?.data?.message ?? t('po.messages.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Load data ─────────────────────────────────────────────────────────────
  onMounted(async () => {
    await Promise.all([
      supplierStore.fetchSuppliers?.(),
      branchStore.fetchBranches?.(),
      productStore.fetchProducts?.({ per_page: 100 })
    ])

    // Pre-fill branch
    if (!isEdit.value) {
      form.branch_id = authStore.branch_id
    }

    // Load existing PO for edit
    if (isEdit.value) {
      try {
        po.value = await poStore.fetchOrder(route.params.id)
        const p = po.value
        form.branch_id = p.branch_id
        form.supplier_id = p.supplier_id
        form.expected_delivery = p.expected_delivery
        form.status = p.status
        form.notes = p.notes ?? ''
        form.items = p.items?.map(i => ({
          product_id: i.product_id,
          product_unit_id: i.product_unit_id,
          quantity_ordered: parseFloat(i.quantity_ordered),
          unit_cost: parseFloat(i.unit_cost)
        })) ?? [defaultItem()]

        // Pre-load units for each item
        for (const item of form.items) {
          if (item.product_id && !unitsCache[item.product_id]) {
            try {
              const { getProductUnitsApi } = await import(
                '@/api/productUnitService'
              )
              const res = await getProductUnitsApi(item.product_id)
              unitsCache[item.product_id] = res.data.data ?? []
            } catch {
              unitsCache[item.product_id] = []
            }
          }
        }
      } catch {
        notif(t('po.load_failed'), { type: 'error' })
        router.push({ name: 'MartPurchaseOrders' })
      }
    }
  })

  const r = { required: v => !!v || t('products.rule.required') }

</script>

<style scoped>
  .item-row {
    background: #f8fafc;
    border-color: #e2e8f0 !important;
    transition: border-color 0.15s;
  }
  .item-row:hover {
    border-color: rgb(var(--v-theme-primary)) !important;
  }

  .stock-info {
    font-size: 11px;
    color: #94a3b8;
    padding: 4px 2px 0;
  }

  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
