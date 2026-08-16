<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="480"
    :title="$t('subscription.payment_dialog.title')"
    :subtitle="$t('subscription.payment_dialog.subtitle')"
    icon="mdi-cash-check"
    color="success"
    :loading="saving"
    :submit-text="$t('subscription.payment_dialog.record')"
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
        <v-col cols="8">
          <v-text-field
            v-model="form.amount_usd"
            :label="$t('subscription.payment_dialog.amount')"
            type="number"
            min="0"
            step="0.01"
            :rules="[r.required]"
            prepend-inner-icon="mdi-currency-usd"
            variant="outlined"
            rounded="lg"
          />
        </v-col>
        <v-col cols="4">
          <v-select
            v-model="form.currency"
            :items="['USD', 'KHR']"
            :label="$t('subscription.payment_dialog.currency')"
            :rules="[r.required]"
            variant="outlined"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12">
          <v-text-field
            v-model="form.paid_at"
            :label="$t('subscription.payment_dialog.paid_at')"
            type="date"
            :rules="[r.required]"
            prepend-inner-icon="mdi-calendar-outline"
            variant="outlined"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12">
          <v-textarea
            v-model="form.note"
            :label="$t('subscription.payment_dialog.note')"
            rows="2"
            auto-grow
            variant="outlined"
            rounded="lg"
          />
        </v-col>
      </v-row>
    </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useSubscriptionStore } from '@/stores/subscriptionStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const props = defineProps({
    modelValue: { type: Boolean, required: true },
    tenantId: { type: String, default: null },
    tenantName: { type: String, default: '' }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const subscriptionStore = useSubscriptionStore()

  const saving = ref(false)
  const formRef = ref(null)
  const defaultForm = () => ({
    amount_usd: null,
    currency: 'USD',
    paid_at: new Date().toISOString().slice(0, 10),
    note: ''
  })
  const form = ref(defaultForm())

  const r = { required: v => (v !== null && v !== undefined && v !== '') || t('form.required') }

  watch(
    () => props.modelValue,
    open => {
      if (open) form.value = defaultForm()
    }
  )

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    saving.value = true
    try {
      await subscriptionStore.recordPayment(props.tenantId, form.value)
      notif(t('subscription.payment_dialog.recorded_success'), { type: 'success' })
      emit('update:modelValue', false)
      emit('saved')
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }
</script>
