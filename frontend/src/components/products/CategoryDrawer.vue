<template>
  <AppDrawer
    :model-value="modelValue"
    width="420"
    :loading="loading"
    :persistent="loading"
    :confirm-close="() => confirmDiscard(isDirty)"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #title>
      {{ isEdit ? t('categories.drawer.edit_title') : t('categories.drawer.add_title') }}
    </template>
    <template #subtitle>{{ t('categories.drawer.subtitle') }}</template>

    <v-alert
      v-if="formError"
      type="error"
      variant="tonal"
      density="compact"
      rounded="lg"
      class="mb-4"
      :text="formError"
    />

    <!-- Name EN/KM -->
    <v-row dense class="mb-2 py-2">
      <v-col cols="12" sm="6">
        <v-text-field
          v-model="form.name"
          :label="t('categories.drawer.name_label') + ' *'"
          variant="outlined"
          rounded="lg"
          density="comfortable"
          hide-details
          maxlength="100"
          :autofocus="!isTouch"
        />
      </v-col>
      <v-col cols="12" sm="6">
        <v-text-field
          v-model="form.name_km"
          :label="t('categories.drawer.name_km_label')"
          variant="outlined"
          rounded="lg"
          density="comfortable"
          hide-details
          maxlength="100"
        />
      </v-col>
    </v-row>

    <v-divider class="my-4" />

    <div class="d-flex align-center justify-space-between pa-3 rounded-lg bg-surface-light mb-3">
      <div>
        <div class="text-body-2 font-weight-medium">{{ t('categories.drawer.show_on_pos') }}</div>
        <div class="text-caption text-medium-emphasis">{{ t('categories.drawer.show_on_pos_hint') }}</div>
      </div>
      <v-switch v-model="form.show_on_pos" color="primary" hide-details inset />
    </div>

    <div class="d-flex align-center justify-space-between pa-3 rounded-lg bg-surface-light mb-4">
      <div>
        <div class="text-body-2 font-weight-medium">{{ t('categories.drawer.show_on_emenu') }}</div>
        <div class="text-caption text-medium-emphasis">{{ t('categories.drawer.show_on_emenu_hint') }}</div>
      </div>
      <v-switch v-model="form.show_on_emenu" color="primary" hide-details inset />
    </div>

    <template #footer>
      <v-btn
        variant="tonal"
        rounded="lg"
        class="flex-grow-1 text-none"
        :disabled="loading"
        @click="close"
      >
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
        {{ t('categories.drawer.save_category') }}
      </v-btn>
    </template>
  </AppDrawer>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useSettingsForm } from '@/composables/useSettingsForm.js'
  import { useConfirmDiscard } from '@/composables/useConfirmDiscard.js'
  import AppDrawer from '@/components/common/AppDrawer.vue'

  const { t } = useI18n()
  const { isTouch } = useTouchDevice()
  const { confirmDiscard } = useConfirmDiscard()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const isEdit = computed(() => !!props.category?.id)
  const formError = ref(null)

  const defaultForm = () => ({
    name: '',
    name_km: '',
    show_on_pos: true,
    show_on_emenu: true
  })

  const { form, isDirty, load, commit } = useSettingsForm(defaultForm())

  watch(
    () => props.modelValue,
    open => {
      if (!open) return
      formError.value = null
      if (props.category) {
        load({
          name: props.category.name ?? '',
          name_km: props.category.name_km ?? '',
          show_on_pos: props.category.show_on_pos !== false,
          show_on_emenu: props.category.show_on_emenu !== false
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
      formError.value = t('categories.drawer.error_name_required')
      return
    }
    formError.value = null

    const payload = {
      ...(isEdit.value ? { id: props.category.id } : {}),
      name: form.name.trim(),
      name_km: form.name_km?.trim() || null,
      show_on_pos: form.show_on_pos,
      show_on_emenu: form.show_on_emenu
    }

    await new Promise((resolve, reject) => emit('saved', payload, { resolve, reject }))
      .then(() => {
        commit()
        emit('update:modelValue', false)
      })
      .catch(error => {
        const serverErrors = error?.response?.data?.errors
        formError.value = serverErrors
          ? Object.values(serverErrors).flat().join(' ')
          : null
      })
  }
</script>
