import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import am from '@/locales/am.json';
import or from '@/locales/or.json';

const messages = { en, am, or };

// Get saved language or default to 'en'
const savedLocale = localStorage.getItem('locale') || 'en';

const i18n = createI18n({
    legacy: false, // Use Composition API
    locale: savedLocale,
    fallbackLocale: 'en',
    messages,
});

export default i18n;