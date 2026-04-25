<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePromotionStore } from '@/stores/promotionStore'
import { useCouponStore } from '@/stores/couponStore'
import PromotionDialog from '../../components/loyalty/PromotionDialog.vue'
import CouponDialog from '../../components/loyalty/CouponDialog.vue'

const promotionStore = usePromotionStore()
const couponStore = useCouponStore()

// ── Tab State ────────────────────────────────────────────────────────────────
const activeTab = ref('promotions')

// ── Loading / Error ──────────────────────────────────────────────────────────
const loading = ref(false)
const error = ref(null)

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
    await promotionStore.deletePromotion(id)
  } catch (e) {
    console.error('Failed to delete promotion:', e)
  }
}

// ── Coupon Dialog ─────────────────────────────────────────────────────────────
const couponDialog = ref(false)
const editingCoupon = ref(null)

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
    await couponStore.deleteCoupon(id)
  } catch (e) {
    console.error('Failed to delete coupon:', e)
  }
}

// ── Fetch on Mount ────────────────────────────────────────────────────────────
onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    await Promise.all([
      promotionStore.fetchPromotions(),
      couponStore.fetchCoupons(),
    ])
  } catch (e) {
    error.value = 'Failed to load data. Please try again.'
    console.error(e)
  } finally {
    loading.value = false
  }
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const promoTypes = [
  { value: 'percentage',   title: 'Percentage Off', icon: 'mdi-percent' },
  { value: 'fixed_amount', title: 'Fixed Amount',   icon: 'mdi-cash-minus' },
  { value: 'bogo',         title: 'Buy 1 Get 1',    icon: 'mdi-gift-outline' },
  { value: 'free_item',    title: 'Free Item',       icon: 'mdi-package-variant' },
  { value: 'combo',        title: 'Combo Deal',      icon: 'mdi-food-variant' },
  { value: 'happy_hour',   title: 'Happy Hour',      icon: 'mdi-clock-outline' },
]

const typeChipColor = type =>
  ({ percentage: 'orange', fixed_amount: 'blue', bogo: 'purple', free_item: 'green', combo: 'teal', happy_hour: 'pink' })[type] || 'grey'

const typeLabel = type => promoTypes.find(t => t.value === type)?.title || type

const formatDate = d =>
  d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'

const usagePercent = (count, limit) =>
  limit ? Math.min(Math.round((count / limit) * 100), 100) : null

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => ({
  activePromos:  promotionStore.promotions.filter(p => p.is_active).length,
  activeCoupons: couponStore.coupons.filter(c => c.is_active).length,
  totalCoupons:  couponStore.coupons.length,
  totalPromos:   promotionStore.promotions.length,
}))
</script>

