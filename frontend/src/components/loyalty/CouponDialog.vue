<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: Boolean,
    editing: { type: Object, default: null },
    promotions: { type: Array, default: () => [] }
  })

  const emit = defineEmits(['update:modelValue', 'save'])

  const saving = ref(false)

  const defaultForm = () => ({
    promotion_id: null,
    code: '',
    usage_limit: null,
    is_active: true,
    expires_at: ''
  })

  const form = ref(defaultForm())

  watch(
    () => props.editing,
    val => {
      form.value = val ? { ...val } : defaultForm()
    },
    { immediate: true }
  )

  const close = () => emit('update:modelValue', false)

  const submit = async () => {
    saving.value = true
    try {
      await emit('save', { ...form.value })
    } finally {
      saving.value = false
    }
  }
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="600"
    :persistent="false"
    :scrollable="false"
    :title="editing ? t('coupons.dialog.edit_title') : t('coupons.dialog.new_title')"
    icon="mdi-ticket-percent-outline"
    :color="editing ? 'primary' : 'success'"
    :loading="saving"
    :submit-text="editing ? t('coupons.dialog.save_changes') : t('coupons.dialog.create_coupon')"
    @update:model-value="emit('update:modelValue', $event)"
    @close="close"
    @submit="submit"
  >
        <v-row dense>
          <v-col cols="6">
            <v-select
              v-model="form.promotion_id"
              :items="promotions"
              item-title="name"
              item-value="id"
              :label="t('coupons.dialog.linked_promotion')"
              variant="outlined"
              rounded="lg"
            />
          </v-col>

          <v-col cols="6">
            <v-text-field
              v-model="form.code"
              :label="t('coupons.dialog.coupon_code')"
              variant="outlined"
              rounded="lg"
              :hint="t('coupons.dialog.coupon_code_hint')"
              persistent-hint
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.usage_limit"
              :label="t('coupons.dialog.usage_limit')"
              type="number"
              variant="outlined"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-date-input
              v-model="form.expires_at"
              :label="t('coupons.dialog.expires_at')"
              variant="outlined"
              rounded="lg"
              prepend-inner-icon="mdi-calendar"
              append-inner-icon=""
            />
          </v-col>

          <v-col cols="12">
            <v-switch
              v-model="form.is_active"
              :label="t('coupons.dialog.active')"
              color="brown-darken-3"
              inset
              hide-details
            />
          </v-col>
        </v-row>
  </AppDialog>
</template>
