<template>
  <div>
    <div
      v-if="filteredPromotions.length"
      class="d-flex flex-column promotion-card-list"
    >
      <v-card
        v-for="item in filteredPromotions"
        :key="item.id"
        rounded="lg"
        elevation="0"
        border
        :class="{ 'promotion-card--inactive': !item.is_active }"
      >
        <!-- ── Header ─────────────────────────────────────────────────── -->
        <div class="d-flex align-center flex-wrap ga-3 pa-4">
          <v-avatar color="primary" variant="tonal" size="36" rounded="md">
            <v-icon icon="mdi-tag-multiple-outline" size="18" />
          </v-avatar>
          <div class="flex-grow-1" style="min-width: 220px">
            <div class="d-flex align-center flex-wrap ga-2">
              <span class="text-subtitle-1 font-weight-bold">
                {{ item.name }}
              </span>
              <v-chip
                :color="typeColor(item.type)"
                size="x-small"
                variant="flat"
              >
                {{ typeLabel(item.type) }}
              </v-chip>
              <AppStatusChip
                :status="item.is_active ? 'active' : 'inactive'"
                size="x-small"
              />
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ discountSummary(item) }} · {{ dateWindowLabel(item) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{
                t('promotions.card.usage_label', { usage: usageSummary(item) })
              }}
              ·
              {{
                couponCountFor(item.id)
                  ? t('promotions.card.coupons_count', {
                      n: couponCountFor(item.id)
                    })
                  : t('promotions.card.no_coupons')
              }}
            </div>
          </div>
          <div class="d-flex align-center ga-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="36"
              variant="text"
              color="primary"
              :aria-label="t('btn.edit')"
              @click="startEdit(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="36"
              variant="text"
              color="error"
              :aria-label="t('btn.delete')"
              @click="confirmDelete(item)"
            />
          </div>
        </div>

        <v-divider />

        <!-- ── Coupons ─────────────────────────────────────────────────── -->
        <div class="px-4 py-1 d-flex flex-column ga-3">
          <template v-if="couponsFor(item.id).length">
            <div
              v-for="c in couponsFor(item.id)"
              :key="c.id"
              class="coupon-row d-flex align-center flex-wrap ga-4"
            >
              <div class="d-flex align-center ga-2 coupon-row__col">
                <v-icon
                  icon="mdi-ticket-percent-outline"
                  size="16"
                  class="text-medium-emphasis"
                />
                <span class="text-body-2 font-weight-medium">
                  {{ c.code || '—' }}
                </span>
              </div>
              <div class="text-body-2 coupon-row__col">
                {{ couponUsageLabel(c) }}
              </div>
              <div class="text-body-2 coupon-row__col">
                {{ couponExpiresLabel(c) }}
              </div>
              <div class="d-flex align-center ga-2 coupon-row__col">
                <span
                  class="status-dot"
                  :class="`status-dot--${couponStatus(c).state}`"
                />
                <span class="text-body-2 text-medium-emphasis">
                  {{ couponStatus(c).label }}
                </span>
              </div>
              <v-spacer />
              <v-btn
                size="small"
                variant="tonal"
                rounded="lg"
                :aria-label="t('btn.edit')"
                @click="openEditCoupon(c)"
              >
                {{ t('btn.edit') }}
              </v-btn>
              <v-btn
                icon="mdi-close-circle-outline"
                size="36"
                variant="text"
                color="error"
                :aria-label="t('promotions.card.remove_title')"
                @click="confirmRemoveCoupon(c.id)"
              />
            </div>
          </template>
          <v-alert
            v-else
            density="compact"
            type="info"
          >
            {{ t('promotions.card.no_coupons_warning') }}
          </v-alert>
        </div>

        <v-divider />

        <div class="pa-3">
          <v-btn
            variant="outlined"
            rounded="lg"
            class="border-dashed"
            prepend-icon="mdi-plus"
            @click="openAddCouponFor(item)"
          >
            {{ t('promotions.card.add_coupon') }}
          </v-btn>
        </div>
      </v-card>
    </div>

    <v-card v-else rounded="lg" elevation="0" border>
      <div class="text-center py-10">
        <v-icon
          icon="mdi-tag-multiple-outline"
          size="40"
          color="grey-lighten-1"
        />
        <p class="text-medium-emphasis mt-2 mb-0">
          {{ t('promotions.empty') }}
        </p>
      </div>
    </v-card>

    <!-- Create / edit promotion -->
    <PromotionDialog
      v-model="promoDialog"
      :editing="editingPromo"
      @save="handleSavePromo"
    />

    <!-- Create / edit coupon -->
    <CouponDialog
      v-model="couponDialog"
      :editing="editingCoupon"
      :promotions="promotionStore.promotions"
      :preset-promotion-id="presetPromotionId"
      @save="handleSaveCoupon"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils, AppStatusChip } from '@nong-official-dev/core'
  import { usePromotionStore } from '@/stores/promotionStore'
  import { useCouponStore } from '@/stores/couponStore'
  import { useDate } from '@/composables/useDate'
  import PromotionDialog from '@/components/loyalty/PromotionDialog.vue'
  import CouponDialog from '@/components/loyalty/CouponDialog.vue'

  const props = defineProps({
    search: { type: String, default: '' }
  })

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const { formatShortDate } = useDate()

  const promotionStore = usePromotionStore()
  const couponStore = useCouponStore()

  const saving = ref(false)

  async function load() {
    await Promise.all([
      promotionStore.fetchPromotions({ perPage: -1 }),
      couponStore.fetchCoupons({ perPage: -1 })
    ])
  }

  onMounted(load)

  function errorMessage(err) {
    return (
      err?.response?.data?.message ??
      err?.response?.data?.errors?.[0] ??
      t('common.error')
    )
  }

  const filteredPromotions = computed(() => {
    const q = props.search.trim().toLowerCase()
    if (!q) return promotionStore.promotions
    return promotionStore.promotions.filter(p =>
      p.name?.toLowerCase().includes(q)
    )
  })

  // Coupons for a promotion, derived from the already-loaded coupons list
  // (CouponRepository::query() always eager-loads `promotion`, so this
  // composes client-side instead of adding a new endpoint / N+1 requests).
  const couponsFor = promotionId =>
    couponStore.coupons.filter(c => c.promotion_id === promotionId)
  const couponCountFor = promotionId => couponsFor(promotionId).length

  // ── Promotion types — same vocabulary as PromotionDialog.vue ───────────────
  const PROMO_TYPES = {
    percentage: {
      label: t('promotions.types.percentage'),
      color: 'orange-darken-2'
    },
    fixed_amount: {
      label: t('promotions.types.fixed'),
      color: 'teal-darken-2'
    },
    bogo: {
      label: t('promotions.types.buy_x_get_y'),
      color: 'purple-darken-2'
    },
    free_item: {
      label: t('promotions.types.free_item'),
      color: 'green-darken-2'
    },
    combo: { label: t('promotions.types.combo'), color: 'blue-darken-2' },
    happy_hour: {
      label: t('promotions.types.happy_hour'),
      color: 'brown-darken-2'
    }
  }
  const typeLabel = type => PROMO_TYPES[type]?.label ?? type
  const typeColor = type => PROMO_TYPES[type]?.color ?? 'grey'

  function discountSummary(item) {
    if (item.type === 'percentage') {
      const base = `${item.discount_value}% ${t('promotions.form.off')}`
      return item.max_discount_amount
        ? `${base} (max $${item.max_discount_amount})`
        : base
    }
    if (
      ['fixed_amount', 'happy_hour'].includes(item.type) &&
      item.discount_value
    ) {
      return `$${item.discount_value} ${t('promotions.form.off')}`
    }
    return typeLabel(item.type)
  }

  function dateWindowLabel(item) {
    const start = formatShortDate(item.start_at)
    const end = item.end_at
      ? formatShortDate(item.end_at)
      : t('promotions.card.no_end_date')
    return `${start} – ${end}`
  }

  function usageSummary(item) {
    return item.usage_limit
      ? `${item.usage_count}/${item.usage_limit}`
      : t('promotions.unlimited_usage')
  }

  function couponUsageLabel(c) {
    return c.usage_limit
      ? `${c.usage_count}/${c.usage_limit}`
      : t('promotions.unlimited_usage')
  }

  function couponExpiresLabel(c) {
    return c.expires_at
      ? formatShortDate(c.expires_at)
      : t('promotions.card.no_end_date')
  }

  function couponStatus(c) {
    if (!c.is_active) return { state: 'off', label: t('status.inactive') }
    if (c.expires_at && new Date(c.expires_at) < new Date()) {
      return { state: 'off', label: t('promotions.card.expired') }
    }
    return { state: 'serving', label: t('status.active') }
  }

  // ── Create / edit promotion ──────────────────────────────────────────────
  // promotionStore.createPromotion/updatePromotion/deletePromotion already
  // patch `promotions` in place — no need to re-fetch the whole list after
  // a save, only the affected card's data changes.
  const promoDialog = ref(false)
  const editingPromo = ref(null)

  function startEdit(item) {
    editingPromo.value = { ...item }
    promoDialog.value = true
  }

  async function handleSavePromo(formData) {
    saving.value = true
    try {
      if (editingPromo.value) {
        await promotionStore.updatePromotion(editingPromo.value.id, formData)
      } else {
        await promotionStore.createPromotion(formData)
      }
      promoDialog.value = false
      notif(t('messages.saved_success'), { type: 'success' })
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  function confirmDelete(promo) {
    const couponCount = couponCountFor(promo.id)
    confirm({
      title: t('promotions.delete_title'),
      message: couponCount
        ? t('promotions.delete_message_with_coupons', { n: couponCount })
        : t('promotions.delete_message'),
      options: { type: 'warning', width: 420 },
      agree: async () => {
        await promotionStore.deletePromotion(promo.id)
        notif(t('messages.deleted_success'), { type: 'success' })
      }
    })
  }

  // ── Create / edit / remove coupon ────────────────────────────────────────
  // couponStore.createCoupon/updateCoupon/deleteCoupon already patch
  // `coupons` in place — same no-full-reload reasoning as promotions above.
  const couponDialog = ref(false)
  const editingCoupon = ref(null)
  const presetPromotionId = ref(null)

  function openAddCouponFor(promo) {
    editingCoupon.value = null
    presetPromotionId.value = promo.id
    couponDialog.value = true
  }

  function openEditCoupon(coupon) {
    editingCoupon.value = { ...coupon }
    presetPromotionId.value = null
    couponDialog.value = true
  }

  async function handleSaveCoupon(formData) {
    saving.value = true
    try {
      if (editingCoupon.value) {
        await couponStore.updateCoupon(editingCoupon.value.id, formData)
        notif(t('messages.saved_success'), { type: 'success' })
      } else {
        await couponStore.createCoupon(formData)
        notif(t('messages.saved_success'), { type: 'success' })
      }
      couponDialog.value = false
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  function confirmRemoveCoupon(couponId) {
    confirm({
      title: t('promotions.card.remove_title'),
      message: t('promotions.card.remove_message'),
      options: { type: 'warning', width: 400 },
      agree: async () => {
        await couponStore.deleteCoupon(couponId)
        notif(t('messages.deleted_success'), { type: 'success' })
      }
    })
  }

  defineExpose({
    openCreatePromotion: () => {
      editingPromo.value = null
      promoDialog.value = true
    }
  })
</script>

<style scoped>
  .promotion-card-list {
    gap: 12px;
  }
  .promotion-card--inactive {
    opacity: 0.7;
  }
  .coupon-row {
    padding: 8px 0;
  }
  .coupon-row + .coupon-row {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .coupon-row__col {
    min-width: 110px;
  }
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex: none;
  }
  .status-dot--serving {
    background: rgb(var(--v-theme-success));
  }
  .status-dot--off {
    background: rgb(var(--v-theme-on-surface));
    opacity: 0.38;
  }
</style>
