<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { usePromotionStore } from '@/stores/promotionStore'
  import { useCouponStore } from '@/stores/couponStore'
  import PromotionDialog from '../../components/loyalty/PromotionDialog.vue'
  import CouponDialog from '../../components/loyalty/CouponDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useDate } from '@/composables/useDate'

  const { t } = useI18n()
  const { formatShortDate: formatDate } = useDate()
  const { confirm } = useAppUtils()
  const promotionStore = usePromotionStore()
  const couponStore = useCouponStore()

  // ── Tab State ────────────────────────────────────────────────────────────────
  const activeTab = ref('promotions')

  // ── Loading / Error ──────────────────────────────────────────────────────────
  const loading = ref(false)
  const couponsLoading = ref(false)

  // ── Promotion Dialog ─────────────────────────────────────────────────────────
  const promoDialog = ref(false)
  const editingPromo = ref(null)

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

  const refresh = async () => {
    await couponStore.fetchCoupons()
  }

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
          await refresh()
        },
        cancel: () => {}
      })
    } catch (e) {
      console.error('Failed to delete coupon:', e)
    }
  }

  // ── Coupon Table (server-side) ──────────────────────────────────────────────
  const search = ref('')

  const tableOptions = reactive({
    page: 1,
    itemsPerPage: 10,
    sortBy: []
  })

  const totalItems = ref(0)

  const headers = [
    {
      title: t('promotions.table.code'),
      key: 'code',
      sortable: true,
      width: 140
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
  ]
  // v-data-table-server emits this on page/sort/itemsPerPage change
  function onUpdateOptions(opts) {
    tableOptions.page = opts.page
    tableOptions.itemsPerPage = opts.itemsPerPage
    tableOptions.sortBy = opts.sortBy
  }

  // Debounced search → reset to page 1
  let searchTimeout = null
  const onSearchInput = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      tableOptions.page = 1
    }, 350)
  }

  // ── Fetch on Mount ────────────────────────────────────────────────────────────
  onMounted(async () => {
    loading.value = true
    try {
      await Promise.all([promotionStore.fetchPromotions(), refresh()])
    } catch (e) {
      console.error(e)
    } finally {
      loading.value = false
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
    totalCoupons: totalItems.value
  }))
</script>

