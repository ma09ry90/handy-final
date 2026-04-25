<script setup>
import { ref, reactive } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import api from '@/plugins/axios';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const authStore = useAuthStore();
const { t, locale } = useI18n(); // Get translation function and current locale

const form = reactive({
    first_name: '',
    last_name: '',
    gender: '',
    email: '',
    phone_number: '',
    birthdate: '',
    password: '',
    password_confirmation: ''
});

const errors = ref({});
const isLoading = ref(false);
const successMessage = ref('');  

const submit = async () => {
    isLoading.value = true;
    errors.value = {};
    successMessage.value = '';
    
    try {
        // Add the current locale to the payload
        const payload = {
            ...form,
            locale: locale.value 
        };

        // --- DEBUG TEST ---
        // We manually force the header here to test if backend responds
        const config = {
            headers: {
                'Accept-Language': locale.value // <--- FORCING THE HEADER
            }
        };

        const response = await api.post('/register/buyer', payload);
        // SUCCESS! Backend returns: { message: "Registration successful. Please check..." }
        successMessage.value = response.data.message;
        
        // Optional: Redirect to login after 4 seconds
        setTimeout(() => {
            router.push('/login');
        }, 40);
        
        /*const { access_token, user } = response.data;

        // Update Auth Store
        authStore.token = access_token;
        authStore.user = user;
        
        // Save to LocalStorage
        localStorage.setItem('token', access_token);
        localStorage.setItem('user', JSON.stringify(user));*/

        // Redirect to home
        router.push('/');

    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
            console.log("Validation Errors:", errors.value);
        } else {
            errors.value.email = [t('common.error_occurred')];
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-white py-12 px-4">
        <div class="w-full max-w-md p-8 bg-white rounded-xl border border-gray-200 shadow-sm">
            
            <!-- Header -->
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold text-gray-900">{{ $t('buyer.register_title') }}</h1>
                <p class="text-gray-500 mt-1">{{ $t('buyer.register_desc') }}</p>
            </div>
            <!-- NEW: Success Message Block -->
            <div v-if="successMessage" class="max-w-md mx-auto p-6 bg-white shadow rounded-lg text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Registration Successful!</h3>
                <p class="text-gray-600 mb-4">{{ successMessage }}</p>
                <p class="text-sm text-gray-500">Redirecting to login...</p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-5">
                
                <!-- Name Fields -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ $t('user.first_name') }}</label>
                        <input 
                            type="text" 
                            v-model="form.first_name" 
                            required 
                            :placeholder="$t('user.first_name')" 
                            class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                        />
                        <p v-if="errors.first_name" class="text-red-500 text-xs mt-1">{{ errors.first_name[0] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ $t('user.last_name') }}</label>
                        <input 
                            type="text" 
                            v-model="form.last_name" 
                            required 
                            :placeholder="$t('user.last_name')" 
                            class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                        />
                        <p v-if="errors.last_name" class="text-red-500 text-xs mt-1">{{ errors.last_name[0] }}</p>
                    </div>
                </div>
                <!-- Gender Radio Buttons -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('user.gender') }} *</label>
                    <div class="flex items-center space-x-6 mt-1">
                        <!-- Male -->
                        <label class="flex items-center cursor-pointer">
                            <input 
                                type="radio" 
                                v-model="form.gender" 
                                value="male" 
                                name="gender_buyer"
                                class="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 focus:ring-emerald-500"
                            >
                            <span class="ml-2 text-sm text-gray-700">{{ $t('gender.male') }}</span>
                        </label>

                        <!-- Female -->
                        <label class="flex items-center cursor-pointer">
                            <input 
                                type="radio" 
                                v-model="form.gender" 
                                value="female" 
                                name="gender_buyer"
                                class="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 focus:ring-emerald-500"
                            >
                            <span class="ml-2 text-sm text-gray-700">{{ $t('gender.female') }}</span>
                        </label>
                    </div>
                    <p v-if="errors.gender" class="text-red-500 text-xs mt-1">{{ errors.gender[0] }}</p>
                </div>
                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.email') }}</label>
                    <input 
                        type="email" 
                        v-model="form.email" 
                        required 
                        placeholder="email@example.com" 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                    />
                    <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.phone') }}</label>
                    <input 
                        type="text" 
                        v-model="form.phone_number" 
                        required 
                        placeholder="0912345678" 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                    />
                    <p class="text-xs text-gray-400 mt-1">{{ $t('buyer.phone_hint') }}</p>
                    <p v-if="errors.phone_number" class="text-red-500 text-xs mt-1">{{ errors.phone_number[0] }}</p>
                </div>

                <!-- Birthdate -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.birthdate') }}</label>
                    <input 
                        type="date" 
                        v-model="form.birthdate" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                    />
                    <p class="text-xs text-gray-400 mt-1">{{ $t('buyer.age_hint') }}</p>
                    <p v-if="errors.birthdate" class="text-red-500 text-xs mt-1">{{ errors.birthdate[0] }}</p>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.password') }}</label>
                    <input 
                        type="password" 
                        v-model="form.password" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                    />
                    <p v-if="errors.password" class="text-red-500 text-xs mt-1">{{ errors.password[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.password_confirm') }}</label>
                    <input 
                        type="password" 
                        v-model="form.password_confirmation" 
                        required 
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" 
                    />
                </div>

                <button 
                    type="submit" 
                    :disabled="isLoading" 
                    class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-md shadow-sm transition duration-200 disabled:opacity-50"
                >
                    {{ isLoading ? $t('common.saving') : $t('common.register') }}
                </button>
            </form>

            <div class="text-center text-sm text-gray-500 mt-6">
                {{ $t('auth.have_account') }}
                <RouterLink to="/login" class="text-emerald-600 hover:underline font-medium">{{ $t('auth.sign_in') }}</RouterLink>
            </div>
        </div>
    </div>
</template>