<template>
  <div class="pa-4">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="text-body-1 font-weight-bold">{{ $t('branches.detail.title') }}</div>
      <div class="d-flex gap-2">
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          @click="$emit('close')"
        />
      </div>
    </div>
    <div v-if="loadingStore.isLoading">
      <v-col cols="12" md="12">
        <v-skeleton-loader
          class="mx-auto"
          type="image, article"
        ></v-skeleton-loader>

        <v-skeleton-loader class="mx-auto" type="article"></v-skeleton-loader>
        <v-skeleton-loader class="mx-auto" type="article"></v-skeleton-loader>
      </v-col>
    </div>

    <!-- Error -->
    <v-alert
      v-else-if="store.error"
      type="error"
      variant="tonal"
      :text="store.error"
      class="mb-4"
    />

    <template v-else-if="store.branch">
      <!-- Branch Header Card -->
      <v-card rounded="xl" elevation="0" border class="mb-4 overflow-hidden">
        <v-card-text>
          <div class="d-flex align-center gap-3 mb-3">
            <v-avatar
              :color="typeColor(store.branch.type)"
              size="52"
              rounded="xl"
              variant="tonal"
            >
              <v-icon :icon="typeIcon(store.branch.type)" size="26" />
            </v-avatar>
            <div class="flex-1 min-w-0">
              <div class="d-flex align-center flex-wrap gap-1 mb-1">
                <span class="text-body-1 font-weight-black text-truncate">
                  {{ store.branch.name }}
                </span>
                <v-chip
                  :color="store.branch.is_open ? 'success' : 'error'"
                  size="x-small"
                  variant="flat"
                  class="font-weight-bold"
                >
                  {{ store.branch.is_open ? $t('branches.detail.open') : $t('branches.detail.closed') }}
                </v-chip>
                <v-chip
                  :color="store.branch.is_active ? 'primary' : 'grey'"
                  size="x-small"
                  variant="tonal"
                >
                  {{ store.branch.is_active ? $t('branches.detail.active') : $t('branches.detail.inactive') }}
                </v-chip>
              </div>
              <div
                class="text-caption text-medium-emphasis d-flex align-center gap-1"
              >
                <v-icon
                  icon="mdi-map-marker-outline"
                  size="13"
                  color="primary"
                />
                {{ fullAddress }}
              </div>
              <div class="d-flex gap-3 mt-1">
                <div
                  v-if="store.branch.phone"
                  class="text-caption text-grey-darken-1 d-flex align-center"
                >
                  <v-icon icon="mdi-phone-outline" size="13" class="mr-1" />
                  {{ store.branch.phone }}
                </div>
                <div
                  v-if="store.branch.email"
                  class="text-caption text-grey-darken-1 d-flex align-center"
                >
                  <v-icon icon="mdi-email-outline" size="13" class="mr-1" />
                  {{ store.branch.email }}
                </div>
              </div>
            </div>
          </div>

          <!-- Settings row -->
          <v-divider class="mb-3" />
          <v-row dense>
            <v-col cols="6">
              <div class="text-tiny text-medium-emphasis">{{ $t('form.tax_rate') }}</div>
              <div class="text-body-2 font-weight-black">
                {{ store.branch.tax_rate }}%
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-tiny text-medium-emphasis">{{ $t('branches.detail.service_charge') }}</div>
              <div class="text-body-2 font-weight-black">
                {{ store.branch.service_charge_rate }}%
              </div>
            </v-col>
            <v-col cols="6" class="mt-2">
              <div class="text-tiny text-medium-emphasis">{{ $t('form.type') }}</div>
              <div class="text-body-2 text-capitalize">
                {{ store.branch.type }}
              </div>
            </v-col>
            <v-col cols="6" class="mt-2">
              <div class="text-tiny text-medium-emphasis">{{ $t('branches.detail.slug') }}</div>
              <div class="text-body-2 text-grey-darken-1">
                /{{ store.branch.slug }}
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Stats -->
      <v-row dense class="mb-4">
        <v-col cols="6">
          <v-card rounded="xl" elevation="0" border class="pa-3 text-center">
            <div class="text-h6 font-weight-black text-primary">
              {{ store.stats?.orders_today ?? 0 }}
            </div>
            <div class="text-caption text-grey">{{ $t('branches.detail.orders_today') }}</div>
          </v-card>
        </v-col>
        <v-col cols="6">
          <v-card rounded="xl" elevation="0" border class="pa-3 text-center">
            <div class="text-h6 font-weight-black text-success">
              ${{ store.stats?.revenue_today ?? '0.00' }}
            </div>
            <div class="text-caption text-grey">{{ $t('branches.detail.revenue_today') }}</div>
          </v-card>
        </v-col>
        <v-col cols="6">
          <v-card rounded="xl" elevation="0" border class="pa-3 text-center">
            <div class="text-h6 font-weight-black text-warning">
              ${{ store.stats?.avg_order ?? '0.00' }}
            </div>
            <div class="text-caption text-grey">{{ $t('branches.detail.avg_order') }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" v-if="resolvedBuCode">
          <v-card rounded="xl" elevation="0" border class="pa-3 text-center">
            <div class="text-h6 font-weight-black">
              {{ store.tableSummary?.total ?? 0 }}
            </div>
            <div class="text-caption text-grey">{{ $t('branches.detail.total_tables') }}</div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Tables -->
      <v-card
        rounded="xl"
        elevation="0"
        border
        class="mb-4"
        v-if="resolvedBuCode"
      >
        <v-card-title
          class="pa-4 pb-2 d-flex align-center justify-space-between"
        >
          <span class="text-body-2 font-weight-bold">{{ $t('menu.tables') }}</span>
          <v-btn
            size="x-small"
            variant="tonal"
            rounded="lg"
            @click="router.push(`/branches/${branchId}/tables`)"
          >
            {{ $t('btn.manage') }}
          </v-btn>
        </v-card-title>
        <v-card-text class="pt-0">
          <div class="d-flex justify-space-around text-center py-2">
            <div>
              <div class="text-body-1 font-weight-bold text-success">
                {{ store.tableSummary?.available ?? 0 }}
              </div>
              <div class="text-caption text-grey">{{ $t('products.stats.available') }}</div>
            </div>
            <v-divider vertical />
            <div>
              <div class="text-body-1 font-weight-bold text-error">
                {{ store.tableSummary?.occupied ?? 0 }}
              </div>
              <div class="text-caption text-grey">{{ $t('branches.detail.occupied') }}</div>
            </div>
            <v-divider vertical />
            <div>
              <div class="text-body-1 font-weight-bold text-warning">
                {{ store.tableSummary?.reserved ?? 0 }}
              </div>
              <div class="text-caption text-grey">{{ $t('branches.detail.reserved') }}</div>
            </div>
          </div>
          <v-list density="compact" class="mt-1">
            <v-list-item
              v-for="table in store.tables.slice(0, 5)"
              :key="table.id"
              rounded="lg"
              class="px-2"
            >
              <template #prepend>
                <v-icon
                  :icon="
                    table.shape === 'circle'
                      ? 'mdi-circle-outline'
                      : 'mdi-rectangle-outline'
                  "
                  size="14"
                  class="mr-2"
                />
              </template>
              <v-list-item-title class="text-body-2">
                {{ $t('branches.detail.table_number', { n: table.table_number }) }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ $t('branches.detail.capacity', { n: table.capacity }) }}
              </v-list-item-subtitle>
              <template #append>
                <v-chip
                  :color="tableStatusColor(table.status)"
                  size="x-small"
                  variant="tonal"
                >
                  {{ table.status }}
                </v-chip>
              </template>
            </v-list-item>
            <v-list-item
              v-if="store.tables.length > 5"
              class="text-caption text-grey text-center"
            >
              {{ $t('branches.detail.more_tables', { n: store.tables.length - 5 }) }}
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>

      <!-- Staff -->
      <v-card rounded="xl" elevation="0" border>
        <v-card-title
          class="pa-4 pb-2 d-flex align-center justify-space-between"
        >
          <span class="text-body-2 font-weight-bold">
            {{ $t('branches.detail.staff_count', { n: store.staff.length }) }}
          </span>
          <v-btn
            size="x-small"
            variant="tonal"
            rounded="lg"
            @click="router.push('/staff-management')"
          >
            {{ $t('btn.manage') }}
          </v-btn>
        </v-card-title>
        <v-card-text class="pt-0">
          <v-list density="compact">
            <v-list-item
              v-for="s in store.staff.slice(0, 6)"
              :key="s.id"
              rounded="lg"
              class="px-2"
            >
              <template #prepend>
                <v-avatar
                  color="secondary"
                  variant="tonal"
                  size="30"
                  rounded="lg"
                  class="mr-2"
                >
                  <span class="text-caption font-weight-bold">
                    {{ s.user?.first_name?.[0] }}{{ s.user?.last_name?.[0] }}
                  </span>
                </v-avatar>
              </template>
              <v-list-item-title class="text-body-2">
                {{ s.user?.first_name }} {{ s.user?.last_name }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ s.role?.name ?? $t('branches.detail.no_role') }}
              </v-list-item-subtitle>
              <template #append>
                <v-chip
                  :color="s.is_active ? 'success' : 'grey'"
                  size="x-small"
                  variant="tonal"
                >
                  {{ s.is_active ? $t('status.active') : $t('status.inactive') }}
                </v-chip>
              </template>
            </v-list-item>
            <v-list-item
              v-if="store.staff.length === 0"
              class="text-caption text-grey"
            >
              {{ $t('branches.detail.no_staff_assigned') }}
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </template>
  </div>
</template>

<script setup>
  import { computed, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import { useBranchStore } from '@/stores/branchStore'
  import { useLoadingStore } from '@/stores/loading'
  const loadingStore = useLoadingStore()

  const props = defineProps({
    branchId: { type: [String, Number], required: true }
  })

  defineEmits(['close'])

  const router = useRouter()
  const store = useBranchStore()

  const resolvedBuCode = computed(() => {
    return store.branch.tenant.business_type.code == 'RESTAURANT'
  })

  const fullAddress = computed(() =>
    [store.branch?.address_line1, store.branch?.city, store.branch?.country]
      .filter(Boolean)
      .join(', ')
  )

  // Re-fetch whenever the drawer opens with a different branch
  watch(
    () => props.branchId,
    id => {
      if (id) store.fetchBranchById(id)
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

  const tableStatusColor = status =>
    ({
      available: 'success',
      occupied: 'error',
      reserved: 'warning'
    })[status] ?? 'grey'
</script>
