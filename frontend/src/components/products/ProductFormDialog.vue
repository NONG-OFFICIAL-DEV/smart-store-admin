<template>
  <v-dialog
    :model-value="modelValue"
    max-width="760"
    persistent
    scrollable
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" elevation="0" border>

      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <v-card-title class="pa-5 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar :color="isEdit ? 'primary' : 'success'" size="42" rounded="lg" variant="tonal">
              <v-icon :icon="isEdit ? 'mdi-package-variant' : 'mdi-package-variant-plus'" size="20" />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">{{ isEdit ? 'Edit Product' : 'New Product' }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ isEdit ? 'Update product information' : 'Add a new product to your menu' }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <!-- ── Tabs ────────────────────────────────────────────────────────── -->
      <v-tabs v-model="activeTab" color="primary" class="px-5 border-b">
        <v-tab value="basic"   prepend-icon="mdi-information-outline">Basic</v-tab>
        <v-tab value="pricing" prepend-icon="mdi-cash-multiple">Pricing</v-tab>
        <v-tab value="details" prepend-icon="mdi-tune-variant">Details</v-tab>
      </v-tabs>

      <v-divider />

      <!-- ── Body ────────────────────────────────────────────────────────── -->
      <v-card-text class="pa-0" style="max-height: 68vh;">
        <v-form ref="formRef">
          <v-tabs-window v-model="activeTab">

            <!-- ════════════════════════════ TAB 1 — Basic ════════════════ -->
            <v-tabs-window-item value="basic">
              <div class="form-section">

                <!-- ── Image + Name/Category side by side ──────────────── -->
                <div class="form-section-label">
                  <v-icon icon="mdi-package-variant-outline" size="13" class="mr-1" />
                  Product Info
                </div>

                <div class="d-flex gap-4 mb-5">

                  <!-- Left: image upload -->
                  <div class="image-col">
                    <div
                      class="image-upload-area"
                      :class="{ dragging: isDragging }"
                      @click="triggerFileInput"
                      @dragover.prevent="isDragging = true"
                      @dragleave="isDragging = false"
                      @drop.prevent="handleDrop"
                    >
                      <!-- Preview -->
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

                      <!-- Empty state -->
                      <template v-else>
                        <v-icon icon="mdi-image-plus-outline" size="28" color="grey-lighten-1" class="mb-1" />
                        <div class="text-caption text-grey font-weight-medium">Upload</div>
                        <div class="text-caption text-grey-lighten-1" style="font-size:10px">JPG PNG WEBP</div>
                      </template>

                      <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="d-none"
                        @change="handleFileChange"
                      />
                    </div>

                    <!-- URL paste -->
                    <v-text-field
                      v-model="form.image_url"
                      placeholder="Or paste URL"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details
                      class="mt-2"
                      style="font-size: 11px;"
                      @update:model-value="onUrlChange"
                    />
                  </div>

                  <!-- Right: name, type, category, tenant -->
                  <div class="flex-grow-1 d-flex flex-column gap-3">
                    <v-text-field
                      v-model="form.name"
                      label="Product Name *"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.required, r.maxLen(200)]"
                      prepend-inner-icon="mdi-package-variant"
                      maxlength="200"
                    />

                    <v-row dense>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.product_type"
                          :items="productTypeOptions"
                          item-title="title"
                          item-value="value"
                          label="Type *"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.required]"
                        >
                          <template #item="{ props: p, item }">
                            <v-list-item v-bind="p">
                              <template #prepend>
                                <v-avatar :color="typeColor(item.value)" size="22" rounded="sm" class="mr-2">
                                  <v-icon :icon="typeIcon(item.value)" size="11" />
                                </v-avatar>
                              </template>
                            </v-list-item>
                          </template>
                          <template #selection="{ item }">
                            <div class="d-flex align-center gap-1">
                              <v-avatar :color="typeColor(item.value)" size="16" rounded="sm">
                                <v-icon :icon="typeIcon(item.value)" size="10" />
                              </v-avatar>
                              <span class="text-body-2">{{ item.title }}</span>
                            </div>
                          </template>
                        </v-select>
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.category_id"
                          :items="categories"
                          item-title="name"
                          item-value="id"
                          label="Category *"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.required]"
                          prepend-inner-icon="mdi-tag-outline"
                        />
                      </v-col>
                    </v-row>

                    <!-- Tenant (super admin only) -->
                    <v-select
                      v-if="tenants.length"
                      v-model="form.tenant_id"
                      :items="tenants"
                      item-title="name"
                      item-value="id"
                      label="Tenant *"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.required]"
                      prepend-inner-icon="mdi-domain"
                    />

                    <!-- Toggles inline -->
                    <div class="d-flex gap-3">
                      <v-card rounded="lg" border elevation="0" class="px-3 py-2 d-flex align-center gap-2 flex-grow-1">
                        <v-switch v-model="form.is_available" color="success" density="compact" inset hide-details />
                        <div>
                          <div class="text-caption font-weight-medium">Available</div>
                          <div class="text-caption text-grey" style="font-size:10px">Visible in menu</div>
                        </div>
                      </v-card>
                      <v-card rounded="lg" border elevation="0" class="px-3 py-2 d-flex align-center gap-2 flex-grow-1">
                        <v-switch v-model="form.is_featured" color="amber" density="compact" inset hide-details />
                        <div>
                          <div class="text-caption font-weight-medium">Featured</div>
                          <div class="text-caption text-grey" style="font-size:10px">Highlighted</div>
                        </div>
                      </v-card>
                    </div>
                  </div>
                </div>

                <v-divider class="mb-4" />

                <!-- SKU, Barcode, Description -->
                <div class="form-section-label">
                  <v-icon icon="mdi-text-box-outline" size="13" class="mr-1" />
                  Additional Info
                </div>
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.sku"
                      label="SKU"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.maxLen(60)]"
                      prepend-inner-icon="mdi-identifier"
                      clearable
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.barcode"
                      label="Barcode"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.maxLen(60)]"
                      prepend-inner-icon="mdi-barcode"
                      clearable
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-textarea
                      v-model="form.description"
                      label="Description"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      rows="3"
                      hide-details
                      prepend-inner-icon="mdi-text"
                      clearable
                    />
                  </v-col>
                </v-row>
              </div>
            </v-tabs-window-item>

            <!-- ════════════════════════════ TAB 2 — Pricing ══════════════ -->
            <v-tabs-window-item value="pricing">
              <div class="form-section">
                <div class="form-section-label">
                  <v-icon icon="mdi-cash-multiple" size="13" class="mr-1" />
                  Pricing
                </div>
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model.number="form.base_price"
                      label="Base Price *"
                      type="number"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.required, r.nonNegative]"
                      prepend-inner-icon="mdi-currency-usd"
                      min="0"
                      step="0.01"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model.number="form.cost_price"
                      label="Cost Price"
                      type="number"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.nonNegative]"
                      prepend-inner-icon="mdi-currency-usd"
                      min="0"
                      step="0.01"
                      clearable
                    />
                  </v-col>
                  <v-col v-if="form.base_price && form.cost_price" cols="12">
                    <v-card rounded="lg" color="success" variant="tonal" class="pa-4">
                      <div class="d-flex align-center justify-space-between">
                        <div>
                          <div class="text-caption text-medium-emphasis">Gross Margin</div>
                          <div class="text-h5 font-weight-black">{{ marginPercent }}%</div>
                        </div>
                        <div class="text-right">
                          <div class="text-caption text-medium-emphasis">Profit / unit</div>
                          <div class="text-h6 font-weight-bold">${{ marginAmount }}</div>
                        </div>
                        <v-avatar color="success" size="44" rounded="lg">
                          <v-icon icon="mdi-trending-up" size="22" />
                        </v-avatar>
                      </div>
                    </v-card>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.tax_category"
                      label="Tax Category"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.maxLen(50)]"
                      prepend-inner-icon="mdi-percent"
                      placeholder="standard, reduced, exempt"
                      clearable
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model.number="form.sort_order"
                      label="Sort Order"
                      type="number"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details
                      prepend-inner-icon="mdi-sort"
                      min="0"
                    />
                  </v-col>
                </v-row>
              </div>
            </v-tabs-window-item>

            <!-- ════════════════════════════ TAB 3 — Details ══════════════ -->
            <v-tabs-window-item value="details">
              <div class="form-section">
                <div class="form-section-label">
                  <v-icon icon="mdi-tune-variant" size="13" class="mr-1" />
                  Product Details
                </div>
                <v-row dense>
                  <v-col cols="12" sm="6">
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
                  <v-col cols="12" sm="6">
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

                <v-alert type="info" variant="tonal" rounded="lg" density="compact" class="mt-4 mb-4 text-caption">
                  Variants & Modifier Groups can be managed from the product detail page after saving.
                </v-alert>

                <!-- Preview -->
                <div class="form-section-label mt-2">
                  <v-icon icon="mdi-eye-outline" size="13" class="mr-1" />
                  Preview
                </div>
                <v-card rounded="lg" elevation="0" color="grey-lighten-5" class="pa-4">
                  <div class="d-flex align-center gap-3">
                    <v-avatar size="52" rounded="lg" color="grey-lighten-3">
                      <img v-if="imagePreview" :src="imagePreview" style="width:100%;height:100%;object-fit:cover;" />
                      <v-icon v-else :icon="typeIcon(form.product_type)" size="24" color="grey" />
                    </v-avatar>
                    <div class="flex-grow-1">
                      <div class="text-body-2 font-weight-bold">{{ form.name || 'Product Name' }}</div>
                      <div class="text-caption text-grey">
                        {{ form.sku || 'No SKU' }} · {{ form.product_type || 'food' }}
                        <span v-if="form.calories"> · {{ form.calories }} kcal</span>
                        <span v-if="form.preparation_time"> · {{ form.preparation_time }}min</span>
                      </div>
                    </div>
                    <div class="text-body-1 font-weight-black text-primary">
                      ${{ Number(form.base_price || 0).toFixed(2) }}
                    </div>
                  </div>
                </v-card>
              </div>
            </v-tabs-window-item>

          </v-tabs-window>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ──────────────────────────────────────────────────────── -->
      <v-card-actions class="pa-4 gap-2">
        <span v-if="tabError" class="text-caption text-error">
          <v-icon icon="mdi-alert-circle-outline" size="14" class="mr-1" />
          {{ tabError }}
        </span>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-plus'"
          :loading="saving"
          @click="handleSubmit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Product' }}
        </v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object,  default: null  },
  categories: { type: Array,   default: () => [] },
  tenants:    { type: Array,   default: () => [] },
  saving:     { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const formRef      = ref(null)
const fileInputRef = ref(null)
const activeTab    = ref('basic')
const isDragging   = ref(false)
const tabError     = ref('')

// ── Image state ────────────────────────────────────────────────────────────────
const imagePreview = ref(null)
const imageFile    = ref(null)

const productTypeOptions = [
  { title: 'Food',     value: 'food'     },
  { title: 'Beverage', value: 'beverage' },
  { title: 'Retail',   value: 'retail'   },
  { title: 'Combo',    value: 'combo'    },
]

const defaultForm = () => ({
  id: null, tenant_id: null, category_id: null,
  sku: null, barcode: null, name: '',
  description: null, image_url: null,
  base_price: 0.0, cost_price: null,
  product_type: 'food', preparation_time: null,
  calories: null, is_available: true,
  is_featured: false, tax_category: null, sort_order: 0,
})

const form   = ref(defaultForm())
const isEdit = computed(() => !!props.editItem?.id)

const marginPercent = computed(() => {
  if (!form.value.base_price || !form.value.cost_price) return 0
  return (((form.value.base_price - form.value.cost_price) / form.value.base_price) * 100).toFixed(1)
})
const marginAmount = computed(() => {
  if (!form.value.base_price || !form.value.cost_price) return '0.00'
  return (form.value.base_price - form.value.cost_price).toFixed(2)
})

const typeColor = t => ({ food: 'orange', beverage: 'blue', retail: 'purple', combo: 'teal' })[t] ?? 'grey'
const typeIcon  = t => ({ food: 'mdi-food', beverage: 'mdi-cup', retail: 'mdi-shopping', combo: 'mdi-layers' })[t] ?? 'mdi-package'

// ── Image ──────────────────────────────────────────────────────────────────────
const triggerFileInput = () => fileInputRef.value?.click()

const handleFileChange = (e) => {
  const file = e.target.files?.[0]
  if (file) processFile(file)
}

const handleDrop = (e) => {
  isDragging.value = false
  const file = e.dataTransfer.files?.[0]
  if (file?.type.startsWith('image/')) processFile(file)
}

const processFile = (file) => {
  if (file.size > 2 * 1024 * 1024) {
    alert('Image must be under 2MB')
    return
  }
  imageFile.value = file
  form.value.image_url = null

  // ✅ Use native FileReader — works reliably in Vue
  const reader = new FileReader()
  reader.addEventListener('load', () => {
    imagePreview.value = reader.result  // string base64
  })
  reader.readAsDataURL(file)
}

const onUrlChange = (val) => {
  if (val) {
    imageFile.value    = null
    imagePreview.value = val
  } else {
    imagePreview.value = null
  }
}

const removeImage = () => {
  imageFile.value      = null
  imagePreview.value   = null
  form.value.image_url = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

// ── Watch ──────────────────────────────────────────────────────────────────────
watch(() => props.editItem, item => {
  activeTab.value = 'basic'
  tabError.value  = ''
  if (item) {
    form.value = {
      id: item.id ?? null, tenant_id: item.tenant_id ?? null,
      category_id: item.category_id ?? null, sku: item.sku ?? null,
      barcode: item.barcode ?? null, name: item.name ?? '',
      description: item.description ?? null, image_url: item.image_url ?? null,
      base_price: item.base_price ?? 0, cost_price: item.cost_price ?? null,
      product_type: item.product_type ?? 'food',
      preparation_time: item.preparation_time ?? null,
      calories: item.calories ?? null, is_available: item.is_available ?? true,
      is_featured: item.is_featured ?? false,
      tax_category: item.tax_category ?? null, sort_order: item.sort_order ?? 0,
    }
    imagePreview.value = item.image_url ?? null
    imageFile.value    = null
  } else {
    form.value = defaultForm()
    imagePreview.value = null
    imageFile.value    = null
  }
}, { immediate: true })

// ── Rules ──────────────────────────────────────────────────────────────────────
const r = {
  required:       v => (v !== null && v !== '' && v !== undefined) || 'Required',
  nonNegative:    v => (!v && v !== 0) || Number(v) >= 0 || 'Must be 0 or greater',
  nonNegativeInt: v => (!v && v !== 0) || (Number.isInteger(Number(v)) && Number(v) >= 0) || 'Must be positive integer',
  maxLen:         n => v => !v || v.length <= n || `Max ${n} characters`,
}

// ── Submit ─────────────────────────────────────────────────────────────────────
const handleSubmit = async () => {
  tabError.value = ''
  const { valid } = await formRef.value.validate()
  if (!valid) {
    tabError.value = 'Please fix errors before saving'
    activeTab.value = 'basic'
    return
  }

  let payload
  if (imageFile.value) {
    const fd = new FormData()
    fd.append('image', imageFile.value)
    Object.entries(form.value).forEach(([k, v]) => {
      if (v !== null && v !== undefined) fd.append(k, v)
    })
    payload = fd
  } else {
    payload = {
      ...form.value,
      sku:              form.value.sku              || null,
      barcode:          form.value.barcode          || null,
      description:      form.value.description      || null,
      image_url:        form.value.image_url        || null,
      base_price:       Number(form.value.base_price),
      cost_price:       form.value.cost_price       !== null ? Number(form.value.cost_price)       : null,
      preparation_time: form.value.preparation_time !== null ? Number(form.value.preparation_time) : null,
      calories:         form.value.calories         !== null ? Number(form.value.calories)         : null,
      sort_order:       Number(form.value.sort_order) ?? 0,
    }
  }
  emit('saved', payload)
}

// ── Close ──────────────────────────────────────────────────────────────────────
const close = () => {
  formRef.value?.reset()
  form.value         = defaultForm()
  imagePreview.value = null
  imageFile.value    = null
  activeTab.value    = 'basic'
  tabError.value     = ''
  emit('update:modelValue', false)
}
</script>

<style scoped>
.form-section { padding: 20px 24px; }
.form-section-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: rgb(var(--v-theme-primary));
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}
.border-b { border-bottom: 1px solid rgba(0,0,0,0.08); }
.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }

/* ── Image column ──────────────────────────────────────────────────── */
.image-col {
  width: 150px;
  min-width: 150px;
  flex-shrink: 0;
}

.image-upload-area {
  position: relative;
  border: 2px dashed rgba(0,0,0,0.14);
  border-radius: 12px;
  cursor: pointer;
  text-align: center;
  transition: all 0.2s ease;
  width: 150px;
  height: 150px;
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

/* ✅ Use native <img> for reliable preview rendering */
.image-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.image-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  opacity: 0;
  transition: opacity 0.2s;
}
.image-upload-area:hover .image-overlay { opacity: 1; }
</style>