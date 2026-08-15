<template>
  <v-card rounded="xl" border elevation="0" class="mb-4">
    <v-card-text class="pa-4">
      <div class="section-label mb-3">
        <v-icon icon="mdi-image-outline" size="12" class="mr-1" />
        {{$t('products.cardTitle.productImage')}}
      </div>

      <!-- Drop / click zone -->
      <div
        class="image-upload-area mx-auto"
        :class="{ dragging: isDragging }"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <template v-if="imagePreview">
          <img :src="imagePreview" class="image-preview" />
          <div class="image-overlay" @click.stop>
            <v-btn
              size="x-small"
              variant="flat"
              color="white"
              rounded="lg"
              icon="mdi-pencil-outline"
              @click.stop="triggerFileInput"
            />
            <v-btn
              size="x-small"
              variant="flat"
              color="error"
              rounded="lg"
              icon="mdi-delete-outline"
              @click.stop="removeImage"
            />
          </div>
        </template>
        <template v-else>
          <v-icon
            icon="mdi-image-plus-outline"
            size="36"
            color="grey-lighten-1"
            class="mb-2"
          />
          <div class="text-body-2 text-grey font-weight-medium">
            {{ $t('products.image.dragHint') }}
          </div>
          <div class="text-caption text-grey-lighten-1 mt-1">
            {{ $t('products.image.formatHint') }}
          </div>
        </template>

        <!-- Regular file picker -->
        <input
          ref="fileInputRef"
          type="file"
          accept="image/*"
          class="d-none"
          @change="handleFileChange"
        />
        <!-- Camera capture -->
        <input
          ref="cameraInputRef"
          type="file"
          accept="image/*"
          capture="environment"
          class="d-none"
          @change="handleFileChange"
        />
      </div>

      <!-- Action buttons: Take Photo + Upload Photo -->
      <div class="d-flex gap-2 mt-3" v-if="!imagePreview">
        <v-btn
          variant="tonal"
          color="primary"
          rounded="lg"
          size="small"
          class="text-none"
          prepend-icon="mdi-camera-outline"
          @click="triggerCameraInput"
        >
          {{ $t('btn.take_photo') }}
        </v-btn>

        <v-btn
          variant="outlined"
          color="primary"
          rounded="lg"
          size="small"
          class="text-none"
          prepend-icon="mdi-image-outline"
          @click="triggerFileInput"
        >
          {{ $t('btn.upload') }}
        </v-btn>
      </div>

      <!-- URL paste (super admin only) -->
      <v-text-field
        v-if="isSuperAdmin"
        :model-value="imageUrl"
        :placeholder="$t('products.image.pasteUrl')"
        variant="outlined"
        rounded="lg"
        hide-details
        class="mt-3"
        prepend-inner-icon="mdi-link-variant"
        clearable
        @update:model-value="onUrlChange"
      />
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'

  const { notif } = useAppUtils()
  const { t } = useI18n()

  const props = defineProps({
    imageFile: { default: null },
    imagePreview: { type: String, default: null },
    imageUrl: { type: String, default: null },
    isSuperAdmin: { type: Boolean, default: false }
  })

  const emit = defineEmits([
    'update:imageFile',
    'update:imagePreview',
    'update:imageUrl'
  ])

  const fileInputRef = ref(null)
  const isDragging = ref(false)

  const triggerFileInput = () => {
    fileInputRef.value.removeAttribute('capture')
    fileInputRef.value.click()
  }
  const triggerCameraInput = () => {
    fileInputRef.value.setAttribute('capture', 'environment')
    fileInputRef.value.click()
  }
  const handleFileChange = e => {
    const f = e.target.files?.[0]
    if (f) processFile(f)
    e.target.value = ''
  }

  const handleDrop = e => {
    isDragging.value = false
    const f = e.dataTransfer.files?.[0]
    if (f?.type.startsWith('image/')) processFile(f)
  }

  const resizeImage = file =>
    new Promise(resolve => {
      const img = new Image()
      const reader = new FileReader()
      reader.onload = e => {
        img.src = e.target.result
      }
      img.onload = () => {
        const canvas = document.createElement('canvas')
        canvas.width = 1000
        canvas.height = img.height * (1000 / img.width)
        canvas
          .getContext('2d')
          .drawImage(img, 0, 0, canvas.width, canvas.height)
        canvas.toBlob(blob => resolve(blob), 'image/jpeg', 0.8)
      }
      reader.readAsDataURL(file)
    })

  const processFile = async file => {
    if (file.size > 5 * 1024 * 1024) {
      notif(t('products.image.tooLarge'), { type: 'error', color: 'error' })
      return
    }
    const resized = await resizeImage(file)
    emit('update:imageFile', resized)
    emit('update:imageUrl', null)
    const reader = new FileReader()
    reader.onload = () => emit('update:imagePreview', reader.result)
    reader.readAsDataURL(resized)
  }

  const onUrlChange = val => {
    emit('update:imageFile', val ? null : props.imageFile)
    emit('update:imagePreview', val || null)
    emit('update:imageUrl', val || null)
  }

  const removeImage = () => {
    emit('update:imageFile', null)
    emit('update:imagePreview', null)
    emit('update:imageUrl', null)
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
</script>

<style scoped>
  .section-label {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgb(var(--v-theme-primary));
    display: flex;
    align-items: center;
  }
  .image-upload-area {
    position: relative;
    border: 2px dashed rgba(0, 0, 0, 0.14);
    border-radius: 16px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s ease;
    width: 100%;
    max-width: 200px;
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .image-upload-area:hover {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.03);
  }
  .image-upload-area.dragging {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
  }
  .image-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .image-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.2s;
  }
  .image-upload-area:hover .image-overlay {
    opacity: 1;
  }
</style>
