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
const isLoading = ref(true);
const isLangOpen = ref(false);
const isMobileMenuOpen = ref(false);

// Category filter state
const selectedParentId = ref(null);
const selectedChildId = ref(null);
const selectedChildren = ref([]);

const isBuyer = computed(() => authStore.isAuthenticated && Number(authStore.user?.role_id) === 1);
const isArtisan = computed(() => authStore.isAuthenticated && Number(authStore.user?.role_id) === 2);
const isDelivery = computed(() => authStore.isAuthenticated && Number(authStore.user?.role_id) === 3);

const mockProducts = ref([
    { id: 1, name: 'Handcrafted Ceramic Vase', price: 45.00, image: 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', shop: { shop_name: 'Clay & Craft' }, version_id: 1, is_in_stock: true, likes_count: 12, rating_avg: 4.5, reviews_count: 23 },
    { id: 2, name: 'Boho Macrame Wall Hanging', price: 35.00, image: 'https://images.unsplash.com/photo-1622227056993-6e7f88420855?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', shop: { shop_name: 'Knotty Art' }, version_id: 2, is_in_stock: true, likes_count: 5, rating_avg: 3.8, reviews_count: 10 },
]);

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
};

const activeFilterLabel = computed(() => {
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

// ── Fetch Products ──
const fetchProducts = async () => {
    isLoading.value = true;
    try {
        const catId = selectedChildId.value || selectedParentId.value;
        const params = {};
        if (catId) params.category_id = catId;
        const response = await api.get('/products', { params });
        if (response.data?.data?.length > 0) {
          // Extra safety: ensure no out-of-stock products show on homepage
          products.value = response.data.data.filter(p => p.is_in_stock);
      } else {
          products.value = [];
      }
    } catch (error) {
        if (!selectedParentId.value) products.value = mockProducts.value;
        else products.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch([selectedParentId, selectedChildId], () => {
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

  // Fetch categories and products in parallel
  const promises = [fetchProducts()];
  try {
    const catRes = await api.get('/categories');
    categories.value = catRes.data;
  } catch (e) { /* silent */ }

  await Promise.all(promises);
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

          <div class="hidden md:flex items-center gap-5">
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

            <template v-if="isBuyer">
              <RouterLink to="/reels" class="text-gray-600 hover:text-emerald-600 font-medium text-sm flex items-center gap-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Reels
              </RouterLink>
              <RouterLink to="/orders" class="text-gray-600 hover:text-emerald-600 font-medium text-sm flex items-center gap-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Orders
              </RouterLink>
              <RouterLink to="/wishlist" class="text-gray-600 hover:text-emerald-600 font-medium text-sm flex items-center gap-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                {{ t('nav.wishlist') }}
              </RouterLink>
              <RouterLink to="/account" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">{{ t('nav.my_account') }}</RouterLink>
              <button @click="authStore.logout" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-full text-sm">{{ t('nav.logout') }}</button>
            </template>

            <template v-else-if="isArtisan || isDelivery">
              <RouterLink :to="isArtisan ? '/artisan/dashboard' : '/delivery/dashboard'" class="bg-emerald-100 hover:bg-emerald-200 text-emerald-700 font-bold py-2 px-4 rounded-full text-sm flex items-center gap-1">Dashboard</RouterLink>
              <button @click="authStore.logout" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-full text-sm">{{ t('nav.logout') }}</button>
            </template>

            <template v-else>
              <RouterLink to="/login" class="text-gray-600 hover:text-emerald-600 font-medium text-sm">{{ t('nav.sign_in') }}</RouterLink>
              <RouterLink to="/register/buyer" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold py-2 px-4 rounded-full text-sm">{{ t('nav.register') }}</RouterLink>
            </template>
          </div>

          <div class="flex items-center gap-3 md:hidden">
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

      <!-- MOBILE DRAWER -->
      <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 md:hidden" style="background-color: rgba(0,0,0,0.5);">
        <div class="fixed inset-y-0 right-0 w-full max-w-sm bg-white shadow-2xl flex flex-col">
          <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <span class="text-xl font-bold text-gray-800">Menu</span>
            <button @click="isMobileMenuOpen = false" class="text-gray-500 hover:text-gray-800"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
          </div>
          <div class="flex-grow p-6 space-y-1 overflow-y-auto">
            <div class="pb-4 mb-4 border-b">
              <p class="text-xs font-bold text-gray-400 uppercase mb-2">Language</p>
              <div class="flex gap-2">
                <button v-for="lang in languages" :key="lang.code" @click="changeLanguage(lang.code)" class="flex-1 text-center py-2 rounded-lg text-sm font-medium border transition" :class="locale === lang.code ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'text-gray-600 border-gray-200 hover:bg-gray-50'">{{ lang.name }}</button>
              </div>
            </div>
            <RouterLink v-if="isBuyer" to="/reels" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">Reels</RouterLink>
            <RouterLink v-if="isBuyer" to="/orders" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">Orders</RouterLink>
            <RouterLink v-if="isBuyer" to="/wishlist" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">{{ t('nav.wishlist') }}</RouterLink>
            <RouterLink v-if="isBuyer" to="/account" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">{{ t('nav.my_account') }}</RouterLink>
            <RouterLink v-if="isArtisan || isDelivery" :to="isArtisan ? '/artisan/dashboard' : '/delivery/dashboard'" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 font-bold">Go to Dashboard</RouterLink>
            <RouterLink v-if="!authStore.isAuthenticated" to="/login" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">{{ t('nav.sign_in') }}</RouterLink>
            <RouterLink v-if="!authStore.isAuthenticated" to="/register/buyer" @click="isMobileMenuOpen = false" class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-gray-100 font-medium">{{ t('nav.register') }}</RouterLink>
          </div>
          <div class="p-6 border-t bg-gray-50">
            <button v-if="authStore.isAuthenticated" @click="authStore.logout(); isMobileMenuOpen = false" class="w-full bg-gray-200 hover:bg-gray-300 py-3 rounded-lg font-bold text-gray-800 transition">{{ t('nav.logout') }}</button>
            <RouterLink v-else to="/register/buyer" @click="isMobileMenuOpen = false" class="block w-full text-center bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold py-3 rounded-lg transition">{{ t('nav.register') }}</RouterLink>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-grow">
            <!-- Hero Section -->
      <section v-if="!authStore.isAuthenticated" class="relative w-full bg-gradient-to-br from-emerald-50 via-white to-white border-b border-gray-100">
        <div class="w-full max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 py-4 md:py-5 flex flex-col md:flex-row items-center justify-between gap-3 md:gap-6">
          <div class="text-center md:text-left">
            <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 leading-tight">{{ t('hero.title_1') }} <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-green-400">{{ t('hero.title_highlight') }}</span></h1>
            <p class="text-xs text-gray-500 mt-1">{{ t('hero.subtitle') }}</p>
            <div class="flex flex-col sm:flex-row gap-2 justify-center md:justify-start mt-3">
              <RouterLink to="/register/buyer" class="inline-flex items-center justify-center bg-emerald-600 text-white font-bold py-2 px-5 rounded-full text-xs">Shop Now</RouterLink>
              <RouterLink to="/register/artisan" class="inline-flex items-center justify-center border-2 border-gray-200 text-gray-700 font-bold py-2 px-5 rounded-full text-xs">Become a Seller</RouterLink>
            </div>
          </div>
          <div class="hidden md:flex items-center">
            <div class="bg-white/70 backdrop-blur-sm border border-emerald-100 rounded-lg p-4 shadow-sm">
              <h3 class="text-lg font-extrabold text-emerald-800 mb-0.5">Build a <span class="text-emerald-600">Green Legacy</span></h3>
              <p class="text-xs text-gray-500 leading-relaxed">Love the environment, plant a tree, and support sustainable local crafts.</p>
            </div>
          </div>
        </div>
      </section>

      <section v-else class="w-full py-8 bg-gradient-to-br from-emerald-50 to-white border-b border-gray-100">
        <div class="max-w-4xl mx-auto text-center px-6 space-y-3">
          <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900">Welcome back, {{ authStore.user?.first_name }}!</h1>
          <p class="text-sm text-gray-600">here is your profile.</p>
          <RouterLink :to="isArtisan ? '/artisan/dashboard' : isDelivery ? '/delivery/dashboard' : '/account'" class="inline-flex items-center justify-center gap-2 bg-emerald-600 text-white font-bold py-2.5 px-6 rounded-full text-sm hover:bg-emerald-700 transition shadow-lg">Go to profile</RouterLink>
        </div>
      </section>

      <!-- ── CATEGORY BAR ── -->
      <section class="w-full bg-white border-b border-gray-100">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto py-5">
          <!-- Parent Categories -->
          <div class="flex items-center gap-3 overflow-x-auto pb-3 scrollbar-hide">
            <button
              @click="clearFilter"
              class="flex-shrink-0 px-4 py-2.5 rounded-full text-sm font-semibold transition border-2"
              :class="!selectedParentId ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-300 hover:text-emerald-700'"
            >
              {{ t('products.all') || 'All' }}
            </button>
            <button
              v-for="cat in categories" :key="cat.id"
              @click="selectParent(cat)"
              class="flex-shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition border-2 bg-white"
              :class="selectedParentId === cat.id ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-gray-200 text-gray-700 hover:border-gray-300'"
            >
              <img v-if="cat.icon_path" :src="cat.icon_path" class="w-5 h-5 rounded object-cover" alt="">
              <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
              <span>{{ cat.name }}</span>
              <span class="text-[10px] text-gray-400 font-normal">{{ cat.products_count }}</span>
            </button>
          </div>

          <!-- Subcategories -->
          <div v-if="selectedChildren.length > 0" class="flex items-center gap-2 overflow-x-auto pt-1 scrollbar-hide">
            <button
              @click="selectedChildId = null"
              class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition border"
              :class="!selectedChildId ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'"
            >
              {{ t('products.all') || 'All' }}
            </button>
            <button
              v-for="child in selectedChildren" :key="child.id"
              @click="selectCategory(child.id)"
              class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition border bg-gray-50"
              :class="selectedChildId === child.id ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'border-gray-200 text-gray-600 hover:bg-gray-100'"
            >
              {{ child.name }}
              <span class="text-gray-400 ml-1">({{ child.products_count }})</span>
            </button>
          </div>
        </div>
      </section>

      <!-- ── PRODUCT SECTION HEADING ── -->
      <section class="py-6 md:py-8 bg-white w-full">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto flex items-center justify-between">
          <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
              <span v-if="activeFilterLabel">{{ activeFilterLabel }}</span>
              <span v-else>{{ t('products.heading') || 'Products' }}</span>
            </h2>
            <p class="text-sm text-gray-500 mt-1">{{ products.length }} {{ t('products.items') || 'items' }}</p>
          </div>
          <button
            v-if="selectedParentId"
            @click="clearFilter"
            class="text-sm text-emerald-600 hover:text-emerald-800 font-medium flex items-center gap-1"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Clear filter
          </button>
        </div>
      </section>

      <!-- ── PRODUCT GRID ── -->
      <section class="py-6 md:py-12 bg-gray-50 w-full">
        <div class="w-full px-4 sm:px-6 lg:px-12 max-w-[1920px] mx-auto">

          <!-- Loading -->
          <div v-if="isLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
            <div v-for="n in 8" :key="n" class="bg-white rounded-2xl overflow-hidden animate-pulse">
              <div class="aspect-square bg-gray-200"></div>
              <div class="p-3 space-y-2">
                <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                <div class="h-3 bg-gray-200 rounded w-1/4"></div>
              </div>
            </div>
          </div>

          <!-- Empty -->
          <div v-else-if="products.length === 0" class="text-center py-20">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            <p class="text-gray-500 text-lg font-medium">No products found</p>
            <button @click="clearFilter" class="mt-4 text-emerald-600 font-medium hover:underline">Show all products</button>
          </div>

          <!-- Products -->
          <div v-else class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
            <div
              v-for="product in products"
              :key="product.id"
              @dblclick="router.push(`/product/${product.id}`)"
              class="group relative flex flex-col bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 cursor-pointer"
            >
              <RouterLink :to="`/product/${product.id}`" class="block relative aspect-square bg-gray-100 overflow-hidden">
                <img :src="getProductImage(product)" :alt="getProductName(product)" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                <div v-if="!product.is_in_stock" class="absolute top-2 left-2 z-10 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow">Sold Out</div>

                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 hidden sm:flex items-center justify-center">
                  <button
                    @click.prevent="quickAddToCart(product)"
                    :disabled="!product.is_in_stock"
                    class="opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300 font-bold py-2 px-4 rounded-full text-sm shadow-lg flex items-center gap-2"
                    :class="product.is_in_stock ? 'bg-white text-gray-800' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    {{ product.is_in_stock ? 'Add to Cart' : 'Unavailable' }}
                  </button>
                </div>

                <button
                  v-if="product.is_in_stock"
                  @click.prevent="quickAddToCart(product)"
                  class="sm:hidden absolute bottom-2 right-2 z-10 bg-white text-gray-800 rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </button>
              </RouterLink>

              <div class="p-3 space-y-1.5 border-t border-gray-50">
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 overflow-hidden border border-emerald-200">
                    <img v-if="getShopLogo(product)" :src="getShopLogo(product)" class="w-full h-full object-cover" alt="">
                    <svg v-else class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                  </div>
                  <span class="text-[11px] font-medium text-gray-500 truncate">{{ getShopName(product) }}</span>
                </div>

                <h3 class="text-gray-900 font-bold text-sm leading-tight truncate">{{ getProductName(product) }}</h3>

                <!-- Rating -->
                <div v-if="product.reviews_count > 0" class="flex items-center gap-1">
                  <div class="flex items-center">
                    <svg v-for="(filled, i) in getStars(product.rating_avg)" :key="i">...</svg>
                  </div>
                  <span class="text-[11px] text-gray-500">{{ product.rating_avg }}</span>
                  <span class="text-[11px] text-gray-400">({{ product.reviews_count }})</span>
                </div>

                <div class="flex items-center justify-between pt-1">
                  <span class="text-lg font-extrabold" :class="product.is_in_stock ? 'text-gray-900' : 'text-gray-400 line-through'">{{ formatPrice(product.price) }}</span>
                  <button
                    v-if="authStore.isAuthenticated"
                    @click="handleToggleWishlist(product)"
                    class="relative p-1.5 rounded-full transition"
                    :class="wishlistStore.likedIds.includes(product.id) ? 'text-red-500' : 'text-gray-400 hover:text-red-400'"
                  >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                    <span v-if="product.likes_count > 0" class="absolute -top-1 -right-1 text-[9px] font-extrabold bg-gray-200 text-gray-700 rounded-full min-w-[14px] h-[14px] flex items-center justify-center px-0.5">{{ product.likes_count }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white pt-16 pb-8 w-full">
      <div class="w-full px-6 lg:px-12 max-w-[1920px] mx-auto grid grid-cols-1 md:grid-cols-4 gap-12">
        <div class="col-span-1 md:col-span-2">
          <span class="text-3xl font-bold text-white">Handy</span><span class="text-3xl font-bold text-amber-400">Store</span>
          <p class="text-gray-400 mt-4 max-w-md leading-relaxed">{{ t('footer.desc') }}</p>
        </div>
        <div>
          <h4 class="font-bold mb-4 text-lg">{{ t('footer.links') }}</h4>
          <ul class="space-y-3 text-gray-400">
            <li><RouterLink to="/" class="hover:text-amber-400 transition">{{ t('nav.home') }}</RouterLink></li>
            <li v-if="!authStore.isAuthenticated"><RouterLink to="/login" class="hover:text-amber-400 transition">{{ t('nav.sign_in') }}</RouterLink></li>
            <li v-if="!authStore.isAuthenticated"><RouterLink to="/register/buyer" class="hover:text-amber-400 transition">{{ t('nav.register') }}</RouterLink></li>
            <li v-if="!authStore.isAuthenticated"><RouterLink to="/register/artisan" class="hover:text-amber-400 transition">Become a Seller</RouterLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-bold mb-4 text-lg">{{ t('footer.contact') }}</h4>
          <ul class="space-y-3 text-gray-400">
            <li>support@handystore.com</li>
            <li>+1 (555) 123-4567</li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-500 text-sm">© 2024 HandyStore. All rights reserved.</div>
    </footer>
  </div>
</template>

<style>
/* Hide scrollbar on category bar */
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>