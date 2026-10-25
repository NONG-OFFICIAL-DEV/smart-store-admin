<template>
  <div>
    <div class="text-h5 font-weight-black text-center mb-3">
      {{ display }}
    </div>
    <v-row dense>
      <v-col v-for="key in keys" :key="key.label" :cols="key.wide ? 12 : 4">
        <v-btn
          block
          height="56"
          variant="tonal"
          rounded="lg"
          class="text-h6"
          :disabled="key.disabled"
          @click="press(key)"
        >
          <v-icon v-if="key.icon" :icon="key.icon" size="24" />
          <span v-else>{{ key.label }}</span>
        </v-btn>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'

  const props = defineProps({
    modelValue: { type: Number, default: null },
    decimal: { type: Boolean, default: false },
    max: { type: Number, default: null },
    suffix: { type: String, default: '' }
  })
  const emit = defineEmits(['update:modelValue'])

  // Owns its own text buffer rather than formatting modelValue directly —
  // typing "5." would otherwise collapse back to "5" every render since
  // Number("5.") === 5, losing the trailing dot the user just typed.
  const buffer = ref(props.modelValue != null ? String(props.modelValue) : '')

  watch(
    () => props.modelValue,
    v => {
      // Only resync from outside (e.g. a quick-amount button, or the field
      // being cleared) — not from our own commit() emitting the same value.
      const parsed = buffer.value === '' || buffer.value === '.' ? null : Number(buffer.value)
      if (v !== parsed) buffer.value = v != null ? String(v) : ''
    }
  )

  const display = computed(() => `${buffer.value || '0'}${props.suffix}`)

  const keys = computed(() => [
    { label: '1', type: 'digit' },
    { label: '2', type: 'digit' },
    { label: '3', type: 'digit' },
    { label: '4', type: 'digit' },
    { label: '5', type: 'digit' },
    { label: '6', type: 'digit' },
    { label: '7', type: 'digit' },
    { label: '8', type: 'digit' },
    { label: '9', type: 'digit' },
    { label: '.', type: 'decimal', disabled: !props.decimal },
    { label: '0', type: 'digit' },
    { label: '00', type: 'digit' },
    { label: 'backspace', type: 'backspace', icon: 'mdi-backspace-outline', wide: true }
  ])

  function press(key) {
    if (key.disabled) return
    if (key.type === 'digit') appendDigit(key.label)
    else if (key.type === 'decimal') appendDecimal()
    else if (key.type === 'backspace') backspace()
  }

  function appendDigit(digit) {
    if (buffer.value.length >= 9) return
    buffer.value += digit
    commit()
  }

  function appendDecimal() {
    if (!props.decimal || buffer.value.includes('.')) return
    buffer.value = `${buffer.value || '0'}.`
    commit()
  }

  function backspace() {
    buffer.value = buffer.value.slice(0, -1)
    commit()
  }

  function commit() {
    let num = buffer.value === '' || buffer.value === '.' ? null : Number(buffer.value)
    if (num !== null && props.max != null && num > props.max) {
      num = props.max
      buffer.value = String(props.max)
    }
    emit('update:modelValue', num)
  }
</script>
