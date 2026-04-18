<script setup>
import { ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';

const authStore = useAuthStore();
const { t } = useI18n();

// Local state for form inputs
const email = ref('');
const password = ref('');

// ---------------------------------------------------------
// UX Improvement: Clear errors when user starts typing
// ---------------------------------------------------------
watch([email, password], () => {
    if (authStore.errors) {
        authStore.errors = null;
    }
});

const submit = async () => {
    // Reset errors manually before submitting (optional, but good practice)
    authStore.errors = null;
    
    // Call the login action from the Pinia store
    // The store handles the API call, setting the user, and redirection
    await authStore.login({
        email: email.value,
        password: password.value
    });
};
</script>

<template>
    <!-- Centered Layout -->
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100">
        <div class="w-full max-w-md p-8 bg-white rounded-xl border border-gray-200 shadow-sm">
            
            <!-- Header -->
            <div class="mb-8 text-center">
                <!-- Branding -->
                <div class="flex justify-center gap-1 mb-4">
                    <span class="text-3xl font-extrabold text-emerald-600">Handy</span>
                    <span class="text-3xl font-extrabold text-amber-400">Store</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $t('auth.login_title') }}</h1>
                <p class="text-gray-500 mt-1">{{ $t('auth.login_desc') }}</p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                
                <!-- Global Error Message -->
                <div v-if="authStore.errors && authStore.errors.email" class="bg-red-50 border-l-4 border-red-400 p-4 rounded" role="alert">
                    <p class="text-red-700 text-sm">{{ authStore.errors.email[0] }}</p>
                </div>
                <div class="grid gap-5">
                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('user.email') }}</label>
                        <input
                            id="email"
                            type="email"
                            v-model="email"
                            required
                            autofocus
                            placeholder="email@example.com"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                    </div>
                    
                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('user.password') }}</label>
                        <input
                            id="password"
                            type="password"
                            v-model="password"
                            required
                            placeholder="••••••••"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                    </div>
                    
                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition disabled:opacity-75"
                        :disabled="authStore.isLoading" 
                    >
                        <span v-if="!authStore.isLoading">{{ $t('auth.sign_in') }}</span>
                        <span v-else class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ $t('common.loading') }}
                        </span>
                    </button>
                </div>
                
                <!-- Links -->
                <div class="text-center text-sm text-gray-500 mt-2">
                    {{ $t('auth.no_account') }} 
                    <RouterLink to="/register/buyer" class="text-emerald-600 hover:text-emerald-700 font-medium">
                        {{ $t('auth.register_link') }}
                    </RouterLink>
                </div>
            </form>
        </div>
    </div>
</template>