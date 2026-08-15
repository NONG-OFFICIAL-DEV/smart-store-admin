<template>
  <v-container fluid class="pa-0">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <AppPageHeader
      :title="isEdit ? t('products.editTitle') : t('products.newTitle')"
      show-back
      :breadcrumbs="[
        { title: t('products.title'), to: '/products' },
        { title: isEdit ? form.name : t('products.newTitle') }
      ]"
    />

    <div class="pa-0">
      <v-form ref="formRef">
        <v-row>
          <!-- ══════════════════════════════════════════════════════════════
               LEFT COLUMN
          ═══════════════════════════════════════════════════════════════════ -->
          <v-col cols="12" md="4" lg="3">
            <!-- ── Product Image ──────────────────────────────────────── -->
            <ProductImageUpload
              v-model:image-file="imageFile"
              v-model:image-preview="imagePreview"
              v-model:image-url="form.image_url"
              :isSuperAdmin="isSuperAdmin()"
            />

            <!-- ── Business Type ──────────────────────────────────────── -->
            <v-card rounded="xl" border elevation="0" class="mb-4">
              <v-card-text class="pa-4">
                <div class="section-label mb-3">
                  <v-icon icon="mdi-store-outline" size="12" class="mr-1" />
                  {{ $t('products.cardTitle.businessType') }}
                </div>

                <template v-if="isSuperAdmin()">
                  <v-select
                    v-model="form.tenant_id"
                    :items="tenants"
                    item-title="name"
                    item-value="id"
                    :label="t('products.field.tenant')"
                    variant="outlined"
                    density="compact"
                    rounded="lg"
                    :rules="[r.required]"
                    prepend-inner-icon="mdi-domain"
                    class="mb-3"
                  >
                    <template #item="{ props: p, item }">
                      <v-list-item v-bind="p">
                        <template #prepend>
                          <v-avatar size="28" rounded="lg" class="mr-2">
                            <v-img
                              v-if="item.raw.logo_url"
                              :src="item.raw.logo_url"
                            />
                            <v-icon v-else icon="mdi-domain" size="14" />
                          </v-avatar>
                        </template>
                        <template #subtitle>
                          <span class="text-caption text-grey">
                            {{ item.raw.business_type?.name ?? '—' }}
                          </span>
                        </template>
                      </v-list-item>
                    </template>
                    <template #selection="{ item }">
                      <div class="d-flex align-center" style="gap: 8px">
                        <v-avatar size="20" rounded="sm">
                          <v-img
                            v-if="item.raw.logo_url"
                            :src="item.raw.logo_url"
                          />
                          <v-icon v-else icon="mdi-domain" size="12" />
                        </v-avatar>
                        <span class="text-body-2">{{ item.raw.name }}</span>
                      </div>
                    </template>
                  </v-select>

                  <BusinessTypeChip
                    v-if="resolvedBuConfig"
                    :config="resolvedBuConfig"
                    :label="resolvedBuLabel"
                    :nature="productNature"
                  />
                  <div v-else class="text-caption text-grey">
                    {{ $t('products.hint.selectTenantForBusinessType') }}
                  </div>
                </template>

                <template v-else>
                  <BusinessTypeChip
                    :config="resolvedBuConfig"
                    :label="resolvedBuLabel"
                    :nature="productNature"
                  />
                </template>
              </v-card-text>
            </v-card>

            <!-- ── Visibility ─────────────────────────────────────────── -->
            <v-card rounded="xl" border elevation="0" class="mb-4">
              <v-card-text class="pa-4">
                <div class="section-label mb-3">
                  <v-icon icon="mdi-eye-outline" size="12" class="mr-1" />
                  {{ $t('products.cardTitle.visibility') }}
                </div>
                <div class="d-flex flex-column" style="gap: 12px">
                  <div class="d-flex align-center justify-space-between">
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        {{ t('products.field.available') }}
                      </div>
                      <div class="text-caption text-grey">
                        {{ $t('products.field.visibleInMenuCatalog') }}
                      </div>
                    </div>
                    <v-switch
                      v-model="form.is_available"
                      color="success"
                      density="compact"
                      inset
                      hide-details
                    />
                  </div>
                  <v-divider />
                  <div class="d-flex align-center justify-space-between">
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        {{ t('products.field.featured') }}
                      </div>
                      <div class="text-caption text-grey">
                        {{ $t('products.field.featuredHintFull') }}
                      </div>
                    </div>
                    <v-switch
                      v-model="form.is_featured"
                      color="amber"
                      density="compact"
                      inset
                      hide-details
                    />
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- ══════════════════════════════════════════════════════════════
               RIGHT COLUMN
          ═══════════════════════════════════════════════════════════════════ -->
          <v-col cols="12" md="8" lg="9">
            <!-- ── Core Info ──────────────────────────────────────────── -->
            <v-card rounded="xl" border elevation="0" class="mb-4">
              <v-card-text class="pa-5">
                <div class="section-label mb-4">
                  <v-icon
                    icon="mdi-information-outline"
                    size="12"
                    class="mr-1"
                  />
                  {{ $t('products.cardTitle.coreInformation') }}
                </div>
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.name"
                      :label="t('products.field.productName')"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :rules="[r.required, r.maxLen(200)]"
                      prepend-inner-icon="mdi-package-variant"
                      maxlength="200"
                      counter="200"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
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
                  <v-col cols="12">
                    <v-textarea
                      v-model="form.description"
                      :label="t('products.field.description')"
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
              </v-card-text>
            </v-card>

            <!-- ── Identifiers ────────────────────────────────────────── -->
            <v-card rounded="xl" border elevation="0" class="mb-4">
              <v-card-text class="pa-5">
                <div class="section-label mb-4">
                  <v-icon icon="mdi-barcode-scan" size="12" class="mr-1" />
                  {{ $t('products.cardTitle.identifiers') }}
                </div>
                <v-row dense>
                  <v-col v-if="isMartProduct" cols="12" sm="4">
                    <v-text-field
                      v-model="form.sku"
                      :label="t('products.field.sku')"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.maxLen(60)]"
                      prepend-inner-icon="mdi-identifier"
                      clearable
                    />
                  </v-col>
                  <v-col v-if="isMartProduct" cols="12" sm="4">
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
                  <v-col cols="12" :sm="isMartProduct ? 4 : 6">
                    <v-text-field
                      v-model.number="form.sort_order"
                      :label="t('products.field.sortOrder')"
                      type="number"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      hide-details="auto"
                      :rules="[r.nonNegativeInt]"
                      prepend-inner-icon="mdi-sort-numeric-ascending"
                      min="0"
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <!-- ══════════════════════════════════════════════════════════
                 FOOD PRODUCT SECTIONS
            ═══════════════════════════════════════════════════════════════ -->
            <template v-if="isFoodProduct && resolvedBuConfig">
              <!-- ── Kitchen Details ──────────────────────────────────── -->
              <v-card rounded="xl" border elevation="0" class="mb-4">
                <v-card-text class="pa-5">
                  <div class="section-label mb-4">
                    <v-icon
                      icon="mdi-silverware-fork-knife"
                      size="12"
                      class="mr-1"
                    />
                    {{ $t('products.cardTitle.kitchenDetails') }}
                  </div>
                  <v-row dense>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model.number="form.preparation_time"
                        :label="t('products.field.prepTime')"
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
                        :label="t('products.field.calories')"
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

                    <template v-if="resolvedBuCode === 'COFFEE_SHOP'">
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.cup_sizes"
                          :items="cupSizeOptions"
                          :label="t('products.field.availableSizes')"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          hide-details
                          multiple
                          chips
                          closable-chips
                          prepend-inner-icon="mdi-cup-outline"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.temperature_options"
                          :items="temperatureOptions"
                          :label="t('products.field.temperatureOptionsLabel')"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          hide-details
                          multiple
                          chips
                          closable-chips
                          prepend-inner-icon="mdi-thermometer"
                        />
                      </v-col>
                    </template>

                    <template v-if="resolvedBuCode === 'BAKERY'">
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model.number="form.shelf_life_hours"
                          :label="t('products.field.shelfLife')"
                          type="number"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.nonNegativeInt]"
                          prepend-inner-icon="mdi-clock-check-outline"
                          suffix="hours"
                          min="0"
                          clearable
                        />
                      </v-col>
                    </template>
                  </v-row>
                </v-card-text>
              </v-card>

              <!-- ── Pricing & Variants ───────────────────────────────── -->
              <v-card rounded="xl" border elevation="0" class="mb-4">
                <v-card-text class="pa-5">
                  <div class="d-flex align-center justify-space-between mb-4">
                    <div class="section-label">
                      <v-icon icon="mdi-currency-usd" size="12" class="mr-1" />
                      {{ $t('products.cardTitle.pricingAndVariants') }}
                    </div>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      label
                    >
                      {{
                        form.variants.length === 0
                          ? t('products.variant.singlePrice')
                          : t(
                              'products.variant.variantCount',
                              form.variants.length,
                              {
                                count: form.variants.length
                              }
                            )
                      }}
                    </v-chip>
                  </div>

                  <v-row dense class="mb-2">
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model.number="form.base_price"
                        :label="t('products.field.basePrice')"
                        type="number"
                        variant="outlined"
                        density="compact"
                        rounded="lg"
                        :rules="[r.required, r.nonNegative]"
                        min="0"
                        step="0.01"
                        :hint="$t('products.variant.defaultSellingPriceHint')"
                        persistent-hint
                      >
                        <template #prepend-inner>
                          <span>
                            {{ currencySymbol() }}
                          </span>
                        </template>
                      </v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model.number="form.cost_price"
                        :label="t('products.field.costPrice')"
                        type="number"
                        variant="outlined"
                        density="compact"
                        rounded="lg"
                        :rules="[r.nonNegative]"
                        prepend-inner-icon="mdi-cash-minus"
                        min="0"
                        step="0.01"
                        :hint="marginLabel"
                        persistent-hint
                      />
                    </v-col>
                  </v-row>

                  <v-divider class="my-4" />

                  <!-- Coffee Shop: size × temp matrix -->
                  <template
                    v-if="
                      resolvedBuCode === 'COFFEE_SHOP' &&
                      (form.cup_sizes.length || form.temperature_options.length)
                    "
                  >
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div>
                        <div class="text-body-2 font-weight-medium">
                          {{ $t('products.variant.sizeTempMatrixTitle') }}
                        </div>
                        <div class="text-caption text-grey">
                          {{ $t('products.variant.sizeTempMatrixHint') }}
                        </div>
                      </div>
                      <v-btn
                        size="x-small"
                        variant="tonal"
                        rounded="lg"
                        prepend-icon="mdi-auto-fix"
                        @click="generateCoffeeVariants"
                      >
                        {{ $t('products.variant.generateMatrix') }}
                      </v-btn>
                    </div>

                    <div v-if="form.variants.length" class="variants-grid mb-2">
                      <div class="text-caption text-grey font-weight-medium">
                        {{ $t('products.variant.sizeTemp') }}
                      </div>
                      <div
                        class="text-caption text-grey font-weight-medium text-center"
                      >
                        {{ $t('products.variant.price') }}
                      </div>
                      <div
                        class="text-caption text-grey font-weight-medium text-center"
                      >
                        {{ $t('products.variant.default') }}
                      </div>
                      <div></div>
                    </div>
                    <div
                      v-for="(variant, i) in form.variants"
                      :key="`coffee-${i}`"
                      class="variants-grid mb-2 align-center"
                    >
                      <v-text-field
                        v-model="variant.name"
                        density="compact"
                        variant="outlined"
                        rounded="lg"
                        hide-details
                        readonly
                      />
                      <v-text-field
                        v-model.number="variant.price_adjustment"
                        type="number"
                        density="compact"
                        variant="outlined"
                        rounded="lg"
                        hide-details
                        prepend-inner-icon="mdi-currency-usd"
                        min="0"
                        step="0.01"
                      />
                      <div class="d-flex justify-center">
                        <v-radio
                          :model-value="defaultVariantIndex === i"
                          color="primary"
                          density="compact"
                          hide-details
                          @change="setDefaultVariant(i)"
                        />
                      </div>
                      <v-btn
                        icon="mdi-delete-outline"
                        variant="text"
                        color="error"
                        size="small"
                        @click="removeVariant(i)"
                      />
                    </div>
                  </template>

                  <!-- Restaurant / Bakery: free-form variants -->
                  <template v-else>
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div>
                        <div class="text-body-2 font-weight-medium">
                          {{ $t('products.variant.optionVariants') }}
                        </div>
                        <div class="text-caption text-grey">
                          {{ $t('products.variant.optionVariantsHint') }}
                        </div>
                      </div>
                    </div>

                    <div v-if="form.variants.length" class="variants-grid mb-2">
                      <div class="text-caption text-grey font-weight-medium">
                        {{ $t('products.variant.name') }}
                      </div>
                      <div
                        class="text-caption text-grey font-weight-medium text-center"
                      >
                        {{ $t('products.variant.sellingPrice') }}
                      </div>
                      <div
                        class="text-caption text-grey font-weight-medium text-center"
                      >
                        {{ $t('products.variant.default') }}
                      </div>
                      <div></div>
                    </div>
                    <div
                      v-for="(variant, i) in form.variants"
                      :key="`variant-${i}`"
                      class="variants-grid mb-2 align-center"
                    >
                      <v-text-field
                        v-model="variant.name"
                        :placeholder="$t('products.variant.freeformNamePlaceholder')"
                        density="compact"
                        variant="outlined"
                        rounded="lg"
                        hide-details
                        :rules="[r.required]"
                      />
                      <v-text-field
                        v-model.number="variant.price_adjustment"
                        type="number"
                        density="compact"
                        variant="outlined"
                        rounded="lg"
                        hide-details
                        prepend-inner-icon="mdi-currency-usd"
                        min="0"
                        step="0.01"
                      />
                      <div class="d-flex justify-center">
                        <v-radio
                          :model-value="defaultVariantIndex === i"
                          color="primary"
                          density="compact"
                          hide-details
                          @change="setDefaultVariant(i)"
                        />
                      </div>
                      <v-btn
                        icon="mdi-delete-outline"
                        variant="text"
                        color="error"
                        size="small"
                        @click="removeVariant(i)"
                      />
                    </div>

                    <v-btn
                      variant="tonal"
                      size="small"
                      rounded="lg"
                      class="mt-2"
                      prepend-icon="mdi-plus"
                      @click="addVariant"
                    >
                      {{ $t('products.variant.addVariant') }}
                    </v-btn>
                  </template>

                  <v-alert
                    v-if="form.variants.length === 0"
                    variant="tonal"
                    color="grey"
                    density="compact"
                    rounded="lg"
                    class="mt-3"
                  >
                    <template #prepend>
                      <v-icon icon="mdi-information-outline" size="14" />
                    </template>
                    <span class="text-caption">
                      {{ $t('products.variant.noVariantsHint') }}
                    </span>
                  </v-alert>
                </v-card-text>
              </v-card>
            </template>

            <!-- ══════════════════════════════════════════════════════════
                 MART PRODUCT SECTIONS
            ═══════════════════════════════════════════════════════════════ -->
            <template v-if="isMartProduct && resolvedBuConfig">
              <!-- ── Inventory & Stock ────────────────────────────────── -->
              <v-card rounded="xl" border elevation="0" class="mb-4">
                <v-card-text class="pa-5">
                  <div class="section-label mb-4">
                    <v-icon icon="mdi-warehouse" size="12" class="mr-1" />
                    {{ $t('products.cardTitle.inventoryAndStock') }}
                  </div>
                  <v-row dense>
                    <v-col cols="12" sm="4">
                      <v-text-field
                        v-model.number="form.stock_quantity"
                        :label="t('products.field.stockQuantity')"
                        type="number"
                        variant="outlined"
                        density="compact"
                        rounded="lg"
                        hide-details="auto"
                        :rules="[r.nonNegativeInt]"
                        prepend-inner-icon="mdi-package-variant-closed"
                        min="0"
                        clearable
                      />
                    </v-col>
                    <v-col cols="12" sm="4">
                      <v-text-field
                        v-model.number="form.reorder_level"
                        :label="t('products.field.lowStockAlert')"
                        type="number"
                        variant="outlined"
                        density="compact"
                        rounded="lg"
                        hide-details="auto"
                        :rules="[r.nonNegativeInt]"
                        prepend-inner-icon="mdi-bell-alert-outline"
                        min="0"
                        clearable
                      />
                    </v-col>
                    <v-col cols="12" sm="4">
                      <v-switch
                        v-model="form.track_stock"
                        :label="t('products.field.trackStock')"
                        color="primary"
                        density="compact"
                        inset
                        hide-details
                        class="pt-1"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-date-input
                        v-model="form.expiry_date"
                        :label="t('products.field.expiryDate')"
                        variant="outlined"
                        density="compact"
                        rounded="lg"
                        hide-details
                        prepend-inner-icon="mdi-calendar-end-outline"
                        append-inner-icon=""
                        clearable
                      />
                    </v-col>

                    <template v-if="resolvedBuCode === 'WHOLESALE'">
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.supplier_code"
                          :label="t('products.field.supplierCode')"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          hide-details="auto"
                          prepend-inner-icon="mdi-truck-delivery-outline"
                          clearable
                        />
                      </v-col>
                    </template>
                  </v-row>
                </v-card-text>
              </v-card>

              <!-- ── Units & Pricing ──────────────────────────────────── -->
              <v-card rounded="xl" border elevation="0" class="mb-4">
                <v-card-text class="pa-5">
                  <div class="d-flex align-center justify-space-between mb-1">
                    <div class="section-label">
                      <v-icon
                        icon="mdi-layers-triple-outline"
                        size="12"
                        class="mr-1"
                      />
                      {{ $t('unit.title') }}
                    </div>
                    <v-chip size="x-small" variant="tonal" color="indigo" label>
                      {{ $t('unit.unit_count', form.units.length) }}
                    </v-chip>
                  </div>
                  <div class="text-caption text-grey mb-4">
                    {{ $t('unit.subtitle_add') }}
                  </div>

                  <div
                    v-for="(unit, i) in form.units"
                    :key="`unit-${i}`"
                    class="unit-row mb-4 pa-4 rounded-lg"
                    :class="unit.is_base_unit ? 'unit-row--base' : ''"
                  >
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div class="d-flex align-center" style="gap: 8px">
                        <v-chip
                          v-if="unit.is_base_unit"
                          size="x-small"
                          color="indigo"
                          label
                          variant="flat"
                        >
                          {{ $t('unit.base_unit_chip') }}
                        </v-chip>
                        <v-chip
                          v-else
                          size="x-small"
                          color="grey"
                          label
                          variant="tonal"
                        >
                          {{ $t('unit.derived_unit_chip') }}
                        </v-chip>
                        <span class="text-body-2 font-weight-medium">
                          {{ unit.unit_name || t('unit.unit_fallback_name', { n: i + 1 }) }}
                        </span>
                      </div>
                      <v-btn
                        icon="mdi-delete-outline"
                        variant="text"
                        color="error"
                        size="small"
                        :disabled="unit.is_base_unit && form.units.length === 1"
                        @click="removeUnit(i)"
                      />
                    </div>

                    <v-row dense>
                      <v-col cols="12" sm="4">
                        <v-combobox
                          v-model="unit.unit_name"
                          :items="unitNameOptions"
                          :loading="loadingNames"
                          item-title="title"
                          item-value="title"
                          :label="t('unit.unit_name') + ' *'"
                          :placeholder="t('unit.unit_name_placeholder')"
                          variant="outlined"
                          density="compact"
                          rounded="lg"
                          :rules="[r.required]"
                          clearable
                          @update:model-value="
                            val => onUnitNameChange(val, unit)
                          "
                        >
                          <!-- Item row in dropdown -->
                          <template #item="{ item, props: iProps }">
                            <v-list-item
                              v-bind="iProps"
                              :subtitle="item.raw?.subtitle"
                            >
                              <template #append>
                                <v-chip
                                  v-if="item.raw?.source === 'db'"
                                  size="x-small"
                                  color="primary"
                                  variant="tonal"
                                  rounded="lg"
                                >
                                  {{ $t('unit.existing') }}
                                </v-chip>
                                <v-chip
                                  v-else
                                  size="x-small"
                                  color="grey"
                                  variant="tonal"
                                  rounded="lg"
                                >
                                  {{ $t('unit.common') }}
                                </v-chip>
                              </template>
                            </v-list-item>
                          </template>

                          <!-- When user typed something not in list -->
                          <template #no-data>
                            <v-list-item>
                              <v-list-item-title class="text-caption">
                                {{ $t('unit.press_enter_before') }}
                                <kbd>Enter</kbd>
                                {{ $t('unit.press_enter_after') }} "
                                <strong>{{ unit.unit_name }}</strong>
                                "
                              </v-list-item-title>
                            </v-list-item>
                          </template>
                        </v-combobox>
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model.number="unit.qty_per_base"
                          :label="$t('unit.qty_per_base')"
                          type="number"
                          density="compact"
                          variant="outlined"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.positiveNumber]"
                          :bg-color="
                            unit.is_base_unit ? 'grey-lighten-4' : undefined
                          "
                          :readonly="unit.is_base_unit"
                          :hint="
                            unit.is_base_unit ? $t('unit.hint_base_unit') : ''
                          "
                          prepend-inner-icon="mdi-numeric"
                          min="0.001"
                          step="1"
                        />
                        <!-- :readonly="unit.is_base_unit" -->
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model="unit.barcode"
                          :label="$t('unit.barcode')"
                          density="compact"
                          variant="outlined"
                          rounded="lg"
                          hide-details
                          prepend-inner-icon="mdi-barcode"
                          clearable
                        />
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model.number="unit.retail_price"
                          :label="$t('unit.retail_price')"
                          type="number"
                          density="compact"
                          variant="outlined"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.required, r.nonNegative]"
                          min="0"
                          step="0.01"
                        >
                          <template #prepend-inner>
                            <span>
                              {{ currencySymbol() }}
                            </span>
                          </template>
                        </v-text-field>
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model.number="unit.wholesale_price"
                          :label="$t('unit.wholesale_price')"
                          type="number"
                          density="compact"
                          variant="outlined"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.nonNegative]"
                          prepend-inner-icon="mdi-tag-multiple-outline"
                          min="0"
                          step="0.01"
                          color="indigo"
                        />
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model.number="unit.cost_price"
                          :label="$t('unit.cost_price')"
                          type="number"
                          density="compact"
                          variant="outlined"
                          rounded="lg"
                          :hint="unitMarginLabel(unit)"
                          persistent-hint
                          :rules="[r.nonNegative]"
                          prepend-inner-icon="mdi-cash-minus"
                          min="0"
                          step="0.01"
                        />
                      </v-col>
                      <v-col
                        cols="12"
                        class="d-flex align-center"
                        style="gap: 16px"
                      >
                        <v-switch
                          v-model="unit.is_active"
                          :label="$t('unit.active')"
                          color="success"
                          density="compact"
                          inset
                          hide-details
                        />
                        <v-btn
                          v-if="!unit.is_base_unit"
                          size="small"
                          variant="tonal"
                          color="indigo"
                          rounded="lg"
                          prepend-icon="mdi-star-outline"
                          @click="setBaseUnit(i)"
                        >
                          {{ $t('unit.set_base_unit') }}
                        </v-btn>
                        <v-btn
                          v-else
                          size="small"
                          variant="tonal"
                          color="warning"
                          rounded="lg"
                          prepend-icon="mdi-star"
                        >
                          {{ $t('unit.base_unit') }}
                        </v-btn>
                      </v-col>
                    </v-row>
                  </div>

                  <v-btn
                    variant="tonal"
                    size="small"
                    rounded="lg"
                    color="indigo"
                    prepend-icon="mdi-plus"
                    @click="addUnit"
                  >
                    {{ $t('unit.add') }}
                  </v-btn>
                  <div
                    v-if="form.units.length === 0"
                    class="text-caption text-error mt-2"
                  >
                    <v-icon
                      icon="mdi-alert-circle-outline"
                      size="12"
                      class="mr-1"
                    />
                    {{ $t('unit.at_least_one_unit_required') }}
                  </div>
                </v-card-text>
              </v-card>
            </template>

            <!-- ── Hint ───────────────────────────────────────────────── -->
            <v-alert
              v-if="resolvedBuConfig"
              variant="tonal"
              rounded="xl"
              density="compact"
              class="mb-4"
              :color="isFoodProduct ? 'primary' : 'indigo'"
            >
              <template #prepend>
                <v-icon
                  :icon="
                    isFoodProduct
                      ? 'mdi-tune-variant'
                      : 'mdi-layers-triple-outline'
                  "
                  size="16"
                />
              </template>
              <span class="text-caption">
                <span v-if="isFoodProduct">{{ t('products.alert.food') }}</span>
                <span v-else>{{ t('products.alert.retail') }}</span>
              </span>
            </v-alert>

            <!-- ── Actions ────────────────────────────────────────────── -->
            <div class="d-flex align-center justify-end" style="gap: 12px">
              <span v-if="formError" class="text-caption text-error mr-auto">
                <v-icon
                  icon="mdi-alert-circle-outline"
                  size="14"
                  class="mr-1"
                />
                {{ formError }}
              </span>
              <v-btn variant="tonal" rounded="lg" :to="'/products'">
                {{ t('btn.cancel') }}
              </v-btn>
              <v-btn
                :color="isEdit ? 'primary' : 'success'"
                variant="flat"
                rounded="lg"
                :loading="isLoadingEdit"
                :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-plus'"
                @click="handleSubmit"
              >
                {{ isEdit ? t('btn.save_changes') : t('btn.create') }}
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-form>
    </div>
  </v-container>
