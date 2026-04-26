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
      <section class="relative w-full bg-[var(--bg-card)] border-b border-[var(--border-soft)]">
        <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-12 py-4 md:py-6 flex items-center justify-between gap-4 md:gap-8">
          
          <!-- Left: Image -->
          <div class="flex-shrink-0 hidden sm:flex justify-center">
            <img 
              src="../assets/images/front.jpg" 
              alt="HandyStore Hero" 
              class="h-24 md:h-32 lg:h-40 w-auto object-contain rounded-lg"
            />
          </div>

          <!-- Right: Text (Aligned Right) -->
          <div class="flex-1 text-center sm:text-right">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-[var(--text-main)] leading-tight">
              {{ t('hero.title_1') }} <span class="text-[var(--accent)]">{{ t('hero.title_highlight') }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-[var(--text-muted)] mt-1">
              {{ t('hero.subtitle') }}
            </p>
            <div class="mt-2 inline-flex items-center gap-2 bg-green-50 border border-green-100 px-3 py-1.5 rounded-full">
              <span class="text-green-600 text-sm">🌱</span>
              <p class="text-xs text-[var(--primary)] font-medium">Build a Green Legacy — plant a tree with every order.</p>
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
       <div class="w-full px-6 lg:px-12 max-w-[1920px] mx-auto text-center text-gray-500 text-sm">© 2024 HandyStore. All rights reserved.</div>
    </footer>
  </div>
</template>
<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>