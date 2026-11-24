<template>
  <div class="d-flex align-center gap-4">
    <v-avatar size="72" :color="avatarColor" class="avatar-preview">
      <v-img v-if="previewUrl" :src="previewUrl" cover />
      <span v-else class="text-title-large font-weight-black text-white">{{ initials }}</span>
    </v-avatar>

    <div class="d-flex flex-column gap-2">
      <div class="d-flex gap-2">
        <v-btn
          size="small"
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-image-outline"
          :loading="processing"
          @click="fileInput?.click()"
        >
          {{ t('account.profile.upload_photo') }}
        </v-btn>
        <v-btn
          v-if="previewUrl"
          size="small"
          variant="text"
          rounded="lg"
          color="error"
          prepend-icon="mdi-delete-outline"
          @click="remove"
        >
          {{ t('btn.remove') }}
        </v-btn>
      </div>
      <span class="text-body-small text-medium-emphasis">{{ t('account.profile.photo_hint') }}</span>
      <span v-if="error" class="text-body-small text-error">{{ error }}</span>
    </div>

    <input
      ref="fileInput"
      type="file"
      accept="image/png,image/jpeg,image/webp"
      class="d-none"
      @change="onFileChange"
    />
  </div>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'

  const MAX_BYTES = 5 * 1024 * 1024
  const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/webp']
  const OUTPUT_SIZE = 480

  const props = defineProps({
    // Existing avatar_url to show before any new file is picked.
    currentUrl: String,
    initials: { type: String, default: '?' },
    avatarColor: { type: String, default: 'primary' }
  })

  // Emits the cropped File (null if removed) so the parent decides when to
  // actually upload it (on Save), plus the preview URL for its own use.
  const emit = defineEmits(['update:file'])

  const { t } = useI18n()
  const fileInput = ref(null)
  const previewUrl = ref(props.currentUrl || '')
  const processing = ref(false)
  const error = ref('')

  function remove() {
    previewUrl.value = ''
    error.value = ''
    if (fileInput.value) fileInput.value.value = ''
    emit('update:file', null)
  }

  async function onFileChange(e) {
    const file = e.target.files?.[0]
    if (!file) return
    error.value = ''

    if (!ALLOWED_TYPES.includes(file.type)) {
      error.value = t('account.profile.photo_type_error')
      return
    }
    if (file.size > MAX_BYTES) {
      error.value = t('account.profile.photo_size_error')
      return
    }

    processing.value = true
    try {
      const { blob, dataUrl } = await cropToSquare(file)
      previewUrl.value = dataUrl
      emit('update:file', new File([blob], file.name, { type: blob.type }))
    } catch {
      error.value = t('account.profile.photo_type_error')
    } finally {
      processing.value = false
    }
  }

  // Fixed center-crop to the largest centered square — not an interactive
  // cropper, just enough to guarantee a square avatar regardless of the
  // original photo's aspect ratio.
  function cropToSquare(file) {
    return new Promise((resolve, reject) => {
      const img = new Image()
      const objectUrl = URL.createObjectURL(file)

      img.onload = () => {
        URL.revokeObjectURL(objectUrl)

        const side = Math.min(img.naturalWidth, img.naturalHeight)
        const sx = (img.naturalWidth - side) / 2
        const sy = (img.naturalHeight - side) / 2

        const canvas = document.createElement('canvas')
        canvas.width = OUTPUT_SIZE
        canvas.height = OUTPUT_SIZE
        const ctx = canvas.getContext('2d')
        ctx.drawImage(img, sx, sy, side, side, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE)

        canvas.toBlob(
          blob => {
            if (!blob) {
              reject(new Error('crop failed'))
              return
            }
            resolve({ blob, dataUrl: canvas.toDataURL(file.type) })
          },
          file.type,
          0.92
        )
      }
      img.onerror = () => {
        URL.revokeObjectURL(objectUrl)
        reject(new Error('invalid image'))
      }
      img.src = objectUrl
    })
  }
</script>

<style scoped>
  .avatar-preview {
    flex-shrink: 0;
  }
</style>
