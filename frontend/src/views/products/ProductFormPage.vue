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
      <!-- ── Step indicator ─────────────────────────────────────────────── -->
      <div class="step-nav mb-6">
        <template v-for="(label, idx) in stepLabels" :key="idx">
          <button
            type="button"
            class="step-pill"
            :class="{
              'step-pill--active': step === idx + 1,
              'step-pill--done': step > idx + 1
            }"
            :disabled="idx + 1 > highestStepReached"
            @click="goToStep(idx + 1)"
          >
            <span class="step-pill__num">
              <v-icon v-if="step > idx + 1" icon="mdi-check" size="12" />
              <template v-else>{{ idx + 1 }}</template>
            </span>
            <span class="step-pill__label">{{ label }}</span>
          </button>
          <div
            v-if="idx < stepLabels.length - 1"
            class="step-pill__connector"
            :class="{ 'step-pill__connector--done': step > idx + 1 }"
          />
        </template>
      </div>

      <span
        v-if="formError"
        class="step-error text-body-small text-error d-flex align-center mb-4"
      >
        <v-icon icon="mdi-alert-circle-outline" size="14" class="mr-1" />
        {{ formError }}
      </span>

      <v-window v-model="step">
        <!-- ══════════════════════════════════════════════════════════════
             STEP 1 — BASICS
        ═══════════════════════════════════════════════════════════════════ -->
        <v-window-item :value="1">
          <v-form ref="step1Ref">
            <v-row>
              <!-- ── LEFT COLUMN ──────────────────────────────────────── -->
              <v-col cols="12" md="4" lg="3">
                <!-- ── Product Image ──────────────────────────────────── -->
                <ProductImageUpload
                  v-model:image-file="imageFile"
                  v-model:image-preview="imagePreview"
                  v-model:image-url="form.image_url"
                />
              </v-col>

              <!-- ── RIGHT COLUMN ─────────────────────────────────────── -->
              <v-col cols="12" md="8" lg="9">
                <!-- ── Core Info ──────────────────────────────────────── -->
                <v-card rounded="lg" border elevation="0" class="mb-4">
                  <v-card-text class="pa-5">
                    <div class="section-label mb-4">
                      <v-icon
                        icon="mdi-information-outline"
                        size="12"
                        class="mr-1"
                      />
                      {{ $t('products.cardTitle.coreInformation') }}
                    </div>
                    <v-row density="compact">
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.name"
                          :label="t('products.field.productName')"
                          variant="outlined"
                          rounded="lg"
                          :rules="[r.required, r.maxLen(200)]"
                          prepend-inner-icon="mdi-package-variant"
                          maxlength="200"
                          counter="200"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.name_km"
                          :label="t('products.field.productNameKm')"
                          variant="outlined"
                          rounded="lg"
                          hide-details="auto"
                          prepend-inner-icon="mdi-package-variant"
                          maxlength="200"
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
                          rounded="lg"
                          :rules="[r.required]"
                          prepend-inner-icon="mdi-tag-outline"
                        />
                      </v-col>
                      <v-col v-if="isFoodProduct" cols="12" sm="6">
                        <v-text-field
                          v-model.number="form.preparation_time"
                          :label="t('products.field.prepTime')"
                          type="number"
                          variant="outlined"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.nonNegativeInt]"
                          prepend-inner-icon="mdi-clock-outline"
                          suffix="min"
                          min="0"
                          clearable
                        />
                      </v-col>
                      <v-col cols="12">
                        <v-textarea
                          v-model="form.description"
                          :label="t('products.field.description')"
                          variant="outlined"
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

                <!-- ── Identifiers ────────────────────────────────────── -->
                <!-- SKU/barcode only apply to mart products — sort_order used
                     to live here too, now set via drag-reorder in the product
                     table instead of a manual number field. -->
                <v-card
                  v-if="isMartProduct"
                  rounded="lg"
                  border
                  elevation="0"
                  class="mb-4"
                >
                  <v-card-text class="pa-5">
                    <div class="section-label mb-4">
                      <v-icon icon="mdi-barcode-scan" size="12" class="mr-1" />
                      {{ $t('products.cardTitle.identifiers') }}
                    </div>
                    <v-row density="compact">
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.sku"
                          :label="t('products.field.sku')"
                          variant="outlined"
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
                          :label="t('products.field.barcode')"
                          variant="outlined"
                          rounded="lg"
                          hide-details="auto"
                          :rules="[r.maxLen(60)]"
                          prepend-inner-icon="mdi-barcode"
                          clearable
                        />
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-form>
        </v-window-item>

        <!-- ══════════════════════════════════════════════════════════════
             STEP 2 — PRICING/VARIANTS (food) OR INVENTORY/UNITS (mart)
        ═══════════════════════════════════════════════════════════════════ -->
        <v-window-item :value="2">
          <v-form ref="step2Ref">
            <!-- ══════ FOOD PRODUCT SECTIONS ══════ -->
            <template v-if="isFoodProduct && resolvedBuConfig">
              <!-- ── Kitchen Details ──────────────────────────────────── -->
              <!-- Prep Time moved to Core Information; calories removed
                   entirely. Only COFFEE_SHOP/BAKERY have anything left to
                   show here, so the card itself is gated on those too. -->
              <v-card
                v-if="
                  resolvedBuCode === 'COFFEE_SHOP' ||
                  resolvedBuCode === 'BAKERY'
                "
                rounded="lg"
                border
                elevation="0"
                class="mb-4"
              >
                <v-card-text class="pa-5">
                  <div class="section-label mb-4">
                    <v-icon
                      icon="mdi-silverware-fork-knife"
                      size="12"
                      class="mr-1"
                    />
                    {{ $t('products.cardTitle.kitchenDetails') }}
                  </div>
                  <v-row density="compact">
                    <template v-if="resolvedBuCode === 'COFFEE_SHOP'">
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.cup_sizes"
                          :items="cupSizeOptions"
                          :label="t('products.field.availableSizes')"
                          variant="outlined"
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
              <v-card rounded="lg" border elevation="0" class="mb-4">
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

                  <v-row density="compact" class="mb-2">
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model.number="form.base_price"
                        :label="t('products.field.basePrice')"
                        type="number"
                        variant="outlined"
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
                        <div class="text-body-medium font-weight-medium">
                          {{ $t('products.variant.sizeTempMatrixTitle') }}
                        </div>
                        <div class="text-body-small text-grey">
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
                      <div class="text-body-small text-grey font-weight-medium">
                        {{ $t('products.variant.sizeTemp') }}
                      </div>
                      <div
                        class="text-body-small text-grey font-weight-medium text-center"
                      >
                        {{ $t('products.variant.price') }}
                      </div>
                      <div
                        class="text-body-small text-grey font-weight-medium text-center"
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
                        variant="outlined"
                        rounded="lg"
                        hide-details
                        readonly
                      />
                      <v-text-field
                        v-model.number="variant.price_adjustment"
                        type="number"
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
                        <div class="text-body-medium font-weight-medium">
                          {{ $t('products.variant.optionVariants') }}
                        </div>
                        <div class="text-body-small text-grey">
                          {{ $t('products.variant.optionVariantsHint') }}
                        </div>
                      </div>
                    </div>

                    <div v-if="form.variants.length" class="variants-grid mb-2">
                      <div class="text-body-small text-grey font-weight-medium">
                        {{ $t('products.variant.name') }}
                      </div>
                      <div
                        class="text-body-small text-grey font-weight-medium text-center"
                      >
                        {{ $t('products.variant.sellingPrice') }}
                      </div>
                      <div
                        class="text-body-small text-grey font-weight-medium text-center"
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
                        :placeholder="
                          $t('products.variant.freeformNamePlaceholder')
                        "
                        variant="outlined"
                        rounded="lg"
                        hide-details
                        :rules="[r.required]"
                      />
                      <v-text-field
                        v-model.number="variant.price_adjustment"
                        type="number"
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
                    <span class="text-body-small">
                      {{ $t('products.variant.noVariantsHint') }}
                    </span>
                  </v-alert>
                </v-card-text>
              </v-card>
            </template>

            <!-- ══════ MART PRODUCT SECTIONS ══════ -->
            <template v-if="isMartProduct && resolvedBuConfig">
              <!-- ── Inventory & Stock ────────────────────────────────── -->
              <v-card rounded="lg" border elevation="0" class="mb-4">
                <v-card-text class="pa-5">
                  <div class="section-label mb-4">
                    <v-icon icon="mdi-warehouse" size="12" class="mr-1" />
                    {{ $t('products.cardTitle.inventoryAndStock') }}
                  </div>
                  <v-row density="compact">
                    <v-col cols="12" sm="4">
                      <v-text-field
                        v-model.number="form.stock_quantity"
                        :label="t('products.field.stockQuantity')"
                        type="number"
                        variant="outlined"
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
                      <AppDatePicker
                        v-model="form.expiry_date"
                        :label="t('products.field.expiryDate')"
                      />
                    </v-col>

                    <template v-if="resolvedBuCode === 'WHOLESALE'">
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.supplier_code"
                          :label="t('products.field.supplierCode')"
                          variant="outlined"
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
              <v-card rounded="lg" border elevation="0" class="mb-4">
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
                  <div class="text-body-small text-grey mb-4">
                    {{ $t('unit.subtitle_add') }}
                  </div>

                  <div
                    v-for="(unit, i) in form.units"
                    :key="`unit-${i}`"
                    class="unit-row mb-4 pa-4 rounded-lg"
                    :class="unit.is_base_unit ? 'unit-row--base' : ''"
                  >
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div
                        class="d-flex align-center flex-grow-1"
                        style="gap: 8px; cursor: pointer; min-width: 0"
                        @click="toggleUnitCollapsed(i)"
                      >
                        <v-icon
                          :icon="
                            isUnitCollapsed(i)
                              ? 'mdi-chevron-right'
                              : 'mdi-chevron-down'
                          "
                          size="18"
                          color="grey"
                        />
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
                        <span class="text-body-medium font-weight-medium">
                          {{
                            unit.unit_name ||
                            t('unit.unit_fallback_name', { n: i + 1 })
                          }}
                        </span>
                        <span
                          v-if="isUnitCollapsed(i)"
                          class="text-body-small text-grey text-truncate"
                        >
                          · {{ formatBase(unit.retail_price ?? 0) }} · ×{{
                            unit.qty_per_base
                          }}
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

                    <v-row v-if="!isUnitCollapsed(i)" density="compact">
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
                              :subtitle="item?.subtitle"
                            >
                              <template #append>
                                <v-chip
                                  v-if="item?.source === 'db'"
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
                              <v-list-item-title class="text-body-small">
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
                    class="text-body-small text-error mt-2"
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
              rounded="lg"
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
              <span class="text-body-small">
                <span v-if="isFoodProduct">{{ t('products.alert.food') }}</span>
                <span v-else>{{ t('products.alert.retail') }}</span>
              </span>
            </v-alert>
          </v-form>
        </v-window-item>

        <!-- ══════════════════════════════════════════════════════════════
             STEP 3 — REVIEW
        ═══════════════════════════════════════════════════════════════════ -->
        <v-window-item :value="3">
          <v-row>
            <v-col cols="12" md="8" lg="9" order="2" order-md="1">
              <!-- ── Basics summary ───────────────────────────────────── -->
              <v-card rounded="lg" border elevation="0" class="mb-4">
                <v-card-text class="pa-5">
                  <div class="d-flex align-center justify-space-between mb-4">
                    <div class="section-label">
                      <v-icon
                        icon="mdi-information-outline"
                        size="12"
                        class="mr-1"
                      />
                      {{ $t('products.cardTitle.coreInformation') }}
                    </div>
                    <v-btn
                      size="x-small"
                      variant="text"
                      color="primary"
                      @click="goToStep(1)"
                    >
                      {{ t('products.review.edit') }}
                    </v-btn>
                  </div>
                  <div class="d-flex flex-wrap" style="gap: 20px">
                    <div class="review-field">
                      <div class="text-body-small text-grey">
                        {{ t('products.field.productName') }}
                      </div>
                      <div class="text-body-large font-weight-medium">
                        {{ form.name || '—' }}
                      </div>
                    </div>
                    <div v-if="form.name_km" class="review-field">
                      <div class="text-body-small text-grey">
                        {{ t('products.field.productNameKm') }}
                      </div>
                      <div class="text-body-large font-weight-medium">
                        {{ form.name_km }}
                      </div>
                    </div>
                    <div class="review-field">
                      <div class="text-body-small text-grey">
                        {{ t('products.field.category') }}
                      </div>
                      <div class="text-body-large font-weight-medium">
                        {{ selectedCategoryName }}
                      </div>
                    </div>
                    <div v-if="isMartProduct && form.sku" class="review-field">
                      <div class="text-body-small text-grey">
                        {{ t('products.field.sku') }}
                      </div>
                      <div class="text-body-large font-weight-medium">
                        {{ form.sku }}
                      </div>
                    </div>
                  </div>
                  <div
                    v-if="form.description"
                    class="review-description mt-4 pa-3 rounded-lg text-body-medium text-grey"
                  >
                    {{ form.description }}
                  </div>
                </v-card-text>
              </v-card>

              <!-- ── Food pricing summary ─────────────────────────────── -->
              <v-card
                v-if="isFoodProduct"
                rounded="lg"
                border
                elevation="0"
                class="mb-4"
              >
                <v-card-text class="pa-5">
                  <div class="d-flex align-center justify-space-between mb-4">
                    <div class="section-label">
                      <v-icon icon="mdi-currency-usd" size="12" class="mr-1" />
                      {{ $t('products.cardTitle.pricingAndVariants') }}
                    </div>
                    <v-btn
                      size="x-small"
                      variant="text"
                      color="primary"
                      @click="goToStep(2)"
                    >
                      {{ t('products.review.edit') }}
                    </v-btn>
                  </div>
                  <div class="d-flex flex-wrap" style="gap: 20px">
                    <div class="review-field">
                      <div class="text-body-small text-grey">
                        {{ t('products.field.basePrice') }}
                      </div>
                      <div class="text-body-large font-weight-medium">
                        {{ formatBase(form.base_price ?? 0) }}
                      </div>
                    </div>
                    <div v-if="form.cost_price" class="review-field">
                      <div class="text-body-small text-grey">
                        {{ t('products.field.costPrice') }}
                      </div>
                      <div class="text-body-large font-weight-medium">
                        {{ formatBase(form.cost_price) }}
                      </div>
                    </div>
                  </div>
                  <div v-if="form.variants.length" class="mt-3">
                    <div class="text-body-small text-grey mb-1">
                      {{
                        t(
                          'products.variant.variantCount',
                          form.variants.length,
                          {
                            count: form.variants.length
                          }
                        )
                      }}
                    </div>
                    <div class="d-flex flex-wrap" style="gap: 8px">
                      <v-chip
                        v-for="(v, i) in form.variants"
                        :key="`review-variant-${i}`"
                        size="small"
                        :color="v.is_default ? 'primary' : undefined"
                        :variant="v.is_default ? 'flat' : 'tonal'"
                        label
                      >
                        {{ v.name || '—' }} ·
                        {{ formatBase(v.price_adjustment ?? 0) }}
                      </v-chip>
                    </div>
                  </div>
                </v-card-text>
              </v-card>

              <!-- ── Mart units summary ────────────────────────────────── -->
              <v-card
                v-if="isMartProduct"
                rounded="lg"
                border
                elevation="0"
                class="mb-4"
              >
                <v-card-text class="pa-5">
                  <div class="d-flex align-center justify-space-between mb-4">
                    <div class="section-label">
                      <v-icon
                        icon="mdi-layers-triple-outline"
                        size="12"
                        class="mr-1"
                      />
                      {{ $t('unit.title') }}
                    </div>
                    <v-btn
                      size="x-small"
                      variant="text"
                      color="primary"
                      @click="goToStep(2)"
                    >
                      {{ t('products.review.edit') }}
                    </v-btn>
                  </div>
                  <div class="text-body-small text-grey mb-3">
                    {{
                      t('products.review.stockSummary', {
                        qty: form.stock_quantity ?? 0
                      })
                    }}
                  </div>
                  <div class="d-flex flex-column" style="gap: 10px">
                    <div
                      v-for="(unit, i) in form.units"
                      :key="`review-unit-${i}`"
                      class="d-flex align-center justify-space-between pa-3 rounded-lg review-unit-row"
                    >
                      <div class="d-flex align-center" style="gap: 10px">
                        <v-avatar size="28" rounded="lg" color="indigo-lighten-5">
                          <v-icon
                            icon="mdi-cube-outline"
                            size="15"
                            color="indigo"
                          />
                        </v-avatar>
                        <div>
                          <div class="text-body-medium font-weight-medium">
                            {{ unit.unit_name || '—' }}
                          </div>
                          <v-chip
                            v-if="unit.is_base_unit"
                            size="x-small"
                            color="indigo"
                            label
                            variant="tonal"
                          >
                            {{ $t('unit.base_unit_chip') }}
                          </v-chip>
                        </div>
                      </div>
                      <span class="text-body-medium font-weight-bold">
                        {{ formatBase(unit.retail_price ?? 0) }}
                      </span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>

            <!-- ── Live preview card ─────────────────────────────────── -->
            <v-col cols="12" md="4" lg="3" order="1" order-md="2">
              <v-card
                rounded="lg"
                border
                elevation="0"
                class="mb-4 review-preview-card"
              >
                <v-img
                  v-if="imagePreview"
                  :src="imagePreview"
                  height="160"
                  cover
                  class="rounded-t-lg"
                />
                <div v-else class="review-preview-placeholder">
                  <v-icon icon="mdi-image-outline" size="28" color="grey" />
                  <span class="text-body-small text-grey mt-1">
                    {{ t('products.review.noImage') }}
                  </span>
                </div>
                <v-card-text class="pa-4 text-center">
                  <div class="text-body-large font-weight-bold">
                    {{ form.name || t('products.newTitle') }}
                  </div>
                  <div class="text-body-small text-grey">
                    {{ selectedCategoryName }}
                  </div>
                </v-card-text>
              </v-card>
              <v-alert
                variant="tonal"
                color="primary"
                rounded="lg"
                density="compact"
              >
                <span class="text-body-small">
                  {{ t('products.review.confirmHint') }}
                </span>
              </v-alert>
            </v-col>
          </v-row>
        </v-window-item>
      </v-window>

      <!-- ── Footer nav ─────────────────────────────────────────────────── -->
      <div class="d-flex align-center justify-end mt-4" style="gap: 12px">
        <v-btn v-if="step === 1" variant="tonal" rounded="lg" :to="'/products'">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn v-else variant="tonal" rounded="lg" @click="goBack">
          {{ t('btn.back') }}
        </v-btn>
        <v-btn
          v-if="step < 3"
          color="primary"
          variant="flat"
          rounded="lg"
          append-icon="mdi-arrow-right"
          @click="goNext"
        >
          {{ t('btn.next') }}
        </v-btn>
        <v-btn
          v-else
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
    </div>
  </v-container>
