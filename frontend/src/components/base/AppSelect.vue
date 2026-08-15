<script setup>
  import { computed, useAttrs } from 'vue'
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  const props = defineProps({
    required: Boolean,
    clearable: Boolean
  })

  const attrs = useAttrs()

  const rules = computed(() => {
    const r = [...(attrs.rules || [])]
    if (props.required) r.push(v => !!v || t('validation.required'))
    return r
  })
</script>

<template>
  <v-select v-bind="$attrs" :rules="rules" :clearable="clearable">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps || {}" />
    </template>
  </v-select>
</template>
