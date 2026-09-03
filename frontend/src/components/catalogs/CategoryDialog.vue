<template>
  <AppDialog
    v-model="model"
    :max-width="670"
    :title="isEdit ? $t('categories.dialog.titleEdit') : $t('btn.add_category')"
    :subtitle="
      isEdit
        ? $t('categories.dialog.edit_subtitle')
        : $t('categories.dialog.add_subtitle')
    "
    :icon="isEdit ? 'mdi-shape-outline' : 'mdi-shape-plus-outline'"
    :color="isEdit ? 'primary' : 'success'"
    :loading="loading"
    :submit-text="isEdit ? $t('btn.save_changes') : $t('categories.dialog.create_category')"
    @close="resetForm"
    @submit="submit"
  >
        <v-form ref="formRef" @submit.prevent="submit">
          <!-- Name -->
          <v-row dense>
            <v-col cols="12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.form.name') }}
                <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="form.name"
                :placeholder="$t('categories.dialog.name_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.name"
                :error-messages="serverErrors.name"
                maxlength="100"
                counter
              />
            </v-col>
          </v-row>

          <!-- Description -->
          <v-row dense>
            <v-col cols="12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.form.description') }}
                <span class="text-caption text-grey ml-1">({{ $t('form.optional') }})</span>
              </label>
              <v-textarea
                v-model="form.description"
                :placeholder="$t('categories.dialog.description_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                rows="3"
                no-resize
                :error-messages="serverErrors.description"
              />
            </v-col>
          </v-row>

          <!-- Business Types — admin mode only: this is what makes a system
               category visible to every tenant of a matching business type,
               instead of assigning tenants one by one. -->
          <v-row v-if="adminMode" dense>
            <v-col cols="12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.dialog.business_types') }}
              </label>
              <v-select
                v-model="form.business_type_ids"
                :items="businessTypeStore.businessTypes"
                item-title="name"
                item-value="id"
                :placeholder="$t('categories.dialog.business_types_placeholder')"
                variant="outlined"
                rounded="lg"
                multiple
                chips
                closable-chips
                hide-details="auto"
                :error-messages="serverErrors.business_type_ids"
              />
              <v-alert
                type="info"
                variant="tonal"
                density="compact"
                rounded="lg"
                class="mt-2 text-caption"
              >
                {{ $t('categories.dialog.system_hint') }}
              </v-alert>
            </v-col>
          </v-row>

          <!-- Active Status -->
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-body-2">{{ $t('categories.dialog.active_status') }}</span>
            <v-switch
              v-model="form.is_active"
              color="success"
              hide-details
              density="compact"
              inset
            />
          </div>

          <!-- Lid Exchange -->
          <div class="d-flex align-center justify-space-between">
            <span class="text-body-2">{{ $t('categories.dialog.lid_exchange') }}</span>
            <v-switch
              v-model="form.is_lid_exchange"
              color="warning"
              hide-details
              density="compact"
              inset
            />
          </div>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useBusinessTypeStore } from '@/stores/businessTypeStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    // Only true on the super-admin "system categories" page — everything
    // created/edited there is a system category shared by business type.
    adminMode: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])
  const categoryStore = useCategoryStore()
  const businessTypeStore = useBusinessTypeStore()

  onMounted(() => {
    if (props.adminMode && !businessTypeStore.businessTypes.length) {
      businessTypeStore.fetchBusinessTypes()
    }
  })

  // ── State ──────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const loading = ref(false)
  const serverErrors = reactive({})

  // ── Model ──────────────────────────────────────────────────────────────────
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const isEdit = computed(() => !!props.category?.id)

  // ── Form ───────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    name: '',
    description: '',
    is_active: true,
    is_lid_exchange: false,
    business_type_ids: []
  })

  const form = reactive(defaultForm())

  // ── Rules ──────────────────────────────────────────────────────────────────
  const rules = {
    name: [
      v => !!v?.trim() || t('categories.validation.name_required'),
      v => v.trim().length >= 2 || t('categories.validation.min_2_chars'),
      v => v.trim().length <= 100 || t('categories.validation.max_100_chars')
    ]
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  const resetForm = () => {
    Object.assign(form, defaultForm())
    formRef.value?.resetValidation()
  }

  const clearServerErrors = () => {
    Object.keys(serverErrors).forEach(key => delete serverErrors[key])
  }

  const close = () => {
    if (loading.value) return
    model.value = false
    resetForm()
  }

  // ── Watchers ───────────────────────────────────────────────────────────────
  watch(
    () => props.category,
    async val => {
      clearServerErrors()
      await nextTick()

      if (val) {
        Object.assign(form, {
          name: val.name ?? '',
          description: val.description ?? '',
          is_active: val.is_active ?? true,
          is_lid_exchange: val.is_lid_exchange ?? false,
          business_type_ids: (val.business_types ?? []).map(b => b.id)
        })
      } else {
        resetForm()
      }
    },
    { immediate: true }
  )

  // ── Submit ─────────────────────────────────────────────────────────────────
  const submit = async () => {
    if (!formRef.value) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    clearServerErrors()

    try {
      const payload = {
        name: form.name.trim(),
        description: form.description || null,
        is_active: form.is_active,
        is_lid_exchange: form.is_lid_exchange,
        is_system: props.adminMode,
        business_type_ids: props.adminMode ? form.business_type_ids : undefined
      }

      if (isEdit.value) {
        await categoryStore.updateCategory(props.category.id, payload)
      } else {
        await categoryStore.createCategory(payload)
      }

      emit('saved')
      close()
    } catch (err) {
      if (err?.response?.status === 422) {
        const errors = err.response.data?.errors ?? {}
        Object.keys(errors).forEach(key => {
          serverErrors[key] = Array.isArray(errors[key])
            ? errors[key][0]
            : errors[key]
        })
      }
    } finally {
      loading.value = false
    }
  }
</script>
