<template>
  <v-dialog v-model="model" max-width="560" persistent scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- Header -->
      <v-card-title class="pa-5 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="42"
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
                {{ isEdit ? 'Edit Stock' : 'Add Stock' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? 'Update quantity and stock info'
                    : 'Add ingredient stock for a branch'
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-0" style="max-height: 65vh">
        <v-form ref="formRef">
          <!-- Location -->
          <div class="form-section">
            <div class="form-section-label">
              <v-icon icon="mdi-map-marker-outline" size="13" class="mr-1" />
              Location & Ingredient
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.branch_id"
                  :items="branchStore.branches?.data ?? []"
                  item-value="id"
                  item-title="name"
                  label="Branch *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[r.required]"
                  prepend-inner-icon="mdi-store-outline"
                  :disabled="isEdit"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-autocomplete
                  v-model="form.ingredient_id"
                  :items="ingredientOptions"
                  item-value="id"
                  item-title="name"
                  label="Ingredient *"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="[r.required]"
                  prepend-inner-icon="mdi-food-variant"
                  :disabled="isEdit"
                >
                  <template #item="{ props: p, item }">
                    <v-list-item v-bind="p" :subtitle="item.raw?.unit ?? ''">
                      <template #append>
                        <v-chip size="x-small" variant="tonal">
                          {{ item.raw?.category }}
                        </v-chip>
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Stock Quantities -->
          <div class="form-section">
            <div class="form-section-label">
              <v-icon
                icon="mdi-package-variant-closed"
                size="13"
                class="mr-1"
              />
              Stock Quantities
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="form.quantity_on_hand"
                  type="number"
                  label="Quantity on Hand *"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="[r.required, r.nonNegative]"
                  prepend-inner-icon="mdi-numeric"
                  :suffix="selectedIngredient?.unit ?? ''"
                  min="0"
                  step="0.001"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="form.quantity_reserved"
                  type="number"
                  label="Quantity Reserved"
                  variant="outlined"
                  rounded="lg"
                  :rules="[r.nonNegative]"
                  prepend-inner-icon="mdi-lock-outline"
                  :suffix="selectedIngredient?.unit ?? ''"
                  min="0"
                  step="0.001"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.last_counted_at"
                  type="datetime-local"
                  label="Last Counted At"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  prepend-inner-icon="mdi-calendar-check-outline"
                />
              </v-col>

              <!-- Ingredient info readonly (context only) -->
              <v-col v-if="selectedIngredient" cols="12">
                <v-card
                  rounded="lg"
                  color="grey-lighten-5"
                  border
                  elevation="0"
                  class="pa-3"
                >
                  <div class="d-flex gap-4 flex-wrap">
                    <div>
                      <div class="text-caption text-grey">Unit Cost</div>
                      <div class="text-body-2 font-weight-medium">
                        {{
                          selectedIngredient.unit_cost
                            ? `$${selectedIngredient.unit_cost}`
                            : '—'
                        }}
                      </div>
                    </div>
                    <div>
                      <div class="text-caption text-grey">Reorder Point</div>
                      <div class="text-body-2 font-weight-medium">
                        {{ selectedIngredient.reorder_point ?? '—' }}
                        {{ selectedIngredient.unit }}
                      </div>
                    </div>
                    <div>
                      <div class="text-caption text-grey">Reorder Qty</div>
                      <div class="text-body-2 font-weight-medium">
                        {{ selectedIngredient.reorder_quantity ?? '—' }}
                        {{ selectedIngredient.unit }}
                      </div>
                    </div>
                    <div>
                      <div class="text-caption text-grey">Supplier</div>
                      <div class="text-body-2 font-weight-medium">
                        {{ selectedIngredient.preferred_supplier?.name ?? '—' }}
                      </div>
                    </div>
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4 gap-2">
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-plus'"
          @click="save"
        >
          {{ isEdit ? 'Save Changes' : 'Add Stock' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { useIngredientStore } from '@/stores/ingredientStore'

  const props = defineProps({
    modelValue: Boolean,
    stock: { type: Object, default: null },
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)
  const branchStore = useBranchStore()
  const ingredientStore = useIngredientStore()

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.stock?.id)

  // Ingredient list for autocomplete
  const ingredientOptions = computed(
    () => ingredientStore.ingredients?.data ?? ingredientStore.ingredients ?? []
  )

  // Show ingredient context info
  const selectedIngredient = computed(
    () => ingredientOptions.value.find(i => i.id === form.ingredient_id) ?? null
  )

  // ── Default form — maps to inventory_stock schema ──────────────────────────────
  const defaultForm = () => ({
    branch_id: null,
    ingredient_id: null,
    quantity_on_hand: 0,
    quantity_reserved: 0,
    last_counted_at: null
  })

  const form = reactive(defaultForm())

  watch(
    () => props.stock,
    val => {
      Object.assign(
        form,
        val
          ? {
              branch_id: val.branch_id,
              ingredient_id: val.ingredient_id,
              quantity_on_hand: parseFloat(val.quantity_on_hand),
              quantity_reserved: parseFloat(val.quantity_reserved),
              last_counted_at: val.last_counted_at
                ? new Date(val.last_counted_at).toISOString().slice(0, 16)
                : null
            }
          : defaultForm()
      )
    },
    { immediate: true }
  )

  // ── Rules ──────────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || v === 0 || 'Required',
    nonNegative: v => !v || parseFloat(v) >= 0 || 'Must be 0 or more'
  }

  // ── Submit ─────────────────────────────────────────────────────────────────────
  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', {
      ...(props.stock?.id ? { id: props.stock.id } : {}),
      ...form
    })
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
    Object.assign(form, defaultForm())
  }

  onMounted(() => {
    branchStore.fetchBranches()
    ingredientStore.fetchIngredients?.()
  })
</script>

<style scoped>
  .form-section {
    padding: 16px 20px;
  }
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
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .gap-4 {
    gap: 16px;
  }
</style>
