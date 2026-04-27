<script setup>
import { RouterLink, useRouter } from 'vue-router';
import { ref, onMounted, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';
import api from '@/plugins/axios';

const router = useRouter();
const { t, locale } = useI18n();
const authStore = useAuthStore();
const cartStore = useCartStore();
const wishlistStore = useWishlistStore();

const products = ref([]);
const categories = ref([]);
const cities = ref([]); 
const isLoading = ref(true);
const isLangOpen = ref(false);
const isCatOpen = ref(false);
const isMobileMenuOpen = ref(false);

// ── Search & Filter State ──
const searchQuery = ref('');
const selectedCityId = ref(null); 
const selectedParentId = ref(null);
const selectedChildId = ref(null);
const selectedChildren = ref([]);

// ── Recommendations State ──
const recommendations = ref({
  near_you: [],
  trending_addis: [],
  popular_jimma: []
});

const isBuyer = computed(() => authStore.isAuthenticated && Number(authStore.user?.role_id) === 1);
const isArtisan = computed(() => authStore.isAuthenticated && Number(authStore.user?.role_id) === 2);
const isDelivery = computed(() => authStore.isAuthenticated && Number(authStore.user?.role_id) === 3);

const languages = [{ code: 'en', name: 'English' }, { code: 'am', name: 'አማርኛ' }, { code: 'or', name: 'Afaan Oromoo' }];
const currentLangName = computed(() => languages.find(l => l.code === locale.value)?.name || 'Language');

const changeLanguage = (code) => {
  locale.value = code;
  localStorage.setItem('locale', code);
  isLangOpen.value = false;
  // Refetch data when language changes to update product names
  fetchProducts();
  fetchRecommendations();
};

// ── HELPERS (Matching your artisan/ProductList.vue logic) ──

// Map locale codes to Database IDs (if needed elsewhere)
const getLangId = (code) => {
  const map = { en: 1, am: 2, or: 3 };
  return map[code] || 1;
};

// Robust Image URL Helper
const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/400x300?text=No+Image';
    if (path.startsWith('http')) return path;
    
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    // Strip '/api' from the end if present, then add path
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

const getProductName = (product) => product.name || 'Product'; // Backend handles translation now
const getShopName = (product) => product.shop?.shop_name || 'Handy Artisan';
const getShopLogo = (product) => getImageUrl(product.shop?.logo);
const getProductImage = (product) => getImageUrl(product.image);
const formatPrice = (price) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(price || 0);

// ── Star Rating ──
const getStars = (rating) => Array.from({ length: 5 }, (_, i) => i < Math.round(rating));
// ── Category Actions ──
const selectParent = (cat) => {
    if (selectedParentId.value === cat.id) {
        selectedParentId.value = null;
        selectedChildId.value = null;
        selectedChildren.value = [];
    } else {
        selectedParentId.value = cat.id;
        selectedChildId.value = null;
        selectedChildren.value = cat.children || [];
    }
};

const selectCategory = (catId) => {
    selectedChildId.value = selectedChildId.value === catId ? null : catId;
    isCatOpen.value = false;
};

const clearFilter = () => {
    selectedParentId.value = null;
    selectedChildId.value = null;
    selectedChildren.value = [];
    searchQuery.value = ''; 
};

const activeFilterLabel = computed(() => {
    if (searchQuery.value) return t('home.search_results');
    if (selectedChildId.value) {
        const parent = categories.value.find(c => c.id === selectedParentId.value);
        return parent?.children?.find(c => c.id === selectedChildId.value)?.name || '';
    }
    if (selectedParentId.value) return categories.value.find(c => c.id === selectedParentId.value)?.name || '';
    return '';
});

// ── API Calls ──

// ... inside <script setup>

// Helper: Get GPS Location (Non-blocking)
const getCoords = async () => {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null);
    
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, long: pos.coords.longitude }),
      (err) => {
        console.warn("GPS denied or error:", err.message);
        resolve(null); // Resolve null instead of rejecting to prevent crash
      },
      { timeout: 3000 }
    );
  });
};

