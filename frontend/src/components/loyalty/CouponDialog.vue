<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDatePicker, AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: Boolean,
    editing: { type: Object, default: null },
    promotions: { type: Array, default: () => [] },
    // Opened from a promotion card's "+ Add coupon" button — the promotion
    // is already known from context, so lock it instead of making the user
    // re-pick it from the dropdown.
    presetPromotionId: { type: String, default: null }
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

  // Populate (edit) or reset+preset (create) in ONE watcher, triggered only
  // when the dialog actually opens — both pieces of logic read `editing`/
  // `presetPromotionId` from the same synchronous callback, so there's no
  // dependency on two separate watchers firing in a particular relative
  // order. Splitting this across two watchers (one for `editing`, one for
  // `modelValue`) was the previous bug: after an edit cycle left `editing`
  // stale, the next "+ Add coupon" open could resolve the two watchers in
  // an order where the preset never got applied, leaving Linked Promotion
  // blank.
  watch(
    () => props.modelValue,
    open => {
      if (!open) return
      if (props.editing) {
        form.value = { ...props.editing }
      } else {
        form.value = defaultForm()
        if (props.presetPromotionId) form.value.promotion_id = props.presetPromotionId
      }
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
    :title="editing ? t('coupons.dialog.edit_title') : t('coupons.dialog.new_title')"
    :loading="saving"
    @update:model-value="emit('update:modelValue', $event)"
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
              :disabled="!!editing || !!presetPromotionId"
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
            <AppDatePicker
              v-model="form.expires_at"
              :label="t('coupons.dialog.expires_at')"
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

        <template #actions="{ loading }">
          <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
            {{ t('btn.cancel') }}
          </v-btn>
          <v-btn
            :color="editing ? 'primary' : 'success'"
            variant="flat"
            rounded="lg"
            :loading="loading"
            @click="submit"
          >
            {{ editing ? t('coupons.dialog.save_changes') : t('coupons.dialog.create_coupon') }}
          </v-btn>
        </template>
  </AppDialog>
</template>
