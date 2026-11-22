<template>
  <AppDrawer
    :model-value="modelValue"
    width="420"
    :persistent="loading"
    :confirm-close="() => confirmDiscard(isDirty)"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #title>
      {{ isEdit ? t('loyalty_rewards.drawer.edit_title') : t('loyalty_rewards.drawer.add_title') }}
    </template>
    <template #subtitle>{{ t('loyalty_rewards.drawer.subtitle') }}</template>

    <v-alert
      v-if="formError"
      type="error"
      variant="tonal"
      density="compact"
      rounded="lg"
      class="mb-4"
      :text="formError"
    />

    <v-text-field
      v-model="form.name"
      :label="t('loyalty_rewards.drawer.name_label') + ' *'"
      variant="outlined"
      rounded="lg"
      density="comfortable"
      hide-details
      maxlength="150"
      :autofocus="!isTouch"
      class="mb-4 mt-4"
    />

    <v-text-field
      v-model="form.name_km"
      :label="t('loyalty_rewards.drawer.name_km_label')"
      variant="outlined"
      rounded="lg"
      density="comfortable"
      hide-details
      maxlength="150"
      class="mb-4"
    />

    <v-textarea
      v-model="form.description"
      :label="t('loyalty_rewards.drawer.description_label')"
      variant="outlined"
      rounded="lg"
      density="comfortable"
      hide-details
      rows="2"
      auto-grow
      class="mb-4"
    />

    <v-textarea
      v-model="form.description_km"
      :label="t('loyalty_rewards.drawer.description_km_label')"
      variant="outlined"
      rounded="lg"
      density="comfortable"
      hide-details
      rows="2"
      auto-grow
      class="mb-4"
    />

    <v-text-field
      v-model.number="form.points_cost"
      type="number"
      min="1"
      :label="t('loyalty_rewards.drawer.points_cost_label') + ' *'"
      :hint="t('loyalty_rewards.drawer.points_cost_hint')"
      persistent-hint
      variant="outlined"
      rounded="lg"
      density="comfortable"
      class="mb-4"
    />

    <v-select
      v-model="form.branch_ids"
      :items="branchStore.branches"
      item-title="name"
      item-value="id"
      multiple
      chips
      closable-chips
      :label="t('loyalty_rewards.drawer.branch_label')"
      :hint="t('loyalty_rewards.drawer.branch_hint')"
      persistent-hint
      variant="outlined"
      rounded="lg"
      density="comfortable"
      class="mb-4"
    />

    <v-select
      v-model="form.type"
      :items="typeOptions"
      item-title="label"
      item-value="value"
      clearable
      :label="t('loyalty_rewards.drawer.type_label')"
      :hint="t('loyalty_rewards.drawer.type_hint')"
      persistent-hint
      variant="outlined"
      rounded="lg"
      density="comfortable"
      class="mb-4"
    />

    <v-text-field
      v-if="form.type === 'discount_amount' || form.type === 'discount_percent'"
      v-model.number="form.discount_value"
      type="number"
      min="0"
      :label="
        form.type === 'discount_percent'
          ? t('loyalty_rewards.drawer.discount_percent_label')
          : t('loyalty_rewards.drawer.discount_amount_label')
      "
      variant="outlined"
      rounded="lg"
      density="comfortable"
      hide-details
      class="mb-4"
    />

    <v-autocomplete
      v-if="form.type === 'free_item'"
      v-model="form.free_product_id"
      :items="productStore.products"
      item-title="name"
      item-value="id"
      :label="t('loyalty_rewards.drawer.free_product_label') + ' *'"
      variant="outlined"
      rounded="lg"
      density="comfortable"
      hide-details
      class="mb-4"
    />

    <div class="d-flex ga-3 mb-4">
      <AppDatePicker
        v-model="form.valid_from"
        :label="t('loyalty_rewards.drawer.valid_from_label')"
        display-format="dd-MM-yyyy"
      />
      <AppDatePicker
        v-model="form.valid_until"
        :label="t('loyalty_rewards.drawer.valid_until_label')"
        display-format="dd-MM-yyyy"
      />
    </div>

    <div class="d-flex align-center justify-space-between pa-3 rounded-lg bg-surface-light mb-2">
      <div>
        <div class="text-body-2 font-weight-medium">{{ t('loyalty_rewards.drawer.is_active_label') }}</div>
        <div class="text-caption text-medium-emphasis">{{ t('loyalty_rewards.drawer.is_active_hint') }}</div>
      </div>
      <v-switch v-model="form.is_active" color="primary" hide-details inset />
    </div>

    <template #footer>
      <v-btn variant="tonal" rounded="lg" class="flex-grow-1 text-none" :disabled="loading" @click="close">
        {{ t('btn.cancel') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        class="flex-grow-1 text-none"
        :loading="loading"
        @click="submit"
      >
        {{ t('loyalty_rewards.drawer.save') }}
      </v-btn>
    </template>
  </AppDrawer>
</template>

<script setup>
  import { computed, ref, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useSettingsForm } from '@/composables/useSettingsForm.js'
  import { useConfirmDiscard } from '@/composables/useConfirmDiscard.js'
  import { useBranchStore } from '@/stores/branchStore'
  import { useProductStore } from '@/stores/productStore'
  import AppDrawer from '@/components/common/AppDrawer.vue'
  import { AppDatePicker } from '@nong-official-dev/core'

  const { t } = useI18n()
  const { isTouch } = useTouchDevice()
  const { confirmDiscard } = useConfirmDiscard()
  const branchStore = useBranchStore()
  const productStore = useProductStore()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    reward: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  onMounted(() => {
    if (!branchStore.branches.length) branchStore.fetchBranches({ perPage: -1 })
    if (!productStore.products.length) productStore.fetchProducts({ perPage: -1 })
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const isEdit = computed(() => !!props.reward?.id)
  const formError = ref(null)

  const typeOptions = computed(() => [
    { value: 'discount_amount', label: t('loyalty_rewards.drawer.type.discount_amount') },
    { value: 'discount_percent', label: t('loyalty_rewards.drawer.type.discount_percent') },
    { value: 'free_item', label: t('loyalty_rewards.drawer.type.free_item') }
  ])

  const defaultForm = () => ({
    name: '',
    name_km: '',
    description: '',
    description_km: '',
    points_cost: 10,
    branch_ids: [],
    type: null,
    discount_value: null,
    free_product_id: null,
    valid_from: null,
    valid_until: null,
    is_active: true
  })

  const { form, isDirty, load, commit } = useSettingsForm(defaultForm())

  watch(
    () => props.modelValue,
    open => {
      if (!open) return
      formError.value = null
      if (props.reward) {
        load({
          name: props.reward.name ?? '',
          name_km: props.reward.name_km ?? '',
          description: props.reward.description ?? '',
          description_km: props.reward.description_km ?? '',
          points_cost: props.reward.points_cost ?? 10,
          branch_ids: props.reward.branch_ids ?? [],
          type: props.reward.type ?? null,
          discount_value: props.reward.discount_value ?? null,
          free_product_id: props.reward.free_product_id ?? null,
          valid_from: props.reward.valid_from ?? null,
          valid_until: props.reward.valid_until ?? null,
          is_active: props.reward.is_active !== false
        })
      } else {
        load(defaultForm())
      }
    },
    { immediate: true }
  )

  async function close() {
    if (props.loading) return
    if (!(await confirmDiscard(isDirty.value))) return
    emit('update:modelValue', false)
  }

  async function submit() {
    if (!form.name?.trim()) {
      formError.value = t('loyalty_rewards.drawer.error_name_required')
      return
    }
    if (!form.points_cost || form.points_cost < 1) {
      formError.value = t('loyalty_rewards.drawer.error_points_required')
      return
    }
    if ((form.type === 'discount_amount' || form.type === 'discount_percent') && !form.discount_value) {
      formError.value = t('loyalty_rewards.drawer.error_discount_value_required')
      return
    }
    if (form.type === 'free_item' && !form.free_product_id) {
      formError.value = t('loyalty_rewards.drawer.error_free_product_required')
      return
    }
    formError.value = null

    const payload = {
      ...(isEdit.value ? { id: props.reward.id } : {}),
      name: form.name.trim(),
      name_km: form.name_km?.trim() || null,
      description: form.description?.trim() || null,
      description_km: form.description_km?.trim() || null,
      points_cost: form.points_cost,
      branch_ids: form.branch_ids,
      type: form.type || null,
      discount_value: form.type ? form.discount_value : null,
      free_product_id: form.type === 'free_item' ? form.free_product_id : null,
      valid_from: form.valid_from || null,
      valid_until: form.valid_until || null,
      is_active: form.is_active
    }

    await new Promise((resolve, reject) => emit('saved', payload, { resolve, reject }))
      .then(() => {
        commit()
        emit('update:modelValue', false)
      })
      .catch(error => {
        const serverErrors = error?.response?.data?.errors
        formError.value = serverErrors ? Object.values(serverErrors).flat().join(' ') : null
      })
  }
</script>