const fetchProducts = async () => {
    isLoading.value = true;
    try {
        const catId = selectedChildId.value || selectedParentId.value;
        const params = {
            lang: locale.value,
            category_id: catId,
            keyword: searchQuery.value,
            city_id: selectedCityId.value
        };

        // Try to get GPS, but don't wait forever
        const coords = await getCoords();
        if (coords) {
            params.lat = coords.lat;
            params.long = coords.long;
        }

        const response = await api.get('/products', { params });
        products.value = response.data?.data?.filter(p => p.is_in_stock) || [];
    } catch (error) {
        console.error("Fetch error", error);
        products.value = [];
    } finally {
        isLoading.value = false;
    }
};

const fetchRecommendations = async () => {
    try {
        const params = { lang: locale.value, city_id: selectedCityId.value };

        const coords = await getCoords();
        if (coords) {
            params.lat = coords.lat;
            params.long = coords.long;
        }
        
        const { data } = await api.get('/products/recommendations', { params });
        recommendations.value = data;
    } catch (e) {
        console.error("Recommendation error", e);
    }
};

// ... rest of code

const fetchCities = async () => {
    try {
        const { data } = await api.get('/cities');
        cities.value = data;
    } catch (e) {
        console.error("City fetch error", e);
    }
};

// Watchers
watch([selectedParentId, selectedChildId, searchQuery, selectedCityId], () => {
    fetchProducts();
});

const handleToggleWishlist = (product) => {
  if (!product) return;
  const isCurrentlyLiked = wishlistStore.likedIds.includes(product.id);
  product.likes_count = isCurrentlyLiked ? Math.max(0, (product.likes_count || 0) - 1) : (product.likes_count || 0) + 1;
  wishlistStore.toggleWishlist(product.id);
};

const quickAddToCart = async (product) => {
  if (!product.is_in_stock) return;
  const versionId = product.version_id;
  if (versionId) await cartStore.addToCart(product.id, versionId, 1);
};

