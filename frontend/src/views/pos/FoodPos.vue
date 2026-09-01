<template>
  <div>
    <AppPageHeader :title="t('pos.food.title')" show-back>
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
          show-notes
          @update-qty="posStore.updateQty"
          @update-notes="posStore.updateNotes"
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
  import { getAllCategoriesApi } from '@/api/categoryService'
  import { getFoodPosProductsApi, submitFoodOrderApi } from '@/api/posService'
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
    rawProducts.value
      .filter(p => !categoryId.value || p.category_id === categoryId.value)
      .map(p => {
        const variants = p.variants ?? []

        const options = variants.length
          ? variants.map(v => ({
              key: `${p.id}:${v.id}`,
              product_id: p.id,
              variant_id: v.id,
              label: v.name,
              price: Number(p.base_price ?? 0) + Number(v.price_adjustment ?? 0)
            }))
          : [
              {
                key: p.id,
                product_id: p.id,
                variant_id: null,
                label: null,
                price: Number(p.base_price ?? 0)
              }
            ]

        return { id: p.id, name: p.name, image_url: p.image_url, options }
      })
  )

  async function loadCategories() {
    const { data } = await getAllCategoriesApi({ perPage: 100 })
    categories.value = data.data
  }

  async function loadProducts() {
    if (!branchId.value) return
    loading.value = true
    try {
      const { data } = await getFoodPosProductsApi({
        branch_id: branchId.value,
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
      variant_id: option.variant_id,
      name: product.name,
      variant_name: option.label,
      unit_price: option.price
    })
  }

  async function submitOrder({ payment_method, cash_tendered }) {
    submitting.value = true
    submitError.value = ''
    try {
      const { data } = await submitFoodOrderApi({
        branch_id: branchId.value,
        payment_method,
        cash_tendered,
        items: posStore.items.map(i => ({
          product_id: i.product_id,
          variant_id: i.variant_id || undefined,
          quantity: i.qty,
          note: i.notes || undefined
        }))
      })
      receipt.value = data.data.prints.receipt
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

  watch(branchId, loadProducts)
  watch(search, loadProducts)

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
    await loadCategories()
    if (branchId.value) await loadProducts()
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handlePosShortcuts)
  })
</script>
