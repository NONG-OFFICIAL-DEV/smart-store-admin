<script setup>
  import { computed } from 'vue'

  const props = defineProps({
    modelValue: Boolean,
    customer: { type: Object, default: null },
    addresses: { type: Array, default: () => [] }
  })

  const emit = defineEmits([
    'update:modelValue',
    'edit-customer',
    'add-address',
    'edit-address',
    'delete-address'
  ])

  const close = () => emit('update:modelValue', false)

  const initials = c =>
    c ? `${c.first_name?.[0] ?? ''}${c.last_name?.[0] ?? ''}`.toUpperCase() : ''
  const avatarColor = c => {
    if (!c) return 'brown-darken-2'
    const colors = [
      'brown-darken-2',
      'blue-darken-2',
      'teal-darken-2',
      'purple-darken-2',
      'orange-darken-2',
      'green-darken-2'
    ]
    return colors[(c.first_name?.charCodeAt(0) ?? 0) % colors.length]
  }

  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString('en-GB', {
          day: '2-digit',
          month: 'short',
          year: 'numeric'
        })
      : '—'

  const defaultAddress = computed(() => props.addresses.find(a => a.is_default))
  const otherAddresses = computed(() =>
    props.addresses.filter(a => !a.is_default)
  )
</script>

<template>
  <v-navigation-drawer
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    location="end"
    width="380"
    temporary
    class="pa-0"
  >
    <template v-if="customer">
      <!-- ── Header ─────────────────────────────────────── -->
      <div class="pa-5 bg-brown-darken-4">
        <div class="d-flex align-center justify-space-between mb-4">
          <span
            class="text-caption font-weight-bold text-white opacity-70 text-uppercase"
            style="letter-spacing: 0.08em"
          >
            Customer Profile
          </span>
          <v-btn
            icon="mdi-close"
            variant="text"
            color="white"
            size="small"
            @click="close"
          />
        </div>

        <div class="d-flex align-center gap-4">
          <v-avatar
            :color="avatarColor(customer)"
            size="56"
            class="border-lg border-white me-3"
          >
            <v-img v-if="customer.avatar_url" :src="customer.avatar_url" />
            <span v-else class="text-h6 font-weight-black text-white">
              {{ initials(customer) }}
            </span>
          </v-avatar>
          <div>
            <p class="text-h6 font-weight-black text-white mb-0">
              {{ customer.first_name }} {{ customer.last_name }}
            </p>
            <p class="text-caption text-white opacity-70 mb-0">
              {{ customer.email ?? '—' }}
            </p>
            <p class="text-caption text-white opacity-70">
              {{ customer.phone ?? '—' }}
            </p>
          </div>
        </div>

        <!-- Quick stats -->
        <v-row
          no-gutters
          class="mt-4 rounded-xl overflow-hidden"
          style="background: rgba(255, 255, 255, 0.1)"
        >
          <v-col
            cols="4"
            class="pa-3 text-center"
            style="border-right: 1px solid rgba(255, 255, 255, 0.15)"
          >
            <p class="text-h6 font-weight-black text-white mb-0">
              {{ customer.total_orders ?? 0 }}
            </p>
            <p class="text-caption text-white opacity-60 mb-0">Orders</p>
          </v-col>
          <v-col
            cols="4"
            class="pa-3 text-center"
            style="border-right: 1px solid rgba(255, 255, 255, 0.15)"
          >
            <p class="text-h6 font-weight-black text-white mb-0">
              ${{ Number(customer.total_spent ?? 0).toFixed(0) }}
            </p>
            <p class="text-caption text-white opacity-60 mb-0">Spent</p>
          </v-col>
          <v-col cols="4" class="pa-3 text-center">
            <p class="text-h6 font-weight-black text-amber-accent-2 mb-0">
              {{ customer.loyalty_points ?? 0 }}
            </p>
            <p class="text-caption text-white opacity-60 mb-0">Points</p>
          </v-col>
        </v-row>
      </div>

      <!-- ── Body ───────────────────────────────────────── -->
      <div class="pa-5 overflow-y-auto" style="height: calc(100% - 220px)">
        <!-- Details section -->
        <div class="d-flex align-center justify-space-between mb-3">
          <p
            class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-0"
            style="letter-spacing: 0.08em"
          >
            Details
          </p>
          <v-btn
            size="x-small"
            variant="tonal"
            color="brown-darken-3"
            rounded="xl"
            prepend-icon="mdi-pencil-outline"
            @click="emit('edit-customer', customer)"
          >
            Edit
          </v-btn>
        </div>

        <v-list density="compact" class="pa-0 mb-5">
          <v-list-item
            v-for="row in [
              {
                icon: 'mdi-cake-variant-outline',
                label: 'Birthday',
                value: formatDate(customer.date_of_birth)
              },
              {
                icon: 'mdi-gender-male-female',
                label: 'Gender',
                value: customer.gender ?? '—'
              },
              {
                icon: 'mdi-translate',
                label: 'Language',
                value: customer.preferred_language ?? '—'
              },
              {
                icon: 'mdi-source-branch',
                label: 'Source',
                value: customer.source ?? '—'
              }
            ]"
            :key="row.label"
            class="px-0"
          >
            <template #prepend>
              <v-icon
                :icon="row.icon"
                size="16"
                color="brown-lighten-1"
                class="mr-3"
              />
            </template>
            <v-list-item-title class="text-caption text-medium-emphasis">
              {{ row.label }}
            </v-list-item-title>
            <template #append>
              <span class="text-body-2 font-weight-medium text-capitalize">
                {{ row.value }}
              </span>
            </template>
          </v-list-item>
        </v-list>

        <!-- Flags -->
        <div class="d-flex gap-2 mb-5">
          <v-chip
            :color="customer.is_active ? 'success' : 'grey'"
            size="small"
            label
          >
            <v-icon start size="14">
              {{ customer.is_active ? 'mdi-check-circle' : 'mdi-cancel' }}
            </v-icon>
            {{ customer.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
          <v-chip
            :color="customer.marketing_opt_in ? 'blue' : 'grey'"
            size="small"
            label
          >
            <v-icon start size="14">mdi-email-newsletter</v-icon>
            {{ customer.marketing_opt_in ? 'Marketing On' : 'Marketing Off' }}
          </v-chip>
        </div>

        <!-- Notes -->
        <div v-if="customer.notes" class="mb-5">
          <p
            class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2"
            style="letter-spacing: 0.08em"
          >
            Notes
          </p>
          <v-card flat color="brown-lighten-5" rounded="xl" class="pa-3">
            <p class="text-body-2 text-medium-emphasis mb-0">
              {{ customer.notes }}
            </p>
          </v-card>
        </div>

        <!-- Addresses -->
        <div class="d-flex align-center justify-space-between mb-3">
          <p
            class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-0"
            style="letter-spacing: 0.08em"
          >
            Addresses
          </p>
          <v-btn
            size="x-small"
            variant="tonal"
            color="brown-darken-3"
            rounded="xl"
            prepend-icon="mdi-plus"
            @click="emit('add-address')"
          >
            Add
          </v-btn>
        </div>

        <div v-if="!addresses.length" class="text-center py-4">
          <v-icon
            icon="mdi-map-marker-off-outline"
            size="32"
            color="brown-lighten-3"
            class="mb-2"
          />
          <p class="text-caption text-medium-emphasis">No addresses yet</p>
        </div>

        <div v-else class="d-flex flex-column gap-3">
          <!-- Default first -->
          <v-card
            v-for="addr in [
              ...(defaultAddress ? [defaultAddress] : []),
              ...otherAddresses
            ]"
            :key="addr.id"
            flat
            rounded="xl"
            :color="addr.is_default ? 'brown-lighten-5' : 'grey-lighten-5'"
            :class="addr.is_default ? 'border border-brown-lighten-2' : ''"
          >
            <v-card-text class="pa-3">
              <div class="d-flex align-start justify-space-between">
                <div class="flex-grow-1">
                  <div class="d-flex align-center gap-2 mb-1">
                    <v-icon
                      icon="mdi-map-marker"
                      size="14"
                      :color="addr.is_default ? 'brown-darken-3' : 'grey'"
                    />
                    <span class="text-caption font-weight-bold text-capitalize">
                      {{ addr.label ?? 'Address' }}
                    </span>
                    <v-chip
                      v-if="addr.is_default"
                      color="brown-darken-3"
                      size="x-small"
                      label
                    >
                      Default
                    </v-chip>
                  </div>
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    {{ addr.address_line1 }}
                    <template v-if="addr.address_line2">
                      , {{ addr.address_line2 }}
                    </template>
                  </p>
                  <p class="text-caption text-medium-emphasis mb-0">
                    {{
                      [addr.city, addr.state, addr.postal_code]
                        .filter(Boolean)
                        .join(', ')
                    }}
                  </p>
                  <p
                    v-if="addr.country"
                    class="text-caption text-medium-emphasis mb-0"
                  >
                    {{ addr.country }}
                  </p>
                </div>
                <div class="d-flex flex-column">
                  <v-btn
                    icon="mdi-pencil-outline"
                    size="x-small"
                    variant="text"
                    color="brown"
                    @click="emit('edit-address', addr)"
                  />
                  <v-btn
                    icon="mdi-delete-outline"
                    size="x-small"
                    variant="text"
                    color="error"
                    @click="emit('delete-address', addr.id)"
                  />
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </div>
    </template>

    <!-- Empty placeholder -->
    <div
      v-else
      class="d-flex align-center justify-center fill-height text-center pa-6"
    >
      <div>
        <v-icon
          icon="mdi-account-circle-outline"
          size="56"
          color="brown-lighten-3"
          class="mb-3"
        />
        <p class="text-body-2 text-medium-emphasis">
          Select a customer to view details
        </p>
      </div>
    </div>
  </v-navigation-drawer>
</template>
