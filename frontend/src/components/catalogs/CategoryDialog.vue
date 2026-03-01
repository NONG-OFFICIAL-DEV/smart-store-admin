<template>
  <v-dialog v-model="model" max-width="670" persistent scrollable>
    <v-card rounded="lg">
      <!-- Header -->
      <v-card-title
        class="d-flex align-center justify-space-between"
      >
        <div class="d-flex align-center gap-3">
          <div>
            {{ isEdit ? 'Edit Category' : 'Add Category' }}
          </div>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          :disabled="loading"
          @click="close"
        />
      </v-card-title>

      <v-divider />

      <v-card-text class="px-6 py-5">
        <v-form ref="formRef" @submit.prevent="submit">
          <v-row dense>
            <v-col col="6">
              <div>
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Name
                  <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.name"
                  placeholder="e.g. Beverages"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.name"
                  :error-messages="serverErrors.name"
                  maxlength="100"
                  counter
                />
              </div>
            </v-col>
            <v-col col="6">
              <div>
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Parent Category
                  <span class="text-caption text-grey ml-1">(optional)</span>
                </label>
                <v-select
                  v-model="form.parent_id"
                  :items="parentOptions"
                  item-title="name"
                  item-value="id"
                  placeholder="None (top-level category)"
                  variant="outlined"
                  density="comfortable"
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
                  <template #item="{ item, props: itemProps }">
                    <v-list-item v-bind="itemProps">
                      <template #prepend>
                        <v-icon
                          icon="mdi-shape-outline"
                          size="16"
                          class="mr-2 text-grey"
                        />
                      </template>
                    </v-list-item>
                  </template>
                </v-select>
                <p class="text-caption text-grey mt-1 ml-1">
                  Leave empty to make this a top-level category
                </p>
              </div>
            </v-col>
          </v-row>
          <v-row dense>
            <v-col>
              <div>
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Description
                </label>
                <v-textarea
                  v-model="form.description"
                  placeholder="Brief description of this category..."
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  rows="3"
                  no-resize
                  :error-messages="serverErrors.description"
                />
              </div>
            </v-col>
          </v-row>

          <v-row dense>
            <v-col cols="12" md="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                Icon
                <span class="text-caption text-grey ml-1">(MDI icon name)</span>
              </label>
              <v-text-field
                v-model="form.icon"
                placeholder="e.g. mdi-food"
                variant="outlined"
                density="comfortable"
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
                Color
                <span class="text-caption text-grey ml-1">(hex)</span>
              </label>
              <v-text-field
                v-model="form.color"
                placeholder="#FF5733"
                variant="outlined"
                density="comfortable"
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

          <!-- Image URL -->
          <v-row dense>
            <v-col>
              <div>
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Image URL
                  <span class="text-caption text-grey ml-1">(optional)</span>
                </label>
                <v-text-field
                  v-model="form.image_url"
                  placeholder="https://example.com/image.png"
                  variant="outlined"
                  density="comfortable"
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
              </div>
            </v-col>
            <v-col>
              <div>
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Sort Order
                </label>
                <v-text-field
                  v-model.number="form.sort_order"
                  placeholder="0"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.sort_order"
                  :error-messages="serverErrors.sort_order"
                  min="-32768"
                  max="32767"
                />
                <p class="text-caption text-grey mt-1 ml-1">
                  Lower numbers appear first
                </p>
              </div>
            </v-col>
          </v-row>
          <v-row dense>
            <v-col>
              <div
                class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5"
              >
                <div>
                  <p
                    class="text-body-2 font-weight-medium text-grey-darken-2 mb-0"
                  >
                    Active Status
                  </p>
                  <p class="text-caption text-grey mb-0">
                    Category will be visible when active
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
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4 gap-3">
        <v-btn
          variant="outlined"
          rounded="lg"
          class="flex-grow-1"
          :disabled="loading"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          class="flex-grow-1"
          :loading="loading"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Category' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useTenantStore } from '@/stores/tenantStore'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    categories: { type: Array, default: () => [] }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])
  const categoryStore = useCategoryStore()
  const tenantStore = useTenantStore()

  // ─── State ────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const loading = ref(false)
  const serverErrors = reactive({})
  const tenants = ref([])

  // ─── Model / Computed ─────────────────────────────────────────────────────────
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const isEdit = computed(() => !!props.category?.id)

  const parentOptions = computed(() =>
    props.categories.filter(c => c.id !== props.category?.id)
  )

  // ─── Form ─────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    name: '',
    description: '',
    parent_id: null,
    image_url: '',
    icon: '',
    color: '',
    sort_order: 0,
    is_active: true
  })

  const form = reactive(defaultForm())

  // ─── Previews ─────────────────────────────────────────────────────────────────
  const iconPreview = computed(() =>
    form.icon && form.icon.startsWith('mdi-') ? form.icon : 'mdi-shape-outline'
  )

  const HEX_REGEX = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/

  const colorPreview = computed(() =>
    HEX_REGEX.test(form.color) ? form.color : '#e0e0e0'
  )

  const imagePreviewValid = computed(() => {
    try {
      if (!form.image_url) return false
      new URL(form.image_url)
      return true
    } catch {
      return false
    }
  })

  // ─── Validation Rules ─────────────────────────────────────────────────────────
  const rules = {
    name: [
      v => !!v?.trim() || 'Name is required',
      v => v.trim().length >= 2 || 'Name must be at least 2 characters',
      v => v.trim().length <= 100 || 'Name must be 100 characters or less'
    ],
    icon: [
      v => !v || v.length <= 50 || 'Icon name must be 50 characters or less',
      v =>
        !v ||
        /^mdi-[a-z0-9-]+$/.test(v) ||
        'Must be a valid MDI icon (e.g. mdi-food)'
    ],
    color: [
      v => !v || HEX_REGEX.test(v) || 'Must be a valid hex color (e.g. #FF5733)'
    ],
    image_url: [
      v => {
        if (!v) return true
        try {
          new URL(v)
          return true
        } catch {
          return 'Must be a valid URL (e.g. https://example.com/image.png)'
        }
      }
    ],
    sort_order: [
      v => v === '' || v === null || !isNaN(Number(v)) || 'Must be a number',
      v =>
        v === '' ||
        v === null ||
        Number.isInteger(Number(v)) ||
        'Must be a whole number',
      v =>
        v === '' ||
        v === null ||
        Number(v) >= -32768 ||
        'Minimum value is -32768',
      v =>
        v === '' || v === null || Number(v) <= 32767 || 'Maximum value is 32767'
    ]
  }

  // ─── Helpers ── MUST be defined BEFORE the immediate watcher ─────────────────
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

  // ─── Watchers ── safe now because helpers are already initialized ─────────────
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
          image_url: val.image_url ?? '',
          icon: val.icon ?? '',
          color: val.color ?? '',
          sort_order: val.sort_order ?? 0,
          is_active: val.is_active ?? true
        })
      } else {
        resetForm()
      }
    },
    { immediate: true }
  )

  // ─── Actions ──────────────────────────────────────────────────────────────────
  const submit = async () => {
    if (!formRef.value) return

    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    clearServerErrors()

    try {
      const payload = {
        ...form,
        name: form.name.trim(),
        parent_id: form.parent_id || null,
        image_url: form.image_url || null,
        icon: form.icon || null,
        color: form.color || null,
        description: form.description || null
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

  const fetchTenants = async () => {
    await tenantStore.fetchTenants()
    tenants.value = tenantStore.tenants
  }

  // ─── Lifecycle ────────────────────────────────────────────────────────────────
  onMounted(fetchTenants) // 👈 was defined but never called
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
