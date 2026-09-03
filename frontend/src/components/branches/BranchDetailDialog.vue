<template>
  <AppDialog
    :model-value="modelValue"
    :title="store.branch?.name ?? $t('branches.detail.title')"
    :subtitle="branchTypeLabel"
    :icon="typeIcon(store.branch?.branch_type?.name)"
    :color="typeColor(store.branch?.branch_type?.name)"
    :max-width="440"
    hide-submit
    :cancel-text="$t('btn.close')"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="$emit('update:modelValue', false)"
  >
    <v-skeleton-loader v-if="loading" type="article" />

    <v-alert v-else-if="store.error" type="error" variant="tonal" :text="store.error" />

    <template v-else-if="store.branch">
      <div class="d-flex ga-2 mb-4">
        <v-chip
          :color="store.branch.is_open ? 'success' : 'error'"
          size="small"
          variant="flat"
          class="font-weight-bold"
        >
          {{ store.branch.is_open ? $t('branches.detail.open') : $t('branches.detail.closed') }}
        </v-chip>
        <v-chip
          :color="store.branch.is_active ? 'primary' : 'grey'"
          size="small"
          variant="tonal"
        >
          {{ store.branch.is_active ? $t('branches.detail.active') : $t('branches.detail.inactive') }}
        </v-chip>
      </div>

      <div class="d-flex flex-column ga-2 mb-2">
        <div v-if="fullAddress" class="d-flex align-center text-body-2">
          <v-icon icon="mdi-map-marker-outline" size="16" color="primary" class="me-2" />
          {{ fullAddress }}
        </div>
        <div v-if="store.branch.phone" class="d-flex align-center text-body-2">
          <v-icon icon="mdi-phone-outline" size="16" color="primary" class="me-2" />
          {{ store.branch.phone }}
        </div>
        <div v-if="store.branch.email" class="d-flex align-center text-body-2">
          <v-icon icon="mdi-email-outline" size="16" color="primary" class="me-2" />
          {{ store.branch.email }}
        </div>
        <div
          v-if="!fullAddress && !store.branch.phone && !store.branch.email"
          class="text-caption text-medium-emphasis"
        >
          {{ $t('branches.detail.no_contact_info') }}
        </div>
      </div>

      <template v-if="hasRates">
        <v-divider class="my-3" />
        <v-row dense>
          <v-col v-if="Number(store.branch.tax_rate) > 0" cols="6">
            <div class="text-caption text-medium-emphasis">{{ $t('form.tax_rate') }}</div>
            <div class="text-body-2 font-weight-bold">{{ store.branch.tax_rate }}%</div>
          </v-col>
          <v-col v-if="store.branch.service_charge_rate" cols="6">
            <div class="text-caption text-medium-emphasis">{{ $t('branches.detail.service_charge') }}</div>
            <div class="text-body-2 font-weight-bold">{{ store.branch.service_charge_rate }}%</div>
          </v-col>
        </v-row>
      </template>

      <v-divider class="my-3" />

      <!-- Today at a glance — the one thing not already visible on the
           branches table itself. -->
      <v-row dense>
        <v-col cols="6">
          <v-card rounded="lg" elevation="0" border class="pa-3 text-center">
            <div class="text-h6 font-weight-black text-primary">
              {{ store.stats?.orders_today ?? 0 }}
            </div>
            <div class="text-caption text-grey">{{ $t('branches.detail.orders_today') }}</div>
          </v-card>
        </v-col>
        <v-col cols="6">
          <v-card rounded="lg" elevation="0" border class="pa-3 text-center">
            <div class="text-h6 font-weight-black text-success">
              ${{ store.stats?.revenue_today ?? '0.00' }}
            </div>
            <div class="text-caption text-grey">{{ $t('branches.detail.revenue_today') }}</div>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </AppDialog>
</template>

<script setup>
  import { computed, ref, watch } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    branchId: { type: [String, Number], default: null }
  })

  defineEmits(['update:modelValue'])

  const store = useBranchStore()
  const loading = ref(false)

  const branchTypeLabel = computed(() => store.branch?.branch_type?.name)

  const fullAddress = computed(() =>
    [store.branch?.address_line1, store.branch?.city, store.branch?.country]
      .filter(Boolean)
      .join(', ')
  )

  const hasRates = computed(
    () => Number(store.branch?.tax_rate) > 0 || !!store.branch?.service_charge_rate
  )

  // Re-fetch every time the dialog opens — covers both a different branch
  // and re-opening the same one after its stats may have changed.
  watch(
    () => [props.modelValue, props.branchId],
    async ([open, id]) => {
      if (!open || !id) return
      loading.value = true
      try {
        await store.fetchBranchById(id)
      } finally {
        loading.value = false
      }
    },
    { immediate: true }
  )

  const typeIcon = type =>
    ({
      restaurant: 'mdi-silverware-fork-knife',
      cafe: 'mdi-coffee',
      kiosk: 'mdi-store-outline',
      food_truck: 'mdi-truck-outline'
    })[type] ?? 'mdi-store'

  const typeColor = type =>
    ({
      restaurant: 'primary',
      cafe: 'brown',
      kiosk: 'orange',
      food_truck: 'teal'
    })[type] ?? 'grey'
</script>
