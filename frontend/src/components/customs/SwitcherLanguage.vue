 <template>
  <v-menu min-width="200px" rounded>
    <template v-slot:activator="{ props }">
      <v-btn v-bind="props" :stacked="stackedBtn" :icon="iconBtn">
        <v-avatar color="brown" size="small">
          <v-img :src="currentLanguageImage" :width="30"></v-img>
        </v-avatar>
      </v-btn>
    </template>
    <v-list density="compact" class="rounded-lg">
      <v-list-item
        v-for="list in orderedLanguage"
        :key="list.lang"
        @click="switchLanguage(list.lang)"
        color="primary"
        :value="list.lang"
      >
        <template v-slot:prepend>
          <v-avatar size="small">
            <v-img :src="list.imgSrc" :alt="list.alt" cover />
          </v-avatar>
        </template>
        <v-list-item-title v-text="list.label"></v-list-item-title>
      </v-list-item>
    </v-list>
  </v-menu>
</template>

<script setup>
  import { computed, onMounted, getCurrentInstance } from 'vue'
  import { useI18n } from 'vue-i18n'

  const props = defineProps({
    stackedBtn: {
      type: Boolean,
      default: true
    },
    iconBtn: {
      type: Boolean,
      default: true
    }
  })

  const { locale, t } = useI18n()

  // Access $vuetify from internal instance context
  const { proxy } = getCurrentInstance()

  const switchLanguage = lang => {
    locale.value = lang
    localStorage.setItem('lang', lang)

    // Set Vuetify language if configured
    if (proxy?.$vuetify?.locale) {
      proxy.$vuetify.locale.current = lang
    }
  }

  const currentLanguageImage = computed(() =>
    locale.value === 'en' ? '/images/gb.png' : '/images/kh.png'
  )

  const orderedLanguage = computed(() => {
    const languages = [
      {
        lang: 'km',
        imgSrc: 'https://flagcdn.com/w80/kh.png',
        alt: 'Khmer Flag',
        label: t('lang.km')
      },
      {
        lang: 'en',
        imgSrc: 'https://flagcdn.com/w80/gb.png',
        alt: 'English Flag',
        label: t('lang.en')
      }
    ]

    return languages
  })

  onMounted(() => {
    const savedLang = localStorage.getItem('lang')
    if (savedLang) {
      locale.value = savedLang
      if (proxy?.$vuetify?.locale) {
        proxy.$vuetify.locale.current = savedLang
      }
    }
  })
</script>