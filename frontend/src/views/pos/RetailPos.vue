<template>
  <div>
    <AppPageHeader :title="t('pos.retail.title')" show-back>
      <template #right>
        <v-chip variant="tonal" rounded="lg" prepend-icon="mdi-store-outline">
          {{ selectedBranchName || t('pos.select_branch') }}
        </v-chip>
        <v-btn
          icon="mdi-cog-outline"
          variant="tonal"
          rounded="lg"
          @click="settingsOpen = true"
        />
      </template>
    </AppPageHeader>

    <AppDialog
      v-model="settingsOpen"
      :title="t('pos.settings.title')"
      icon="mdi-cog-outline"
      :max-width="380"
      hide-submit
      :cancel-text="t('btn.close')"
      @close="settingsOpen = false"
    >
      <v-select
        v-model="branchId"
        :items="branchStore.branches"
        item-title="name"
        item-value="id"
        :label="t('pos.branch_label')"
        variant="outlined"
        rounded="lg"
        class="mb-2"
      />
      <v-select
        v-model="customerType"
        :items="customerTypeOptions"
        item-title="label"
        item-value="value"
        :label="t('pos.retail.customer_type')"
        variant="outlined"
        rounded="lg"
      />
    </AppDialog>

    <v-alert v-if="!branchId" type="warning" variant="tonal" rounded="lg">
      {{ t('pos.select_branch') }}
    </v-alert>

    <v-row v-else dense>
      <v-col cols="12" md="8">
        <PosProductGrid
          ref="productGridRef"
          :products="normalizedProducts"
          :categories="categories"
          :category-id="categoryId"
          :search="search"
          :loading="loading"
          @update:search="search = $event"
          @update:category-id="categoryId = $event"
          @add="onAdd"
        />
      </v-col>
      <v-col cols="12" md="4">
        <PosCartPanel
          :items="posStore.items"
          :subtotal="posStore.subtotal"
          @update-qty="posStore.updateQty"
          @remove="posStore.removeItem"
          @clear="posStore.clear"
          @checkout="checkoutOpen = true"
        />
      </v-col>
    </v-row>

    <PosCheckoutDialog
      v-model="checkoutOpen"
      :subtotal="posStore.subtotal"
      :loading="submitting"
      :error-message="submitError"
      @close="checkoutOpen = false"
      @submit="submitOrder"
    />

    <PosReceiptDialog
      v-model="receiptOpen"
      :receipt="receipt"
      @close="receiptOpen = false"
    />
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { usePosStore } from '@/stores/posStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import {
    getRetailPosProductsApi,
    getRetailPosCategoriesApi,
    submitRetailOrderApi
  } from '@/api/posService'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import AppDialog from '@/components/common/AppDialog.vue'
  import PosProductGrid from '@/components/pos/PosProductGrid.vue'
  import PosCartPanel from '@/components/pos/PosCartPanel.vue'
  import PosCheckoutDialog from '@/components/pos/PosCheckoutDialog.vue'
  import PosReceiptDialog from '@/components/pos/PosReceiptDialog.vue'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const posStore = usePosStore()

  const branchId = ref(authStore.branch_id)
  const customerType = ref('retail')
  const customerTypeOptions = computed(() => [
    { value: 'retail', label: t('pos.retail.customer_types.retail') },
    { value: 'wholesale', label: t('pos.retail.customer_types.wholesale') }
  ])
  const settingsOpen = ref(false)
  const selectedBranchName = computed(
    () => branchStore.branches.find(b => b.id === branchId.value)?.name ?? ''
  )
  const productGridRef = ref(null)

  const categories = ref([])
  const rawProducts = ref([])
  const categoryId = ref(null)
  const search = ref('')
  const loading = ref(false)

  const checkoutOpen = ref(false)
  const receiptOpen = ref(false)
  const receipt = ref(null)
  const submitting = ref(false)
  const submitError = ref('')

  const normalizedProducts = computed(() =>
    rawProducts.value.map(p => {
      const units = (p.active_units ?? [])
        .filter(u => u.is_active)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))

      const options = units.length
        ? units.map(u => ({
            key: `${p.id}:${u.id}`,
            product_id: p.id,
            product_unit_id: u.id,
            label: u.unit_label || u.unit_name,
            price:
              customerType.value === 'wholesale'
                ? Number(u.wholesale_price ?? u.retail_price ?? 0)
                : Number(u.retail_price ?? 0)
          }))
        : [
            {
              key: p.id,
              product_id: p.id,
              product_unit_id: null,
              label: null,
              price: Number(p.selling_price ?? p.base_price ?? 0)
            }
          ]

      return {
        id: p.id,
        name: p.name,
        image_url: p.image_url,
        options,
        stockStatus: stockStatus(p)
      }
    })
  )

  function stockStatus(p) {
    const qty = Number(p.stock_quantity ?? 0)
    if (qty <= 0) return 'out_of_stock'
    if (p.reorder_level && qty <= Number(p.reorder_level)) return 'low_stock'
    return 'in_stock'
  }

  async function loadCategories() {
    if (!branchId.value) return
    const { data } = await getRetailPosCategoriesApi({ branch_id: branchId.value })
    categories.value = data.data
  }

  async function loadProducts() {
    if (!branchId.value) return
    loading.value = true
    try {
      const { data } = await getRetailPosProductsApi({
        branch_id: branchId.value,
        category_id: categoryId.value || undefined,
        search: search.value || undefined
      })
      rawProducts.value = data.data.data
    } finally {
      loading.value = false
    }
  }

  function onAdd({ product, option }) {
    posStore.addItem({
      key: option.key,
      product_id: option.product_id,
      product_unit_id: option.product_unit_id,
      name: product.name,
      unit_label: option.label,
      unit_price: option.price
    })
  }

  async function submitOrder({ payment_method, cash_tendered }) {
    submitting.value = true
    submitError.value = ''
    try {
      const { data } = await submitRetailOrderApi({
        branch_id: branchId.value,
        payment_method,
        cash_tendered,
        customer_type: customerType.value,
        items: posStore.items.map(i => ({
          product_id: i.product_id,
          product_unit_id: i.product_unit_id || undefined,
          quantity: i.qty
        }))
      })
      receipt.value = data.data.receipt
      checkoutOpen.value = false
      receiptOpen.value = true
      posStore.clear()
      notif(t('pos.checkout.success'), { type: 'success' })
    } catch (err) {
      submitError.value =
        err.response?.data?.message ??
        err.response?.data?.errors?.[0] ??
        t('pos.checkout.failed')
    } finally {
      submitting.value = false
    }
  }

  watch(branchId, () => {
    loadCategories()
    loadProducts()
  })
  watch([categoryId, search], loadProducts)

  function handlePosShortcuts(e) {
    if (((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') || e.key === 'F2') {
      e.preventDefault()
      productGridRef.value?.focus()
    } else if (e.key === 'F10') {
      e.preventDefault()
      if (posStore.items.length) checkoutOpen.value = true
    }
  }

  onMounted(async () => {
    window.addEventListener('keydown', handlePosShortcuts)
    await branchStore.fetchBranches?.()
    if (branchId.value) {
      await Promise.all([loadCategories(), loadProducts()])
    }
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handlePosShortcuts)
  })
</script>
