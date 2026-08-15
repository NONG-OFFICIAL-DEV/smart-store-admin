<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-crown-outline"
      :title="$t('subscription.plan.title')"
      :subtitle="$t('subscription.plan.subtitle')"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ $t('subscription.plan.new') }}
        </v-btn>
      </template>
    </custom-title>

    <v-card rounded="xl" border elevation="0">
      <v-data-table
        :headers="headers"
        :items="planStore.plans"
        :loading="loading"
        rounded="xl"
        hover
      >
        <!-- Name + code -->
        <template #item.name="{ item }">
          <div class="d-flex align-center ga-2">
            <v-avatar
              :color="planColor(item.code)"
              variant="tonal"
              size="32"
              rounded="md"
            >
              <v-icon :icon="planIcon(item.code)" size="16" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.code }}
              </div>
            </div>
          </div>
        </template>

        <template #item.price_usd="{ item }">
          <div class="text-body-2 font-weight-medium">
            ${{ item.price_usd }}
            <span class="text-caption text-medium-emphasis">/mo</span>
          </div>
        </template>

        <template #item.seats="{ item }">
          <div class="d-flex flex-wrap ga-1">
            <v-chip
              size="x-small"
              variant="tonal"
              :color="planColor(item.code)"
            >
              {{ $t('subscription.plan.seats_count', item.seats) }}
            </v-chip>
            <v-chip
              size="x-small"
              variant="tonal"
              :color="planColor(item.code)"
            >
              {{ item.storage_gb }}GB
            </v-chip>
            <v-chip
              size="x-small"
              variant="tonal"
              :color="planColor(item.code)"
            >
              {{
                item.api_limit > 0
                  ? item.api_limit.toLocaleString() + ' API'
                  : '∞ API'
              }}
            </v-chip>
          </div>
        </template>

        <template #item.is_active="{ item }">
          <v-chip
            size="x-small"
            :color="item.is_active ? 'success' : 'default'"
            variant="flat"
          >
            {{ item.is_active ? $t('status.active') : $t('status.inactive') }}
          </v-chip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end ga-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              @click="openEdit(item)"
            />
            <v-switch
              v-model="item.is_active"
              inset
              hide-details
              density="compact"
              color="success"
              true-icon="mdi-checkbox-marked-circle-outline"
              @click="togglePlan(item)"
            ></v-switch>
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="openDelete(item)"
            />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Plan Dialog -->
    <PlanDialog
      v-model="dialog"
      :editing-plan="editingPlan"
      :saving="saving"
      @submit="handleSubmit"
    />
  </v-container>
</template>

<script setup>
  import { ref, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { usePlanStore } from '@/stores/planStore'
  import PlanDialog from '../../components/subscriptions/PlanDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  const planStore = usePlanStore()

  const dialog = ref(false)
  const editingPlan = ref(null)
  const saving = ref(false)
  const loading = ref(false)

  // Table headers
  const headers = [
    { title: t('subscription.plan.table.name'), key: 'name', sortable: true },
    { title: t('form.price'), key: 'price_usd', sortable: true },
    { title: t('subscription.plan.table.limits'), key: 'seats', sortable: false },
    { title: t('form.status'), key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // Plan UI
  const PLAN_UI = {
    free: { color: 'grey', icon: 'mdi-gift-outline' },
    starter: { color: 'blue', icon: 'mdi-star-half-full' },
    pro: { color: 'primary', icon: 'mdi-star' },
    enterprise: { color: 'warning', icon: 'mdi-crown' }
  }
  const planColor = code => PLAN_UI[code]?.color ?? 'grey'
  const planIcon = code => PLAN_UI[code]?.icon ?? 'mdi-help-circle-outline'

  // Dialog handlers
  const openCreate = () => {
    editingPlan.value = null
    dialog.value = true
  }

  const openEdit = item => {
    editingPlan.value = item
    dialog.value = true
  }

  const handleSubmit = async formData => {
    saving.value = true
    try {
      if (editingPlan.value) {
        await planStore.updatePlan(editingPlan.value.id, formData)
        notif(t('subscription.plan.messages.updated'), { type: 'success' })
      } else {
        await planStore.createPlan(formData)
        notif(t('subscription.plan.messages.created'), { type: 'success' })
      }
      dialog.value = false
    } finally {
      saving.value = false
    }
  }
  const openDelete = async item => {
    try {
      confirm({
        title: t('subscription.plan.confirm_delete.title'),
        message: t('subscription.plan.confirm_delete.message', { name: item.name }),
        options: { type: 'warning', width: 400 },
        agree: async () => {
          await planStore.deletePlan(item.id)
          notif(t('subscription.plan.messages.deleted'), { type: 'success' })
        },
        cancel: () => {}
      })
    } catch {
      notif(t('subscription.plan.messages.delete_failed'), { type: 'error' })
    }
  }

  const togglePlan = item => planStore.toggleActive(item.id)

  // Fetch on mount
  onMounted(async () => {
    loading.value = true
    await planStore.fetchPlans()
    loading.value = false
  })
</script>