<template>
  <div>
    <v-container fluid class="pa-0">
      <!-- ── Page Header ─────────────────────────────────────── -->
      <custom-title
        icon="mdi-ticket-percent"
        :title="$t('promotions.title')"
        :subtitle="$t('promotions.subtitle')"
      >
        <template #right>
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
        </template>
      </custom-title>

      <!-- ── Stat Cards ──────────────────────────────────────── -->
      <v-row class="mb-6" dense>
        <v-col
          cols="6"
          md="3"
          v-for="card in [
            {
              label: t('promotions.stats.active_promotions'),
              value: stats.activePromos,
              icon: 'mdi-tag-multiple',
              color: 'orange-darken-2'
            },
            {
              label: t('promotions.stats.total_promotions'),
              value: stats.totalPromos,
              icon: 'mdi-tag-outline',
              color: 'brown-darken-2'
            },
            {
              label: t('promotions.stats.active_coupons'),
              value: stats.activeCoupons,
              icon: 'mdi-ticket-percent',
              color: 'blue-darken-2'
            },
            {
              label: t('promotions.stats.total_coupons'),
              value: stats.totalCoupons,
              icon: 'mdi-ticket-outline',
              color: 'teal-darken-2'
            }
          ]"
          :key="card.label"
        >
          <v-card flat rounded="xl" class="pa-4 ">
            <div class="d-flex align-center justify-space-between">
              <div>
                <p class="text-caption text-medium-emphasis mb-1">
                  {{ card.label }}
                </p>
                <p
                  class="text-h5 font-weight-black"
                  :class="`text-${card.color}`"
                >
                  <v-skeleton-loader v-if="loading" type="text" width="40" />
                  <template v-else>{{ card.value }}</template>
                </p>
              </div>
              <v-avatar :color="card.color" size="44" class="opacity-90">
                <v-icon :icon="card.icon" color="white" size="22" />
              </v-avatar>
            </div>
          </v-card>
        </v-col>
      </v-row>

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
          <!-- Loading skeletons -->
          <v-row v-if="loading" dense>
            <v-col cols="12" md="6" lg="4" v-for="n in 4" :key="n">
              <v-skeleton-loader type="card" rounded="xl" />
            </v-col>
          </v-row>

          <!-- Empty state -->
          <div
            v-else-if="!promotionStore.promotions.length"
            class="pa-12 text-center"
          >
            <v-icon
              icon="mdi-tag-off-outline"
              size="48"
              
              class="mb-3"
            />
            <p class="text-h6 font-weight-bold">
              {{ $t('promotions.empty') }}
            </p>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{ $t('promotions.empty_sub') }}
            </p>
            <v-btn
              color="primary"
              rounded="xl"
              prepend-icon="mdi-plus"
              @click="openNewPromo"
            >
              {{ $t('btn.promotions') }}
            </v-btn>
          </div>

          <!-- Cards -->
          <v-row v-else dense>
            <v-col
              cols="12"
              md="6"
              lg="4"
              v-for="promo in promotionStore.promotions"
              :key="promo.id"
            >
              <v-card
                flat
                rounded="xl"
                class=" h-100"
                :class="{ 'opacity-60': !promo.is_active }"
              >
                <v-card-text class="pa-5">
                  <div class="d-flex align-start justify-space-between mb-3">
                    <div class="flex-grow-1 mr-2">
                      <div class="d-flex align-center gap-2 mb-1">
                        <v-chip
                          :color="typeChipColor(promo.type)"
                          size="x-small"
                          label
                          class="font-weight-bold"
                        >
                          {{ typeLabel(promo.type) }}
                        </v-chip>
                        <v-chip
                          v-if="!promo.is_active"
                          color="grey"
                          size="x-small"
                          label
                        >
                          Inactive
                        </v-chip>
                      </div>
                      <p
                        class="text-subtitle-1 font-weight-bold mt-1"
                      >
                        {{ promo.name }}
                      </p>
                    </div>
                    <div>
                      <v-btn
                        icon="mdi-pencil-outline"
                        size="small"
                        variant="text"
                        color="primary"
                        @click="openEditPromo(promo)"
                      />
                      <v-btn
                        icon="mdi-delete-outline"
                        size="small"
                        variant="text"
                        color="error"
                        @click="deletePromo(promo.id)"
                      />
                    </div>
                  </div>

                  <div v-if="promo.discount_value" class="mb-3">
                    <span class="text-h4 font-weight-black">
                      {{
                        promo.type === 'percentage'
                          ? promo.discount_value + '%'
                          : '$' + promo.discount_value
                      }}
                    </span>
                    <span class="text-caption text-medium-emphasis ml-1">
                      off
                    </span>
                  </div>

                  <v-divider class="mb-3 opacity-30" />

                  <div
                    class="d-flex flex-wrap gap-x-4 gap-y-1 text-caption text-medium-emphasis mb-3"
                  >
                    <span>
                      <v-icon size="13" class="mr-1">mdi-calendar-start</v-icon>
                      {{ formatDate(promo.start_at) }}
                    </span>
                    <span>
                      <v-icon size="13" class="mr-1">mdi-calendar-end</v-icon>
                      {{ formatDate(promo.end_at) }}
                    </span>
                    <span v-if="promo.min_order_amount">
                      <v-icon size="13" class="mr-1">mdi-cash</v-icon>
                      Min ${{ promo.min_order_amount }}
                    </span>
                  </div>

                  <div v-if="promo.usage_limit">
                    <div class="d-flex justify-space-between text-caption mb-1">
                      <span class="text-medium-emphasis">
                        {{ $t('promotions.usage') }}
                      </span>
                      <span class="font-weight-bold">
                        {{ promo.usage_count }} / {{ promo.usage_limit }}
                      </span>
                    </div>
                    <v-progress-linear
                      :model-value="
                        usagePercent(promo.usage_count, promo.usage_limit)
                      "
                      rounded
                      height="6"
                      bg-color="brown-lighten-4"
                      :color="
                        usagePercent(promo.usage_count, promo.usage_limit) >=
                        100
                          ? 'error'
                          : 'brown-darken-2'
                      "
                    />
                  </div>
                  <p v-else class="text-caption text-medium-emphasis">
                    <v-icon size="13" class="mr-1">mdi-infinity</v-icon>
                    {{ $t('promotions.unlimited_usage') }} .
                    {{
                      $t('promotions.used_count', { count: promo.usage_count })
                    }}
                  </p>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-tabs-window-item>

        <!-- ══════════════════ COUPONS ══════════════════════════ -->
        <v-tabs-window-item value="coupons">
          <v-card flat rounded="xl" class="">
            <!-- Header / search bar -->
            <v-card-text class="pb-0">
              <div
                class="d-flex align-center justify-space-between flex-wrap gap-3 mb-2"
              >
                <v-text-field
                  v-model="search"
                  prepend-inner-icon="mdi-magnify"
                  :label="$t('promotions.filter.search_placeholder')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details
                  clearable
                  style="max-width: 320px"
                  @update:model-value="onSearchInput"
                />
              </div>
            </v-card-text>
            <v-data-table-server
              v-model:items-per-page="couponStore.pagination.per_page"
              v-model:page="tableOptions.page"
              v-model:sort-by="tableOptions.sortBy"
              :headers="headers"
              :items="couponStore.coupons"
              :items-length="couponStore.pagination.total || 0"
              :loading="couponsLoading"
              item-value="id"
              @update:options="onUpdateOptions"
            >
              <!-- Empty state -->
              <template #no-data>
                <div class="pa-12 text-center">
                  <v-icon
                    icon="mdi-ticket-outline"
                    size="48"
                    color="brown-lighten-3"
                    class="mb-3"
                  />
                  <p class="text-h6 font-weight-bold">
                    {{ $t('promotions.empty_coupon') }}
                  </p>
                  <p class="text-body-2 text-medium-emphasis mb-4">
                    {{ $t('promotions.empty_coupon_sub') }}
                  </p>
                  <v-btn
                    color="primary"
                    rounded="xl"
                    prepend-icon="mdi-plus"
                    @click="openNewCoupon"
                  >
                    {{ $t('btn.create_coupon') }}
                  </v-btn>
                </div>
              </template>

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
                <div v-if="item.usage_limit" class="d-flex align-center gap-2">
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
            </v-data-table-server>
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
