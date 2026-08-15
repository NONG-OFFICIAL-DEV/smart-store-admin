<template>
  <AppDialog
    v-model="internalValue"
    :max-width="800"
    :title="$t('sale.dialog_title')"
    icon="mdi-cart-outline"
    color="primary"
    :submit-text="$t('btn.save')"
    :disable-submit="!valid"
    @close="close"
    @submit="save"
  >
    <v-form ref="formRef" v-model="valid">
      <v-date-input
        v-model="sale.sale_date"
        :label="$t('sale.sale_date')"
        input-format="DD-MM-YYYY"
        :rules="[v => !!v || $t('form.required')]"
      />

      <div
        v-for="(item, i) in sale.items"
        :key="i"
        class="d-flex align-center mb-2"
      >
        <v-select
          :items="products"
          v-model="item.product_id"
          item-title="name"
          item-value="id"
          :label="$t('sale.product')"
          class="me-2"
        />
        <v-text-field
          v-model.number="item.quantity"
          type="number"
          :label="$t('form.quantity')"
          min="1"
          class="me-2"
        />
        <v-text-field
          v-model.number="item.price"
          type="number"
          :label="$t('form.price')"
          min="0"
        />
        <v-btn icon color="red" @click="removeItem(i)">
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </div>

      <v-btn text color="primary" @click="addItem">+ {{ $t('btn.add_product') }}</v-btn>

      <v-row class="mt-3">
        <v-col class="text-end">
          <h3>{{ $t('common.total') }}: {{ totalAmount.toFixed(2) }}</h3>
        </v-col>
      </v-row>
    </v-form>
  </AppDialog>
</template>

<script setup>
import { reactive, computed, ref, watch } from "vue"
import AppDialog from "@/components/common/AppDialog.vue"

// props & emits for v-model
const props = defineProps({
  modelValue: { type: Boolean, required: true }
})
const emit = defineEmits(["update:modelValue", "save"])

// local dialog state
const internalValue = ref(props.modelValue)
watch(() => props.modelValue, val => (internalValue.value = val))
watch(internalValue, val => emit("update:modelValue", val))

// form state
const formRef = ref(null)
const valid = ref(false)

const sale = reactive({
  sale_date: null,
  items: []
})

// dummy products (replace with API/store later)
const products = [
  { id: 1, name: "Product A" },
  { id: 2, name: "Product B" }
]

function addItem() {
  sale.items.push({ product_id: null, quantity: 1, price: 0 })
}
function removeItem(i) {
  sale.items.splice(i, 1)
}

const totalAmount = computed(() =>
  sale.items.reduce((sum, i) => sum + (i.quantity * i.price || 0), 0)
)

async function save() {
  const ok = await formRef.value.validate()
  if (!ok) return
  emit("save", { ...sale, sale_date: sale.sale_date?.split("T")[0] })
  close()
}

function close() {
  emit("update:modelValue", false)
}
</script>
