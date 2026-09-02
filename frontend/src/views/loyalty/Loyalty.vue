<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { usePromotionStore } from '@/stores/promotionStore'
  import { useCouponStore } from '@/stores/couponStore'
  import PromotionDialog from '../../components/loyalty/PromotionDialog.vue'
  import CouponDialog from '../../components/loyalty/CouponDialog.vue'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useDate } from '@/composables/useDate'

  const { t } = useI18n()
  const { formatShortDate: formatDate } = useDate()
  const { confirm } = useAppUtils()
  const promotionStore = usePromotionStore()
  const couponStore = useCouponStore()

  // ── Tab State ────────────────────────────────────────────────────────────────
  const activeTab = ref('promotions')

  // ── Promotion Dialog ─────────────────────────────────────────────────────────
  const promoDialog = ref(false)
  const editingPromo = ref(null)

  const promoHeaders = computed(() => [
    {
      title: t('promotions.table.promotion'),
      key: 'name',
      width: 150,
      sortable: true
    },
    {
      title: t('promotions.table.type'),
      key: 'type',
      sortable: false,
      width: 150
    },
    {
      title: t('promotions.table.discount'),
      key: 'discount_value',
      sortable: false,
      width: 110
    },
    {
      title: t('promotions.table.duration'),
      key: 'end_at',
      sortable: true,
      width: 200
    },
    {
      title: t('promotions.table.usage'),
      key: 'usage',
      sortable: false,
      width: 160
    },
    {
      title: t('promotions.table.status'),
      key: 'is_active',
      sortable: true,
      width: 110
    },
    { title: '', key: 'actions', sortable: false, align: 'end', width: 90 }
  ])

  const fetchPromotionsForTable = async ({
    page,
    perPage,
    sortBy,
    sortDesc,
    search
  }) => {
    await promotionStore.fetchPromotions({
      page,
      per_page: perPage,
      sort_by: sortBy,
      sort_desc: sortDesc,
      search
    })
    return {
      items: promotionStore.promotions,
      total: promotionStore.pagination?.total || 0
    }
  }

  const openNewPromo = () => {
    editingPromo.value = null
    promoDialog.value = true
  }

  const openEditPromo = p => {
    editingPromo.value = { ...p }
    promoDialog.value = true
  }

  const handleSavePromo = async formData => {
    try {
      if (editingPromo.value) {
        await promotionStore.updatePromotion(editingPromo.value.id, formData)
      } else {
        await promotionStore.createPromotion(formData)
      }
      promoDialog.value = false
    } catch (e) {
      console.error('Failed to save promotion:', e)
    }
  }

  const deletePromo = async id => {
    try {
      confirm({
        title: 'Delete Promotion',
        message: `Are you sure you want to delete this"?`,
        options: { type: 'warning', width: 400 },
        agree: async () => {
          await promotionStore.deletePromotion(id)
          await promotionStore.fetchPromotions()
        },
        cancel: () => {}
      })
    } catch (e) {
      console.error('Failed to delete promotion:', e)
    }
  }

  // ── Coupon Dialog ─────────────────────────────────────────────────────────────
  const couponDialog = ref(false)
  const editingCoupon = ref(null)
  const couponTableRef = ref(null)

  const openNewCoupon = () => {
    editingCoupon.value = null
    couponDialog.value = true
  }

  const handleSaveCoupon = async formData => {
    try {
      if (editingCoupon.value) {
        await couponStore.updateCoupon(editingCoupon.value.id, formData)
      } else {
        await couponStore.createCoupon(formData)
      }
      couponDialog.value = false
      couponTableRef.value?.refresh()
    } catch (e) {
      console.error('Failed to save coupon:', e)
    }
  }

  const deleteCoupon = async id => {
    try {
      confirm({
        title: 'Delete Coupon',
        message: `Are you sure you want to delete this"?`,
        options: { type: 'warning', width: 400 },
        agree: async () => {
          await couponStore.deleteCoupon(id)
          couponTableRef.value?.refresh()
        },
        cancel: () => {}
      })
    } catch (e) {
      console.error('Failed to delete coupon:', e)
    }
  }

  // ── Coupon Table (AppTable-driven) ──────────────────────────────────────────
  const headers = computed(() => [
    {
      title: t('promotions.table.code'),
      key: 'code',
      sortable: true,
      width: 170
    },
    {
      title: t('promotions.table.promotion'),
      key: 'promotion',
      sortable: false
    },
    {
      title: t('promotions.table.usage'),
      key: 'usage',
      sortable: false,
      width: 160
    },
    {
      title: t('promotions.table.expires'),
      key: 'expires_at',
      sortable: true,
      width: 140
    },
    {
      title: t('promotions.table.status'),
      key: 'is_active',
      sortable: true,
      width: 110
    },
    { title: '', key: 'actions', sortable: false, align: 'end', width: 60 }
  ])

  // AppTable calls this on mount and whenever page/sort/search/filters change.
  // It must resolve to { items, total }.
  const fetchCouponsForTable = async ({
    page,
    perPage,
    sortBy,
    sortDesc,
    search
  }) => {
    await couponStore.fetchCoupons({
      page,
      per_page: perPage,
      sort_by: sortBy,
      sort_desc: sortDesc,
      search
    })
    return {
      items: couponStore.coupons,
      total: couponStore.pagination?.total || 0
    }
  }

  // ── Fetch on Mount ────────────────────────────────────────────────────────────
  // AppTable fetches coupons itself (immediate watcher), so we only need to
  // load promotions here.
  onMounted(async () => {
    try {
      await promotionStore.fetchPromotions()
    } catch (e) {
      console.error(e)
    } finally {
    }
  })

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const promoTypes = [
    {
      value: 'percentage',
      title: 'Percentage Off',
      icon: 'mdi-percent',
      color: 'orange-darken-2'
    },
    {
      value: 'fixed_amount',
      title: 'Fixed Amount',
      icon: 'mdi-cash-minus',
      color: 'teal-darken-2'
    },
    {
      value: 'bogo',
      title: 'Buy 1 Get 1',
      icon: 'mdi-gift-outline',
      color: 'purple-darken-2'
    },
    {
      value: 'free_item',
      title: 'Free Item',
      icon: 'mdi-package-variant',
      color: 'green-darken-2'
    },
    {
      value: 'combo',
      title: 'Combo Deal',
      icon: 'mdi-food-variant',
      color: 'blue-darken-2'
    },
    {
      value: 'happy_hour',
      title: 'Happy Hour',
      icon: 'mdi-clock-outline',
      color: 'brown-darken-2'
    }
  ]

  const typeLabel = type =>
    promoTypes.find(p => p.value === type)?.title ?? type
  const typeChipColor = type =>
    promoTypes.find(p => p.value === type)?.color ?? 'grey'

  const usagePercent = (count, limit) =>
    limit ? Math.min(Math.round((count / limit) * 100), 100) : null

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => ({
    activePromos: promotionStore.promotions.filter(p => p.is_active).length,
    totalPromos: promotionStore.promotions.length,
    activeCoupons: couponStore.coupons.filter(c => c.is_active).length,
    totalCoupons: couponStore.pagination?.total || 0
  }))
