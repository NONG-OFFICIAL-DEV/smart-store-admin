<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="480"
    :title="currentPlan ? $t('subscription.list.change_plan') : $t('subscription.list.assign_plan')"
    :subtitle="
      currentPlan
        ? $t('subscription.list.change_plan_subtitle')
        : $t('subscription.list.assign_plan_subtitle')
    "
    :icon="currentPlan ? 'mdi-swap-horizontal' : 'mdi-plus'"
    :color="currentPlan ? 'primary' : 'success'"
    :loading="saving"
    :submit-text="currentPlan ? $t('btn.save_changes') : $t('btn.create')"
    body-class="pa-5"
    @update:model-value="$emit('update:modelValue', $event)"
    @submit="submit"
  >
    <v-form ref="formRef" @submit.prevent="submit">
      <div class="d-flex align-center ga-2 mb-4 pa-3 rounded-lg bg-grey-lighten-4">
        <v-icon size="18" color="primary">mdi-domain</v-icon>
        <span class="text-body-2 font-weight-medium">{{ tenantName }}</span>
      </div>

      <v-row dense>
        <v-col cols="12">
          <v-select
            v-model="form.plan_id"
            :items="planStore.plans"
            item-title="name"
            item-value="id"
            :label="$t('subscription.list.field.plan')"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            prepend-inner-icon="mdi-crown-outline"
            :rules="[r.required]"
          >
            <template #item="{ props: p, item: it }">
              <v-list-item v-bind="p">
                <template #prepend>
                  <v-avatar
                    :color="planColor(it.raw.code)"
                    variant="tonal"
                    size="28"
                    rounded="md"
                    class="mr-2"
                  >
                    <v-icon :icon="planIcon(it.raw.code)" size="15" />
                  </v-avatar>
                </template>
                <template #append>
                  <span class="text-caption text-medium-emphasis">
                    ${{ it.raw.price_usd }}/mo
                  </span>
                </template>
              </v-list-item>
            </template>
          </v-select>
        </v-col>

        <v-col cols="12">
          <v-select
            v-model="form.billing_cycle_id"
            :items="availableCycles"
            item-title="label"
            item-value="id"
            :label="$t('subscription.list.field.billing_cycle')"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            prepend-inner-icon="mdi-calendar-sync-outline"
            :rules="[r.required]"
            :disabled="!form.plan_id"
            :hint="!form.plan_id ? $t('subscription.list.select_plan_first') : undefined"
            persistent-hint
          />
        </v-col>
      </v-row>
    </v-form>
  </AppDialog>
</template>

<script setup>
  // Single shared "assign or change a tenant's plan" dialog — used from both
  // the Subscriptions page and the Tenants list, so there is exactly one
  // implementation of this flow instead of one per screen.
  import { ref, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { usePlanStore } from '@/stores/planStore'
  import { useSubscriptionStore } from '@/stores/subscriptionStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const props = defineProps({
    modelValue: { type: Boolean, required: true },
    tenantId: { type: String, default: null },
    tenantName: { type: String, default: '' },
    // The tenant's current plan object, or null/undefined if they don't have one yet.
    currentPlan: { type: Object, default: null }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const planStore = usePlanStore()
  const subscriptionStore = useSubscriptionStore()

  const saving = ref(false)
  const formRef = ref(null)
  const form = ref({ plan_id: null, billing_cycle_id: null })

  const PLAN_UI = {
    free: { color: 'grey', icon: 'mdi-gift-outline' },
    starter: { color: 'blue', icon: 'mdi-star-half-full' },
    pro: { color: 'primary', icon: 'mdi-star' },
    enterprise: { color: 'warning', icon: 'mdi-crown' }
  }
  const planColor = code => PLAN_UI[code]?.color ?? 'grey'
  const planIcon = code => PLAN_UI[code]?.icon ?? 'mdi-help-circle-outline'

  const availableCycles = computed(() => {
    const plan = planStore.plans.find(p => p.id === form.value.plan_id)
    return plan?.billing_cycles?.filter(c => c.is_active) ?? []
  })

  const r = { required: v => !!v || t('products.rule.required') }

  watch(
    () => props.modelValue,
    open => {
      if (open) form.value = { plan_id: null, billing_cycle_id: null }
    }
  )

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    saving.value = true
    try {
      await subscriptionStore.createSubscription({
        tenant_id: props.tenantId,
        plan_id: form.value.plan_id,
        billing_cycle_id: form.value.billing_cycle_id
      })
      notif(t('messages.saved_success'), { type: 'success' })
      emit('update:modelValue', false)
      emit('saved')
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    if (!planStore.plans.length) planStore.fetchPlans()
  })
</script>
