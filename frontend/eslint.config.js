// import js from '@eslint/js';
// import pluginVue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser'; // Import the parser
export default [
  // 1. Global Ignores
  {
    ignores: ['dist/**', 'node_modules/**', 'public/**'],
  },

  // 2. Base JavaScript Recommended Rules
  // js.configs.recommended,

  // 3. Vue 3 Recommended Rules
  // ...pluginVue.configs['flat/recommended'],

  // 4. Custom Rules and Language Options
  {
    files: ['**/*.js', '**/*.vue'],
    languageOptions: {
      ecmaVersion: 'latest',
      parser: vueParser,
      sourceType: 'module',
      globals: {
        // This adds browser globals like 'window' or 'document'
        window: 'readonly',
        document: 'readonly',
        process: 'readonly',
      },
    },
    rules: {
      // Customize your rules here
      'vue/multi-word-component-names': 'off', // Allows SingleWord.vue components
      'no-unused-vars': 'warn',
      'no-console': 'warn',
    },
  },
];