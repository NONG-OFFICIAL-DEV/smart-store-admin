<template>
  <custom-title
    title="Product Variants"
    subtitle="Manage sizes and configurations"
  >
    <template #right>
      <v-btn
        color="primary"
        rounded="lg"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        Add Variant
      </v-btn>
    </template>
  </custom-title>

  <v-card rounded="xl" elevation="0" border>
    <v-list lines="two" class="pa-2">
      <v-list-item
        v-for="variant in productVariants"
        :key="variant.id"
        rounded="lg"
      >
        <template #append>
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openEdit(variant)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(variant)"
            />
          </div>
        </template>
      </v-list-item>
    </v-list>
  </v-card>

  <ProductVariantDialog
    v-model="dialog"
    :variant="selectedVariant"
    :product-id="productId"
    :product-name="productName"
    :loading="loading"
    :server-errors="fieldErrors"
    @save="handleSave"
  />

  <v-dialog v-model="deleteDialog" max-width="380"></v-dialog>
</template>

<script setup>
  import { ref, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useProductVariantStore } from '@/stores/productVariantStore'
  import ProductVariantDialog from '@/components/products/ProductVariantDialog.vue'

  const props = defineProps({
    productId: { type: String, required: true },
    productName: { type: String, default: '' }
  })

  const variantStore = useProductVariantStore()
  const { productVariants } = storeToRefs(variantStore)

  const dialog = ref(false)
  const deleteDialog = ref(false)
  const selectedVariant = ref(null)
  const deleteTarget = ref(null)
  const loading = ref(false)
  const fieldErrors = ref({})

  onMounted(() =>
    variantStore.fetchProductVariants({ product_id: props.productId })
  )

  const openCreate = () => {
    selectedVariant.value = null
    fieldErrors.value = {}
    dialog.value = true
  }

  const openEdit = variant => {
    selectedVariant.value = { ...variant }
    fieldErrors.value = {}
    dialog.value = true
  }

  const handleSave = async payload => {
    loading.value = true
    fieldErrors.value = {}
    try {
      if (payload.id) {
        await variantStore.updateProductVariant(payload.id, payload)
      } else {
        await variantStore.createProductVariant(payload)
      }
      dialog.value = false
    } catch (err) {
      if (err.response?.data?.errors)
        fieldErrors.value = err.response.data.errors
    } finally {
      loading.value = false
    }
  }

  const confirmDelete = v => {
    deleteTarget.value = v
    deleteDialog.value = true
  }
  const doDelete = async () => {
    loading.value = true
    try {
      await variantStore.deleteProductVariant(deleteTarget.value.id)
      deleteDialog.value = false
    } finally {
      loading.value = false
    }
  }
</script>