onMounted(async () => {
  if (authStore.isAuthenticated) {
    await cartStore.fetchCart();
    if (isBuyer.value && wishlistStore.fetchWishlist) await wishlistStore.fetchWishlist();
  } else {
    if (cartStore.loadLocal) cartStore.loadLocal();
  }

  await Promise.all([ fetchProducts(), fetchCities(), fetchRecommendations() ]);

  try {
    const catRes = await api.get('/categories');
    categories.value = catRes.data;
  } catch (e) { /* silent */ }
});
</script>
<template>
  <div class="min-h-screen flex flex-col app-bg font-sans">
    
    <!-- NAVIGATION BAR -->
    <header class="bg-[var(--bg-card)] border-b border-[var(--border-soft)] sticky top-0 z-50">
      <div class="w-full px-4 sm:px-6 lg:px-12 h-16 md:h-20">
        <div class="flex justify-between items-center h-full max-w-[1920px] mx-auto gap-4">
          
          <!-- Logo -->
          <RouterLink to="/" class="flex items-center gap-1 flex-shrink-0">
            <span class="text-2xl sm:text-3xl font-extrabold text-[var(--primary)]">Handy</span>
            <span class="text-2xl sm:text-3xl font-extrabold text-[var(--accent)]">Store</span>
          </RouterLink>

          <!-- Desktop Search (Center) -->
          <div class="hidden lg:flex items-center gap-2 flex-grow max-w-xl mx-4">
            <div class="relative flex-grow">
              <input 
                v-model="searchQuery"
                type="text" 
                :placeholder="t('home.search_placeholder')"
                class="input w-full pl-10 pr-4 text-sm"
              />
              <svg class="absolute left-3 top-2.5 w-5 h-5 text-[var(--text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select v-model="selectedCityId" class="input w-32 text-sm appearance-none cursor-pointer flex-shrink-0">
              <option :value="null">{{ t('home.all_cities') }}</option>
              <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
          </div>

          <!-- Desktop Right Actions -->
          <div class="hidden md:flex items-center gap-4">
            
            <!-- Category Dropdown -->
            <div class="relative">
              <button @click="isCatOpen = !isCatOpen" class="nav-link flex items-center gap-1 font-medium">
                {{ activeFilterLabel || 'Categories' }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <div v-if="isCatOpen" class="absolute right-0 mt-2 w-56 bg-[var(--bg-card)] rounded-md shadow-lg py-1 border border-[var(--border-soft)] z-50 max-h-80 overflow-y-auto">
                <button @click="clearFilter(); isCatOpen = false" class="block w-full text-left px-4 py-2 text-sm hover:bg-[var(--bg-main)]" :class="{'bg-[var(--bg-main)] font-semibold': !selectedParentId}">{{ t('products.all') || 'All' }}</button>
                <button v-for="cat in categories" :key="cat.id" @click="selectParent(cat); isCatOpen = false" class="block w-full text-left px-4 py-2 text-sm hover:bg-[var(--bg-main)]" :class="{'bg-[var(--bg-main)] font-semibold': selectedParentId === cat.id}">{{ cat.name }}</button>
              </div>
            </div>

            <!-- Language Dropdown -->
            <div class="relative">
              <button @click="isLangOpen = !isLangOpen" class="nav-link flex items-center gap-1 font-medium">
                {{ currentLangName }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <div v-if="isLangOpen" class="absolute right-0 mt-2 w-40 bg-[var(--bg-card)] rounded-md shadow-lg py-1 border border-[var(--border-soft)] z-50">
                <button v-for="lang in languages" :key="lang.code" @click="changeLanguage(lang.code)" class="block w-full text-left px-4 py-2 text-sm hover:bg-[var(--bg-main)]" :class="{'bg-[var(--bg-main)] font-semibold': locale === lang.code}">{{ lang.name }}</button>
              </div>
            </div>

            <RouterLink to="/cart" class="relative p-2 text-[var(--text-muted)] hover:text-[var(--primary)]">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <span v-if="cartStore.count > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full font-bold">{{ cartStore.count }}</span>
            </RouterLink>

            <template v-if="isBuyer">
              <RouterLink to="/reels" class="nav-link font-medium">Reels</RouterLink>
              <RouterLink to="/orders" class="nav-link font-medium">Orders</RouterLink>
              <RouterLink to="/wishlist" class="nav-link">{{ t('nav.wishlist') }}</RouterLink>
              <RouterLink to="/account" class="nav-link">{{ t('nav.my_account') }}</RouterLink>
              <button @click="authStore.logout" class="btn-neutral rounded-full text-sm font-bold">{{ t('nav.logout') }}</button>
            </template>

            <template v-else-if="isArtisan || isDelivery">
              <RouterLink :to="isArtisan ? '/artisan/dashboard' : '/delivery/dashboard'" class="btn-primary rounded-full text-sm font-bold">Dashboard</RouterLink>
              <button @click="authStore.logout" class="btn-neutral rounded-full text-sm font-bold">{{ t('nav.logout') }}</button>
            </template>

            <template v-else>
              <RouterLink to="/login" class="nav-link font-medium">{{ t('nav.sign_in') }}</RouterLink>
              <RouterLink to="/register/buyer" class="btn-accent rounded-full text-sm font-bold">{{ t('nav.register') }}</RouterLink>
            </template>
          </div>

          <!-- Mobile Top Bar -->
          <div class="flex items-center gap-3 md:hidden">
            <RouterLink to="/cart" class="relative p-2 text-[var(--text-muted)]">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <span v-if="cartStore.count > 0" class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full">{{ cartStore.count }}</span>
            </RouterLink>
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="p-2 text-[var(--text-muted)] focus:outline-none">
              <svg v-if="!isMobileMenuOpen" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
              <svg v-else class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      </div> 
      
      <!-- MOBILE DRAWER -->
      <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 md:hidden" style="background-color: rgba(0,0,0,0.5);">
        <div class="fixed inset-y-0 right-0 w-full max-w-sm bg-[var(--bg-card)] shadow-2xl flex flex-col">
          <div class="p-6 border-b border-[var(--border-soft)] flex justify-between items-center bg-[var(--bg-main)]">
            <span class="text-xl font-bold text-[var(--text-main)]">Menu</span>
            <button @click="isMobileMenuOpen = false" class="text-[var(--text-muted)] hover:text-[var(--text-main)]"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
          </div>
          
          <div class="flex-grow p-6 space-y-4 overflow-y-auto">
            
            <!-- Mobile Search -->
            <div class="relative">
              <input v-model="searchQuery" type="text" :placeholder="t('home.search_placeholder')" class="input w-full pl-10 pr-4 text-sm" />
              <svg class="absolute left-3 top-2.5 w-5 h-5 text-[var(--text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select v-model="selectedCityId" class="input w-full text-sm appearance-none cursor-pointer">
              <option :value="null">{{ t('home.all_cities') }}</option>
              <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>

            <!-- Mobile Categories -->
            <div class="border-b border-[var(--border-soft)] pb-4">
              <p class="text-xs text-[var(--text-muted)] mb-2 font-semibold uppercase tracking-wide">Categories</p>
              <div class="flex flex-wrap gap-2">
                <button @click="clearFilter(); isMobileMenuOpen = false" class="px-3 py-1.5 rounded-lg text-sm font-medium transition" :class="!selectedParentId ? 'bg-[var(--primary)] text-white' : 'bg-[var(--bg-main)] text-[var(--text-muted)]'">{{ t('products.all') || 'All' }}</button>
                <button v-for="cat in categories" :key="cat.id" @click="selectParent(cat); isMobileMenuOpen = false" class="px-3 py-1.5 rounded-lg text-sm font-medium transition" :class="selectedParentId === cat.id ? 'bg-[var(--primary)] text-white' : 'bg-[var(--bg-main)] text-[var(--text-muted)]'">{{ cat.name }}</button>
              </div>
            </div>

            <!-- Language Switcher -->
            <div class="border-b border-[var(--border-soft)] pb-4">
              <p class="text-xs text-[var(--text-muted)] mb-2 font-semibold uppercase tracking-wide">Language</p>
              <div class="flex flex-wrap gap-2">
                <button v-for="lang in languages" :key="lang.code" @click="changeLanguage(lang.code); isMobileMenuOpen = false" class="px-4 py-2 rounded-lg text-sm font-medium transition" :class="locale === lang.code ? 'bg-[var(--primary)] text-white' : 'bg-[var(--bg-main)] text-[var(--text-muted)]'">{{ lang.name }}</button>
              </div>
            </div>

            <!-- Links -->
            <template v-if="isBuyer">
              <RouterLink to="/reels" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-[var(--text-main)] hover:bg-[var(--bg-main)] font-medium">Reels</RouterLink>
              <RouterLink to="/orders" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-[var(--text-main)] hover:bg-[var(--bg-main)] font-medium">Orders</RouterLink>
              <RouterLink to="/wishlist" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-[var(--text-main)] hover:bg-[var(--bg-main)] font-medium">{{ t('nav.wishlist') }}</RouterLink>
              <RouterLink to="/account" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-[var(--text-main)] hover:bg-[var(--bg-main)] font-medium">{{ t('nav.my_account') }}</RouterLink>
            </template>

            <template v-if="isArtisan || isDelivery">
              <RouterLink :to="isArtisan ? '/artisan/dashboard' : '/delivery/dashboard'" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-[var(--primary)] bg-[var(--bg-main)] font-bold">Go to Dashboard</RouterLink>
            </template>

            <!-- Auth Buttons -->
            <div class="pt-4 border-t border-[var(--border-soft)] space-y-3">
              <template v-if="isBuyer || isArtisan || isDelivery">
                <button @click="authStore.logout(); isMobileMenuOpen = false" class="btn-neutral w-full rounded-lg text-sm font-bold">{{ t('nav.logout') }}</button>
              </template>
              <template v-else>
                <RouterLink to="/login" @click="isMobileMenuOpen = false" class="btn-primary w-full text-center block rounded-lg text-sm font-bold">{{ t('nav.sign_in') }}</RouterLink>
                <RouterLink to="/register/buyer" @click="isMobileMenuOpen = false" class="btn-accent w-full text-center block rounded-lg text-sm font-bold">{{ t('nav.register') }}</RouterLink>
              </template>
            </div>

          </div>
        </div>
      </div>
    </header>

    <main class="flex-grow">
     
      <!-- COMPACT HERO SECTION -->
<section class="relative w-full overflow-hidden bg-gradient-to-br from-[var(--bg-card)] via-[var(--bg-card)] to-[var(--bg-sub)] border-b border-[var(--border-soft)]">
  
  <!-- Soft Decorative Background Blobs -->
  <div class="absolute -top-20 -left-20 w-64 h-64 bg-[var(--accent)] opacity-10 rounded-full blur-3xl"></div>
  <div class="absolute -bottom-20 right-10 w-48 h-48 bg-[var(--primary)] opacity-10 rounded-full blur-3xl"></div>

  <!-- ✅ CHANGED: justify-between to justify-center, and increased gap -->
  <div class="relative z-10 w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-12 py-8 md:py-12 flex items-center justify-center gap-8 md:gap-16">
    
    <!-- Left: Cute Animated Illustration -->
    <div class="flex-shrink-0 hidden sm:flex justify-center hero-float-animation">
      <svg class="w-36 h-36 md:w-44 md:h-44 drop-shadow-lg" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="40" y="110" width="120" height="80" rx="12" fill="var(--bg-sub)" stroke="var(--text-main)" stroke-width="4" stroke-linecap="round"/>
        <path d="M30 110 L100 80 L170 110" stroke="var(--text-main)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="var(--bg-sub)"/>
        <path d="M30 110 L100 85 L170 110" fill="var(--bg-card)" stroke="var(--text-main)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="80" y="85" width="40" height="15" rx="2" fill="var(--accent)" opacity="0.8"/>
        <path d="M100 85 C100 65, 95 50, 100 30" stroke="var(--primary)" stroke-width="4" stroke-linecap="round"/>
        <path d="M100 55 C85 45, 80 35, 90 30 C95 35, 100 45, 100 55Z" fill="var(--primary)" opacity="0.9"/>
        <path d="M100 45 C115 35, 120 25, 110 20 C105 25, 100 35, 100 45Z" fill="var(--accent)" opacity="0.9"/>
        <path d="M115 25 C115 20, 125 20, 125 25 C125 30, 115 35, 115 35 C115 35, 105 30, 105 25 C105 20, 115 20, 115 25Z" fill="#f87171" opacity="0.6" class="hero-heart-beat"/>
        <circle cx="65" cy="90" r="2" fill="var(--accent)" class="hero-sparkle" style="animation-delay: 0s;"/>
        <circle cx="140" cy="100" r="2" fill="var(--primary)" class="hero-sparkle" style="animation-delay: 0.5s;"/>
        <circle cx="75" cy="140" r="1.5" fill="var(--text-main)" opacity="0.3" class="hero-sparkle" style="animation-delay: 1s;"/>
      </svg>
    </div>

    <!-- ✅ CHANGED: Text Section - Aligned center on mobile, left on desktop, constrained width -->
    <div class="flex-1 text-center lg:text-left max-w-xl hero-fade-in-up">
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-[var(--text-main)] leading-tight tracking-tight">
        {{ t('hero.title_1') }} <br class="hidden sm:block"/>
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--accent)] to-[var(--primary)]">
          {{ t('hero.title_highlight') }}
        </span>
      </h1>
      
      <p class="text-sm sm:text-base text-[var(--text-muted)] mt-3 lg:mx-0 sm:mx-auto font-medium leading-relaxed">
        {{ t('hero.subtitle') }}
      </p>
      
      <div class="mt-4 inline-flex items-center gap-2.5 bg-white/50 dark:bg-black/20 backdrop-blur-sm border border-green-200 dark:border-green-900 px-4 py-2 rounded-full shadow-sm">
        <span class="text-lg hero-plant-grow">🌱</span>
        <p class="text-xs sm:text-sm text-[var(--primary)] font-semibold">
          {{ t('hero.badge') || 'Build a Green Legacy — plant a tree with every order.' }}
        </p>
      </div>
    </div>

  </div>
