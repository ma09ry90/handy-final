<script setup>
import { useI18n } from 'vue-i18n';
import api from '@/plugins/axios';

const { locale } = useI18n();

const languages = [
  { code: 'en', name: 'English' },
  { code: 'am', name: 'አማርኛ' },
  { code: 'or', name: 'Afaan Oromoo' }
];

const changeLanguage = async (langCode) => {
  locale.value = langCode;
  localStorage.setItem('locale', langCode);

  // Update user preference in database if logged in
  if (localStorage.getItem('token')) {
    try {
      await api.post('/user/locale', { locale: langCode });
    } catch (e) {
      console.error('Failed to save language preference');
    }
  }
};
</script>

<template>
  <div class="flex items-center gap-1">
    <button 
      v-for="lang in languages" 
      :key="lang.code"
      @click="changeLanguage(lang.code)"
      :class="[
        'px-2 py-1 text-xs font-medium rounded transition-colors',
        locale === lang.code 
          ? 'bg-emerald-600 text-white' 
          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
      ]"
    >
      {{ lang.name }}
    </button>
  </div>
</template>