</template>

<script setup>
  import { ref, computed, watch, onMounted, nextTick } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { storeToRefs } from 'pinia'
  import { useAppUtils, AppDatePicker } from '@nong-official-dev/core'
  import { useAuthStore } from '@/stores/authStore'
  import { useProductStore } from '@/stores/productStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { BUSINESS_TYPES } from '@/constants/businessTypes'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import ProductImageUpload from '@/components/products/ProductImageUpload.vue'
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
  const productUnitStore = useProductUnitStore()
  const { categories } = storeToRefs(categoryStore)

  // ── Composables ────────────────────────────────────────────────────────────────
  const { t } = useI18n()
  const { notif } = useAppUtils()
  const { currencySymbol, formatBase } = useCurrency()

  // ── State ──────────────────────────────────────────────────────────────────────
  const formError = ref('')
  const imagePreview = ref(null)
  const imageFile = ref(null)
  const isEdit = ref(false)
  const isLoadingEdit = ref(false)
  const isHydrating = ref(false)
  const dbUnits = ref([])
  const loadingNames = ref(false)

  // ── Stepper ────────────────────────────────────────────────────────────────────
  const step = ref(1)
  const highestStepReached = ref(1)
  const step1Ref = ref(null)
  const step2Ref = ref(null)

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
    category_id: null,
    sku: null,
    barcode: '',
    name: '',
    name_km: '',
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
  const resolvedBuCode = computed(
    () => authStore.bu_type?.toUpperCase() ?? 'RESTAURANT'
  )

  const resolvedBuConfig = computed(() =>
    resolvedBuCode.value ? (BUSINESS_TYPES[resolvedBuCode.value] ?? null) : null
  )

  // Dynamic (backend business_types.category), not the static icon/color
  // registry — this is the current tenant only (create/edit form), so
  // authStore.bu_category is always the right source.
  const productNature = computed(() => authStore.bu_category ?? 'food')
  const isFoodProduct = computed(() => productNature.value === 'food')
  const isMartProduct = computed(() => productNature.value === 'mart')

  // ── Stepper labels & navigation ─────────────────────────────────────────────────
  const stepLabels = computed(() => [
    t('products.step.basics'),
    isFoodProduct.value
      ? t('products.step.pricingVariants')
      : t('products.step.inventoryUnits'),
    t('products.step.review')
  ])

  const selectedCategoryName = computed(
    () =>
      categories.value.find(c => c.id === form.value.category_id)?.name ?? '—'
  )

  function goToStep(n) {
    if (n <= highestStepReached.value) step.value = n
  }

  function goBack() {
    formError.value = ''
    step.value -= 1
  }

  async function goNext() {
    formError.value = ''
    const activeFormRef = step.value === 1 ? step1Ref : step2Ref
    const { valid } = await activeFormRef.value.validate()
    if (!valid) {
      formError.value = t('products.errors.fixBeforeSaving')
      return
    }
    if (
      step.value === 2 &&
      isMartProduct.value &&
      form.value.units.length === 0
    ) {
      formError.value = t('unit.at_least_one_unit_required')
      return
    }
    step.value += 1
    if (step.value > highestStepReached.value)
      highestStepReached.value = step.value
  }

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

  // ── Unit row collapse (purely UI state, never sent to the backend) ─────────
  const collapsedUnits = ref(new Set())

  const isUnitCollapsed = i => collapsedUnits.value.has(i)

  const toggleUnitCollapsed = i => {
    const next = new Set(collapsedUnits.value)
    if (next.has(i)) next.delete(i)
    else next.add(i)
    collapsedUnits.value = next
  }

  const removeUnit = i => {
    const wasBase = form.value.units[i]?.is_base_unit
    form.value.units.splice(i, 1)
    if (wasBase && form.value.units.length > 0) {
      form.value.units[0].is_base_unit = true
      form.value.units[0].qty_per_base = 1
    }
    // Re-key collapsed-row indices since the splice shifts everything above i down by one
    const next = new Set()
    collapsedUnits.value.forEach(idx => {
      if (idx === i) return
      next.add(idx > i ? idx - 1 : idx)
    })
    collapsedUnits.value = next
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
    step.value = 1
    highestStepReached.value = 1
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
    maxLen: n => v => !v || v.length <= n || t('products.rule.maxLen', { n })
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
      // products.base_price is NOT NULL (default 0.00) — unlike cost_price,
      // sending null here 500s the update. Mart products don't price via
      // base_price (they use per-unit retail_price), so 0 is the correct
      // "unused" value, not null.
      base_price: food ? form.value.base_price : 0,
      cost_price: food ? form.value.cost_price : null,
      variants: food ? form.value.variants : [],
      preparation_time: food ? form.value.preparation_time : null,
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
    const [{ valid: step1Valid }, { valid: step2Valid }] = await Promise.all([
      step1Ref.value.validate(),
      step2Ref.value.validate()
    ])
    if (!step1Valid || !step2Valid) {
      formError.value = t('products.errors.fixBeforeSaving')
      step.value = !step1Valid ? 1 : 2
      return
    }
    if (isMartProduct.value && form.value.units.length === 0) {
      formError.value = t('unit.at_least_one_unit_required')
      step.value = 2
      return
    }

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
      categoryStore.fetchCategories({ perPage: -1 }),
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
      // Editing an already-saved product — every step is already filled in,
      // so let the user jump straight to any step instead of re-walking Next.
      highestStepReached.value = 3
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

  /* ── Step indicator ──────────────────────────────────────────────────── */
  .step-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-x: auto;
    padding-bottom: 2px;
  }

  .step-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 8px;
    flex-shrink: 0;
  }

  .step-pill:disabled {
    cursor: default;
    opacity: 0.5;
  }

  .step-pill__num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    background: rgba(var(--v-theme-on-surface), 0.08);
    color: rgba(var(--v-theme-on-surface), 0.6);
    flex-shrink: 0;
  }

  .step-pill--active .step-pill__num {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
  }

  .step-pill--done .step-pill__num {
    background: rgb(var(--v-theme-success));
    color: white;
  }

  .step-pill--active .step-pill__label {
    font-weight: 700;
    color: rgb(var(--v-theme-primary));
  }

  .step-pill__label {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.7);
    white-space: nowrap;
  }

  .step-pill__connector {
    width: 40px;
    height: 2px;
    background: rgba(var(--v-theme-on-surface), 0.12);
    margin: 0 4px;
    flex-shrink: 0;
  }

  .step-pill__connector--done {
    background: rgb(var(--v-theme-success));
  }

  .step-error {
    justify-content: center;
  }

  /* ── Review step ─────────────────────────────────────────────────────── */
  .review-field {
    flex: 0 1 180px;
  }

  .review-description {
    background: rgba(var(--v-theme-surface-variant), 0.3);
    line-height: 1.5;
  }

  .review-unit-row {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
  }

  .review-preview-card {
    position: sticky;
    overflow: hidden;
  }

  .review-preview-placeholder {
    height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(var(--v-theme-surface-variant), 0.3);
  }
</style>
