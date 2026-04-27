<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { t } = useI18n();

const userName = computed(() => {
    return authStore.user?.name || 'Artisan';
});

const pageTitle = computed(() => {
    if (route.path.includes('products')) return t('nav.products');
    if (route.path.includes('videos')) return t('nav.videos');
    if (route.path.includes('orders')) return t('nav.orders');
    if (route.path.includes('wallet')) return t('nav.wallet');
    if (route.path.includes('profile')) return t('nav.profile');
    return t('nav.dashboard');
});

// ✅ Sidebar State
const isSidebarOpen = ref(false);

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

// ✅ Close sidebar automatically when navigating on mobile
watch(() => route.path, () => {
    isSidebarOpen.value = false;
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
    
    <!-- ✅ Mobile Backdrop Overlay -->
    <transition 
      enter-active-class="transition-opacity ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isSidebarOpen" 
        @click="isSidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm md:hidden"
      ></div>
    </transition>

    <!-- ✅ Sidebar -->
    <aside 
      class="
        fixed inset-y-0 z-40 w-64 bg-green-50 text-green-900 flex flex-col shadow-sm
        ltr:left-0 rtl:right-0
        ltr:border-r rtl:border-l ltr:border-r-green-200 rtl:border-l-green-200
        transform transition-transform duration-300 ease-in-out
        ltr:-translate-x-full rtl:translate-x-full
        md:ltr:translate-x-0 md:rtl:translate-x-0
        md:relative md:z-auto md:shadow-none
      " 
      :class="{ 'ltr:translate-x-0 rtl:translate-x-0': isSidebarOpen }"
    >
      
      <!-- Branding -->
      <div class="h-20 flex items-center px-6 ltr:border-r rtl:border-l ltr:border-r-green-200 rtl:border-l-green-200 bg-white shrink-0">
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
      <nav class="flex-1 py-6 px-4 space-y-1 overflow-y-auto">
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

        <router-link to="/artisan/profile" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path.includes('/artisan/profile') ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">🛠️</span>
            <span>{{ $t('nav.profile') }}</span>
        </router-link>
      </nav>

      <!-- User Section -->
      <div class="p-4 ltr:border-r rtl:border-l ltr:border-r-green-200 rtl:border-l-green-200 bg-white shrink-0">
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
    <div class="flex-1 flex flex-col bg-gray-50 min-h-screen w-full">
      
      <!-- ✅ Top Header -->
      <header class="h-16 md:h-20 bg-white shadow-sm flex items-center justify-between px-4 md:px-8 border-b border-gray-200 z-50 relative">
        
        <!-- Left Side: Hamburger + Title -->
        <div class="flex items-center gap-3">
          <!-- Hamburger Menu Button (Mobile Only) -->
          <button 
            @click="toggleSidebar" 
            class="md:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition-colors"
          >
            <!-- Hamburger Icon -->
            <svg v-if="!isSidebarOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <!-- Close X Icon -->
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
          
          <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">{{ pageTitle }}</h1>
        </div>
        
        <!-- Right Side: Language Switcher -->
        <div class="flex items-center gap-4 shrink-0">
            <LanguageSwitcher />
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-4 md:p-8 overflow-y-auto">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>