<template>
  <v-list v-if="showSkeleton || showSwitcher" nav density="compact">
    <!-- Loading — shown while branches are being fetched, so the switcher
         doesn't just pop in once the request resolves. -->
    <template v-if="showSkeleton">
      <div class="d-flex align-center" :class="rail ? 'justify-center py-2' : 'px-3 py-2 ga-3'">
        <v-progress-circular :size="25" indeterminate></v-progress-circular>
      </div>
      <v-divider class="mx-2" />
    </template>

    <!-- Rail mode — icon only, opens a menu -->
    <template v-else-if="rail">
      <v-tooltip location="right">
        <template #activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-store-outline"
            rounded="lg"
            class="mb-1"
          />
        </template>
        <span>{{ activeBranchName || t('branch_switcher.select') }}</span>
      </v-tooltip>
      <v-menu activator="parent" location="right">
        <v-list density="compact">
          <v-list-item
            v-for="b in branches"
            :key="b.id"
            :active="b.id === authStore.activeBranchId"
            @click="select(b.id)"
          >
            {{ b.name }}
          </v-list-item>
        </v-list>
      </v-menu>
      <v-divider class="mx-2" />
    </template>

    <!-- Expanded mode -->
    <template v-else>
      <v-menu location="bottom">
        <template #activator="{ props }">
          <v-list-item v-bind="props" rounded="lg" class="mb-1 branch-switcher-item">
            <template #prepend>
              <v-icon icon="mdi-store-outline" />
            </template>
            <v-list-item-title class="font-weight-medium">
              {{ activeBranchName || t('branch_switcher.select') }}
            </v-list-item-title>
            <template #append>
              <v-icon icon="mdi-unfold-more-horizontal" size="16" />
            </template>
          </v-list-item>
        </template>
        <v-list density="compact">
          <v-list-item
            v-for="b in branches"
            :key="b.id"
            :active="b.id === authStore.activeBranchId"
            @click="select(b.id)"
          >
            {{ b.name }}
          </v-list-item>
        </v-list>
      </v-menu>
      <v-divider class="mx-2" />
    </template>
  </v-list>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'

  defineProps({ rail: Boolean })

  const { t } = useI18n()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()

  const branches = computed(() => branchStore.branches)
  const loading = ref(false)

  // Super admin has no branch concept; staff are pinned to one branch
  // (authStore.branch_id forces activeBranchId, see authStore.fetchMe()) —
  // for either, we already know synchronously there's nothing to show, so
  // skip the loading flash entirely.
  const isFixed = computed(() => authStore.isSuperAdmin || !!authStore.branch_id)

  const showSkeleton = computed(() => !isFixed.value && loading.value)

  // Only worth showing when there's an actual choice to make — a
  // single-branch tenant has nothing to switch between either way.
  const showSwitcher = computed(
    () => !isFixed.value && !loading.value && branches.value.length > 1
  )

  const activeBranchName = computed(
    () => branches.value.find(b => b.id === authStore.activeBranchId)?.name
  )

  function select(id) {
    authStore.setActiveBranch(id)
  }

  onMounted(async () => {
    if (isFixed.value) return
    if (branchStore.branches.length) return

    loading.value = true
    try {
      await branchStore.fetchBranches()
      const fetched = branchStore.branches
      const activeStillValid = fetched.some(b => b.id === authStore.activeBranchId)
      // Covers both: nothing persisted yet (first time an owner lands
      // here), and a stale/foreign id left over in localStorage (e.g. the
      // branch was deleted, or another tenant's id on a shared device).
      // With a single branch there's no ambiguity and no switcher UI to
      // fix it manually (showSwitcher requires length > 1), so this is
      // the only place a mismatch ever gets corrected.
      if (!activeStillValid && fetched.length) {
        authStore.setActiveBranch(fetched[0].id)
      }
    } finally {
      loading.value = false
    }
  })
</script>

<style scoped>
  .branch-switcher-item :deep(.v-list-item-title) {
    font-size: 0.875rem;
  }
</style>
