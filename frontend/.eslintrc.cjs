/* eslint-env node */
require('@vue/eslint-config-typescript')

module.exports = {
  root: true,
  extends: [
    'plugin:vue/vue3-essential',
    'eslint:recommended',
    '@vue/eslint-config-typescript'
  ],
  parserOptions: {
    ecmaVersion: 'latest'
  },
  rules: {
    // В проекте активно используются односоставные имена view-компонентов
    'vue/multi-word-component-names': 'off',
    // Vuetify использует синтаксис item.actions для слотов, который конфликтует с этой проверкой
    'vue/valid-v-slot': 'off'
  },
  ignorePatterns: [
    'dev-dist/**',
    'vendor/**',
    'public/sw.js'
  ]
}








