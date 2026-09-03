<template>
  <AppDialog
    v-model="model"
    :max-width="520"
    :title="isEdit ? $t('tables.dialog.edit_title') : $t('tables.dialog.add_title')"
    :loading="loading"
  >
    <v-form ref="formRef">
      <v-row dense>
            <!-- Branch -->
            <v-col cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches.data"
                item-value="id"
                item-title="name"
                :label="$t('form.branch')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
                :hint="$t('tables.fields.branch_hint')"
                persistent-hint
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.table_number"
                :label="$t('tables.fields.table_number')"
                :placeholder="$t('tables.fields.table_number_placeholder')"
                variant="outlined"
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
                :label="$t('tables.fields.capacity')"
                variant="outlined"
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
                :label="$t('tables.fields.shape')"
                variant="outlined"
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
                :label="$t('tables.fields.floor_plan')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-floor-plan"
                clearable
                :hint="$t('tables.fields.floor_plan_hint')"
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
                  {{ $t('tables.fields.position_title') }}
                </p>
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="form.position_x"
                  type="number"
                  :label="$t('tables.fields.position_x')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-arrow-left-right"
                  min="0"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model.number="form.position_y"
                  type="number"
                  :label="$t('tables.fields.position_y')"
                  variant="outlined"
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
                :label="$t('tables.fields.current_status')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-information-outline"
              />
            </v-col>

            <v-col cols="12" sm="6" class="d-flex align-center">
              <v-switch
                v-model="form.is_active"
                :label="$t('status.active')"
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

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
        :loading="loading"
        @click="submit"
      >
        {{ isEdit ? $t('btn.save_changes') : $t('tables.dialog.add_title') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { useBranchStore } from '@/stores/branchStore'

  const { t } = useI18n()

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

  const shapeOptions = computed(() => [
    { value: 'round', label: t('tables.shapes.round'), icon: 'mdi-circle-outline' },
    { value: 'square', label: t('tables.shapes.square'), icon: 'mdi-square-outline' },
    { value: 'rectangle', label: t('tables.shapes.rectangle'), icon: 'mdi-rectangle-outline' },
    { value: 'bar', label: t('tables.shapes.bar'), icon: 'mdi-minus' }
  ])

  const statusOptions = computed(() => [
    { value: 'available', label: t('tables.status.available') },
    { value: 'occupied', label: t('tables.status.occupied') },
    { value: 'reserved', label: t('tables.status.reserved') },
    { value: 'cleaning', label: t('tables.status.cleaning') },
    { value: 'inactive', label: t('status.inactive') }
  ])

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
    required: v => !!v || v === 0 || t('form.required'),
    positive: v => !v || Number(v) > 0 || t('tables.rules.min_one')
  }

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    model.value = false
  }

  watch(model, val => {
    if (!val) {
      formRef.value?.reset()
      Object.assign(form, defaultForm())
    }
  })

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
