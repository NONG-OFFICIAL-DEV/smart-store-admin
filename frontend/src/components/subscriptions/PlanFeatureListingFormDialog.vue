<template>
  <AppDialog
    v-model="model"
    :max-width="480"
    :title="editingListing ? $t('subscription.plan_feature_listings.edit_title') : $t('subscription.plan_feature_listings.new_title')"
    :icon="editingListing ? 'mdi-pencil-outline' : 'mdi-plus'"
    :color="editingListing ? 'primary' : 'success'"
    :loading="saving"
    :submit-text="editingListing ? $t('btn.save_changes') : $t('btn.save')"
    @submit="submit"
  >
    <v-form ref="formRef" @submit.prevent="submit" v-model="isValid">
      <v-row dense>
        <v-col cols="12">
          <v-text-field
            v-model="form.key"
            :label="$t('subscription.plan_feature_listings.fields.key')"
            :hint="$t('subscription.plan_feature_listings.fields.key_hint')"
            persistent-hint
            :rules="[r.required, r.keyFormat]"
            :disabled="Boolean(editingListing)"
            rounded="lg"
            variant="outlined"
          />
        </v-col>
        <v-col cols="12">
          <v-text-field
            v-model="form.label_en"
            :label="$t('subscription.plan_feature_listings.fields.label_en')"
            :rules="[r.required]"
            rounded="lg"
            variant="outlined"
          />
        </v-col>
        <v-col cols="12">
          <v-text-field
            v-model="form.label_km"
            :label="$t('subscription.plan_feature_listings.fields.label_km')"
            rounded="lg"
            variant="outlined"
          />
        </v-col>
        <v-col cols="12" sm="6">
          <v-select
            v-model="form.value_type"
            :items="valueTypeOptions"
            item-title="title"
            item-value="value"
            :label="$t('subscription.plan_feature_listings.fields.value_type')"
            :rules="[r.required]"
            rounded="lg"
            variant="outlined"
          />
        </v-col>
        <v-col cols="12" sm="6">
          <v-text-field
            v-model="form.sort_order"
            type="number"
            min="0"
            :label="$t('subscription.plan_feature_listings.fields.sort_order')"
            rounded="lg"
            variant="outlined"
          />
        </v-col>
        <v-col cols="12">
          <v-switch v-model="form.is_active" :label="$t('status.active')" color="success" inset />
        </v-col>
      </v-row>
    </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

  const props = defineProps({
    editingListing: { type: Object, default: null },
    saving: { type: Boolean, default: false }
  })

  const emit = defineEmits(['submit'])
  const model = defineModel({ type: Boolean, default: false })

  const formRef = ref(null)
  const isValid = ref(false)

  const r = {
    required: v => (v !== null && v !== undefined && v !== '') || t('form.required'),
    keyFormat: v => !v || /^[a-z0-9_]+$/.test(v) || t('subscription.plan_feature_listings.fields.key_format_error')
  }

  const valueTypeOptions = computed(() => [
    { title: t('subscription.plan_feature_listings.value_types.text'), value: 'text' },
    { title: t('subscription.plan_feature_listings.value_types.boolean'), value: 'boolean' }
  ])

  const defaultForm = () => ({
    key: '',
    label_en: '',
    label_km: '',
    value_type: 'text',
    sort_order: 0,
    is_active: true
  })

  const form = ref(defaultForm())

  watch(
    () => props.editingListing,
    listing => {
      if (listing) {
        form.value = {
          key: listing.key ?? '',
          label_en: listing.label?.en ?? '',
          label_km: listing.label?.km ?? '',
          value_type: listing.value_type ?? 'text',
          sort_order: listing.sort_order ?? 0,
          is_active: listing.is_active ?? true
        }
      } else {
        form.value = defaultForm()
      }
    },
    { immediate: true }
  )

  const submit = async () => {
    if (!formRef.value) return
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('submit', { ...form.value })
  }
</script>
