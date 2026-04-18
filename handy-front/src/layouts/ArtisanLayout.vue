<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { t, locale } = useI18n();

const userName = computed(() => {
    return authStore.user?.name || 'Artisan';
});

const pageTitle = computed(() => {
    if (route.path.includes('products')) return t('nav.products');
    if (route.path.includes('videos')) return t('nav.videos'); // ✅ Added Videos Title
    if (route.path.includes('orders')) return t('nav.orders');
    if (route.path.includes('wallet')) return t('nav.wallet');
    return t('nav.dashboard');
});

const logout = async () => {
  try {
    await authStore.logout();
  } catch (e) { console.error(e); }
  router.push('/login');
};
</script>

<template>
  <div class="min-h-screen flex bg-gray-50 font-sans">
    
    <!-- Sidebar -->
    <aside class="w-64 bg-green-50 border-r border-green-200 text-green-900 flex flex-col shadow-sm">
      
      <!-- Branding -->
      <div class="h-20 flex items-center px-6 border-b border-green-200 bg-white">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center shadow">
            <span class="text-white font-bold text-xl">H</span>
          </div>
          <div>
            <span class="block text-lg font-bold text-green-800">Handy</span>
            <span class="block text-xs text-green-600">{{ $t('nav.artisan_panel') }}</span>
          </div>
        </div>
      </div>
      
      <!-- Navigation -->
      <nav class="flex-1 py-6 px-4 space-y-1">
        <p class="px-4 text-xs font-bold text-green-600 uppercase tracking-wider mb-4">{{ $t('nav.menu') }}</p>

        <router-link to="/artisan/dashboard" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path === '/artisan/dashboard' ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">📊</span>
            <span>{{ $t('nav.dashboard') }}</span>
        </router-link>

        <router-link to="/artisan/products" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path.includes('/artisan/products') ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">📦</span>
            <span>{{ $t('nav.products') }}</span>
        </router-link>

        <!-- ✅ ADDED VIDEOS LINK -->
        <router-link to="/artisan/videos" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path.includes('/artisan/videos') ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">🎥</span>
            <span>{{ $t('nav.videos') }}</span>
        </router-link>

        <router-link to="/artisan/orders" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path === '/artisan/orders' ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">🛒</span>
            <span>{{ $t('nav.orders') }}</span>
        </router-link>
          <router-link to="/artisan/wallet" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path === '/artisan/wallet' ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">💰</span>
            <span>{{ $t('nav.wallet') }}</span>
        </router-link>
      </nav>

      <!-- User Section -->
      <div class="p-4 border-t border-green-200 bg-white">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-green-200 flex items-center justify-center text-green-700 font-bold border border-green-300">
                {{ userName.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ userName }}</p>
                <p class="text-xs text-gray-500">{{ $t('nav.artisan') }}</p>
            </div>
        </div>
        
        <button @click="logout" 
            class="w-full flex items-center gap-3 px-4 py-2 rounded-lg text-red-600 hover:bg-red-50 transition-colors text-sm font-medium">
            <span>🚪</span> 
            <span>{{ $t('logout') }}</span>
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col bg-gray-50">
      
      <!-- Top Header -->
      <header class="h-20 bg-white shadow-sm flex items-center justify-between px-8 border-b border-gray-200 z-10">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ pageTitle }}</h1>
        </div>
        
        <div class="flex items-center gap-4">
            <LanguageSwitcher />
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-8 overflow-y-auto">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>