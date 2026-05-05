<template>
  <v-dialog v-model="model" max-width="660" persistent scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- ── Header ── -->
      <v-card-title class="pa-0">
        <div class="d-flex align-center gap-3 pa-5 pb-4">
          <v-avatar
            :color="isEdit ? 'primary' : 'success'"
            size="42"
            rounded="lg"
            variant="tonal"
          >
            <v-icon
              :icon="
                isEdit ? 'mdi-office-building-cog' : 'mdi-office-building-plus'
              "
              size="20"
            />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">
              {{ isEdit ? 'Edit Tenant' : 'Create New Tenant' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{
                isEdit
                  ? 'Update business info and settings'
                  : 'Creates owner account + business in one step'
              }}
            </div>
          </div>
          <v-spacer />
          <v-btn
            icon="mdi-close"
            size="small"
            variant="text"
            density="comfortable"
            @click="close"
          />
        </div>

        <!-- ── Stepper (create only: 3 steps; edit: 2 steps) ── -->
        <div class="stepper-bar px-5 pb-4">
          <div class="stepper-track">
            <template v-for="(step, i) in activeSteps" :key="i">
              <div
                class="step-item"
                :class="{
                  'step-active': i === currentStep,
                  'step-done': i < currentStep
                }"
                @click="tryJumpTo(i)"
              >
                <div class="step-dot">
                  <v-icon v-if="i < currentStep" icon="mdi-check" size="13" />
                  <span v-else>{{ i + 1 }}</span>
                </div>
                <span class="step-label">{{ step.label }}</span>
              </div>
              <div
                v-if="i < activeSteps.length - 1"
                class="step-connector"
                :class="{ 'connector-done': i < currentStep }"
              />
            </template>
          </div>
        </div>
      </v-card-title>

      <v-divider />

      <!-- ── Body ── -->
      <v-card-text class="pa-0" style="max-height: 62vh; overflow-y: auto">
        <v-form ref="formRef" @submit.prevent>
          <v-window v-model="currentStep" :touch="false">
            <!-- ════ STEP 0 — Owner (create only) / Business overview (edit) ════ -->
            <v-window-item :value="0">
              <div class="step-content pa-5">
                <!-- CREATE: owner fields -->
                <template v-if="!isEdit">
                  <div class="section-label mb-4">
                    <v-icon
                      icon="mdi-account-circle-outline"
                      size="15"
                      class="mr-1"
                    />
                    Owner Account
                  </div>

                  <v-row dense>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.owner_first_name"
                        label="First Name *"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        :rules="[r.required]"
                        prepend-inner-icon="mdi-account-outline"
                        maxlength="80"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.owner_last_name"
                        label="Last Name *"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        :rules="[r.required]"
                        maxlength="80"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.owner_email"
                        label="Email *"
                        type="email"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        :rules="[r.required, r.email]"
                        :error-messages="serverErrors.owner_email"
                        prepend-inner-icon="mdi-email-outline"
                        maxlength="255"
                        @update:model-value="clearServerError('owner_email')"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.owner_phone"
                        label="Phone"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        prepend-inner-icon="mdi-phone-outline"
                        maxlength="30"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.owner_password"
                        label="Password *"
                        :type="showPassword ? 'text' : 'password'"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        :rules="[r.required, r.minPassword]"
                        prepend-inner-icon="mdi-lock-outline"
                        :append-inner-icon="
                          showPassword ? 'mdi-eye-off' : 'mdi-eye'
                        "
                        @click:append-inner="showPassword = !showPassword"
                      />
                    </v-col>
                    <v-col cols="12" sm="6" class="d-flex align-center pb-5">
                      <v-btn
                        variant="tonal"
                        rounded="lg"
                        size="small"
                        prepend-icon="mdi-refresh"
                        @click="generatePassword"
                      >
                        Generate
                      </v-btn>
                    </v-col>
                  </v-row>

                  <v-alert
                    v-if="credentialGenerated"
                    type="warning"
                    variant="tonal"
                    rounded="lg"
                    density="compact"
                    icon="mdi-key-alert-outline"
                    class="mt-1"
                  >
                    Share these credentials with the owner. Password won't be
                    visible again after saving.
                  </v-alert>
                </template>

                <!-- EDIT: readonly owner card -->
                <template v-else>
                  <div class="section-label mb-3">
                    <v-icon
                      icon="mdi-account-circle-outline"
                      size="15"
                      class="mr-1"
                    />
                    Owner
                  </div>

                  <v-card rounded="lg" variant="outlined" class="pa-4 mb-5">
                    <div class="d-flex align-center gap-3">
                      <v-avatar
                        color="primary"
                        variant="tonal"
                        size="44"
                        rounded="lg"
                      >
                        <span class="text-body-2 font-weight-bold">
                          {{ ownerInitials }}
                        </span>
                      </v-avatar>
                      <div class="flex-grow-1">
                        <div class="text-body-2 font-weight-medium">
                          {{ props.item?.owner?.first_name }}
                          {{ props.item?.owner?.last_name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ props.item?.owner?.email }}
                        </div>
                        <div
                          v-if="props.item?.owner?.phone"
                          class="text-caption text-medium-emphasis"
                        >
                          {{ props.item?.owner?.phone }}
                        </div>
                      </div>
                      <v-btn
                        size="small"
                        variant="tonal"
                        rounded="lg"
                        prepend-icon="mdi-swap-horizontal"
                        color="warning"
                        @click="transferDialog = true"
                      >
                        Transfer
                      </v-btn>
                    </div>
                  </v-card>

                  <v-divider class="mb-4" />
                  <div class="section-label mb-3">
                    <v-icon icon="mdi-store-outline" size="15" class="mr-1" />
                    Business Info
                  </div>

                  <v-row dense>
                    <v-col cols="12" sm="8">
                      <v-text-field
                        v-model="form.name"
                        label="Business Name *"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        :rules="[r.required]"
                        prepend-inner-icon="mdi-domain"
                        maxlength="150"
                        @update:model-value="onNameChange"
                      />
                    </v-col>
                    <v-col cols="12" sm="4">
                      <v-select
                        v-model="form.plan"
                        :items="planOptions"
                        item-title="label"
                        item-value="value"
                        label="Plan"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        prepend-inner-icon="mdi-crown-outline"
                      >
                        <template #item="{ props: p, item }">
                          <v-list-item v-bind="p">
                            <template #prepend>
                              <v-icon
                                :icon="item.raw.icon"
                                :color="item.raw.color"
                                size="18"
                                class="mr-2"
                              />
                            </template>
                          </v-list-item>
                        </template>
                      </v-select>
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-select
                        v-model="form.business_type_id"
                        :items="businessTypeOptions"
                        :loading="buTypesLoading"
                        item-title="label"
                        item-value="value"
                        label="Business Type *"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        :rules="[r.required]"
                        prepend-inner-icon="mdi-store-outline"
                        no-data-text="Loading business types..."
                      >
                        <template #item="{ props: p, item }">
                          <v-list-item v-bind="p">
                            <template #prepend>
                              <v-avatar
                                :color="item.raw.color"
                                variant="tonal"
                                size="28"
                                rounded="md"
                                class="mr-2"
                              >
                                <v-icon :icon="item.raw.icon" size="15" />
                              </v-avatar>
                            </template>
                          </v-list-item>
                        </template>
                        <template #selection="{ item }">
                          <div class="d-flex align-center gap-2">
                            <v-avatar
                              :color="item.raw.color"
                              variant="tonal"
                              size="22"
                              rounded="sm"
                            >
                              <v-icon :icon="item.raw.icon" size="13" />
                            </v-avatar>
                            <span class="text-body-2">
                              {{ item.raw.label }}
                            </span>
                          </div>
                        </template>
                      </v-select>
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.slug"
                        label="Slug"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        prepend-inner-icon="mdi-link-variant"
                        :readonly="!editingSlug"
                        :bg-color="!editingSlug ? 'grey-lighten-4' : undefined"
                        hint="Auto-generated from business name"
                        persistent-hint
                        maxlength="100"
                      >
                        <template #append-inner>
                          <v-tooltip
                            :text="editingSlug ? 'Lock slug' : 'Edit manually'"
                          >
                            <template #activator="{ props: ttProps }">
                              <v-icon
                                v-bind="ttProps"
                                :icon="
                                  editingSlug
                                    ? 'mdi-lock-open-outline'
                                    : 'mdi-lock-outline'
                                "
                                size="18"
                                class="cursor-pointer text-medium-emphasis"
                                @click="editingSlug = !editingSlug"
                              />
                            </template>
                          </v-tooltip>
                        </template>
                      </v-text-field>
                    </v-col>

                    <v-col cols="12" sm="4" class="d-flex align-center">
                      <v-switch
                        v-model="form.is_active"
                        label="Active"
                        color="success"
                        inset
                        hide-details
                      />
                    </v-col>
                  </v-row>
                </template>
              </div>
            </v-window-item>

            <!-- ════ STEP 1 — Business Info (create only) ════ -->
            <v-window-item v-if="!isEdit" :value="1">
              <div class="step-content pa-5">
                <div class="section-label mb-4">
                  <v-icon icon="mdi-store-outline" size="15" class="mr-1" />
                  Business Info
                </div>

                <v-row dense>
                  <v-col cols="12" sm="8">
                    <v-text-field
                      v-model="form.name"
                      label="Business Name *"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      :rules="[r.required]"
                      prepend-inner-icon="mdi-domain"
                      maxlength="150"
                      @update:model-value="onNameChange"
                    />
                  </v-col>
                  <v-col cols="12" sm="4">
                    <v-select
                      v-model="form.plan"
                      :items="planOptions"
                      item-title="label"
                      item-value="value"
                      label="Plan"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      prepend-inner-icon="mdi-crown-outline"
                    >
                      <template #item="{ props: p, item }">
                        <v-list-item v-bind="p">
                          <template #prepend>
                            <v-icon
                              :icon="item.raw.icon"
                              :color="item.raw.color"
                              size="18"
                              class="mr-2"
                            />
                          </template>
                        </v-list-item>
                      </template>
                    </v-select>
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.business_type_id"
                      :items="businessTypeOptions"
                      :loading="buTypesLoading"
                      item-title="label"
                      item-value="value"
                      label="Business Type *"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      :rules="[r.required]"
                      prepend-inner-icon="mdi-store-outline"
                      no-data-text="Loading business types..."
                    >
                      <template #item="{ props: p, item }">
                        <v-list-item v-bind="p">
                          <template #prepend>
                            <v-avatar
                              :color="item.raw.color"
                              variant="tonal"
                              size="28"
                              rounded="md"
                              class="mr-2"
                            >
                              <v-icon :icon="item.raw.icon" size="15" />
                            </v-avatar>
                          </template>
                        </v-list-item>
                      </template>
                      <template #selection="{ item }">
                        <div class="d-flex align-center gap-2">
                          <v-avatar
                            :color="item.raw.color"
                            variant="tonal"
                            size="22"
                            rounded="sm"
                          >
                            <v-icon :icon="item.raw.icon" size="13" />
                          </v-avatar>
                          <span class="text-body-2">{{ item.raw.label }}</span>
                        </div>
                      </template>
                    </v-select>
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.slug"
                      label="Slug"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      prepend-inner-icon="mdi-link-variant"
                      :readonly="!editingSlug"
                      :bg-color="!editingSlug ? 'grey-lighten-4' : undefined"
                      hint="Auto-generated from business name"
                      persistent-hint
                      maxlength="100"
                    >
                      <template #append-inner>
                        <v-tooltip
                          :text="editingSlug ? 'Lock slug' : 'Edit manually'"
                        >
                          <template #activator="{ props: ttProps }">
                            <v-icon
                              v-bind="ttProps"
                              :icon="
                                editingSlug
                                  ? 'mdi-lock-open-outline'
                                  : 'mdi-lock-outline'
                              "
                              size="18"
                              class="cursor-pointer text-medium-emphasis"
                              @click="editingSlug = !editingSlug"
                            />
                          </template>
                        </v-tooltip>
                      </template>
                    </v-text-field>
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.logo_url"
                      label="Logo URL"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      prepend-inner-icon="mdi-image-outline"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-color-input
                      v-model="form.primary_color"
                      color-pip
                      label="Brand Color"
                      variant="outlined"
                      density="comfortable"
                      pip-location="prepend-inner"
                    />
                  </v-col>
                </v-row>
              </div>
            </v-window-item>

            <!-- ════ STEP: Localization (last step for both modes) ════ -->
            <v-window-item :value="isEdit ? 1 : 2">
              <div class="step-content pa-5">
                <div class="section-label mb-4">
                  <v-icon icon="mdi-earth" size="15" class="mr-1" />
                  Localization
                </div>

                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.currency"
                      :items="currencyOptions"
                      item-title="label"
                      item-value="value"
                      label="Currency"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      prepend-inner-icon="mdi-currency-usd"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.locale"
                      :items="localeOptions"
                      item-title="label"
                      item-value="value"
                      label="Locale"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      prepend-inner-icon="mdi-translate"
                    />
                  </v-col>

                  <!-- Brand color + logo (edit mode puts them here) -->
                  <template v-if="isEdit">
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.logo_url"
                        label="Logo URL"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        prepend-inner-icon="mdi-image-outline"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-color-input
                        v-model="form.primary_color"
                        color-pip
                        label="Brand Color"
                        variant="outlined"
                        density="comfortable"
                        pip-location="prepend-inner"
                      />
                    </v-col>
                  </template>
                </v-row>

                <!-- Review summary on last step -->
                <v-divider class="my-5" />
                <div class="section-label mb-3">
                  <v-icon
                    icon="mdi-check-circle-outline"
                    size="15"
                    class="mr-1"
                  />
                  Summary
                </div>

                <v-row dense>
                  <v-col
                    v-for="item in reviewItems"
                    :key="item.label"
                    cols="6"
                    sm="4"
                  >
                    <div class="review-tile pa-3">
                      <div class="review-label">{{ item.label }}</div>
                      <div class="review-value">{{ item.value || '—' }}</div>
                    </div>
                  </v-col>
                </v-row>
              </div>
            </v-window-item>
          </v-window>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Footer ── -->
      <v-card-actions class="pa-4">
        <div class="text-caption text-medium-emphasis">
          Step {{ currentStep + 1 }} of {{ activeSteps.length }}
        </div>
        <v-spacer />

        <v-btn
          v-if="currentStep > 0"
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-arrow-left"
          @click="prevStep"
        >
          Back
        </v-btn>

        <v-btn
          variant="tonal"
          rounded="lg"
          :disabled="props.loading"
          @click="close"
        >
          Cancel
        </v-btn>

        <v-btn
          v-if="currentStep < activeSteps.length - 1"
          color="primary"
          variant="flat"
          rounded="lg"
          append-icon="mdi-arrow-right"
          @click="nextStep"
        >
          Continue
        </v-btn>

        <v-btn
          v-else
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="props.loading"
          :prepend-icon="
            isEdit ? 'mdi-content-save' : 'mdi-office-building-plus'
          "
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Tenant' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- ── Transfer Ownership Dialog ── -->
  <v-dialog v-model="transferDialog" max-width="420" persistent>
    <v-card rounded="xl" elevation="0" border>
      <v-card-title class="pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="warning" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-swap-horizontal" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">Transfer Ownership</div>
            <div class="text-caption text-medium-emphasis">
              Assign a new owner to this tenant
            </div>
          </div>
        </div>
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-5">
        <v-text-field
          v-model="transferEmail"
          label="New Owner Email"
          type="email"
          variant="outlined"
          density="comfortable"
          rounded="lg"
          prepend-inner-icon="mdi-email-outline"
          hint="Must be an existing user in the system"
          persistent-hint
        />
      </v-card-text>
      <v-card-actions class="px-5 pb-5 pt-0 gap-3">
        <v-btn variant="tonal" rounded="lg" @click="transferDialog = false">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          color="warning"
          variant="flat"
          rounded="lg"
          @click="emitTransfer"
        >
          Confirm Transfer
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { slugify } from '@/utils/slugify'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useBusinessTypes } from '@/composables/useBusinessTypes'

  const props = defineProps({
    modelValue: Boolean,
    item: Object,
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save', 'transfer'])

  // ── Store & composable ────────────────────────────────────────────────────────
  const store = useTenantStore()
  const { businessTypeOptions } = useBusinessTypes()
  const buTypesLoading = ref(false)

  // ── Local state ───────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const currentStep = ref(0)
  const showPassword = ref(false)
  const editingSlug = ref(false)
  const credentialGenerated = ref(false)
  const transferDialog = ref(false)
  const transferEmail = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  // Steps differ by mode
  // Create: Owner → Business → Localization (3 steps)
  // Edit:   Business + Owner → Localization  (2 steps)
  const activeSteps = computed(() =>
    isEdit.value
      ? [{ label: 'Business' }, { label: 'Localization' }]
      : [{ label: 'Owner' }, { label: 'Business' }, { label: 'Localization' }]
  )

  const ownerInitials = computed(() => {
    const f = props.item?.owner?.first_name?.[0] ?? ''
    const l = props.item?.owner?.last_name?.[0] ?? ''
    return (f + l).toUpperCase() || '?'
  })

  // ── Review summary (shown on last step) ───────────────────────────────────────
  const reviewItems = computed(() => {
    const items = []
    if (!isEdit.value) {
      items.push(
        {
          label: 'Owner',
          value: `${form.owner_first_name} ${form.owner_last_name}`.trim()
        },
        { label: 'Email', value: form.owner_email }
      )
    }
    items.push(
      { label: 'Business', value: form.name },
      { label: 'Plan', value: form.plan?.toUpperCase() },
      { label: 'Slug', value: form.slug },
      { label: 'Currency', value: form.currency },
      { label: 'Locale', value: form.locale }
    )
    return items
  })

  // ── Options ───────────────────────────────────────────────────────────────────
  const planOptions = [
    { value: 'free', label: 'Free', icon: 'mdi-star-outline', color: 'grey' },
    {
      value: 'starter',
      label: 'Starter',
      icon: 'mdi-star-half-full',
      color: 'blue'
    },
    { value: 'pro', label: 'Pro', icon: 'mdi-star', color: 'primary' },
    {
      value: 'enterprise',
      label: 'Enterprise',
      icon: 'mdi-crown',
      color: 'warning'
    }
  ]

  const currencyOptions = [
    { value: 'USD', label: 'USD — US Dollar' },
    { value: 'KHR', label: 'KHR — Cambodian Riel' },
    { value: 'EUR', label: 'EUR — Euro' },
    { value: 'GBP', label: 'GBP — British Pound' },
    { value: 'SGD', label: 'SGD — Singapore Dollar' },
    { value: 'MYR', label: 'MYR — Malaysian Ringgit' }
  ]

  const localeOptions = [
    { value: 'en-US', label: 'English (US)' },
    { value: 'en-GB', label: 'English (UK)' },
    { value: 'km-KH', label: 'Khmer (KH)' },
    { value: 'zh-CN', label: 'Chinese (CN)' }
  ]

  // ── Form ──────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    owner_first_name: '',
    owner_last_name: '',
    owner_email: '',
    owner_phone: '',
    owner_password: '',
    name: '',
    slug: '',
    business_type_id: null,
    plan: 'free',
    logo_url: '',
    primary_color: '#6366f1',
    is_active: true,
    currency: 'USD',
    locale: 'en-US'
  })

  const form = reactive(defaultForm())

  watch(
    () => props.item,
    val => {
      editingSlug.value = false
      credentialGenerated.value = false
      currentStep.value = 0

      if (val) {
        Object.assign(form, {
          name: val.name,
          business_type_id: val.business_type_id,
          slug: val.slug,
          plan: val.plan ?? 'free',
          logo_url: val.logo_url ?? '',
          primary_color: val.primary_color ?? '#6366f1',
          is_active: val.is_active ?? true,
          currency: val.currency ?? 'USD',
          locale: val.locale ?? 'en-US'
        })
      } else {
        Object.assign(form, defaultForm())
      }
    },
    { immediate: true }
  )

  // Load business types when dialog opens
  watch(
    () => props.modelValue,
    async open => {
      if (open && store.businessTypes.length === 0) {
        buTypesLoading.value = true
        try {
          await store.fetchBusinessTypes()
        } finally {
          buTypesLoading.value = false
        }
      }
    }
  )

  // ── Auto slug ─────────────────────────────────────────────────────────────────
  const onNameChange = val => {
    if (!editingSlug.value) form.slug = slugify(val)
  }

  // ── Password generator ────────────────────────────────────────────────────────
  const generatePassword = () => {
    const chars =
      'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#!'
    form.owner_password = Array.from(
      { length: 12 },
      () => chars[Math.floor(Math.random() * chars.length)]
    ).join('')
    showPassword.value = true
    credentialGenerated.value = true
  }

  // ── Validation ────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || 'Required',
    email: v => /.+@.+\..+/.test(v) || 'Invalid email',
    minPassword: v => !v || v.length >= 6 || 'Minimum 6 characters'
  }

  // Fields validated per step (create mode)
  const stepFieldsCreate = {
    0: ['owner_first_name', 'owner_last_name', 'owner_email', 'owner_password'],
    1: ['name', 'business_type_id'],
    2: []
  }
  // Fields validated per step (edit mode)
  const stepFieldsEdit = {
    0: ['name', 'business_type_id'],
    1: []
  }

  // ── Stepper navigation ────────────────────────────────────────────────────────
  const nextStep = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    if (currentStep.value < activeSteps.value.length - 1) currentStep.value++
  }

  const prevStep = () => {
    if (currentStep.value > 0) currentStep.value--
  }

  const tryJumpTo = i => {
    if (i < currentStep.value) currentStep.value = i
  }

  // ── Server-side errors ────────────────────────────────────────────────────────
  // Call setErrors(response.errors) from the parent after a failed API call.
  // Each key maps to a form field; the value is an array of error strings.
  const serverErrors = reactive({})

  const clearServerError = field => {
    if (serverErrors[field]) delete serverErrors[field]
  }

  // Field → which step it lives on (create mode; step 0 = owner)
  const fieldStepMap = {
    owner_first_name: 0,
    owner_last_name: 0,
    owner_email: 0,
    owner_phone: 0,
    owner_password: 0,
    name: 1,
    slug: 1,
    business_type_id: 1,
    plan: 1,
    currency: 2,
    locale: 2
  }

  const setErrors = errors => {
    // Clear old server errors first
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])

    if (!errors || typeof errors !== 'object') return

    Object.entries(errors).forEach(([field, messages]) => {
      serverErrors[field] = Array.isArray(messages) ? messages : [messages]
    })

    // Jump to the step that contains the first error field so user sees it
    const firstField = Object.keys(errors)[0]
    if (firstField && !isEdit.value) {
      const targetStep = fieldStepMap[firstField] ?? 0
      currentStep.value = targetStep
    }
  }

  defineExpose({ setErrors })

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    if (isEdit.value) {
      emit('save', {
        id: props.item.id,
        name: form.name,
        business_type_id: form.business_type_id,
        slug: form.slug,
        plan: form.plan,
        logo_url: form.logo_url,
        primary_color: form.primary_color,
        is_active: form.is_active,
        currency: form.currency,
        locale: form.locale
      })
    } else {
      emit('save', { ...form })
    }
  }

  const emitTransfer = () => {
    if (!transferEmail.value) return
    emit('transfer', {
      tenant_id: props.item.id,
      new_owner_email: transferEmail.value
    })
    transferDialog.value = false
    transferEmail.value = ''
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])
    credentialGenerated.value = false
    editingSlug.value = false
    currentStep.value = 0
    emit('update:modelValue', false)
  }