<template>
  <v-container fluid class="pa-0">
    <!-- ── Page Header ─────────────────────────────────────── -->
    <custom-title icon="mdi-ticket-percent" title="Promotions & Loyalty">
      <template #right>
        <v-btn
          v-if="activeTab === 'promotions'"
          color="primary"
          rounded="lg"
          elevation="2"
          prepend-icon="mdi-plus"
          @click="openNewPromo"
        >
          New Promotion
        </v-btn>
        <v-btn
          v-else-if="activeTab === 'coupons'"
          color="primary"
          rounded="lg"
          elevation="2"
          prepend-icon="mdi-plus"
          @click="openNewCoupon"
        >
          New Coupon
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Error Banner ───────────────────────────────────── -->
    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      rounded="xl"
      class="mb-4"
      closable
      @click:close="error = null"
    >
      {{ error }}
    </v-alert>

    <!-- ── Stat Cards ──────────────────────────────────────── -->
    <v-row class="mb-6" dense>
      <v-col
        cols="6"
        md="3"
        v-for="card in [
          { label: 'Active Promotions', value: stats.activePromos,  icon: 'mdi-tag-multiple',   color: 'orange-darken-2' },
          { label: 'Total Promotions',  value: stats.totalPromos,   icon: 'mdi-tag-outline',    color: 'brown-darken-2' },
          { label: 'Active Coupons',    value: stats.activeCoupons, icon: 'mdi-ticket-percent', color: 'blue-darken-2' },
          { label: 'Total Coupons',     value: stats.totalCoupons,  icon: 'mdi-ticket-outline', color: 'teal-darken-2' },
        ]"
        :key="card.label"
      >
        <v-card flat rounded="xl" class="pa-4 bg-white">
          <div class="d-flex align-center justify-space-between">
            <div>
              <p class="text-caption text-medium-emphasis mb-1">{{ card.label }}</p>
              <p class="text-h5 font-weight-black" :class="`text-${card.color}`">
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
    <v-tabs v-model="activeTab" color="brown-darken-3" class="mb-4">
      <v-tab value="promotions" prepend-icon="mdi-tag-multiple">Promotions</v-tab>
      <v-tab value="coupons"    prepend-icon="mdi-ticket-percent">Coupons</v-tab>
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
        <v-card
          v-else-if="!promotionStore.promotions.length"
          flat rounded="xl"
          class="bg-white pa-12 text-center"
        >
          <v-icon icon="mdi-tag-off-outline" size="48" color="brown-lighten-3" class="mb-3" />
          <p class="text-h6 font-weight-bold text-brown-darken-3">No promotions yet</p>
          <p class="text-body-2 text-medium-emphasis mb-4">Create your first promotion to get started.</p>
          <v-btn color="brown-darken-3" rounded="xl" prepend-icon="mdi-plus" @click="openNewPromo">
            New Promotion
          </v-btn>
        </v-card>

        <!-- Cards -->
        <v-row v-else dense>
          <v-col cols="12" md="6" lg="4" v-for="promo in promotionStore.promotions" :key="promo.id">
            <v-card flat rounded="xl" class="bg-white h-100" :class="{ 'opacity-60': !promo.is_active }">
              <v-card-text class="pa-5">
                <div class="d-flex align-start justify-space-between mb-3">
                  <div class="flex-grow-1 mr-2">
                    <div class="d-flex align-center gap-2 mb-1">
                      <v-chip :color="typeChipColor(promo.type)" size="x-small" label class="font-weight-bold">
                        {{ typeLabel(promo.type) }}
                      </v-chip>
                      <v-chip v-if="!promo.is_active" color="grey" size="x-small" label>Inactive</v-chip>
                    </div>
                    <p class="text-subtitle-1 font-weight-bold text-brown-darken-4 mt-1">{{ promo.name }}</p>
                  </div>
                  <div>
                    <v-btn icon="mdi-pencil-outline" size="small" variant="text" color="brown" @click="openEditPromo(promo)" />
                    <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="deletePromo(promo.id)" />
                  </div>
                </div>

                <div v-if="promo.discount_value" class="mb-3">
                  <span class="text-h4 font-weight-black text-brown-darken-3">
                    {{ promo.type === 'percentage' ? promo.discount_value + '%' : '$' + promo.discount_value }}
                  </span>
                  <span class="text-caption text-medium-emphasis ml-1">off</span>
                </div>

                <v-divider class="mb-3 opacity-30" />

                <div class="d-flex flex-wrap gap-x-4 gap-y-1 text-caption text-medium-emphasis mb-3">
                  <span><v-icon size="13" class="mr-1">mdi-calendar-start</v-icon>{{ formatDate(promo.start_at) }}</span>
                  <span><v-icon size="13" class="mr-1">mdi-calendar-end</v-icon>{{ formatDate(promo.end_at) }}</span>
                  <span v-if="promo.min_order_amount">
                    <v-icon size="13" class="mr-1">mdi-cash</v-icon>Min ${{ promo.min_order_amount }}
                  </span>
                </div>

                <div v-if="promo.usage_limit">
                  <div class="d-flex justify-space-between text-caption mb-1">
                    <span class="text-medium-emphasis">Usage</span>
                    <span class="font-weight-bold">{{ promo.usage_count }} / {{ promo.usage_limit }}</span>
                  </div>
                  <v-progress-linear
                    :model-value="usagePercent(promo.usage_count, promo.usage_limit)"
                    rounded height="6" bg-color="brown-lighten-4"
                    :color="usagePercent(promo.usage_count, promo.usage_limit) >= 100 ? 'error' : 'brown-darken-2'"
                  />
                </div>
                <p v-else class="text-caption text-medium-emphasis">
                  <v-icon size="13" class="mr-1">mdi-infinity</v-icon>
                  Unlimited usage · {{ promo.usage_count }} used
                </p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-tabs-window-item>

      <!-- ══════════════════ COUPONS ══════════════════════════ -->
      <v-tabs-window-item value="coupons">
        <v-card flat rounded="xl" class="bg-white">
          <!-- Loading -->
          <v-skeleton-loader v-if="loading" type="table-tbody" />

          <!-- Empty state -->
          <div
            v-else-if="!couponStore.coupons.length"
            class="pa-12 text-center"
          >
            <v-icon icon="mdi-ticket-outline" size="48" color="brown-lighten-3" class="mb-3" />
            <p class="text-h6 font-weight-bold text-brown-darken-3">No coupons yet</p>
            <p class="text-body-2 text-medium-emphasis mb-4">Create a coupon linked to a promotion.</p>
            <v-btn color="brown-darken-3" rounded="xl" prepend-icon="mdi-plus" @click="openNewCoupon">
              New Coupon
            </v-btn>
          </div>

          <v-table v-else>
            <thead>
              <tr>
                <th class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Code</th>
                <th class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Promotion</th>
                <th class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Usage</th>
                <th class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Expires</th>
                <th class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in couponStore.coupons" :key="c.id">
                <td>
                  <v-chip label color="brown-lighten-4" class="font-weight-black text-brown-darken-4" size="small">
                    {{ c.code }}
                  </v-chip>
                </td>
                <td class="text-body-2">{{ c.promotion?.name ?? c.promotion_name ?? '—' }}</td>
                <td>
                  <div v-if="c.usage_limit" class="d-flex align-center gap-2">
                    <v-progress-linear
                      :model-value="usagePercent(c.usage_count, c.usage_limit)"
                      rounded height="6" bg-color="brown-lighten-4"
                      :color="usagePercent(c.usage_count, c.usage_limit) >= 100 ? 'error' : 'brown-darken-2'"
                      style="min-width: 80px"
                    />
                    <span class="text-caption">{{ c.usage_count }}/{{ c.usage_limit }}</span>
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">{{ c.usage_count }} used</span>
                </td>
                <td class="text-body-2 text-medium-emphasis">{{ formatDate(c.expires_at) }}</td>
                <td>
                  <v-chip :color="c.is_active ? 'success' : 'grey'" size="x-small" label>
                    {{ c.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </td>
                <td>
                  <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="deleteCoupon(c.id)" />
                </td>
              </tr>
            </tbody>
          </v-table>
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
</template>