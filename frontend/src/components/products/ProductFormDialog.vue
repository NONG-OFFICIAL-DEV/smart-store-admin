<template>
  <v-dialog
    :model-value="modelValue"
    max-width="680"
    persistent
    scrollable
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" elevation="0" border>
      <!-- ── Header ─────────────────────────────────────────────────────── -->
      <v-card-title class="pa-5 pb-3">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="40"
              rounded="lg"
              variant="tonal"
            >
              <v-icon
                :icon="
                  isEdit ? 'mdi-package-variant' : 'mdi-package-variant-plus'
                "
                size="20"
              />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ isEdit ? t('products.editTitle') : t('products.newTitle') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? t('products.editSubtitle')
                    : t('products.newSubtitle')
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <!-- ── Nature ─────────────────────────────────────────────────────── -->
      <div class="px-5 pb-3">
        <!-- SuperAdmin: toggle freely -->
        <v-btn-toggle
          v-if="isSuperAdmin()"
          v-model="manualNature"
          mandatory
          rounded="lg"
          color="primary"
          density="compact"
          variant="outlined"
          style="width: 100%"
        >
          <v-btn
            value="food"
            prepend-icon="mdi-silverware-fork-knife"
            size="small"
            style="flex: 1"
          >
            Food & Beverage
          </v-btn>
          <v-btn
            value="retail"
            prepend-icon="mdi-shopping-outline"
            size="small"
            style="flex: 1"
          >
            Retail / Mart
          </v-btn>
        </v-btn-toggle>

        <!-- Normal user: read-only chip locked to their bu_type -->
        <v-chip
          v-else
          :color="isFoodProduct ? 'orange' : 'indigo'"
          variant="tonal"
          rounded="lg"
          size="small"
          :prepend-icon="
            isFoodProduct ? 'mdi-silverware-fork-knife' : 'mdi-shopping-outline'
          "
        >
          {{ isFoodProduct ? 'Food & Beverage' : 'Retail / Mart' }}
        </v-chip>
      </div>

      <v-divider />

      <!-- ── Body ───────────────────────────────────────────────────────── -->
      <v-card-text class="pa-0" style="max-height: 70vh; overflow-y: auto">
        <v-form ref="formRef">
          <div class="pa-5">
            <!-- ── Image + Core Fields ─────────────────────────────────── -->
            <div class="d-flex gap-4 mb-4">
              <!-- Image Upload -->
              <div style="width: 130px; min-width: 130px; flex-shrink: 0">
                <div
                  class="image-upload-area"
                  :class="{ dragging: isDragging }"
                  @click="triggerFileInput"
                  @dragover.prevent="isDragging = true"
                  @dragleave="isDragging = false"
                  @drop.prevent="handleDrop"
                >
                  <template v-if="imagePreview">
                    <img :src="imagePreview" class="image-preview" />
                    <div class="image-overlay" @click.stop>
                      <v-btn
                        size="x-small"
                        variant="flat"
                        color="white"
                        rounded="lg"
                        icon="mdi-pencil-outline"
                        class="mr-1"
                        @click.stop="triggerFileInput"
                      />
                      <v-btn
                        size="x-small"
                        variant="flat"
                        color="error"
                        rounded="lg"
                        icon="mdi-delete-outline"
                        @click.stop="removeImage"
                      />
                    </div>
                  </template>
                  <template v-else>
                    <v-icon
                      icon="mdi-image-plus-outline"
                      size="26"
                      color="grey-lighten-1"
                      class="mb-1"
                    />
                    <div class="text-caption text-grey font-weight-medium">
                      Photo
                    </div>
                    <div
                      class="text-caption text-grey-lighten-1"
                      style="font-size: 10px"
                    >
                      JPG · PNG · WEBP
                    </div>
                  </template>
                  <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="d-none"
                    @change="handleFileChange"
                  />
                </div>
                <v-text-field
                  v-model="form.image_url"
                  placeholder="Or paste URL"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  hide-details
                  class="mt-2"
                  style="font-size: 11px"
                  @update:model-value="onUrlChange"
                />
              </div>

              <!-- Core Fields -->
              <div class="flex-grow-1 d-flex flex-column gap-3">
                <v-text-field
                  v-model="form.name"
                  :label="t('products.field.productName')"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  :rules="[r.required, r.maxLen(200)]"
                  prepend-inner-icon="mdi-package-variant"
                  maxlength="200"
                />

                <v-row dense>
                  <v-col cols="6">
                    <v-select
                      v-model="form.product_type"
                      :items="filteredProductTypeOptions"
                      item-title="title"
                      item-value="value"
                      :label="t('products.field.type')"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :rules="[r.required]"
                    >
                      <template #item="{ props: p, item }">
                        <v-list-item v-bind="p">
                          <template #prepend>
                            <v-avatar
                              :color="typeColor(item.value)"
                              size="20"
                              rounded="sm"
                              class="mr-2"
                            >
                              <v-icon :icon="typeIcon(item.value)" size="11" />
                            </v-avatar>
                          </template>
                        </v-list-item>
                      </template>
                      <template #selection="{ item }">
                        <div class="d-flex align-center gap-1">
                          <v-avatar
                            :color="typeColor(item.value)"
                            size="14"
                            rounded="sm"
                          >
                            <v-icon :icon="typeIcon(item.value)" size="9" />
                          </v-avatar>
                          <span class="text-body-2">{{ item.title }}</span>
                        </div>
                      </template>
                    </v-select>
                  </v-col>
                  <v-col cols="6">
                    <v-select
                      v-model="form.category_id"
                      :items="categories"
                      item-title="name"
                      item-value="id"
                      :label="t('products.field.category')"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :rules="[r.required]"
                      prepend-inner-icon="mdi-tag-outline"
                    />
                  </v-col>
                </v-row>

                <v-select
                  v-if="tenants.length && isSuperAdmin()"
                  v-model="form.tenant_id"
                  :items="tenants"
                  item-title="name"
                  item-value="id"
                  :label="t('products.field.tenant')"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  hide-details="auto"
                  :rules="[r.required]"
                  prepend-inner-icon="mdi-domain"
                />

                <div class="d-flex gap-2">
                  <v-card
                    rounded="lg"
                    border
                    elevation="0"
                    class="px-3 py-2 d-flex align-center gap-2 flex-grow-1"
                  >
                    <v-switch
                      v-model="form.is_available"
                      color="success"
                      density="compact"
                      inset
                      hide-details
                    />
                    <div>
                      <div class="text-caption font-weight-medium">
                        {{ t('products.field.available') }}
                      </div>
                      <div
                        class="text-caption text-grey"
                        style="font-size: 10px"
                      >
                        Visible in menu
                      </div>
                    </div>
                  </v-card>
                  <v-card
                    rounded="lg"
                    border
                    elevation="0"
                    class="px-3 py-2 d-flex align-center gap-2 flex-grow-1"
                  >
                    <v-switch
                      v-model="form.is_featured"
                      color="amber"
                      density="compact"
                      inset
                      hide-details
                    />
                    <div>
                      <div class="text-caption font-weight-medium">
                        Featured
                      </div>
                      <div
                        class="text-caption text-grey"
                        style="font-size: 10px"
                      >
                        Highlighted
                      </div>
                    </div>
                  </v-card>
                </div>
              </div>
            </div>

            <v-divider class="mb-3" />

            <!-- ── SKU / Barcode ───────────────────────────────────────── -->
            <v-row dense class="mb-2">
              <v-col cols="6">
                <v-text-field
                  v-model="form.sku"
                  label="SUK"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  hide-details="auto"
                  :rules="[r.maxLen(60)]"
                  prepend-inner-icon="mdi-identifier"
                  clearable
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="form.barcode"
                  :label="t('products.field.barcode')"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  hide-details="auto"
                  :rules="[r.maxLen(60)]"
                  prepend-inner-icon="mdi-barcode"
                  clearable
                />
              </v-col>
            </v-row>

            <!-- ── Description ────────────────────────────────────────── -->
            <v-textarea
              v-model="form.description"
              :label="t('products.field.description')"
              variant="outlined"
              density="compact"
              rounded="lg"
              rows="2"
              hide-details
              prepend-inner-icon="mdi-text"
              clearable
            />

            <!-- ── Food-only: Prep Time + Calories ────────────────────── -->
            <template v-if="isFoodProduct">
              <v-divider class="my-4" />
              <div class="section-label mb-3">
                <v-icon
                  icon="mdi-silverware-fork-knife"
                  size="12"
                  class="mr-1"
                />
                Kitchen Details
              </div>
              <v-row dense>
                <v-col cols="6">
                  <v-text-field
                    v-model.number="form.preparation_time"
                    label="Prep Time"
                    type="number"
                    variant="outlined"
                    density="compact"
                    rounded="lg"
                    hide-details="auto"
                    :rules="[r.nonNegativeInt]"
                    prepend-inner-icon="mdi-clock-outline"
                    suffix="min"
                    min="0"
                    clearable
                  />
                </v-col>
                <v-col cols="6">
                  <v-text-field
                    v-model.number="form.calories"
                    label="Calories"
                    type="number"
                    variant="outlined"
                    density="compact"
                    rounded="lg"
                    hide-details="auto"
                    :rules="[r.nonNegativeInt]"
                    prepend-inner-icon="mdi-fire"
                    suffix="kcal"
                    min="0"
                    clearable
                  />
                </v-col>
              </v-row>
            </template>

            <!-- ── After-save hint ────────────────────────────────────── -->
            <v-alert
              variant="tonal"
              rounded="lg"
              density="compact"
              class="mt-4 text-caption"
              :color="isFoodProduct ? 'primary' : 'indigo'"
            >
              <template #prepend>
                <v-icon
                  :icon="
                    isFoodProduct
                      ? 'mdi-tune-variant'
                      : 'mdi-layers-triple-outline'
                  "
                  size="15"
                />
              </template>
              <span v-if="isFoodProduct">
                {{ t('products.alert.food') }}
              </span>
              <span v-else>
                {{ t('products.alert.retail') }}
              </span>
            </v-alert>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ────────────────────────────────────────────────────── -->
      <v-card-actions class="pa-4 gap-2">
        <span v-if="formError" class="text-caption text-error">
          <v-icon icon="mdi-alert-circle-outline" size="14" class="mr-1" />
          {{ formError }}
        </span>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" @click="close">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-plus'"
          :loading="saving"
          @click="handleSubmit"
        >
          {{ isEdit ? t('btn.save_changes') : t('btn.create') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { usePermission } from '@/composables/usePermission'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'

  const authStore = useAuthStore()
  const { t } = useI18n()
  const { isSuperAdmin } = usePermission()
  const { notif } = useAppUtils()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    tenants: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const formRef = ref(null)
  const fileInputRef = ref(null)
  const isDragging = ref(false)
  const formError = ref('')
  const imagePreview = ref(null)
  const imageFile = ref(null)

  // ── Nature ────────────────────────────────────────────────────────────────────
  // SuperAdmin can switch manually via toggle → manualNature
  // Normal user is locked to their tenant's bu_type → authStore.isFood / isMart
  const manualNature = ref('food')

  const productNature = computed(() => {
    if (isSuperAdmin()) return manualNature.value // superAdmin: use toggle
    return authStore.isFood ? 'food' : 'retail' // normal user: locked
  })

  const isFoodProduct = computed(() => productNature.value === 'food')

  // ── Type options filtered by nature ──────────────────────────────────────────
  const allProductTypeOptions = [
    { title: 'Food', value: 'food', nature: 'food' },
    { title: 'Beverage', value: 'beverage', nature: 'food' },
    { title: 'Combo', value: 'combo', nature: 'food' },
    { title: 'Retail', value: 'retail', nature: 'retail' }
  ]
  const filteredProductTypeOptions = computed(() =>
    allProductTypeOptions.filter(o => o.nature === productNature.value)
  )

  // Reset product_type and food-only fields when nature changes
  watch(productNature, nature => {
    form.value.product_type = nature === 'food' ? 'food' : 'retail'
    form.value.preparation_time = null
    form.value.calories = null
  })

  // ── Form ──────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    id: null,
    tenant_id: null,
    category_id: null,
    sku: null,
    barcode: null,
    name: '',
    description: null,
    image_url: null,
    product_type: 'food',
    preparation_time: null,
    calories: null,
    is_available: true,
    is_featured: false,
    sort_order: 0
  })

  const form = ref(defaultForm())
  const isEdit = computed(() => !!props.editItem?.id)

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const typeColor = v =>
    ({ food: 'orange', beverage: 'blue', retail: 'purple', combo: 'teal' })[
      v
    ] ?? 'grey'
  const typeIcon = v =>
    ({
      food: 'mdi-food',
      beverage: 'mdi-cup',
      retail: 'mdi-shopping',
      combo: 'mdi-layers'
    })[v] ?? 'mdi-package'

  // ── Image ─────────────────────────────────────────────────────────────────────
  const triggerFileInput = () => fileInputRef.value?.click()
  const handleFileChange = e => {
    const f = e.target.files?.[0]
    if (f) processFile(f)
  }
  const handleDrop = e => {
    isDragging.value = false
    const f = e.dataTransfer.files?.[0]
    if (f?.type.startsWith('image/')) processFile(f)
  }
  const resizeImage = file =>
    new Promise(resolve => {
      const img = new Image()
      const reader = new FileReader()
      reader.onload = e => {
        img.src = e.target.result
      }
      img.onload = () => {
        const canvas = document.createElement('canvas')
        canvas.width = 1000
        canvas.height = img.height * (1000 / img.width)
        canvas
          .getContext('2d')
          .drawImage(img, 0, 0, canvas.width, canvas.height)
        canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.8)
      }
      reader.readAsDataURL(file)
    })
  const processFile = async file => {
    if (file.size > 5 * 1024 * 1024) {
      notif('Image must be under 5MB', { type: 'error', color: 'error' })
      return
    }
    const resized = await resizeImage(file)
    imageFile.value = resized
    form.value.image_url = null
    const reader = new FileReader()
    reader.onload = () => {
      imagePreview.value = reader.result
    }
    reader.readAsDataURL(resized)
  }
  const onUrlChange = val => {
    imageFile.value = val ? null : imageFile.value
    imagePreview.value = val || null
  }
  const removeImage = () => {
    imageFile.value = null
    imagePreview.value = null
    form.value.image_url = null
    if (fileInputRef.value) fileInputRef.value.value = ''
  }

  // ── Watch editItem ────────────────────────────────────────────────────────────
  watch(
    () => props.editItem,
    item => {
      formError.value = ''
      if (item) {
        // For superAdmin editing an existing product, sync the toggle to match
        if (isSuperAdmin()) {
          manualNature.value = ['food', 'beverage', 'combo'].includes(
            item.product_type
          )
            ? 'food'
            : 'retail'
        }
        form.value = {
          id: item.id ?? null,
          tenant_id: item.tenant_id ?? null,
          category_id: item.category_id ?? null,
          sku: item.sku ?? null,
          barcode: item.barcode ?? null,
          name: item.name ?? '',
          description: item.description ?? null,
          image_url: item.image_url ?? null,
          product_type: item.product_type ?? 'food',
          preparation_time: item.preparation_time ?? null,
          calories: item.calories ?? null,
          is_available: item.is_available ?? true,
          is_featured: item.is_featured ?? false,
          sort_order: item.sort_order ?? 0
        }
        imagePreview.value = item.image_url ?? null
        imageFile.value = null
      } else {
        // Reset: superAdmin defaults to food, normal user stays locked
        if (isSuperAdmin()) manualNature.value = 'food'
        form.value = defaultForm()
        imagePreview.value = null
        imageFile.value = null
      }
    },
    { immediate: true }
  )

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const r = {
    required: v => (v !== null && v !== '' && v !== undefined) || 'Required',
    nonNegativeInt: v =>
      (!v && v !== 0) ||
      (Number.isInteger(Number(v)) && Number(v) >= 0) ||
      'Must be a positive integer',
    maxLen: n => v => !v || v.length <= n || `Max ${n} characters`
  }

  // ── Submit ────────────────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    formError.value = ''
    const { valid } = await formRef.value.validate()
    if (!valid) {
      formError.value = 'Please fix errors before saving'
      return
    }

    const payload_data = {
      ...form.value,
      preparation_time: isFoodProduct.value
        ? form.value.preparation_time
        : null,
      calories: isFoodProduct.value ? form.value.calories : null
    }

    let payload
    if (imageFile.value) {
      const fd = new FormData()
      fd.append('image', imageFile.value)
      Object.entries(payload_data).forEach(([k, v]) => {
        if (v !== null && v !== undefined && k !== 'image_url') fd.append(k, v)
      })
      payload = fd
    } else {
      payload = payload_data
    }

    emit('saved', payload)
  }

  // ── Close ─────────────────────────────────────────────────────────────────────
  const close = () => {
    formRef.value?.reset()
    if (isSuperAdmin()) manualNature.value = 'food'
    form.value = defaultForm()
    imagePreview.value = null
    imageFile.value = null
    formError.value = ''
    emit('update:modelValue', false)
  }
</script>

<style scoped>
  .section-label {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgb(var(--v-theme-primary));
    display: flex;
    align-items: center;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .gap-4 {
    gap: 16px;
  }
  .image-upload-area {
    position: relative;
    border: 2px dashed rgba(0, 0, 0, 0.14);
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s ease;
    width: 130px;
    height: 130px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .image-upload-area:hover {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.03);
  }
  .image-upload-area.dragging {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
  }
  .image-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .image-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.2s;
  }
  .image-upload-area:hover .image-overlay {
    opacity: 1;
  }
</style>