</script>

<style scoped>
  /* ── Stepper ── */
  .stepper-track {
    display: flex;
    align-items: center;
  }
  .step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: default;
  }
  .step-item.step-done {
    cursor: pointer;
  }
  .step-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid rgba(var(--v-border-color), 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    color: rgba(var(--v-theme-on-surface), 0.4);
    background: transparent;
    transition: all 0.2s ease;
  }
  .step-active .step-dot {
    background: rgb(var(--v-theme-primary));
    border-color: rgb(var(--v-theme-primary));
    color: #fff;
  }
  .step-done .step-dot {
    background: rgb(var(--v-theme-success));
    border-color: rgb(var(--v-theme-success));
    color: #fff;
  }
  .step-label {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.4);
    white-space: nowrap;
    transition: color 0.2s;
  }
  .step-active .step-label {
    color: rgba(var(--v-theme-on-surface), 0.87);
    font-weight: 500;
  }
  .step-done .step-label {
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
  .step-connector {
    flex: 1;
    height: 1px;
    background: rgba(var(--v-border-color), 0.3);
    margin: 0 8px;
    min-width: 16px;
    transition: background 0.3s;
  }
  .connector-done {
    background: rgb(var(--v-theme-success));
  }

  /* ── Content ── */
  .step-content {
    min-height: 260px;
  }

  .section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: rgb(var(--v-theme-primary));
    display: flex;
    align-items: center;
  }

  /* ── Review tiles ── */
  .review-tile {
    background: rgba(var(--v-theme-surface-variant), 0.35);
    border-radius: 10px;
    border: 0.5px solid rgba(var(--v-border-color), 0.15);
  }
  .review-label {
    font-size: 11px;
    color: rgba(var(--v-theme-on-surface), 0.45);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 3px;
  }
  .review-value {
    font-size: 13px;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.87);
  }

  .cursor-pointer {
    cursor: pointer;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
