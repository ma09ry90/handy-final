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
const cities = ref([]); // NEW: For location filter
const isLoading = ref(true);
const isLangOpen = ref(false);
const isMobileMenuOpen = ref(false);

// ── Search & Filter State ──
const searchQuery = ref('');
const selectedCityId = ref(null); // NEW: Location filter
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
};

// ── Helpers ──
const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/400x300?text=No+Image';
    if (path.startsWith('http')) return path;
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};
const getProductName = (product) => product.name || 'Product';
const getShopName = (product) => product.shop?.shop_name || 'Handy Artisan';
const getShopLogo = (product) => getImageUrl(product.shop?.logo);
const getProductImage = (product) => getImageUrl(product.image);
const formatPrice = (price) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(price || 0);

// ── Star Rating ──
const getStars = (rating) => {
    return Array.from({ length: 5 }, (_, i) => i < Math.round(rating));
};

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
    if (selectedChildId.value === catId) {
        selectedChildId.value = null;
    } else {
        selectedChildId.value = catId;
    }
};

const clearFilter = () => {
    selectedParentId.value = null;
    selectedChildId.value = null;
    selectedChildren.value = [];
    searchQuery.value = ''; // Clear search too
};

const activeFilterLabel = computed(() => {
    if (searchQuery.value) return t('home.search_results'); // "Search Results"
    if (selectedChildId.value) {
        const parent = categories.value.find(c => c.id === selectedParentId.value);
        const child = parent?.children?.find(c => c.id === selectedChildId.value);
        return child?.name || '';
    }
    if (selectedParentId.value) {
        return categories.value.find(c => c.id === selectedParentId.value)?.name || '';
    }
    return '';
});

// ── API Calls ──// 1. Fetch Products (Search + Filter)
const fetchProducts = async () => {
    isLoading.value = true;
    try {
        const catId = selectedChildId.value || selectedParentId.value;
        const params = {};
        if (catId) params.category_id = catId;
        if (searchQuery.value) params.keyword = searchQuery.value;
        if (selectedCityId.value) params.city_id = selectedCityId.value; // Location Filter

        const response = await api.get('/products', { params });
        if (response.data?.data?.length > 0) {
          products.value = response.data.data.filter(p => p.is_in_stock);
      } else {
          products.value = [];
      }
    } catch (error) {
        console.error("Fetch error", error);
        products.value = [];
    } finally {
        isLoading.value = false;
    }
};

// 2. Fetch Recommendations (Home Page)
const fetchRecommendations = async () => {
    try {
        const params = {};
        if (selectedCityId.value) params.city_id = selectedCityId.value;
        
        const { data } = await api.get('/products/recommendations', { params });
        recommendations.value = data;
    } catch (e) {
        console.error("Recommendation error", e);
    }
};

