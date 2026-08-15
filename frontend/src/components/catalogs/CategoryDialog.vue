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
          <v-row dense>
            <!-- Name -->
            <v-col cols="6">
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

            <!-- Parent Category -->
            <v-col cols="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.dialog.parent_category') }}
                <span class="text-caption text-grey ml-1">({{ $t('form.optional') }})</span>
              </label>
              <v-select
                v-model="form.parent_id"
                :items="parentOptions"
                item-title="name"
                item-value="id"
                :placeholder="$t('categories.dialog.parent_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                clearable
                :error-messages="serverErrors.parent_id"
              >
                <template #prepend-inner>
                  <v-icon
                    icon="mdi-file-tree-outline"
                    size="18"
                    class="text-grey"
                  />
                </template>
              </v-select>
              <p class="text-caption text-grey mt-1 ml-1">
                {{ $t('categories.dialog.leave_empty_top_level') }}
              </p>
            </v-col>
          </v-row>

          <!-- Assign to Tenants -->
          <v-row dense>
            <v-col cols="12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.dialog.assign_to_tenants') }}
                <span class="text-caption text-grey ml-1">({{ $t('form.optional') }})</span>
              </label>
              <v-select
                v-model="form.tenant_ids"
                :items="tenants"
                item-title="name"
                item-value="id"
                :placeholder="$t('categories.dialog.select_tenants_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                multiple
                chips
                closable-chips
                clearable
                :loading="loadingTenants"
                :error-messages="serverErrors.tenant_ids"
              >
                <template #prepend-inner>
                  <v-icon
                    icon="mdi-store-outline"
                    size="18"
                    class="text-grey"
                  />
                </template>
                <template #chip="{ item, props: chipProps }">
                  <v-chip
                    v-bind="chipProps"
                    size="small"
                    rounded="md"
                    color="primary"
                    variant="tonal"
                  >
                    {{ item.title }}
                  </v-chip>
                </template>
              </v-select>
              <p class="text-caption text-grey mt-1 ml-1">
                {{ $t('categories.dialog.leave_empty_assigning') }}
              </p>
            </v-col>
          </v-row>

          <!-- Description -->
          <v-row dense>
            <v-col cols="12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.form.description') }}
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

          <!-- Icon & Color -->
          <v-row dense>
            <v-col cols="12" md="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.form.icon') }}
                <span class="text-caption text-grey ml-1">({{ $t('categories.dialog.mdi_icon_name') }})</span>
              </label>
              <v-text-field
                v-model="form.icon"
                :placeholder="$t('categories.dialog.icon_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.icon"
                :error-messages="serverErrors.icon"
                maxlength="50"
              >
                <template #prepend-inner>
                  <v-icon
                    :icon="iconPreview"
                    size="18"
                    :color="form.color || 'grey'"
                  />
                </template>
              </v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.form.color') }}
                <span class="text-caption text-grey ml-1">({{ $t('categories.dialog.hex') }})</span>
              </label>
              <v-text-field
                v-model="form.color"
                placeholder="#FF5733"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.color"
                :error-messages="serverErrors.color"
                maxlength="7"
              >
                <template #prepend-inner>
                  <div
                    class="color-swatch"
                    :style="{ background: colorPreview }"
                  />
                </template>
              </v-text-field>
            </v-col>
          </v-row>

          <!-- Image URL & Sort Order -->
          <v-row dense>
            <v-col cols="12" md="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.dialog.image_url') }}
                <span class="text-caption text-grey ml-1">({{ $t('form.optional') }})</span>
              </label>
              <v-text-field
                v-model="form.image_url"
                placeholder="https://example.com/image.png"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.image_url"
                :error-messages="serverErrors.image_url"
              >
                <template #prepend-inner>
                  <v-icon
                    icon="mdi-image-outline"
                    size="18"
                    class="text-grey"
                  />
                </template>
                <template v-if="imagePreviewValid" #append-inner>
                  <v-avatar size="28" rounded="sm">
                    <v-img :src="form.image_url" cover />
                  </v-avatar>
                </template>
              </v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ $t('categories.form.sort_order') }}
              </label>
              <v-text-field
                v-model.number="form.sort_order"
                placeholder="0"
                type="number"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.sort_order"
                :error-messages="serverErrors.sort_order"
                min="-32768"
                max="32767"
              />
              <p class="text-caption text-grey mt-1 ml-1">
                {{ $t('categories.dialog.sort_order_hint') }}
              </p>
            </v-col>
          </v-row>

          <!-- Active Status -->
          <v-row dense>
            <v-col cols="6">
              <div
                class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5"
              >
                <div>
                  <p
                    class="text-body-2 font-weight-medium text-grey-darken-2 mb-0"
                  >
                    {{ $t('categories.dialog.active_status') }}
                  </p>
                  <p class="text-caption text-grey mb-0">
                    {{ $t('categories.dialog.active_status_hint') }}
                  </p>
                </div>
                <v-switch
                  v-model="form.is_active"
                  color="success"
                  hide-details
                  inset
                />
              </div>
            </v-col>

            <!-- ── Lid Exchange ───────────────────────────────────────────────── -->
            <v-col cols="6">
              <div
                class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5"
              >
                <div>
                  <p
                    class="text-body-2 font-weight-medium text-grey-darken-2 mb-0"
                  >
                    <v-icon
                      icon="mdi-circle-outline"
                      size="15"
                      class="mr-1"
                      color="warning"
                    />
                    {{ $t('categories.dialog.lid_exchange') }}
                  </p>
                  <p class="text-caption text-grey mb-0">
                    {{ $t('categories.dialog.lid_exchange_hint') }}
                  </p>
                </div>
                <v-switch
                  v-model="form.is_lid_exchange"
                  color="warning"
                  hide-details
                  inset
                />
              </div>
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()
  const { isSuperAdmin } = usePermission()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    categories: { type: Array, default: () => [] } // ✅ for parent dropdown
  })

  const emit = defineEmits(['update:modelValue', 'saved'])
  const categoryStore = useCategoryStore()
  const tenantStore = useTenantStore()

  // ── State ──────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const loading = ref(false)
  const loadingTenants = ref(false)
  const serverErrors = reactive({})
  const tenants = ref([])

  // ── Model ──────────────────────────────────────────────────────────────────
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const isEdit = computed(() => !!props.category?.id)

  const parentOptions = computed(() =>
    props.categories.filter(c => c.id !== props.category?.id)
  )

  // ── Form ───────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    name: '',
    description: '',
    parent_id: null,
    tenant_ids: [], // ✅ new
    image_url: '',
    icon: '',
    color: '',
    sort_order: 0,
    is_active: true,
    is_lid_exchange: false
  })

  const form = reactive(defaultForm())

  // ── Previews ───────────────────────────────────────────────────────────────
  const iconPreview = computed(() =>
    form.icon?.startsWith('mdi-') ? form.icon : 'mdi-shape-outline'
  )

  const HEX_REGEX = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/

  const colorPreview = computed(() =>
    HEX_REGEX.test(form.color) ? form.color : '#e0e0e0'
  )

  const imagePreviewValid = computed(() => {
    try {
      return !!form.image_url && !!new URL(form.image_url)
    } catch {
      return false
    }
  })

  // ── Rules ──────────────────────────────────────────────────────────────────
  const rules = {
    name: [
      v => !!v?.trim() || t('categories.validation.name_required'),
      v => v.trim().length >= 2 || t('categories.validation.min_2_chars'),
      v => v.trim().length <= 100 || t('categories.validation.max_100_chars')
    ],
    icon: [
      v => !v || v.length <= 50 || t('categories.validation.max_50_chars'),
      v =>
        !v ||
        /^mdi-[a-z0-9-]+$/.test(v) ||
        t('categories.validation.invalid_icon')
    ],
    color: [
      v => !v || HEX_REGEX.test(v) || t('categories.validation.invalid_color')
    ],
    image_url: [
      v => {
        if (!v) return true
        try {
          new URL(v)
          return true
        } catch {
          return t('categories.validation.invalid_url')
        }
      }
    ],
    sort_order: [
      v => v === '' || v === null || !isNaN(Number(v)) || t('products.rule.isNumber'),
      v =>
        v === '' ||
        v === null ||
        Number.isInteger(Number(v)) ||
        t('categories.validation.whole_number'),
      v => v === '' || v === null || Number(v) >= -32768 || t('categories.validation.min_sort_order'),
      v => v === '' || v === null || Number(v) <= 32767 || t('categories.validation.max_sort_order')
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
          parent_id: val.parent_id ?? null,
          tenant_ids: val.tenants?.map(t => t.id) ?? [], // ✅ pre-fill assigned tenants
          image_url: val.image_url ?? '',
          icon: val.icon ?? '',
          color: val.color ?? '',
          sort_order: val.sort_order ?? 0,
          is_active: val.is_active ?? true,
          is_lid_exchange: val.is_lid_exchange ?? false,
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
        parent_id: form.parent_id || null,
        tenant_ids: form.tenant_ids, // ✅ send to backend
        image_url: form.image_url || null,
        icon: form.icon || null,
        color: form.color || null,
        sort_order: form.sort_order,
        is_active: form.is_active,
        is_lid_exchange:form.is_lid_exchange
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

  // ── Fetch Tenants ──────────────────────────────────────────────────────────
  // /v1/tenants is superadmin-only — skip for tenant-logged-in users, who
  // would otherwise get Forbidden and never see the rest of the dialog load.
  const fetchTenants = async () => {
    if (!isSuperAdmin()) return
    loadingTenants.value = true
    try {
      await tenantStore.fetchTenants()
      tenants.value = tenantStore.tenants
    } finally {
      loadingTenants.value = false
    }
  }

  onMounted(fetchTenants)
</script>

<style scoped>
  .color-swatch {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
  }
</style>
