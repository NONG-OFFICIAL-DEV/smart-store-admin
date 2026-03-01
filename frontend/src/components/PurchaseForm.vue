<template>
  <custom-title>
    <v-btn
      size="x-small"
      icon="mdi-arrow-left"
      class="white mr-2"
      @click="goBack"
      variant="tonal"
    ></v-btn>

    <strong>{{ isEdit ? 'Edit Purchase' : 'Add Purchase' }}</strong>
    <template #right>
      <v-switch
        label="Use AI Suggestions"
        inset
        width="230"
        hide-details
        :false-value="false"
        :true-value="true"
        @change="onToggleAI"
      ></v-switch>
      <!-- v-model="aiEnabled" -->
    </template>
  </custom-title>

  <v-container fluid class="pa-0">
    <v-form ref="formRef" v-model="isValid">
      <!-- WRAP FORM IN CARD -->
      <v-card rounded="lg" elevation="0" class="pa-4 mb-4">
        <v-card-title class="text-h6 font-weight-bold pb-2">
          Purchase Information
        </v-card-title>

        <v-divider />
        <v-row class="mt-4">
          <v-col cols="12" sm="6" md="3">
            <v-select
              label="Supplier"
              :items="supplierStore.suppliers.data"
              v-model="purchase.supplier_id"
              item-title="name"
              item-value="id"
              :rules="[v => !!v || 'Supplier is required']"
            >
              <template #append-item>
                <v-divider />
                <v-list-item class="text-primary" @click="openCreateSupplier">
                  <v-list-item-title>
                    <v-icon>mdi-plus</v-icon>
                    Create new supplier
                  </v-list-item-title>
                </v-list-item>
              </template>
            </v-select>
          </v-col>

          <v-col cols="12" sm="6" md="3">
            <v-date-input
              v-model="purchase.purchase_date"
              label="Purchase Date"
              :rules="[v => !!v || 'Date is required']"
              :min="today"
            />
          </v-col>
          <!-- <v-col cols="12" sm="6" md="3">
            <v-select
              label="Purchase Status"
              v-model="purchase.status"
              :items="purchaseStore.statuses"
              item-title="label"
              item-value="code"
            />
          </v-col> -->

          <v-col cols="12" sm="6" md="3">
            <v-select
              label="Payment Status"
              v-model="purchase.payment_status"
              :items="['unpaid', 'partial', 'paid']"
            />
          </v-col>
        </v-row>
      </v-card>

      <!-- ITEMS CARD -->
      <v-card rounded="lg" elevation="0" class="pa-4">
        <v-card-title class="text-h6 font-weight-bold pb-2">
          Item List
        </v-card-title>
        <div v-if="isLoading">
          <v-skeleton-loader
            :loading="isLoading"
            type="table-row-divider, table-row, table-row, table-row"
          />
        </div>
        <div v-else>
          <v-divider />
          <v-row class="mt-3" dense>
            <!-- {{ isLoading }} -->
            <v-col cols="12">
              <v-row
                v-for="(item, index) in purchase.items"
                :key="index"
                dense
                class="mb-2"
              >
                <v-col cols="12" md="2">
                  <v-select
                    :items="filteredProducts"
                    v-model="item.product_id"
                    item-title="name"
                    item-value="id"
                    label="Product"
                    density="compact"
                    :rules="[v => !!v || 'Product is required']"
                  />
                </v-col>

                <v-col cols="12" md="1">
                  <v-text-field
                    v-model.number="item.quantity"
                    type="number"
                    label="Quantity"
                    density="compact"
                    min="1"
                    :rules="[v => v > 0 || 'Quantity must be > 0']"
                  />
                </v-col>

                <v-col cols="12" md="2">
                  <v-text-field
                    v-model.number="item.cost_price"
                    type="number"
                    label="Cost Price"
                    density="compact"
                    min="0"
                    prefix="$"
                    :readonly="true"
                    :disabled="!item.product_id"
                    :rules="[v => v >= 0 || 'Price must be ≥ 0']"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-text-field
                    v-model.number="item.item_tax"
                    type="number"
                    suffix="%"
                    label="Tax"
                    density="compact"
                    min="0"
                  />
                </v-col>

                <v-col cols="12" md="2">
                  <v-text-field
                    v-model.number="item.item_discount"
                    type="number"
                    label="Discount"
                    suffix="%"
                    density="compact"
                    min="0"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-text-field
                    :model-value="getItemTotal(index)"
                    prefix="$"
                    type="text"
                    label="Total"
                    density="compact"
                    min="0"
                    :readonly="true"
                    :disabled="!item.product_id"
                  />
                </v-col>

                <v-col cols="12" md="1">
                  <v-btn
                    icon="mdi-delete"
                    color="error"
                    variant="text"
                    size="small"
                    @click="removeItem(index)"
                  />
                </v-col>
              </v-row>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12" sm="6" md="8">
              <v-textarea label="Note" v-model="purchase.note"></v-textarea>
            </v-col>
            <v-col cols="12" sm="8" md="4">
              <v-sheet border rounded="lg" class="pa-4">
                <div class="mb-2">Summary</div>

                <div class="d-flex justify-space-between mb-1">
                  <span>Subtotal:</span>
                  <strong>{{ formatCurrency(subtotal) }}</strong>
                </div>

                <div class="d-flex justify-space-between mb-1">
                  <span>Total Discount Amount:</span>
                  <strong>{{ formatCurrency(totalDiscount) }}</strong>
                </div>

                <div class="d-flex justify-space-between mb-1">
                  <span>Total Tax Amount:</span>
                  <strong>{{ formatCurrency(totalTax) }}</strong>
                </div>

                <v-divider class="my-2" />

                <div class="d-flex justify-space-between">
                  <h3 class="font-weight-bold">Total Amount:</h3>
                  <h3>{{ formatCurrency(totalAmount) }}</h3>
                </div>
              </v-sheet>
            </v-col>
          </v-row>
        </div>

        <v-divider class="my-3" />

        <!-- ACTION BUTTONS -->
        <v-row>
          <v-col>
            <v-btn text color="primary" @click="addItem">+ Add Product</v-btn>
          </v-col>

          <v-col class="text-end">
            <v-btn
              v-if="hasRole(3) || hasRole(1)"
              color="primary"
              @click="savePurchase(false)"
              :disabled="purchase.items.length <= 0 || !isValid"
              class="me-2"
            >
              {{ isEdit ? 'Update Draft' : 'Save Draft' }}
            </v-btn>

            <v-btn
              v-if="hasRole(3)"
              color="success"
              @click="savePurchase(true)"
              :disabled="purchase.items.length <= 0 || !isValid"
            >
              Submit Request
            </v-btn>

            <!-- Manager buttons -->
            <v-btn
              v-if="hasRole(2) || hasRole(1)"
              color="primary"
              class="me-3"
              @click="approvePurchase"
            >
              Approve
            </v-btn>

            <v-btn v-if="hasRole(2)" color="error" @click="rejectPurchase">
              Reject
            </v-btn>
          </v-col>
        </v-row>
      </v-card>
    </v-form>
    <SupplierDialog
      v-model="isDialogSupplierOpen"
      :supplier="selectedSupplier"
      @save="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { reactive, ref, computed, onMounted, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useProductStore } from '@/stores/productStore'
  import { usePurchaseStore } from '@/stores/purchaseStore'
  import { useInventoryAIStore } from '@/stores/inventoryAIStore'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useCurrency } from '@/composables/useCurrency.js'
  import { useDate } from '@/composables/useDate'
  import { usePurchaseCalculator } from '@/composables/usePurchaseCalculator'
  import { usePermission } from '@/composables/usePermission'
  import { PURCHASE_STATUSES } from '@/constants/purchaseStatuses.js'
  import SupplierDialog from '@/components/SupplierDialog.vue'

  // ------------------------------
  // Composables & Utils
  // ------------------------------
  const { hasRole } = usePermission()
  const { formatLocalDate } = useDate()
  const { formatCurrency, formatCurrencyNoSymbol } = useCurrency()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  const route = useRoute()
  const router = useRouter()

  const supplierStore = useSupplierStore()
  const productStore = useProductStore()
  const purchaseStore = usePurchaseStore()
  const AIStore = useInventoryAIStore()
  const isDialogSupplierOpen = ref(false)
  const selectedSupplier = ref(null)
  // ------------------------------
  // Refs & Reactive State
  // ------------------------------
  const formRef = ref(null)
  const isValid = ref(false)
  const isLoading = ref(false)

  const purchase = reactive({
    id: null,
    supplier_id: null,
    purchase_date: new Date(),
    purchase_status_code: PURCHASE_STATUSES.DRAFT,
    payment_status: 'unpaid',
    note: '',
    items: ref([])
  })
  // today's date in YYYY-MM-DD format
  const today = new Date().toISOString().split('T')[0]

  const { itemTotals, subtotal, totalDiscount, totalTax, totalAmount } =
    usePurchaseCalculator(purchase)

  // ------------------------------
  // Computed Properties
  // ------------------------------
  const isEdit = computed(() => !!purchase.id)

  const filteredProducts = computed(() => {
    if (!purchase.supplier_id) return []

    return productStore.products.data.filter(p => {
      // include product if it belongs to selected supplier
      if (p.supplier_id === purchase.supplier_id) return true

      // or include if product is already selected in any item
      return purchase.items.some(item => item.product_id === p.id)
    })
  })

  // ------------------------------
  // Watchers
  // ------------------------------
  watch(
    () => purchase.items,
    items => {
      const seen = new Set()
      items.forEach((item, index) => {
        if (!item.product_id) return

        const product = productStore.products.data.find(
          p => p.id === item.product_id
        )
        if (product) item.cost_price = product.price

        if (seen.has(item.product_id)) {
          // Duplicate detected: show confirmation
          confirm({
            title: 'Duplicate Product',
            message: `Product "${product.name}" is already added! Do you want to remove this duplicate?`,
            options: { type: 'warning', width: 500 },
            agree: () => {
              removeItem(index) // remove the duplicate
            },
            cancel: () => {
              // optionally reset product selection to null
              // item.product_id = null
            }
          })
        } else {
          seen.add(item.product_id)
        }
      })
    },
    { deep: true }
  )

  watch(
    () => purchase.supplier_id,
    (newVal, oldVal) => {
      if (oldVal && newVal !== oldVal) {
        purchase.items.forEach(item => {
          // only reset if the product is not from the new supplier
          const product = productStore.products.data.find(
            p => p.id === item.product_id
          )
          if (product && product.supplier_id !== newVal) {
            item.product_id = null
          }
        })
      }
    }
  )

  // ------------------------------
  // Lifecycle Hooks
  // ------------------------------
  onMounted(async () => {
    await loadInitialData()
    if (route.params.id) await loadPurchase(route.params.id)
    else addItem()
  })

  // ------------------------------
  // Methods
  // ------------------------------
  const openCreateSupplier = supplier => {
    selectedSupplier.value = { ...supplier }
    isDialogSupplierOpen.value = true
  }
  const handleSave = async supplier => {
    await supplierStore.addSupplier(supplier)
    notif(t('messages.saved_success'), { type: 'success' })

    isDialogSupplierOpen.value = false
    supplierStore.fetchSuppliers({ status: 1, per_page: -1 })
  }

  async function loadInitialData() {
    await supplierStore.fetchSuppliers({ status: 1, per_page: -1 })
    await productStore.fetchProducts({ status: 'active', per_page: -1 })
    await purchaseStore.fetchStatuses()
  }

  async function loadPurchase(id) {
    await purchaseStore.fetchPurchaseById(id)
    Object.assign(purchase, purchaseStore.purchase)
  }

  function addItem() {
    purchase.items.push({
      product_id: null,
      quantity: 1,
      cost_price: 0,
      item_discount: 0,
      item_tax: 0
    })
  }

  function removeItem(index) {
    purchase.items.splice(index, 1)
  }

  function getItemTotal(index) {
    return formatCurrencyNoSymbol(itemTotals.value[index]?.total || 0)
  }

  async function savePurchase(requestApproval = false) {
    // 1️⃣ Validate form
    const { valid } = await formRef.value.validate()
    if (!valid) return

    // 5️⃣ Determine status
    const status = requestApproval
      ? PURCHASE_STATUSES.REQUEST
      : PURCHASE_STATUSES.DRAFT

    // 2️⃣ Format date
    const date =
      purchase.purchase_date instanceof Date
        ? formatLocalDate(purchase.purchase_date)
        : purchase.purchase_date

    // 3️⃣ Prepare payload
    const payload = {
      ...purchase,
      purchase_status_code: status,
      subtotal: subtotal.value,
      total_discount: totalDiscount.value,
      total_tax: totalTax.value,
      total_amount: totalAmount.value,
      purchase_date: date
    }

    // 4️⃣ Prevent editing received purchase
    if (isEdit.value && purchase.status === PURCHASE_STATUSES.RECEIVED) {
      return notif('Cannot edit a received purchase.', {
        type: 'error',
        color: 'error'
      })
    }

    // 6️⃣ Save purchase
    if (isEdit.value) {
      await purchaseStore.updatePurchase(purchase.id, payload)
      notif(
        requestApproval
          ? t('messages.saved_success') + ' & requested approval'
          : t('messages.updated_success'),
        { type: 'success', color: 'primary' }
      )
    } else {
      await purchaseStore.addPurchase(payload)
      notif(
        requestApproval
          ? t('messages.saved_success') + ' & requested approval'
          : t('messages.saved_success'),
        { type: 'success', color: 'primary' }
      )
    }

    router.push('/purchases')
  }

  const approvePurchase = async () => {
    await purchaseStore.updateStatus(purchase.id, 'approved')
    notif('Purchase approved', { type: 'success', color: 'primary' })
    router.push('/purchases')
  }

  const rejectPurchase = async () => {
    await purchaseStore.updateStatus(purchase.id, 'rejected')
    notif('Purchase rejected', { type: 'error', color: 'error' })
    router.push('/purchases')
  }

  let aiSuggestions = {}

  // Fetch AI suggestions from backend
  async function fetchAISuggestions() {
    try {
      isLoading.value = true
      const res = await AIStore.fetchPurchaseRecommendation()
      aiSuggestions = await res.data
      isLoading.value = false
    } catch (error) {
      return notif('Failed to fetch AI suggestions:', error, {
        type: 'error',
        color: 'error'
      })
    }
  }

  // Watch AI switch
  function onToggleAI(enabled) {
    if (enabled) {
      // Fetch AI suggestions
      fetchAISuggestions().then(() => {
        // Fill items for current supplier if available
        addAISuggestionsToPurchase()
      })
    } else {
      // Optionally clear AI items
      purchase.items = []
    }
  }

  // Add AI suggested products for current supplier
  function addAISuggestionsToPurchase() {
    // Assign supplier_id to the purchase form
    purchase.supplier_id = aiSuggestions.supplier_id

    // Clear current items
    purchase.items.splice(0, purchase.items.length)

    const productsList = aiSuggestions.items || [] // fallback
    productsList.forEach(p => {
      purchase.items.push({
        product_id: p.product_id,
        product_name: p.product_name, // include name if needed in form
        quantity: p.reorder_qty,
        cost_price: 0, // fill later if needed
        total: 0
      })
    })
  }

  function goBack() {
    router.push('/purchases')
  }
</script>
