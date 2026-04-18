<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from 'vue-i18n';
import api from '@/plugins/axios';

const router = useRouter();
const authStore = useAuthStore();
const { t, locale } = useI18n();

const form = reactive({
    first_name: '',
    last_name: '',
    phone_number: '',
    gender: '',
    birthdate: '',
    locale: 'en'
    // Removed Password fields
});

const isLoading = ref(false);
const errors = ref({});

onMounted(() => {
    if (authStore.user) {
        form.first_name = authStore.user.first_name;
        form.last_name = authStore.user.last_name;
        form.phone_number = authStore.user.phone_number;
        form.gender = authStore.user.gender;
        form.birthdate = authStore.user.birthdate;
        form.locale = authStore.user.locale || 'en';
    }
});

const submit = async () => {
    isLoading.value = true;
    errors.value = {};

    try {
        const response = await api.put('/user/profile', form);
        
        // Update Store & LocalStorage
        authStore.user = response.data.user;
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        // Update Language if changed
        if (response.data.user.locale) {
            locale.value = response.data.user.locale;
            localStorage.setItem('locale', response.data.user.locale);
        }

        alert(t('common.success_update'));
        router.push('/'); 

    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors;
            const firstError = Object.values(errors.value)[0];
            alert(firstError[0]);
        } else {
            console.error(error);
            alert(t('common.error_occurred'));
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
  <div class="min-h-screen bg-gray-100 py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $t('profile.title') }}</h1>

      <form @submit.prevent="submit" class="space-y-6">
        
        <!-- Personal Info Section -->
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">{{ $t('profile.personal_info') }}</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('user.first_name') }} *</label>
              <input type="text" v-model="form.first_name" required 
                class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-emerald-500"
                :class="{'border-red-500': errors.first_name}">
              <p v-if="errors.first_name" class="text-red-500 text-xs mt-1">{{ errors.first_name[0] }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('user.last_name') }} *</label>
              <input type="text" v-model="form.last_name" required 
                class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-emerald-500"
                :class="{'border-red-500': errors.last_name}">
              <p v-if="errors.last_name" class="text-red-500 text-xs mt-1">{{ errors.last_name[0] }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('user.phone') }} *</label>
              <input type="tel" v-model="form.phone_number" required 
                class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-emerald-500"
                :class="{'border-red-500': errors.phone_number}">
              <p v-if="errors.phone_number" class="text-red-500 text-xs mt-1">{{ errors.phone_number[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('user.birthdate') }} *</label>
              <input type="date" v-model="form.birthdate" required 
                class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-emerald-500"
                :class="{'border-red-500': errors.birthdate}">
              <p v-if="errors.birthdate" class="text-red-500 text-xs mt-1">{{ errors.birthdate[0] }}</p>
            </div>

            <!-- Gender -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('user.gender') }} *</label>
              <div class="flex items-center space-x-6 mt-1">
                <label class="flex items-center cursor-pointer">
                  <input type="radio" v-model="form.gender" value="male" class="w-4 h-4 text-emerald-600">
                  <span class="ml-2 text-sm text-gray-700">{{ $t('gender.male') }}</span>
                </label>
                <label class="flex items-center cursor-pointer">
                  <input type="radio" v-model="form.gender" value="female" class="w-4 h-4 text-emerald-600">
                  <span class="ml-2 text-sm text-gray-700">{{ $t('gender.female') }}</span>
                </label>
              </div>
              <p v-if="errors.gender" class="text-red-500 text-xs mt-1">{{ errors.gender[0] }}</p>
            </div>

          </div>
        </div>

        <!-- Language Section -->
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">{{ $t('account.language_pref') }}</h2>
          <p class="text-sm text-gray-500 mb-4">{{ $t('account.language_desc') }}</p>

          <div class="grid grid-cols-3 gap-4">
            <button type="button" @click="form.locale = 'en'"
              :class="form.locale === 'en' ? 'bg-emerald-50 border-emerald-600 text-emerald-700 font-semibold' : 'bg-gray-50'"
              class="p-4 border rounded-lg text-center transition">
              {{ $t('common.english') }}
            </button>
            <button type="button" @click="form.locale = 'am'"
              :class="form.locale === 'am' ? 'bg-emerald-50 border-emerald-600 text-emerald-700 font-semibold' : 'bg-gray-50'"
              class="p-4 border rounded-lg text-center transition">
              {{ $t('common.amharic') }}
            </button>
            <button type="button" @click="form.locale = 'or'"
              :class="form.locale === 'or' ? 'bg-emerald-50 border-emerald-600 text-emerald-700 font-semibold' : 'bg-gray-50'"
              class="p-4 border rounded-lg text-center transition">
              {{ $t('common.oromo') }}
            </button>
          </div>
        </div>

        <!-- REMOVED: Password Section -->

        <!-- Submit Button -->
        <button type="submit" :disabled="isLoading"
            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow disabled:opacity-50 transition">
            {{ isLoading ? $t('common.saving') : $t('common.save_changes') }}
        </button>

      </form>
    </div>
  </div>
</template>