<template>
  <div class="pos-numpad">
    <v-btn
      v-for="key in keys"
      :key="key.label"
      variant="tonal"
      rounded="lg"
      size="large"
      class="pos-numpad__key text-none"
      @click="onKey(key)"
    >
      <v-icon v-if="key.icon" :icon="key.icon" />
      <span v-else class="text-h6">{{ key.label }}</span>
    </v-btn>
  </div>
</template>

<script setup>
  const emit = defineEmits(['digit', 'decimal', 'backspace'])

  const keys = [
    { label: '1', type: 'digit', value: '1' },
    { label: '2', type: 'digit', value: '2' },
    { label: '3', type: 'digit', value: '3' },
    { label: '4', type: 'digit', value: '4' },
    { label: '5', type: 'digit', value: '5' },
    { label: '6', type: 'digit', value: '6' },
    { label: '7', type: 'digit', value: '7' },
    { label: '8', type: 'digit', value: '8' },
    { label: '9', type: 'digit', value: '9' },
    { label: '.', type: 'decimal' },
    { label: '0', type: 'digit', value: '0' },
    { icon: 'mdi-backspace-outline', type: 'backspace' }
  ]

  function onKey(key) {
    if (key.type === 'digit') emit('digit', key.value)
    else if (key.type === 'decimal') emit('decimal')
    else emit('backspace')
  }
</script>

<style scoped>
  .pos-numpad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }
  .pos-numpad__key {
    min-height: 56px;
  }
</style>