// 3. Fetch Cities for Filter
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
    // If user filters or searches, we might want to hide recommendations to save space
    // or keep them. Usually recommendations are for "Idle" browsing.
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

  // Load initial data
  await Promise.all([
      fetchProducts(),
      fetchCities(),
      fetchRecommendations()
  ]);

  // Fetch categories separately to keep logic clean
  try {
    const catRes = await api.get('/categories');
    categories.value = catRes.data;
  } catch (e) { /* silent */ }
});
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white font-sans">
    
    <!-- NAVIGATION BAR -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50 h-20">
      <div class="w-full px-4 sm:px-6 lg:px-12 h-full">
        <div class="flex justify-between items-center h-full max-w-[1920px] mx-auto">
          <RouterLink to="/" class="flex items-center gap-1 flex-shrink-0">
            <span class="text-2xl sm:text-3xl font-extrabold text-emerald-600">Handy</span>
            <span class="text-2xl sm:text-3xl font-extrabold text-amber-400">Store</span>
          </RouterLink>

          <!-- Desktop Nav -->
          <div class="hidden md:flex items-center gap-5">
             <!-- ... (Keep existing desktop navigation code here) ... -->
             <!-- Language -->
             <div class="relative">
              <button @click="isLangOpen = !isLangOpen" class="flex items-center gap-1 text-gray-600 hover:text-gray-900 font-medium text-sm">
                {{ currentLangName }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <div v-if="isLangOpen" class="absolute right-0 mt-2 w-40 bg-white rounded-md shadow-lg py-1 border z-50">
                <button v-for="lang in languages" :key="lang.code" @click="changeLanguage(lang.code)" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100" :class="{'bg-gray-100 font-semibold': locale === lang.code}">{{ lang.name }}</button>
              </div>
            </div>

            <RouterLink to="/cart" class="relative p-2 text-gray-600 hover:text-emerald-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <span v-if="cartStore.count > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full font-bold">{{ cartStore.count }}</span>
            </RouterLink>

             <!-- Auth Buttons (Copied from original) -->
            <template v-if="isBuyer">
              <RouterLink to="/reels" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">Reels</RouterLink>
              <RouterLink to="/orders" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">Orders</RouterLink>
              <RouterLink to="/wishlist" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">{{ t('nav.wishlist') }}</RouterLink>
              <RouterLink to="/account" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">{{ t('nav.my_account') }}</RouterLink>
              <button @click="authStore.logout" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-full text-sm">{{ t('nav.logout') }}</button>
            </template>
            <template v-else-if="isArtisan  isDelivery">
              <RouterLink :to="isArtisan ? '/artisan/dashboard' : '/delivery/dashboard'" class="bg-emerald-100 hover:bg-emerald-200 text-emerald-700 font-bold py-2 px-4 rounded-full text-sm">Dashboard</RouterLink>
              <button @click="authStore.logout" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-full text-sm">{{ t('nav.logout') }}</button>
            </template>
            <template v-else>
              <RouterLink to="/login" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">{{ t('nav.sign_in') }}</RouterLink>
              <RouterLink to="/register/buyer" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold py-2 px-4 rounded-full text-sm">{{ t('nav.register') }}</RouterLink>
            </template>
          </div>
          <!-- Mobile Menu Button -->
          <div class="flex items-center gap-3 md:hidden">
             <!-- ... (Keep existing mobile button code) ... -->
             <RouterLink to="/cart" class="relative p-2 text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <span v-if="cartStore.count > 0" class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full">{{ cartStore.count }}</span>
            </RouterLink>
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="p-2 text-gray-600 focus:outline-none">
              <svg v-if="!isMobileMenuOpen" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
              <svg v-else class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      </div> 
      
      <!-- MOBILE DRAWER (Keep existing structure) -->
      <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 md:hidden" style="background-color: rgba(0,0,0,0.5);">
         <!-- ... (Copy existing Mobile Drawer code here) ... -->
         <div class="fixed inset-y-0 right-0 w-full max-w-sm bg-white shadow-2xl flex flex-col">
          <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <span class="text-xl font-bold text-gray-800">Menu</span>
            <button @click="isMobileMenuOpen = false" class="text-gray-500 hover:text-gray-800"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
          </div>
          <div class="flex-grow p-6 space-y-1 overflow-y-auto">
             <!-- Mobile Links -->
            <RouterLink v-if="isBuyer" to="/reels" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">Reels</RouterLink>
            <RouterLink v-if="isBuyer" to="/orders" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">Orders</RouterLink>
            <!-- ... other links ... -->
          </div>
        </div>
      </div>
    </header>

    <main class="flex-grow">
      <!-- HERO SECTION -->
      <section class="relative w-full bg-gradient-to-br from-emerald-50 via-white to-white border-b border-gray-100">
        <!-- ... (Keep existing Hero logic) ... -->
        <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 py-4 md:py-5 flex flex-col md:flex-row items-center justify-between gap-3 md:gap-6">
          <div class="text-center md:text-left">
            <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 leading-tight">{{ t('hero.title_1') }} <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-green-400">{{ t('hero.title_highlight') }}</span></h1>
            <p class="text-xs text-gray-500 mt-1">{{ t('hero.subtitle') }}</p>
          </div>
        </div>
      </section>
      <!-- ── NEW: SEARCH & LOCATION BAR ── -->
      <section class="sticky top-20 z-30 bg-white shadow-sm border-b">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto py-3">
          <div class="flex flex-col sm:flex-row gap-3">
            <!-- Search Input -->
            <div class="relative flex-grow">
              <input 
                v-model="searchQuery"
                type="text" 
                :placeholder="t('home.search_placeholder')"
                class="w-full border border-gray-200 rounded-full py-2.5 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm"
              />
              <svg class="absolute left-3.5 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            
            <!-- Location Filter -->
            <div class="flex-shrink-0">
              <select 
                v-model="selectedCityId" 
                class="w-full sm:w-40 border border-gray-200 rounded-full py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm bg-white appearance-none cursor-pointer"
              >
                <option :value="null">{{ t('home.all_cities') }}</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
              </select>
            </div>
          </div>
        </div>
      </section>

      <!-- ── CATEGORY BAR ── -->
      <section class="w-full bg-white border-b border-gray-100">
        <!-- ... (Keep existing Category Bar code) ... -->
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto py-5">
          <div class="flex items-center gap-3 overflow-x-auto pb-3 scrollbar-hide">
            <button @click="clearFilter" class="flex-shrink-0 px-4 py-2.5 rounded-full text-sm font-semibold transition border-2" :class="!selectedParentId ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-200'">{{ t('products.all') || 'All' }}</button>
            <!-- Categories Loop -->
            <button v-for="cat in categories" :key="cat.id" @click="selectParent(cat)" class="flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition border-2 bg-white" :class="selectedParentId === cat.id ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-gray-200 text-gray-700'">
              <span>{{ cat.name }}</span>
            </button>
          </div>
        </div>
      </section>

      <!-- ── NEW: RECOMMENDATIONS SECTION ── -->
      <!-- Only show when not searching or filtering -->
      <section v-if="!searchQuery && !selectedParentId" class="bg-gray-50 py-8">
         <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto space-y-10">
            
            <!-- Near You -->
            <div v-if="recommendations.near_you && recommendations.near_you.length > 0">
              <h3 class="text-lg font-bold text-gray-800 mb-4">{{ t('home.near_you') }}</h3>
              <div class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide">
                <div v-for="product in recommendations.near_you" :key="product.id" @click="router.push(`/product/${product.id}`)" class="min-w-[160px] sm:min-w-[200px] bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border overflow-hidden">
                  <img :src="getProductImage(product)" class="w-full h-36 object-cover" />
                  <div class="p-3">
                    <h4 class="font-semibold text-sm truncate">{{ product.name }}</h4>
                    <p class="text-emerald-600 font-bold text-sm mt-1">{{ formatPrice(product.price) }}</p>
                  </div>
                </div>
              </div>
            </div>
            <!-- Trending Addis -->
            <div v-if="recommendations.trending_addis && recommendations.trending_addis.length > 0">
               <h3 class="text-lg font-bold text-gray-800 mb-4">{{ t('home.trending_addis') }}</h3>
               <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                  <div v-for="product in recommendations.trending_addis" :key="product.id" @click="router.push(`/product/${product.id}`)" class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer border overflow-hidden">
                    <img :src="getProductImage(product)" class="w-full h-32 object-cover" />
                    <div class="p-2.5">
                      <h4 class="font-semibold text-xs truncate">{{ product.name }}</h4>
                      <p class="text-emerald-600 font-bold text-xs mt-1">{{ formatPrice(product.price) }}</p>
                    </div>
                  </div>
               </div>
            </div>

         </div>
      </section>

      <!-- ── PRODUCT SECTION HEADING ── -->
      <section class="py-6 md:py-8 bg-white w-full">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto flex items-center justify-between">
          <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
              <span v-if="activeFilterLabel">{{ activeFilterLabel }}</span>
              <span v-else>{{ t('products.heading') || 'All Products' }}</span>
            </h2>
            <p class="text-sm text-gray-500 mt-1">{{ products.length }} {{ t('products.items') || 'items' }}</p>
          </div>
          <button v-if="selectedParentId  searchQuery" @click="clearFilter" class="text-sm text-emerald-600 hover:text-emerald-800 font-medium">Clear</button>
        </div>
      </section>

      <!-- ── PRODUCT GRID ── -->
      <section class="py-6 md:py-12 bg-gray-50 w-full">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto">
          <!-- Loading State -->
          <div v-if="isLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
             <!-- ... (Skeleton loader) ... -->
             <div v-for="n in 8" :key="n" class="bg-white rounded-2xl overflow-hidden animate-pulse"><div class="aspect-square bg-gray-200"></div></div>
          </div><!-- Empty State -->
          <div v-else-if="products.length === 0" class="text-center py-20">
             <!-- ... (Empty state) ... -->
             <p class="text-gray-500">No products found</p>
          </div>

          <!-- Product List -->
          <div v-else class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
            <div v-for="product in products" :key="product.id" class="group relative flex flex-col bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-xl transition cursor-pointer">
               <!-- ... (Keep existing Product Card structure) ... -->
               <RouterLink :to="`/product/${product.id}`" class="block relative aspect-square bg-gray-100 overflow-hidden">
                <img :src="getProductImage(product)" :alt="getProductName(product)" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                <div v-if="!product.is_in_stock" class="absolute top-2 left-2 z-10 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow">Sold Out</div>
               </RouterLink>
               <div class="p-3 space-y-1.5 border-t border-gray-50">
                 <h3 class="text-gray-900 font-bold text-sm leading-tight truncate">{{ getProductName(product) }}</h3>
                 <div class="flex items-center justify-between pt-1">
                   <span class="text-lg font-extrabold" :class="product.is_in_stock ? 'text-gray-900' : 'text-gray-400 line-through'">{{ formatPrice(product.price) }}</span>
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

    <!-- Footer (Keep existing) -->
    <footer class="bg-gray-900 text-white pt-16 pb-8 w-full">
       <!-- ... -->
    </footer>
  </div>
</template>

<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>