</template>

<script setup>
  import { ref, computed, watch, onMounted, nextTick } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { storeToRefs } from 'pinia'
  import { useAppUtils } from '@nong-official-dev/core'
  import { usePermission } from '@/composables/usePermission'
  import { useAuthStore } from '@/stores/authStore'
  import { useProductStore } from '@/stores/productStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { BUSINESS_TYPES } from '@/constants/businessTypes'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import ProductImageUpload from '@/components/products/ProductImageUpload.vue'
  import BusinessTypeChip from '@/components/products/BusinessTypeChip.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  // ── Router ─────────────────────────────────────────────────────────────────────
  const route = useRoute()
  const router = useRouter()

  // ── Constants ──────────────────────────────────────────────────────────────────
  const cupSizeOptions = ['S', 'M', 'L', 'XL']
  const temperatureOptions = ['Hot', 'Iced', 'Blended']

  // ── Stores ─────────────────────────────────────────────────────────────────────
  const authStore = useAuthStore()
  const productStore = useProductStore()
  const categoryStore = useCategoryStore()
  const tenantStore = useTenantStore()
  const productUnitStore = useProductUnitStore()
  const { categories } = storeToRefs(categoryStore)
  const { tenants } = storeToRefs(tenantStore)

  // ── Composables ────────────────────────────────────────────────────────────────
  const { t } = useI18n()
  const { isSuperAdmin } = usePermission()
  const { notif } = useAppUtils()
  const { currencySymbol } = useCurrency()

  // ── State ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const formError = ref('')
  const imagePreview = ref(null)
  const imageFile = ref(null)
  const isEdit = ref(false)
  const isLoadingEdit = ref(false)
  const isHydrating = ref(false)
  const dbUnits = ref([])
  const loadingNames = ref(false)
  // ── Form ───────────────────────────────────────────────────────────────────────
  const commonUnits = [
    { title: 'pcs', qty_per_base: 1, source: 'common' },
    { title: 'can', qty_per_base: 1, source: 'common' },
    {
      title: 'bottle',
      qty_per_base: 1,
      source: 'common'
    },
    { title: 'pack', qty_per_base: 6, source: 'common' },
    { title: 'box', qty_per_base: 24, source: 'common' },
    {
      title: 'carton',
      qty_per_base: 24,
      source: 'common'
    },
    { title: 'case', qty_per_base: 12, source: 'common' },
    { title: 'kg', qty_per_base: 1, source: 'common' },
    { title: 'g', qty_per_base: 1, source: 'common' },
    { title: 'litre', qty_per_base: 1, source: 'common' },
    { title: 'dozen', qty_per_base: 12, source: 'common' },
    { title: 'bag', qty_per_base: 1, source: 'common' }
  ]

  const defaultForm = () => ({
    id: null,
    tenant_id: null,
    category_id: null,
    sku: null,
    barcode: '',
    name: '',
    description: null,
    image_url: null,
    sort_order: 0,
    is_available: true,
    is_featured: false,
    // Food
    base_price: null,
    cost_price: null,
    variants: [],
    preparation_time: null,
    calories: null,
    cup_sizes: [],
    temperature_options: [],
    shelf_life_hours: null,
    // Mart
    stock_quantity: null,
    reorder_level: null,
    track_stock: true,
    expiry_date: null,
    supplier_code: null,
    units: []
  })

  const form = ref(defaultForm())

  // ── Merge DB units first, then fill in common ones not already in DB ───────
  const unitNameOptions = computed(() => {
    const dbTitles = new Set(dbUnits.value.map(u => u.title))
    const extras = commonUnits.filter(u => !dbTitles.has(u.title))

    return [
      // DB units shown first with "existing" badge
      ...dbUnits.value.map(u => ({
        ...u,
        source: 'db',
        subtitle: `× ${u.qty_per_base} · used in your products`
      })),
      // Common units not yet in DB shown as fallback
      ...extras.map(u => ({
        ...u,
        source: 'common',
        subtitle: `× ${u.qty_per_base}`
      }))
    ]
  })

  // ── Business type ──────────────────────────────────────────────────────────────
  const resolvedBuCode = computed(() => {
    if (isSuperAdmin()) {
      if (!form.value.tenant_id) return null
      const tenant = tenants.value.find(t => t.id === form.value.tenant_id)
      return tenant?.business_type?.code?.toUpperCase() ?? null
    }
    return authStore.bu_type?.toUpperCase() ?? 'RESTAURANT'
  })

  const resolvedBuConfig = computed(() =>
    resolvedBuCode.value ? (BUSINESS_TYPES[resolvedBuCode.value] ?? null) : null
  )

  const resolvedBuLabel = computed(() => {
    if (isSuperAdmin()) {
      const tenant = tenants.value.find(t => t.id === form.value.tenant_id)
      return tenant?.business_type?.name ?? resolvedBuCode.value ?? '—'
    }
    return (
      resolvedBuCode.value
        ?.replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase()) ?? '—'
    )
  })

  const productNature = computed(
    () => resolvedBuConfig.value?.category ?? 'food'
  )
  const isFoodProduct = computed(() => productNature.value === 'food')
  const isMartProduct = computed(() => productNature.value === 'mart')

  // ── Pricing helpers ────────────────────────────────────────────────────────────
  const defaultVariantIndex = computed(() =>
    form.value.variants.findIndex(v => v.is_default)
  )

  const marginLabel = computed(() => {
    const { base_price: base, cost_price: cost } = form.value
    if (!base || !cost || base === 0)
      return t('products.variant.internalCostReference')
    return t('products.variant.marginLabel', {
      value: (((base - cost) / base) * 100).toFixed(1)
    })
  })

  const unitMarginLabel = unit => {
    const { retail_price: retail, cost_price: cost } = unit
    if (!retail || !cost || retail === 0) return ''
    return t('products.variant.marginLabel', {
      value: (((retail - cost) / retail) * 100).toFixed(1)
    })
  }

  // ── When user picks or types a unit name ──────────────────────────────────
  const onUnitNameChange = (val, unit) => {
    // Typed a plain string (custom value or cleared)
    if (typeof val === 'string' || val === null) {
      unit.unit_name = val ?? ''
      return
    }

    // Selected an object from the dropdown
    if (typeof val === 'object') {
      // Auto-fill qty if still at default 1 and selected has a better default
      if (unit.qty_per_base === 1 && val.qty_per_base > 1) {
        unit.qty_per_base = val.qty_per_base
      }
      // Always store plain string, not the object
      unit.unit_name = val.title
    }
  }

  // ── Variant helpers ────────────────────────────────────────────────────────────
  const addVariant = () =>
    form.value.variants.push({
      name: '',
      price_adjustment: Number(form.value.base_price ?? 0),
      is_default: form.value.variants.length === 0,
      sort_order: form.value.variants.length
    })

  const removeVariant = i => {
    const wasDefault = form.value.variants[i]?.is_default
    form.value.variants.splice(i, 1)
    if (wasDefault && form.value.variants.length > 0)
      form.value.variants[0].is_default = true
  }

  const setDefaultVariant = i =>
    form.value.variants.forEach((v, idx) => (v.is_default = idx === i))

  const generateCoffeeVariants = () => {
    const sizes = form.value.cup_sizes
    const temps = form.value.temperature_options
    if (!sizes.length && !temps.length) return

    const names =
      sizes.length && temps.length
        ? sizes.flatMap(s => temps.map(t => `${s} / ${t}`))
        : [...(sizes.length ? sizes : temps)]

    const priceMap = Object.fromEntries(
      form.value.variants.map(v => [v.name, v.price])
    )

    form.value.variants = names.map((name, idx) => ({
      name,
      price_adjustment: priceMap[name] ?? Number(form.value.base_price ?? 0),
      is_default: idx === 0,
      sort_order: idx
    }))
  }

  // ── Unit helpers ───────────────────────────────────────────────────────────────
  const makeUnit = (isBase = false) => ({
    unit_name: '',
    qty_per_base: 1,
    barcode: '',
    retail_price: null,
    wholesale_price: null,
    cost_price: null,
    is_base_unit: isBase,
    is_active: true
  })

  const addUnit = () =>
    form.value.units.push(makeUnit(form.value.units.length === 0))

  const removeUnit = i => {
    const wasBase = form.value.units[i]?.is_base_unit
    form.value.units.splice(i, 1)
    if (wasBase && form.value.units.length > 0) {
      form.value.units[0].is_base_unit = true
      form.value.units[0].qty_per_base = 1
    }
  }

  const setBaseUnit = i =>
    form.value.units.forEach((u, idx) => {
      u.is_base_unit = idx === i
      if (idx === i) u.qty_per_base = 1
    })

  // ── Watchers ───────────────────────────────────────────────────────────────────

  // Reset nature-specific fields when business type changes (skipped during hydration)
  watch(productNature, () => {
    if (isHydrating.value) return
    Object.assign(form.value, {
      preparation_time: null,
      calories: null,
      cup_sizes: [],
      temperature_options: [],
      shelf_life_hours: null,
      base_price: null,
      cost_price: null,
      variants: [],
      stock_quantity: null,
      reorder_level: null,
      track_stock: true,
      expiry_date: null,
      supplier_code: null,
      units: []
    })
  })

  // Auto-seed base unit for mart (skipped during hydration)
  watch(
    isMartProduct,
    val => {
      if (isHydrating.value) return
      if (val && form.value.units.length === 0)
        form.value.units.push(makeUnit(true))
    },
    { immediate: true }
  )

  // ── Validation rules ───────────────────────────────────────────────────────────
  const r = {
    required: v =>
      (v !== null && v !== '' && v !== undefined) ||
      t('products.rule.required'),
    nonNegative: v =>
      (!v && v !== 0) || Number(v) >= 0 || t('products.rule.nonNegative'),
    positiveNumber: v =>
      (!v && v !== 0) || Number(v) > 0 || t('validation.positive'),
    nonNegativeInt: v =>
      (!v && v !== 0) ||
      (Number.isInteger(Number(v)) && Number(v) >= 0) ||
      t('products.rule.nonNegativeInt'),
    maxLen: n => v =>
      !v || v.length <= n || t('products.rule.maxLen', { n })
  }

  // ── Hydrate form for edit ──────────────────────────────────────────────────────
  const hydrateForm = item => {
    form.value = {
      ...defaultForm(),
      ...item,
      name: item.name ?? '',
      sort_order: item.sort_order ?? 0,
      is_available: item.is_available ?? true,
      is_featured: item.is_featured ?? false,
      cup_sizes: item.cup_sizes ?? [],
      temperature_options: item.temperature_options ?? [],
      variants:
        item.variants?.map((v, i) => ({
          id: v.id,
          name: v.name,
          price_adjustment: Number(v.price_adjustment ?? v.price ?? 0), // Force number
          is_default: v.is_default ?? i === 0,
          sort_order: v.sort_order ?? i
        })) ?? [],
      units: item.units ?? []
    }
    imagePreview.value = item.image_url ?? null
    imageFile.value = null
  }

  // ── Build submit payload ───────────────────────────────────────────────────────
  const buildPayload = () => {
    const food = isFoodProduct.value
    const data = {
      ...form.value,
      base_price: food ? form.value.base_price : null,
      cost_price: food ? form.value.cost_price : null,
      variants: food ? form.value.variants : [],
      preparation_time: food ? form.value.preparation_time : null,
      calories: food ? form.value.calories : null,
      cup_sizes: food ? form.value.cup_sizes : [],
      temperature_options: food ? form.value.temperature_options : [],
      shelf_life_hours: food ? form.value.shelf_life_hours : null,
      units: !food ? form.value.units : [],
      stock_quantity: !food ? form.value.stock_quantity : null,
      reorder_level: !food ? form.value.reorder_level : null,
      track_stock: !food ? form.value.track_stock : false,
      expiry_date: !food ? form.value.expiry_date : null,
      supplier_code: !food ? form.value.supplier_code : null
    }

    // Don't overwrite image_url if user didn't change the image
    delete data.image_url // ← add this line before the FormData check

    if (!imageFile.value) return data

    const fd = new FormData()
    fd.append('image', imageFile.value)
    Object.entries(data).forEach(([k, v]) => {
      if (v === null || v === undefined || k === 'image_url') return
      fd.append(
        k,
        Array.isArray(v) || typeof v === 'object' ? JSON.stringify(v) : v
      )
    })
    return fd
  }

  // ── Submit ─────────────────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    formError.value = ''
    const { valid } = await formRef.value.validate()
    if (!valid)
      return (formError.value = t('products.errors.fixBeforeSaving'))
    if (isMartProduct.value && form.value.units.length === 0)
      return (formError.value = t('unit.at_least_one_unit_required'))

    const payload = buildPayload()
    try {
      if (isEdit.value) {
        await productStore.updateProductV2(form.value.id, payload)
        notif(t('products.messages.updated'), { type: 'success' })
      } else {
        await productStore.createProduct(payload)
        notif(t('products.messages.created'), { type: 'success' })
      }
      router.push({ name: 'Products' })
    } catch (error) {
      const raw =
        error?.response?.data?.message || error?.response?.data?.error || ''

      // Parse common DB errors into friendly messages
      if (raw.includes('product_units_barcode_unique')) {
        formError.value = t('products.errors.duplicateBarcode')
      } else if (
        raw.includes('Unique violation') ||
        raw.includes('unique constraint')
      ) {
        formError.value = t('products.errors.duplicateValue')
      } else {
        formError.value = t('products.errors.saveFailed')
      }

      notif(formError.value, { type: 'error' })
    }
  }

  const fetchUnitNames = async () => {
    loadingNames.value = true
    try {
      const res = await productUnitStore.fetchUnitName()
      dbUnits.value = (res.data.data ?? []).map(u => ({
        title: u.unit_name ?? u.title,
        qty_per_base: u.qty_per_base
      }))
    } catch {
      dbUnits.value = []
    } finally {
      loadingNames.value = false
    }
  }
  // ── Mount ──────────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await Promise.all([
      categoryStore.fetchCategories({ perPage: 1000 }),
      // /v1/tenants is superadmin-only — tenant-logged-in users get Forbidden
      isSuperAdmin() ? tenantStore.fetchTenants() : Promise.resolve(),
      fetchUnitNames()
    ])

    const id = route.params.id
    if (!id) return

    isEdit.value = true
    isLoadingEdit.value = true
    isHydrating.value = true

    try {
      const item = await productStore.fetchProductForEdit(id)
      hydrateForm(item)
    } catch {
      formError.value = t('products.errors.loadFailed')
    } finally {
      isLoadingEdit.value = false
      await nextTick()
      isHydrating.value = false
    }
  })
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

  /* 4-column grid: name | price | default | delete */
  .variants-grid {
    display: grid;
    grid-template-columns: 1fr 160px 56px 40px;
    gap: 8px;
    align-items: center;
  }

  .unit-row {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-surface-variant), 0.3);
    transition: border-color 0.2s;
  }

  .unit-row--base {
    border-color: rgb(var(--v-theme-indigo));
    background: rgba(var(--v-theme-indigo), 0.04);
  }
</style>