</section>
      <!-- RECOMMENDATIONS SECTION -->
      <section v-if="!searchQuery && !selectedParentId" class="bg-[var(--bg-main)] py-6">
         <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto space-y-8">
            <div v-if="recommendations.near_you && recommendations.near_you.length > 0">
              <h3 class="text-lg font-bold text-[var(--text-main)] mb-4">{{ t('home.near_you') }}</h3>
              <div class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide">
                <div v-for="product in recommendations.near_you" :key="product.id" @click="router.push(`/product/${product.id}`)" class="card min-w-[160px] sm:min-w-[200px] cursor-pointer">
                  <img :src="getProductImage(product)" class="w-full h-36 object-cover" />
                  <div class="p-3">
                    <h4 class="font-semibold text-sm truncate">{{ product.name }}</h4>
                    <p class="text-[var(--primary)] font-bold text-sm mt-1">{{ formatPrice(product.price) }}</p>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="recommendations.trending_addis && recommendations.trending_addis.length > 0">
               <h3 class="text-lg font-bold text-[var(--text-main)] mb-4">{{ t('home.trending_addis') }}</h3>
               <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                  <div v-for="product in recommendations.trending_addis" :key="product.id" @click="router.push(`/product/${product.id}`)" class="card cursor-pointer">
                    <img :src="getProductImage(product)" class="w-full h-32 object-cover" />
                    <div class="p-2.5">
                      <h4 class="font-semibold text-xs truncate">{{ product.name }}</h4>
                      <p class="text-[var(--primary)] font-bold text-xs mt-1">{{ formatPrice(product.price) }}</p>
                    </div>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <!-- PRODUCT SECTION HEADING -->
      <section class="py-4 md:py-6 bg-[var(--bg-card)] w-full">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto flex items-center justify-between">
          <div>
            <h2 class="text-xl md:text-2xl font-bold text-[var(--text-main)]">
              <span v-if="activeFilterLabel">{{ activeFilterLabel }}</span>
              <span v-else>{{ t('products.heading') || 'All Products' }}</span>
            </h2>
            <p class="text-sm text-[var(--text-muted)] mt-1">{{ products.length }} {{ t('products.items') || 'items' }}</p>
          </div>
          <button v-if="selectedParentId || searchQuery" @click="clearFilter" class="text-sm text-[var(--primary)] hover:text-[var(--primary-hover)] font-medium">Clear</button>
        </div>
      </section>

      <!-- PRODUCT GRID -->
      <section class="py-4 md:py-8 bg-[var(--bg-main)] w-full">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto">
          <div v-if="isLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
             <div v-for="n in 8" :key="n" class="card animate-pulse"><div class="aspect-square bg-[var(--bg-main)]"></div></div>
          </div>

          <div v-else-if="products.length === 0" class="text-center py-20">
             <p class="text-[var(--text-muted)]">No products found</p>
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
            <div v-for="product in products" :key="product.id" class="group relative flex flex-col card cursor-pointer">
               <RouterLink :to="`/product/${product.id}`" class="block relative aspect-square bg-[var(--bg-main)] overflow-hidden">
                <img :src="getProductImage(product)" :alt="getProductName(product)" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                <div v-if="!product.is_in_stock" class="absolute top-2 left-2 z-10 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow">Sold Out</div>
               </RouterLink>
               <div class="p-3 space-y-1.5 border-t border-[var(--border-soft)]">
                 <h3 class="text-[var(--text-main)] font-bold text-sm leading-tight truncate">{{ getProductName(product) }}</h3>
                 <div class="flex items-center justify-between pt-1">
                   <span class="text-lg font-extrabold" :class="product.is_in_stock ? 'text-[var(--text-main)]' : 'text-gray-400 line-through'">{{ formatPrice(product.price) }}</span>
                   <button v-if="authStore.isAuthenticated" @click="handleToggleWishlist(product)" class="p-1.5 rounded-full transition" :class="wishlistStore.likedIds.includes(product.id) ? 'text-red-500' : 'text-gray-400 hover:text-red-400'">
                     <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                   </button>
                 </div>
               </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="bg-[var(--text-main)] text-white pt-16 pb-8 w-full">
  <div class="w-full px-6 lg:px-12 max-w-[1920px] mx-auto">
    
    <!-- Top Grid Section -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
      
      <!-- Column 1: Branding -->
      <div class="sm:col-span-2 lg:col-span-1">
        <h2 class="text-2xl font-extrabold tracking-tight mb-4">
          Handy<span class="text-emerald-400">Store</span>
        </h2>
        <p class="text-sm text-gray-400 leading-relaxed max-w-xs">
          {{ t('footer.brand_desc') }}
        </p>
      </div>

      <!-- Column 2: Registration Links -->
      <div>
        <h3 class="text-white font-semibold text-sm uppercase tracking-wider mb-5">
          {{ t('footer.join_title') }}
        </h3>
        <ul class="space-y-3">
          <li>
            <router-link to="/register/artisan" class="group flex items-center gap-2 text-gray-400 hover:text-emerald-400 transition-colors duration-200 text-sm">
              <span class="w-1.5 h-1.5 bg-gray-600 rounded-full group-hover:bg-emerald-400 transition-colors"></span>
              {{ t('footer.link_artisan') }}
            </router-link>
          </li>
          <li>
            <router-link to="/register/delivery" class="group flex items-center gap-2 text-gray-400 hover:text-emerald-400 transition-colors duration-200 text-sm">
              <span class="w-1.5 h-1.5 bg-gray-600 rounded-full group-hover:bg-emerald-400 transition-colors"></span>
              {{ t('footer.link_delivery') }}
            </router-link>
          </li>
        </ul>
      </div>

      <!-- Column 3: Contact Info -->
      <div>
        <h3 class="text-white font-semibold text-sm uppercase tracking-wider mb-5">
          {{ t('footer.contact_title') }}
        </h3>
        <ul class="space-y-4 text-sm text-gray-400">
          <li class="flex items-start gap-3">
            <svg class="w-4 h-4 mt-0.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span dir="ltr" class="text-left w-full">+251 991876250</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="w-4 h-4 mt-0.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>support@handystore.com</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="w-4 h-4 mt-0.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>{{ t('footer.address_text') }}</span>
          </li>
        </ul>
      </div>

      <!-- Column 4: Social Media -->
      <div>
        <h3 class="text-white font-semibold text-sm uppercase tracking-wider mb-5">
          {{ t('footer.social_title') }}
        </h3>
        <div class="flex gap-3">
          <a href="#" target="_blank" class="w-9 h-9 bg-gray-800 text-gray-400 rounded-lg flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all duration-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </a>
          <a href="#" target="_blank" class="w-9 h-9 bg-gray-800 text-gray-400 rounded-lg flex items-center justify-center hover:bg-sky-500 hover:text-white transition-all duration-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
          </a>
          <a href="#" target="_blank" class="w-9 h-9 bg-gray-800 text-gray-400 rounded-lg flex items-center justify-center hover:bg-gradient-to-tr hover:from-yellow-500 hover:via-pink-500 hover:to-purple-500 hover:text-white transition-all duration-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
          </a>
          <a href="#" target="_blank" class="w-9 h-9 bg-gray-800 text-gray-400 rounded-lg flex items-center justify-center hover:bg-black hover:text-white transition-all duration-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
          </a>
        </div>
      </div>
    </div>

    <!-- Bottom Copyright Section -->
    <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
      <p class="text-gray-500 text-sm">© 2026 HandyStore. {{ t('footer.rights') }}</p>
      <div class="flex gap-6 text-sm text-gray-500">
        <a href="/terms-and-conditions" class="hover:text-white transition-colors">{{ t('footer.terms') }}</a>
        <a href="/privacy-policy" class="hover:text-white transition-colors">{{ t('footer.privacy') }}</a>
      </div>
    </div>

  </div>
