<template>
  <!-- ── Product Modifier Groups Assignment Panel ──────────────────────────── -->
  <v-card rounded="xl" elevation="0" border>
    <v-card-title class="pa-5 pb-4">
      <div class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <v-avatar color="secondary" size="36" rounded="lg">
            <v-icon icon="mdi-tune-variant" size="18" />
          </v-avatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold">Modifier Groups</div>
            <div class="text-caption text-medium-emphasis">Assign add-on groups to this product</div>
          </div>
        </div>
        <v-btn
          color="secondary"
          variant="tonal"
          size="small"
          rounded="lg"
          prepend-icon="mdi-link-variant-plus"
          @click="dialog = true"
        >
          Assign Group
        </v-btn>
      </div>
    </v-card-title>

    <v-divider />

    <!-- Assigned groups -->
    <v-list lines="two" class="pa-2">
      <v-list-item
        v-for="assignment in assigned"
        :key="assignment.modifier_group_id"
        rounded="lg"
        class="mb-1"
      >
        <template #prepend>
          <v-avatar
            :color="assignment.group.selection_type === 'single' ? 'primary' : 'secondary'"
            size="36"
            rounded="lg"
            class="mr-3"
          >
            <v-icon
              :icon="assignment.group.selection_type === 'single' ? 'mdi-radiobox-marked' : 'mdi-checkbox-marked'"
              size="18"
            />
          </v-avatar>
        </template>

        <v-list-item-title class="font-weight-medium">
          {{ assignment.group.name }}
          <v-chip
            v-if="assignment.group.is_required"
            size="x-small"
            color="error"
            variant="tonal"
            class="ml-2"
          >
            Required
          </v-chip>
        </v-list-item-title>
        <v-list-item-subtitle>
          <span class="text-capitalize">{{ assignment.group.selection_type }}</span>
          · {{ assignment.group.options?.length || 0 }} options
          · Sort: {{ assignment.sort_order }}
        </v-list-item-subtitle>

        <template #append>
          <div class="d-flex align-center gap-2">
            <!-- Quick sort_order edit -->
            <v-text-field
              v-model.number="assignment.sort_order"
              type="number"
              min="0"
              density="compact"
              variant="outlined"
              hide-details
              style="width: 72px"
              @change="emitUpdate(assignment)"
            />
            <v-btn
              icon="mdi-link-variant-off"
              size="small"
              variant="text"
              color="error"
              @click="confirmUnassign(assignment)"
            />
          </div>
        </template>
      </v-list-item>

      <div v-if="!assigned.length" class="text-center py-8 text-medium-emphasis">
        <v-icon icon="mdi-tune-variant" size="40" color="grey-lighten-2" />
        <p class="text-body-2 mt-2">No modifier groups assigned yet</p>
      </div>
    </v-list>

    <!-- ── Assign Dialog ─────────────────────────────────────────────────── -->
    <v-dialog v-model="dialog" max-width="520" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-title class="pa-6 pb-4">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-3">
              <v-avatar color="secondary" size="36" rounded="lg">
                <v-icon icon="mdi-link-variant-plus" size="18" />
              </v-avatar>
              <div>
                <div class="text-subtitle-1 font-weight-bold">Assign Modifier Group</div>
                <div class="text-caption text-medium-emphasis">{{ productName }}</div>
              </div>
            </div>
            <v-btn icon="mdi-close" size="small" variant="text" @click="dialog = false" />
          </div>
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-6">
          <v-form ref="formRef">
            <v-row dense>
              <!-- Group selector -->
              <v-col cols="12">
                <v-select
                  v-model="form.modifier_group_id"
                  :items="availableGroups"
                  item-title="name"
                  item-value="id"
                  label="Modifier Group"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tune"
                  :rules="[rules.required]"
                  hint="Only unassigned groups are shown"
                  persistent-hint
                  no-data-text="All groups are already assigned"
                >
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template #prepend>
                        <v-avatar
                          :color="item.raw.selection_type === 'single' ? 'primary' : 'secondary'"
                          size="30"
                          rounded="md"
                          class="mr-2"
                        >
                          <v-icon
                            :icon="item.raw.selection_type === 'single' ? 'mdi-radiobox-marked' : 'mdi-checkbox-marked'"
                            size="14"
                          />
                        </v-avatar>
                      </template>
                      <template #subtitle>
                        {{ item.raw.selection_type }} · {{ item.raw.options?.length || 0 }} options
                        <v-chip v-if="item.raw.is_required" size="x-small" color="error" variant="tonal" class="ml-1">required</v-chip>
                      </template>
                    </v-list-item>
                  </template>
                </v-select>
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
                  hint="Order shown on product page"
                  persistent-hint
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />
        <v-card-actions class="pa-6 pt-4 gap-3">
          <v-btn block variant="tonal" rounded="lg" size="large" @click="dialog = false">Cancel</v-btn>
          <v-btn
            block
            color="secondary"
            variant="flat"
            rounded="lg"
            size="large"
            prepend-icon="mdi-link-variant"
            @click="handleAssign"
          >
            Assign
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Unassign confirm -->
    <v-dialog v-model="unassignDialog" max-width="380">
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="52" rounded="lg" class="mb-3">
            <v-icon icon="mdi-link-variant-off" size="26" />
          </v-avatar>
          <h3 class="text-subtitle-1 font-weight-bold mb-1">Remove Modifier Group?</h3>
          <p class="text-body-2 text-medium-emphasis">
            Remove <strong>{{ unassignTarget?.group?.name }}</strong> from this product?
          </p>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0 gap-3">
          <v-btn block variant="tonal" rounded="lg" @click="unassignDialog = false">Cancel</v-btn>
          <v-btn block color="error" variant="flat" rounded="lg" @click="doUnassign">Remove</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue'

// ── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps({
  productId:      { type: String, required: true },
  productName:    { type: String, default: '' },
  assigned:       { type: Array, default: () => [] }, // [{ modifier_group_id, sort_order, group: {...} }]
  allGroups:      { type: Array, default: () => [] }, // all tenant modifier groups
})

const emit = defineEmits(['assigned', 'unassigned', 'updated'])

// ── Refs ──────────────────────────────────────────────────────────────────────
const dialog        = ref(false)
const formRef       = ref(null)
const unassignDialog = ref(false)
const unassignTarget = ref(null)

const form = ref({ modifier_group_id: null, sort_order: 0 })

// ── Computed ──────────────────────────────────────────────────────────────────
// Only show groups not yet assigned
const availableGroups = computed(() => {
  const assignedIds = props.assigned.map(a => a.modifier_group_id)
  return props.allGroups.filter(g => !assignedIds.includes(g.id))
})

// ── Rules ─────────────────────────────────────────────────────────────────────
const rules = { required: v => !!v || 'Required' }

// ── Actions ───────────────────────────────────────────────────────────────────
const handleAssign = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  emit('assigned', {
    product_id:        props.productId,
    modifier_group_id: form.value.modifier_group_id,
    sort_order:        form.value.sort_order ?? 0,
  })

  form.value = { modifier_group_id: null, sort_order: 0 }
  dialog.value = false
}

const emitUpdate = assignment => {
  emit('updated', {
    product_id:        props.productId,
    modifier_group_id: assignment.modifier_group_id,
    sort_order:        assignment.sort_order,
  })
}

const confirmUnassign = assignment => {
  unassignTarget.value = assignment
  unassignDialog.value = true
}

const doUnassign = () => {
  emit('unassigned', {
    product_id:        props.productId,
    modifier_group_id: unassignTarget.value.modifier_group_id,
  })
  unassignDialog.value = false
}
</script>