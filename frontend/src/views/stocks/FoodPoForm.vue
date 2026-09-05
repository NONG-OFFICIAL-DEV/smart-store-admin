<template>
  <div>
    <AppPageHeader
      :title="isEdit ? t('po.edit') : t('po.new')"
      show-back
      :breadcrumbs="[
        { title: t('purchase_order.title'), to: '/inventory/purchase-orders' },
        { title: isEdit ? po?.po_number : t('po.new') }
      ]"
    >
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
      <!-- ── Order Details ─────────────────────────────────────────────── -->
      <v-card rounded="xl" border elevation="0" class="mb-4">
        <v-card-title class="pa-5 pb-3">
          <div class="text-body-1 font-weight-bold">{{ t('po.order_details') }}</div>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <v-row dense>
            <v-col cols="12" sm="6" lg="3">
              <v-select
                v-model="form.branch_id"
                :items="branchList"
                item-title="name"
                item-value="id"
                :label="t('po.field.branch')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
              />
            </v-col>
            <v-col cols="12" sm="6" lg="3">
              <v-select
                v-model="form.supplier_id"
                :items="supplierList"
                item-title="name"
                item-value="id"
                :label="t('po.field.supplier')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-truck-outline"
              />
            </v-col>
            <v-col cols="12" sm="6" lg="3">
              <AppDatePicker
                v-model="form.expected_delivery"
                :label="t('form.expected_delivery')"
              />
            </v-col>
            <v-col cols="12" sm="6" lg="3">
              <v-textarea
                v-model="form.notes"
                :label="t('form.notes')"
                variant="outlined"
                rounded="lg"
                rows="1"
                auto-grow
                prepend-inner-icon="mdi-note-outline"
              />
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- ── Line Items ────────────────────────────────────────────────── -->
      <v-card rounded="xl" border elevation="0">
        <div class="d-flex align-center justify-space-between pa-5 pb-3">
          <div class="text-body-1 font-weight-bold">
            {{ t('po.order_items') }}
            <v-chip size="x-small" color="primary" variant="tonal" rounded="lg" class="ml-2">
              {{ form.items.length }}
            </v-chip>
          </div>
          <!-- Existing line items are locked once a PO exists — the backend
               only ever processes `items` on create, never on update, so
               offering an editable-looking row here would silently discard
               whatever the user typed. -->
          <v-btn
            v-if="!isEdit"
            size="small"
            variant="tonal"
            color="primary"
            rounded="lg"
            prepend-icon="mdi-plus"
            @click="addItem"
          >
            {{ t('po.add_item') }}
          </v-btn>
        </div>
        <v-divider />

        <div class="pa-5">
          <v-alert
            v-if="isEdit"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-4"
            :text="t('po.items_locked_hint')"
          />

          <div v-for="(item, i) in form.items" :key="i" class="mb-3">
            <v-row dense align="center">
              <v-col cols="12" sm="5">
                <v-autocomplete
                  v-model="item.ingredient_id"
                  :items="ingredients"
                  item-title="name"
                  item-value="id"
                  :label="t('po.field.ingredient_n', { n: i + 1 })"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  :readonly="isEdit"
                  :rules="[r.required]"
                />
              </v-col>
              <v-col cols="5" sm="3">
                <v-text-field
                  v-model.number="item.quantity_ordered"
                  type="number"
                  :label="t('po.field.qty')"
                  min="0.001"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  :readonly="isEdit"
                  :rules="[r.required, r.positive]"
                />
              </v-col>
              <v-col cols="5" sm="3">
                <v-text-field
                  v-model.number="item.unit_price"
                  type="number"
                  :label="t('po.field.unit_cost')"
                  min="0"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  :readonly="isEdit"
                  :prefix="currencySymbol()"
                  :rules="[r.required]"
                />
              </v-col>
              <v-col v-if="!isEdit" cols="2" sm="1" class="d-flex justify-center">
                <v-btn
                  icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  color="error"
                  :disabled="form.items.length === 1"
                  @click="removeItem(i)"
                />
              </v-col>
            </v-row>
            <div class="text-caption text-right text-medium-emphasis mt-1 pr-10">
              {{ t('common.subtotal') }}: {{ format(item.quantity_ordered * item.unit_price) }}
            </div>
          </div>

          <v-divider class="my-3" />
          <div class="d-flex justify-end">
            <div class="text-body-1 font-weight-bold">
              {{ t('common.total') }}: <span class="text-primary">{{ format(totalAmount) }}</span>
            </div>
          </div>
        </div>
      </v-card>
    </v-form>
  </div>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useRouter, useRoute } from 'vue-router'
  import { usePurchaseOrderStore } from '@/stores/purchaseOrderStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useIngredientStore } from '@/stores/ingredientStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useDate } from '@/composables/useDate'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import { AppDatePicker } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'

  const router = useRouter()
  const route = useRoute()
  const { t } = useI18n()
  const { notif } = useAppUtils()
  const { formatLocalDate } = useDate()
  const { format, currencySymbol } = useCurrency()

  const poStore = usePurchaseOrderStore()
  const branchStore = useBranchStore()
  const supplierStore = useSupplierStore()
  const ingredientStore = useIngredientStore()
  const authStore = useAuthStore()

  const formRef = ref(null)
  const saving = ref(false)
  const po = ref(null)
  const isEdit = computed(() => !!route.params.id)

  const branchList = computed(() => branchStore.branches ?? [])
  const supplierList = computed(() => {
    const s = supplierStore.suppliers
    return Array.isArray(s) ? s : (s?.data ?? [])
  })
  const ingredients = computed(() => ingredientStore.ingredients ?? [])

  const defaultItem = () => ({ ingredient_id: null, quantity_ordered: 1, unit_price: 0 })
  const form = reactive({
    branch_id: null,
    supplier_id: null,
    expected_delivery: null,
    notes: '',
    items: [defaultItem()]
  })

  const totalAmount = computed(() =>
    form.items.reduce((sum, i) => sum + (i.quantity_ordered || 0) * (i.unit_price || 0), 0)
  )

  const r = {
    required: v => !!v || t('validation.required'),
    positive: v => v > 0 || t('validation.positive')
  }

  const addItem = () => form.items.push(defaultItem())
  const removeItem = i => form.items.splice(i, 1)

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    saving.value = true
    try {
      const payload = {
        branch_id: form.branch_id,
        supplier_id: form.supplier_id,
        expected_delivery: form.expected_delivery instanceof Date
          ? formatLocalDate(form.expected_delivery)
          : form.expected_delivery,
        notes: form.notes,
        // Items are immutable after creation server-side — omit them on
        // update rather than send values the backend will silently ignore.
        ...(isEdit.value ? {} : { items: form.items })
      }

      if (isEdit.value) {
        await poStore.updatePurchaseOrder(route.params.id, payload)
        notif(t('po.messages.updated'), { type: 'success' })
      } else {
        await poStore.createPurchaseOrder(payload)
        notif(t('po.messages.created'), { type: 'success' })
      }
      router.push({ name: 'inventory-purchase-orders' })
    } catch (e) {
      notif(e.response?.data?.message ?? t('po.messages.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  onMounted(async () => {
    await Promise.all([
      branchStore.fetchBranches({ perPage: -1 }),
      supplierStore.fetchSuppliers({ perPage: -1 }),
      ingredientStore.fetchIngredients({ perPage: -1 })
    ])

    if (!isEdit.value) {
      form.branch_id = authStore.branch_id
      return
    }

    try {
      await poStore.fetchPurchaseOrderById(route.params.id)
      po.value = poStore.purchaseOrder
      form.branch_id = po.value.branch_id
      form.supplier_id = po.value.supplier_id
      form.expected_delivery = po.value.expected_delivery ?? null
      form.notes = po.value.notes ?? ''
      form.items = po.value.items?.map(i => ({
        ingredient_id: i.ingredient_id,
        quantity_ordered: parseFloat(i.quantity_ordered),
        unit_price: parseFloat(i.unit_price)
      })) ?? [defaultItem()]
    } catch {
      notif(t('po.load_failed'), { type: 'error' })
      router.push({ name: 'inventory-purchase-orders' })
    }
  })
</script>
