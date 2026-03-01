<template>
  <v-dialog
    :model-value="modelValue"
    max-width="720"
    persistent
    scrollable
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" elevation="0" border>
      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <v-card-title>
        <div class="d-flex align-center justify-space-between">
          <div>
            {{ isEdit ? 'Edit Product' : 'New Product' }}
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <!-- ── Tabs ────────────────────────────────────────────────────────── -->
      <v-tabs v-model="activeTab" color="primary" class="px-6 pt-2">
        <v-tab value="basic" prepend-icon="mdi-information-outline">
          Basic Info
        </v-tab>
        <v-tab value="pricing" prepend-icon="mdi-cash">Pricing</v-tab>
        <v-tab value="details" prepend-icon="mdi-tune">Details</v-tab>
      </v-tabs>

      <v-divider />

      <!-- ── Form ────────────────────────────────────────────────────────── -->
      <v-card-text class="pa-6" style="max-height: 520px; overflow-y: auto">
        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <v-tabs-window v-model="activeTab" class="py-4">
            <!-- ── Tab: Basic Info ──────────────────────────────────────── -->
            <v-tabs-window-item value="basic">
              <v-row>
                <v-col>
                  <v-select
                    v-model="form.tenant_id"
                    :items="tenants"
                    item-title="name"
                    item-value="id"
                    label="Tenant *"
                    variant="outlined"
                  />
                </v-col>
              </v-row>
              <v-row dense>
                <!-- Image preview + URL -->
                <v-col cols="12">
                  <div class="d-flex align-center gap-4 mb-4">
                    <v-avatar size="80" rounded="xl" color="grey-lighten-2">
                      <v-img
                        v-if="form.image_url"
                        :src="form.image_url"
                        cover
                      />
                      <v-icon
                        v-else
                        icon="mdi-image-plus"
                        size="32"
                        color="grey"
                      />
                    </v-avatar>
                    <div class="flex-1">
                      <v-text-field
                        v-model="form.image_url"
                        label="Image URL"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-link"
                        hint="Paste a public image URL"
                        persistent-hint
                        clearable
                      />
                    </div>
                  </div>
                </v-col>

                <!-- Name -->
                <v-col cols="12">
                  <v-text-field
                    v-model="form.name"
                    label="Product Name"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-package-variant"
                    :rules="[rules.required, rules.maxLen(200)]"
                    counter="200"
                    hint="Full display name of the product"
                    persistent-hint
                  />
                </v-col>

                <!-- Category -->
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.category_id"
                    :items="categories"
                    item-title="name"
                    item-value="id"
                    label="Category"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-tag-outline"
                    :rules="[rules.required]"
                    hint="Product category"
                    persistent-hint
                  />
                </v-col>

                <!-- Product Type -->
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.product_type"
                    :items="productTypeOptions"
                    label="Product Type"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-shape-outline"
                    :rules="[rules.required]"
                    hint="food, beverage, retail, combo"
                    persistent-hint
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template #prepend>
                          <v-avatar
                            :color="typeColor(item.value)"
                            size="28"
                            rounded="md"
                            class="mr-2"
                          >
                            <v-icon :icon="typeIcon(item.value)" size="14" />
                          </v-avatar>
                        </template>
                      </v-list-item>
                    </template>
                    <template #selection="{ item }">
                      <div class="d-flex align-center gap-2">
                        <v-avatar
                          :color="typeColor(item.value)"
                          size="20"
                          rounded="sm"
                        >
                          <v-icon :icon="typeIcon(item.value)" size="12" />
                        </v-avatar>
                        {{ item.title }}
                      </div>
                    </template>
                  </v-select>
                </v-col>

                <!-- SKU -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.sku"
                    label="SKU"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-identifier"
                    :rules="[rules.maxLen(60)]"
                    hint="Unique stock keeping unit"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Barcode -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.barcode"
                    label="Barcode"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-barcode"
                    :rules="[rules.maxLen(60)]"
                    hint="EAN, UPC, or custom barcode"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Description -->
                <v-col cols="12">
                  <v-textarea
                    v-model="form.description"
                    label="Description"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-text"
                    rows="3"
                    auto-grow
                    hint="Short description shown to customers"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Toggles -->
                <v-col cols="12">
                  <div class="d-flex gap-6 flex-wrap">
                    <div class="d-flex align-center gap-2">
                      <v-switch
                        v-model="form.is_available"
                        color="success"
                        density="compact"
                        hide-details
                        inset
                      />
                      <span class="text-body-2">Available for ordering</span>
                    </div>
                    <div class="d-flex align-center gap-2">
                      <v-switch
                        v-model="form.is_featured"
                        color="warning"
                        density="compact"
                        hide-details
                        inset
                      />
                      <span class="text-body-2">Featured product</span>
                    </div>
                  </div>
                </v-col>
              </v-row>
            </v-tabs-window-item>

            <!-- ── Tab: Pricing ─────────────────────────────────────────── -->
            <v-tabs-window-item value="pricing">
              <v-row dense>
                <!-- Base Price -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.base_price"
                    label="Base Price"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="0"
                    step="0.01"
                    prepend-inner-icon="mdi-currency-usd"
                    :rules="[rules.required, rules.nonNegative]"
                    hint="Selling price shown to customers"
                    persistent-hint
                  />
                </v-col>

                <!-- Cost Price -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.cost_price"
                    label="Cost Price"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="0"
                    step="0.01"
                    prepend-inner-icon="mdi-currency-usd"
                    :rules="[rules.nonNegative]"
                    hint="Your internal cost (optional)"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Margin indicator -->
                <v-col v-if="form.base_price && form.cost_price" cols="12">
                  <v-card
                    rounded="lg"
                    color="success"
                    variant="tonal"
                    class="pa-4"
                  >
                    <div class="d-flex align-center justify-space-between">
                      <div>
                        <p class="text-caption text-medium-emphasis">
                          Gross Margin
                        </p>
                        <p class="text-h5 font-weight-bold">
                          {{ marginPercent }}%
                        </p>
                      </div>
                      <div class="text-right">
                        <p class="text-caption text-medium-emphasis">
                          Profit per unit
                        </p>
                        <p class="text-h6 font-weight-bold">
                          ${{ marginAmount }}
                        </p>
                      </div>
                      <v-avatar color="success" size="48" rounded="lg">
                        <v-icon icon="mdi-trending-up" size="24" />
                      </v-avatar>
                    </div>
                  </v-card>
                </v-col>

                <!-- Tax Category -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.tax_category"
                    label="Tax Category"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-percent"
                    :rules="[rules.maxLen(50)]"
                    hint="e.g. standard, reduced, exempt"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Sort Order -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.sort_order"
                    label="Sort Order"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="0"
                    prepend-inner-icon="mdi-sort"
                    hint="Display order (0 = first)"
                    persistent-hint
                  />
                </v-col>
              </v-row>
            </v-tabs-window-item>

            <!-- ── Tab: Details ─────────────────────────────────────────── -->
            <v-tabs-window-item value="details">
              <v-row dense>
                <!-- Preparation time -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.preparation_time"
                    label="Preparation Time"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="0"
                    prepend-inner-icon="mdi-clock-outline"
                    suffix="min"
                    :rules="[rules.nonNegativeInt]"
                    hint="Average time to prepare (minutes)"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Calories -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.calories"
                    label="Calories"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="0"
                    prepend-inner-icon="mdi-fire"
                    suffix="kcal"
                    :rules="[rules.nonNegativeInt]"
                    hint="Nutritional info (optional)"
                    persistent-hint
                    clearable
                  />
                </v-col>

                <!-- Info card -->
                <v-col cols="12">
                  <v-alert
                    type="info"
                    variant="tonal"
                    rounded="lg"
                    icon="mdi-information-outline"
                    class="mt-2"
                  >
                    <p class="text-body-2">
                      <strong>Variants & Modifier Groups</strong>
                      can be managed after saving the product from the product
                      detail page.
                    </p>
                  </v-alert>
                </v-col>

                <!-- Summary preview -->
                <v-col cols="12">
                  <v-card
                    rounded="lg"
                    elevation="0"
                    color="grey-lighten-4"
                    class="pa-4"
                  >
                    <p
                      class="text-caption text-medium-emphasis font-weight-bold mb-3"
                    >
                      <v-icon icon="mdi-eye-outline" size="14" class="mr-1" />
                      PREVIEW SUMMARY
                    </p>
                    <div class="d-flex align-center gap-3">
                      <v-avatar size="52" rounded="lg" color="grey-lighten-2">
                        <v-img
                          v-if="form.image_url"
                          :src="form.image_url"
                          cover
                        />
                        <v-icon
                          v-else
                          :icon="typeIcon(form.product_type)"
                          size="24"
                          color="grey"
                        />
                      </v-avatar>
                      <div>
                        <p class="font-weight-semibold">
                          {{ form.name || 'Product Name' }}
                        </p>
                        <p class="text-caption text-medium-emphasis">
                          {{ form.sku || 'No SKU' }} ·
                          {{ form.product_type || 'food' }}
                          <span v-if="form.calories">
                            · {{ form.calories }} kcal
                          </span>
                          <span v-if="form.preparation_time">
                            · {{ form.preparation_time }} min prep
                          </span>
                        </p>
                      </div>
                      <v-spacer />
                      <p class="text-h6 font-weight-bold">
                        ${{ Number(form.base_price || 0).toFixed(2) }}
                      </p>
                    </div>
                  </v-card>
                </v-col>
              </v-row>
            </v-tabs-window-item>
          </v-tabs-window>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ─────────────────────────────────────────────────────── -->
      <v-card-actions>
        <v-spacer/>
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
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

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    tenants: { type: Array, default: () => [] }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── Refs ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const activeTab = ref('basic')
  const saving = ref(false)

  // ── Constants ─────────────────────────────────────────────────────────────────
  const productTypeOptions = [
    { title: 'Food', value: 'food' },
    { title: 'Beverage', value: 'beverage' },
    { title: 'Retail', value: 'retail' },
    { title: 'Combo', value: 'combo' }
  ]

  // ── Default form ──────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    id: null,
    tenant_id: null,
    category_id: null,
    sku: null,
    barcode: null,
    name: '',
    description: null,
    image_url: null,
    base_price: 0.0,
    cost_price: null,
    product_type: 'food',
    preparation_time: null,
    calories: null,
    is_available: true,
    is_featured: false,
    tax_category: null,
    sort_order: 0
  })

  const form = ref(defaultForm())

  // ── Computed ──────────────────────────────────────────────────────────────────
  const isEdit = computed(() => !!props.editItem)

  const marginPercent = computed(() => {
    if (!form.value.base_price || !form.value.cost_price) return 0
    return (
      ((form.value.base_price - form.value.cost_price) /
        form.value.base_price) *
      100
    ).toFixed(1)
  })

  const marginAmount = computed(() => {
    if (!form.value.base_price || !form.value.cost_price) return '0.00'
    return (form.value.base_price - form.value.cost_price).toFixed(2)
  })

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v =>
      (v !== null && v !== '' && v !== undefined) || 'This field is required',
    nonNegative: v =>
      (!v && v !== 0) || Number(v) >= 0 || 'Must be 0 or greater',
    nonNegativeInt: v =>
      (!v && v !== 0) ||
      (Number.isInteger(Number(v)) && Number(v) >= 0) ||
      'Must be a positive integer',
    maxLen: n => v => !v || v.length <= n || `Max ${n} characters`
  }

  // ── Watch editItem ────────────────────────────────────────────────────────────
  watch(
    () => props.editItem,
    item => {
      activeTab.value = 'basic'
      if (item) {
        form.value = {
          id: item.id ?? null,
          tenant_id: item.tenant_id ?? null,
          category_id: item.category_id ?? null,
          sku: item.sku ?? null,
          barcode: item.barcode ?? null,
          name: item.name ?? '',
          description: item.description ?? null,
          image_url: item.image_url ?? null,
          base_price: item.base_price ?? 0,
          cost_price: item.cost_price ?? null,
          product_type: item.product_type ?? 'food',
          preparation_time: item.preparation_time ?? null,
          calories: item.calories ?? null,
          is_available: item.is_available ?? true,
          is_featured: item.is_featured ?? false,
          tax_category: item.tax_category ?? null,
          sort_order: item.sort_order ?? 0
        }
      } else {
        form.value = defaultForm()
      }
    },
    { immediate: true }
  )

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const typeColor = t =>
    ({ food: 'orange', beverage: 'blue', retail: 'purple', combo: 'teal' })[
      t
    ] || 'grey'
  const typeIcon = t =>
    ({
      food: 'mdi-food',
      beverage: 'mdi-cup',
      retail: 'mdi-shopping',
      combo: 'mdi-layers'
    })[t] || 'mdi-package'

  // ── Submit ────────────────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) {
      // Jump to first tab that has errors
      activeTab.value = 'basic'
      return
    }

    saving.value = true

    const payload = {
      id: form.value.id,
      tenant_id: form.value.tenant_id,
      category_id: form.value.category_id,
      sku: form.value.sku || null,
      barcode: form.value.barcode || null,
      name: form.value.name,
      description: form.value.description || null,
      image_url: form.value.image_url || null,
      base_price: Number(form.value.base_price),
      cost_price:
        form.value.cost_price !== null ? Number(form.value.cost_price) : null,
      product_type: form.value.product_type,
      preparation_time:
        form.value.preparation_time !== null
          ? Number(form.value.preparation_time)
          : null,
      calories:
        form.value.calories !== null ? Number(form.value.calories) : null,
      is_available: form.value.is_available,
      is_featured: form.value.is_featured,
      tax_category: form.value.tax_category || null,
      sort_order: Number(form.value.sort_order) ?? 0
    }

    emit('saved', payload)
    saving.value = false
    close()
  }

  // ── Close ─────────────────────────────────────────────────────────────────────
  const close = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    activeTab.value = 'basic'
    emit('update:modelValue', false)
  }
</script>
