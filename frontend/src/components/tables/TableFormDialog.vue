<template>
  <v-dialog v-model="model" max-width="520" persistent scrollable>
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="40"
              rounded="lg"
            >
              <v-icon :icon="isEdit ? 'mdi-pencil' : 'mdi-plus'" size="20" />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ isEdit ? 'Edit Table' : 'Add Table' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Configure table details
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <v-row dense>
            <!-- Branch -->
            <v-col cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches.data"
                item-value="id"
                item-title="name"
                label="Branch"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
                hint="Which branch is this table at?"
                persistent-hint
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.table_number"
                label="Table Number"
                placeholder="e.g. 1, A1, VIP-01"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-table-chair"
                maxlength="20"
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.capacity"
                type="number"
                label="Capacity (seats)"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required, r.positive]"
                prepend-inner-icon="mdi-account-group-outline"
                min="1"
                max="50"
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-select
                v-model="form.shape"
                :items="shapeOptions"
                item-title="label"
                item-value="value"
                label="Table Shape"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-shape-outline"
                clearable
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #prepend>
                      <v-icon :icon="item.raw.icon" size="18" class="mr-2" />
                    </template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>

            <v-col cols="12" sm="6">
              <v-select
                v-model="form.floor_plan_id"
                :items="floorPlans"
                item-title="name"
                item-value="id"
                label="Floor Plan"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-floor-plan"
                clearable
                hint="Which floor is this table on?"
                persistent-hint
              />
            </v-col>

            <!-- Position (only if floor plan selected) -->
            <template v-if="form.floor_plan_id">
              <v-col cols="12">
                <v-divider class="mb-2 mt-1" />
                <p class="text-caption text-medium-emphasis">
                  <v-icon
                    icon="mdi-map-marker-outline"
                    size="14"
                    class="mr-1"
                  />
                  Position on Floor Plan
                </p>
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="form.position_x"
                  type="number"
                  label="X Position"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-arrow-left-right"
                  min="0"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="form.position_y"
                  type="number"
                  label="Y Position"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-arrow-up-down"
                  min="0"
                />
              </v-col>
            </template>

            <v-col cols="12" sm="6">
              <v-select
                v-model="form.status"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Current Status"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-information-outline"
              />
            </v-col>

            <v-col cols="12" sm="6" class="d-flex align-center">
              <v-switch
                v-model="form.is_active"
                label="Active"
                color="success"
                inset
                hide-details
              />
            </v-col>

            <!-- Shape preview -->
            <v-col v-if="form.shape" cols="12">
              <v-card
                rounded="lg"
                border
                elevation="0"
                class="pa-4 d-flex align-center justify-center"
                color="grey-lighten-5"
                height="100"
              >
                <div
                  class="table-preview"
                  :class="`shape-${form.shape}`"
                  :style="{ background: statusBg(form.status) }"
                >
                  <span class="preview-number">
                    {{ form.table_number || '?' }}
                  </span>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-6 pt-4 gap-3">
        <v-btn
          block
          variant="tonal"
          rounded="lg"
          size="large"
          :disabled="loading"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          block
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          size="large"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Add Table' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useBranchStore } from '@/stores/branchStore'

  const props = defineProps({
    modelValue: Boolean,
    item: Object,
    floorPlans: { type: Array, default: () => [] },
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const branchStore = useBranchStore()
  const { branches } = storeToRefs(branchStore)

  const formRef = ref(null)
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  const shapeOptions = [
    { value: 'round', label: 'Round', icon: 'mdi-circle-outline' },
    { value: 'square', label: 'Square', icon: 'mdi-square-outline' },
    { value: 'rectangle', label: 'Rectangle', icon: 'mdi-rectangle-outline' },
    { value: 'bar', label: 'Bar', icon: 'mdi-minus' }
  ]

  const statusOptions = [
    { value: 'available', label: 'Available' },
    { value: 'occupied', label: 'Occupied' },
    { value: 'reserved', label: 'Reserved' },
    { value: 'cleaning', label: 'Cleaning' },
    { value: 'inactive', label: 'Inactive' }
  ]

  const defaultForm = () => ({
    branch_id: null,
    table_number: '',
    capacity: 4,
    shape: 'square',
    floor_plan_id: null,
    position_x: null,
    position_y: null,
    status: 'available',
    is_active: true
  })

  const form = reactive(defaultForm())

  watch(
    () => props.item,
    val => {
      Object.keys(form).forEach(k => delete form[k])
      Object.assign(form, val ? { ...val } : defaultForm())
    },
    { immediate: true }
  )

  const statusBg = s =>
    ({
      available: '#e8f5e9',
      occupied: '#ffebee',
      reserved: '#fff3e0',
      cleaning: '#e3f2fd',
      inactive: '#f5f5f5'
    })[s] || '#f5f5f5'

  const r = {
    required: v => !!v || v === 0 || 'Required',
    positive: v => !v || Number(v) > 0 || 'Must be at least 1'
  }

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    emit('update:modelValue', false)
  }

  onMounted(() => branchStore.fetchBranches?.())
</script>

<style scoped>
  .table-preview {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
  }
  .table-preview.shape-round {
    border-radius: 50%;
  }
  .table-preview.shape-square {
    border-radius: 8px;
  }
  .table-preview.shape-rectangle {
    border-radius: 8px;
    width: 96px;
    height: 56px;
  }
  .table-preview.shape-bar {
    border-radius: 4px;
    width: 110px;
    height: 40px;
  }
  .preview-number {
    font-weight: 700;
    font-size: 18px;
  }
</style>