</footer>
  </div>
</template>
<!-- Custom Scoped Animations (Unchanged) -->
<style scoped>
.hero-float-animation {
  animation: float 6s ease-in-out infinite;
}
@keyframes float {
  0% { transform: translateY(0px) rotate(0deg); }
  50% { transform: translateY(-15px) rotate(2deg); }
  100% { transform: translateY(0px) rotate(0deg); }
}
.hero-fade-in-up {
  animation: fade-in-up 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  opacity: 0;
}
@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.hero-heart-beat {
  animation: heartbeat 2s ease-in-out infinite;
  transform-origin: center;
}
@keyframes heartbeat {
  0%, 100% { transform: scale(1); }
  25% { transform: scale(1.2); }
  50% { transform: scale(1); }
}
.hero-sparkle {
  animation: sparkle 2s ease-in-out infinite;
}
@keyframes sparkle {
  0%, 100% { opacity: 0.2; transform: scale(1); }
  50% { opacity: 0.8; transform: scale(1.5); }
}
.hero-plant-grow {
  display: inline-block;
  animation: pop 3s ease-in-out infinite;
}
@keyframes pop {
  0%, 100% { transform: scale(1) rotate(0deg); }
  10% { transform: scale(1.2) rotate(-10deg); }
  20% { transform: scale(1) rotate(0deg); }
}
</style>