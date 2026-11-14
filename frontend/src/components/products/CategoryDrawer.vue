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

    <!-- Super-admin-only levers — shared system category instead of one
         tenant's private one. See CategoryService::create()/update(): a
         non-super-admin caller has these silently forced regardless of
         what's submitted, so there's no point showing them outside
         adminMode. -->
    <template v-if="adminMode">
      <v-divider class="my-4" />

      <div class="d-flex align-center justify-space-between pa-3 rounded-lg bg-surface-light mb-3">
        <div>
          <div class="text-body-2 font-weight-medium">{{ t('categories.drawer.is_system_label') }}</div>
          <div class="text-caption text-medium-emphasis">{{ t('categories.drawer.is_system_hint') }}</div>
        </div>
        <v-switch v-model="form.is_system" color="primary" hide-details inset />
      </div>

      <v-select
        v-if="form.is_system"
        v-model="form.business_type_ids"
        :items="businessTypeStore.businessTypes"
        item-title="name"
        item-value="id"
        :label="t('categories.drawer.business_types_label')"
        :placeholder="t('categories.drawer.business_types_placeholder')"
        variant="outlined"
        rounded="lg"
        density="comfortable"
        multiple
        chips
        closable-chips
        hide-details
        class="mb-2"
      />
    </template>

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
  import { ref, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useSettingsForm } from '@/composables/useSettingsForm.js'
  import { useConfirmDiscard } from '@/composables/useConfirmDiscard.js'
  import { useBusinessTypeStore } from '@/stores/businessTypeStore'
  import AppDrawer from '@/components/common/AppDrawer.vue'

  const { t } = useI18n()
  const { isTouch } = useTouchDevice()
  const { confirmDiscard } = useConfirmDiscard()
  const businessTypeStore = useBusinessTypeStore()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    // Only true from the super-admin "system categories" page — see
    // CategoriesTab.vue. Surfaces is_system/business_type_ids, the two
    // fields only a super admin is ever allowed to set.
    adminMode: { type: Boolean, default: false }
  })

  onMounted(() => {
    if (props.adminMode && !businessTypeStore.businessTypes.length) {
      businessTypeStore.fetchBusinessTypes()
    }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const isEdit = computed(() => !!props.category?.id)
  const formError = ref(null)

  const defaultForm = () => ({
    name: '',
    name_km: '',
    show_on_pos: true,
    show_on_emenu: true,
    is_system: false,
    business_type_ids: []
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
          show_on_emenu: props.category.show_on_emenu !== false,
          is_system: props.category.is_system ?? false,
          business_type_ids: (props.category.business_types ?? []).map(b => b.id)
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
      show_on_emenu: form.show_on_emenu,
      ...(props.adminMode
        ? { is_system: form.is_system, business_type_ids: form.is_system ? form.business_type_ids : [] }
        : {})
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