</script>

<template>
  <div>
    <v-container fluid class="pa-0">
      <!-- ── Actions ─────────────────────────────────────────── -->
      <div class="d-flex justify-end mb-4">
        <v-btn
          v-if="activeTab === 'promotions'"
          color="primary"
          rounded="lg"
          elevation="2"
          prepend-icon="mdi-plus"
          @click="openNewPromo"
        >
          {{ $t('btn.promotions') }}
        </v-btn>
        <v-btn
          v-else-if="activeTab === 'coupons'"
          color="primary"
          rounded="lg"
          elevation="2"
          prepend-icon="mdi-plus"
          @click="openNewCoupon"
        >
          {{ $t('btn.create_coupon') }}
        </v-btn>
      </div>

      <!-- ── Tabs ───────────────────────────────────────────── -->
      <v-tabs v-model="activeTab" color="primary" class="mb-4">
        <v-tab value="promotions" prepend-icon="mdi-tag-multiple">
          {{ $t('promotions.tabs.promotions') }}
        </v-tab>
        <v-tab value="coupons" prepend-icon="mdi-ticket-percent">
          {{ $t('promotions.tabs.coupons') }}
        </v-tab>
      </v-tabs>

      <v-tabs-window v-model="activeTab">
        <!-- ══════════════════ PROMOTIONS ══════════════════════ -->
        <v-tabs-window-item value="promotions">
          <v-card flat rounded="lg" border>
            <v-card-text>
              <AppTable
                ref="promoTableRef"
                :headers="promoHeaders"
                :fetch-fn="fetchPromotionsForTable"
                :item-label="$t('promotions.tabs.promotions')"
                class="promo-card-table"
              >
                <template #item.name="{ item }">
                  <div class="text-body-2">
                    <div class="font-weight-bold">{{ item.name }}</div>
                    <div
                      v-if="item.min_order_amount"
                      class="text-caption text-medium-emphasis"
                    >
                      Min ${{ item.min_order_amount }}
                    </div>
                  </div>
                </template>

                <!-- Type -->
                <template #item.type="{ item }">
                  <v-chip
                    :color="typeChipColor(item.type)"
                    size="small"
                    label
                    class="font-weight-bold"
                  >
                    {{ typeLabel(item.type) }}
                  </v-chip>
                </template>

                <!-- Discount -->
                <template #item.discount_value="{ item }">
                  <span v-if="item.discount_value" class="font-weight-black">
                    {{
                      item.type === 'percentage'
                        ? item.discount_value + '%'
                        : '$' + item.discount_value
                    }}
                  </span>
                  <span v-else class="text-medium-emphasis">—</span>
                </template>

                <!-- Duration -->
                <template #item.end_at="{ item }">
                  <div class="text-caption text-medium-emphasis">
                    <div class="d-flex align-center">
                      <v-icon size="13" class="mr-1">mdi-calendar-start</v-icon>
                      {{ formatDate(item.start_at) }}
                    </div>
                    <div class="d-flex align-center">
                      <v-icon size="13" class="mr-1">mdi-calendar-end</v-icon>
                      {{ formatDate(item.end_at) }}
                    </div>
                  </div>
                </template>

                <!-- Usage -->
                <template #item.usage="{ item }">
                  <div
                    v-if="item.usage_limit"
                    class="d-flex align-center gap-2"
                  >
                    <v-progress-linear
                      :model-value="
                        usagePercent(item.usage_count, item.usage_limit)
                      "
                      rounded
                      height="6"
                      bg-color="brown-lighten-4"
                      :color="
                        usagePercent(item.usage_count, item.usage_limit) >= 100
                          ? 'error'
                          : 'brown-darken-2'
                      "
                      style="min-width: 80px"
                    />
                    <span class="text-caption">
                      {{ item.usage_count }}/{{ item.usage_limit }}
                    </span>
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">
                    <v-icon size="13" class="mr-1">mdi-infinity</v-icon>
                    {{ item.usage_count }} used
                  </span>
                </template>

                <!-- Status -->
                <template #item.is_active="{ item }">
                  <v-chip
                    :color="item.is_active ? 'success' : 'grey'"
                    size="x-small"
                    label
                  >
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </template>

                <!-- Actions -->
                <template #item.actions="{ item }">
                  <v-btn
                    icon="mdi-pencil-outline"
                    size="small"
                    variant="text"
                    color="primary"
                    @click="openEditPromo(item)"
                  />
                  <v-btn
                    icon="mdi-delete-outline"
                    size="small"
                    variant="text"
                    color="error"
                    @click="deletePromo(item.id)"
                  />
                </template>
              </AppTable>
            </v-card-text>
          </v-card>
        </v-tabs-window-item>

        <!-- ══════════════════ COUPONS ══════════════════════════ -->
        <v-tabs-window-item value="coupons">
          <v-card flat rounded="lg" border>
            <v-card-text>
              <AppTable
                ref="couponTableRef"
                :headers="headers"
                :fetch-fn="fetchCouponsForTable"
                :item-label="$t('promotions.tabs.coupons')"
              >
                <!-- Code -->
                <template #item.code="{ item }">
                  <v-chip
                    label
                    color="primary-lighten-4"
                    class="font-weight-black"
                    size="small"
                  >
                    {{ item.code }}
                  </v-chip>
                </template>

                <!-- Promotion -->
                <template #item.promotion="{ item }">
                  <div v-if="item.promotion" class="text-body-2">
                    <div class="font-weight-medium">
                      {{ item.promotion.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{
                        item.promotion.type === 'percentage'
                          ? item.promotion.discount_value + '% off'
                          : '$' + item.promotion.discount_value + ' off'
                      }}
                    </div>
                  </div>
                  <span v-else class="text-body-2 text-medium-emphasis">—</span>
                </template>

                <!-- Usage -->
                <template #item.usage="{ item }">
                  <div
                    v-if="item.usage_limit"
                    class="d-flex align-center gap-2"
                  >
                    <v-progress-linear
                      :model-value="
                        usagePercent(item.usage_count, item.usage_limit)
                      "
                      rounded
                      height="6"
                      bg-color="brown-lighten-4"
                      :color="
                        usagePercent(item.usage_count, item.usage_limit) >= 100
                          ? 'error'
                          : 'brown-darken-2'
                      "
                      style="min-width: 80px"
                    />
                    <span class="text-caption">
                      {{ item.usage_count }}/{{ item.usage_limit }}
                    </span>
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">
                    {{ item.usage_count }} used
                  </span>
                </template>

                <!-- Expires -->
                <template #item.expires_at="{ item }">
                  <span class="text-body-2 text-medium-emphasis">
                    {{ formatDate(item.expires_at) }}
                  </span>
                </template>

                <!-- Status -->
                <template #item.is_active="{ item }">
                  <v-chip
                    :color="item.is_active ? 'success' : 'grey'"
                    size="x-small"
                    label
                  >
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </template>

                <!-- Actions -->
                <template #item.actions="{ item }">
                  <v-btn
                    icon="mdi-delete-outline"
                    size="small"
                    variant="text"
                    color="error"
                    @click="deleteCoupon(item.id)"
                  />
                </template>
              </AppTable>
            </v-card-text>
          </v-card>
        </v-tabs-window-item>
      </v-tabs-window>
    </v-container>

    <!-- ── Dialogs ─────────────────────────────────────────── -->
    <PromotionDialog
      v-model="promoDialog"
      :editing="editingPromo"
      @save="handleSavePromo"
    />

    <CouponDialog
      v-model="couponDialog"
      :editing="editingCoupon"
      :promotions="promotionStore.promotions"
      @save="handleSaveCoupon"
    />
  </div>
</template>
