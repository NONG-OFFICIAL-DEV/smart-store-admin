<template>
  <div>
    <AppToolbar :title="t('loyalty_rewards.title')" :subtitle="t('loyalty_rewards.subtitle')">
      <template #actions>
        <v-text-field
          v-model="search"
          :placeholder="t('loyalty_rewards.search_placeholder')"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          style="min-width: 220px"
        />
        <v-btn
          v-if="can('customers.manage')"
          color="primary"
          variant="flat"
          rounded="lg"
          class="text-none"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('loyalty_rewards.add_reward') }}
        </v-btn>
      </template>
    </AppToolbar>

    <v-card rounded="lg">
      <v-list v-if="filteredRewards.length" lines="two">
        <template v-for="(reward, i) in filteredRewards" :key="reward.id">
          <v-list-item>
            <template #prepend>
              <v-avatar color="primary" variant="tonal" rounded="lg" size="40" class="me-3">
                <v-icon icon="mdi-gift-outline" size="20" />
              </v-avatar>
            </template>

            <v-list-item-title class="d-flex align-center ga-2 flex-wrap">
              <span class="font-weight-medium">{{ reward.name }}</span>
              <v-chip size="x-small" variant="tonal" color="amber-darken-2" prepend-icon="mdi-star">
                {{ t('loyalty_rewards.points_cost_chip', { points: reward.points_cost }) }}
              </v-chip>
            </v-list-item-title>

            <v-list-item-subtitle>
              <div v-if="reward.description" class="text-truncate">{{ reward.description }}</div>
              <div class="text-body-small">
                {{ reward.branches?.length ? reward.branches.map(b => b.name).join(', ') : t('loyalty_rewards.all_branches') }}
              </div>
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex align-center ga-2">
                <v-chip :color="reward.is_active ? 'success' : 'grey'" variant="tonal" size="small" prepend-icon="mdi-circle">
                  {{ reward.is_active ? t('loyalty_rewards.status.active') : t('loyalty_rewards.status.inactive') }}
                </v-chip>
                <v-btn
                  v-if="can('customers.manage')"
                  variant="tonal"
                  rounded="lg"
                  size="small"
                  class="text-none"
                  @click="openEdit(reward)"
                >
                  {{ t('btn.edit') }}
                </v-btn>
                <v-menu v-if="can('customers.manage')" location="bottom end">
                  <template #activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" />
                  </template>
                  <v-list density="compact" min-width="170">
                    <v-list-item prepend-icon="mdi-delete-outline" base-color="error" @click="handleDelete(reward)">
                      <v-list-item-title>{{ t('btn.delete') }}</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>
              </div>
            </template>
          </v-list-item>
          <v-divider v-if="i < filteredRewards.length - 1" />
        </template>
      </v-list>

      <div v-else class="pa-8 text-center text-medium-emphasis">
        <v-icon icon="mdi-gift-outline" size="36" class="mb-2" />
        <div>{{ search ? t('loyalty_rewards.no_results') : t('loyalty_rewards.empty') }}</div>
        <div v-if="!search" class="text-body-small">{{ t('loyalty_rewards.empty_sub') }}</div>
      </div>
    </v-card>

    <LoyaltyRewardDrawer v-model="formOpen" :reward="editingReward" :loading="saving" @saved="handleSave" />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useLoyaltyRewardStore } from '@/stores/loyaltyRewardStore'
  import { usePermission } from '@/composables/usePermission'
  import { useAppUtils, AppToolbar } from '@nong-official-dev/core'
  import LoyaltyRewardDrawer from '@/components/loyalty/LoyaltyRewardDrawer.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const { can } = usePermission()
  const { confirm, notif } = useAppUtils()
  const rewardStore = useLoyaltyRewardStore()

  const saving = ref(false)
  const search = ref('')

  const formOpen = ref(false)
  const editingReward = ref(null)

  const filteredRewards = computed(() => {
    const q = search.value?.trim().toLowerCase()
    if (!q) return rewardStore.rewards
    return rewardStore.rewards.filter(r => [r.name, r.description].some(v => v?.toLowerCase().includes(q)))
  })

  const openCreate = () => {
    editingReward.value = null
    formOpen.value = true
  }

  const openEdit = reward => {
    editingReward.value = { ...reward }
    formOpen.value = true
  }

  const handleSave = async rewardData => {
    saving.value = true
    try {
      if (rewardData.id) {
        await rewardStore.updateReward(rewardData.id, rewardData)
      } else {
        await rewardStore.createReward(rewardData)
      }
      formOpen.value = false
      notif(t('loyalty_rewards.messages.saved'))
    } finally {
      saving.value = false
    }
  }

  const handleDelete = reward => {
    confirm({
      title: t('loyalty_rewards.messages.delete_title'),
      message: t('loyalty_rewards.messages.delete_message', { name: reward.name }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        await rewardStore.deleteReward(reward.id)
        notif(t('loyalty_rewards.messages.deleted'))
      },
      cancel: () => {}
    })
  }

  onMounted(() => {
    if (!rewardStore.rewards.length) rewardStore.fetchRewards({ perPage: -1 })
  })
</script>
