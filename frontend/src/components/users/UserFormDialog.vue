<template>
  <v-dialog
    :model-value="modelValue"
    max-width="600"
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" elevation="0" border>
      <!-- Header -->
      <v-card-title>
        <div class="d-flex align-center justify-space-between">
          <div>
            {{ isEdit ? 'Edit User' : 'Add User' }}
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <v-row dense>
            <!-- Avatar preview -->
            <v-col cols="3">
              <v-avatar
                size="70"
                rounded="xl"
                :color="avatarColor(form.first_name)"
              >
                <v-img v-if="form.avatar_url" :src="form.avatar_url" cover />
                <span v-else class="text-h6 font-weight-bold text-white">
                  {{ form.first_name ? form.first_name[0].toUpperCase() : '?' }}
                </span>
              </v-avatar>
            </v-col>
            <v-col col="7">
              <v-text-field
                v-model="form.avatar_url"
                label="Avatar URL"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-link"
                hint="Optional profile image URL"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>
          <v-row>
            <!-- First Name -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.first_name"
                label="First Name"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account"
                :rules="[rules.required, rules.maxLen(80)]"
                hint="Legal first name"
                persistent-hint
              />
            </v-col>

            <!-- Last Name -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.last_name"
                label="Last Name"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account"
                :rules="[rules.required, rules.maxLen(80)]"
                hint="Legal last name"
                persistent-hint
              />
            </v-col>

            <!-- Email -->
            <v-col cols="12">
              <v-text-field
                v-model="form.email"
                label="Email"
                variant="outlined"
                density="comfortable"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                :rules="[rules.required, rules.email]"
                :disabled="isEdit"
                hint="Used for login and notifications"
                persistent-hint
              />
            </v-col>

            <!-- Phone -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.phone"
                label="Phone"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-phone"
                :rules="[rules.maxLen(30)]"
                hint="Optional mobile number"
                persistent-hint
                clearable
              />
            </v-col>

            <!-- Language -->
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.preferred_language"
                :items="languages"
                label="Preferred Language"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-translate"
                hint="UI language preference"
                persistent-hint
                clearable
              />
            </v-col>

            <!-- Password (create only) -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.password"
                label="Password"
                variant="outlined"
                density="comfortable"
                :type="showPassword ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="[rules.required, rules.minLen(8)]"
                hint="Minimum 8 characters"
                persistent-hint
                @click:append-inner="showPassword = !showPassword"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.pin_code"
                label="POS PIN (4–6 digits)"
                :type="showPin ? 'text' : 'password'"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-numeric"
                :append-inner-icon="showPin ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="[rules.pin]"
                maxlength="6"
                @click:append-inner="showPin = !showPin"
              />
            </v-col>

            <!-- Active toggle -->
            <v-col cols="12">
              <div class="d-flex gap-6 flex-wrap mt-1">
                <v-switch
                  v-model="form.is_active"
                  color="success"
                  density="compact"
                  hide-details
                  inset
                  label="Account Active"
                />
              </div>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          @click="handleSubmit"
        >
          {{ isEdit ? 'Save Changes' : 'Create User' }}
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
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── Refs ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const showPin = ref(false)
  const showPassword = ref(false)

  // ── Constants ─────────────────────────────────────────────────────────────────
  const languages = [
    { title: 'English', value: 'en' },
    { title: 'Thai', value: 'th' },
    { title: 'Chinese', value: 'zh' },
    { title: 'Japanese', value: 'ja' }
  ]

  // ── Form ──────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    phone: null,
    avatar_url: null,
    preferred_language: 'en',
    password: '',
    pin_code: '',
    is_active: true
  })

  const form = ref(defaultForm())
  const isEdit = computed(() => !!props.editItem)

  const WEAK_PINS = [
    '1234',
    '12345',
    '123456',
    '0000',
    '00000',
    '000000',
    '1111',
    '11111',
    '111111',
    '2222',
    '22222',
    '222222',
    '3333',
    '33333',
    '333333',
    '4444',
    '44444',
    '444444',
    '5555',
    '55555',
    '555555',
    '6666',
    '66666',
    '666666',
    '7777',
    '77777',
    '777777',
    '8888',
    '88888',
    '888888',
    '9999',
    '99999',
    '999999',
    '9876',
    '98765',
    '987654',
    '1212',
    '123123',
    '121212',
    '112233',
    '102030',
    '010203'
  ]

  const isSequential = pin => {
    // detect ascending: 1234, 2345, 3456...
    const asc = pin.split('').every((d, i) => i === 0 || +d === +pin[i - 1] + 1)
    // detect descending: 9876, 8765...
    const desc = pin
      .split('')
      .every((d, i) => i === 0 || +d === +pin[i - 1] - 1)
    return asc || desc
  }

  const isRepeating = pin => {
    // all same digits: 0000, 1111
    return /^(\d)\1+$/.test(pin)
  }

  const isBirthdateLike = pin => {
    // patterns like DDMM, MMDD, MMYY, YYMM, DDMMYY
    // basic: first 2 digits look like day (01-31) or month (01-12)
    const d = parseInt(pin.substring(0, 2))
    const m = parseInt(pin.substring(2, 4))
    if (pin.length === 4 || pin.length === 6) {
      if (d >= 1 && d <= 31 && m >= 1 && m <= 12) return true
      if (m >= 1 && m <= 12 && d >= 1 && d <= 31) return true
    }
    return false
  }

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || 'Required',
    email: v => /.+@.+\..+/.test(v) || 'Invalid email',
    maxLen: n => v => !v || v.length <= n || `Max ${n} chars`,
    minLen: n => v => !v || v.length >= n || `Min ${n} chars`,
    pin: v => {
      if (!v) return true
      if (!/^\d{4,6}$/.test(v)) return 'PIN must be 4–6 digits'
      if (WEAK_PINS.includes(v))
        return 'PIN is too common, choose a stronger PIN'
      if (isRepeating(v)) return 'PIN cannot be all the same digit'
      if (isSequential(v)) return 'PIN cannot be sequential numbers'
      if (isBirthdateLike(v)) return 'Avoid using date patterns as PIN'
      return true
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const colors = [
    'primary',
    'success',
    'warning',
    'error',
    'secondary',
    'teal',
    'purple',
    'orange'
  ]
  const avatarColor = name =>
    name ? colors[name.charCodeAt(0) % colors.length] : 'grey'

  // ── Watch editItem ────────────────────────────────────────────────────────────
  watch(
    () => props.editItem,
    item => {
      showPassword.value = false
      form.value = item
        ? {
            id: item.id,
            first_name: item.first_name,
            last_name: item.last_name,
            email: item.email,
            phone: item.phone ?? null,
            avatar_url: item.avatar_url ?? null,
            preferred_language: item.preferred_language ?? 'en',
            is_active: item.is_active,
            password: ''
          }
        : defaultForm()
    },
    { immediate: true }
  )

  // ── Actions ───────────────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = { ...form.value }
    if (!payload.password) delete payload.password

    emit('saved', payload)
  }

  const close = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    showPassword.value = false
    emit('update:modelValue', false)
  }
</script>
