<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';
import { useI18n } from 'vue-i18n';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const product = ref(null);
const loading = ref(true);

const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/400';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

// ── Star Rating Helper ──
const getStars = (rating) => {
    return Array.from({ length: 5 }, (_, i) => i < Math.round(rating));
};

// ── Gallery ──
const currentIndex = ref(0);
const isLightboxOpen = ref(false);
const currentImage = computed(() => {
    if (!product.value?.images?.length) return '';
    const rawImg = product.value.images[currentIndex.value];
    return getImageUrl(rawImg?.image_path || rawImg);
});
const nextImage = () => { if (product.value) currentIndex.value = (currentIndex.value + 1) % product.value.images.length; };
const prevImage = () => { if (product.value) currentIndex.value = (currentIndex.value - 1 + product.value.images.length) % product.value.images.length; };
const selectImage = (i) => { currentIndex.value = i; };
const openLightbox = () => { isLightboxOpen.value = true; };
const closeLightbox = () => { isLightboxOpen.value = false; };

// ── Cart & Wishlist ──
const cartStore = useCartStore();
const wishlistStore = useWishlistStore();
const quantity = ref(1);
const isWishlisted = computed(() => wishlistStore.likedIds.includes(product.value?.id));

const handleAddToCart = async () => {
    if (!product.value?.versions?.[0]) return;
    try {
        await cartStore.addToCart(product.value.id, product.value.versions[0].id, quantity.value);
    } catch (error) {
        alert(error.response?.data?.message || 'Could not add to cart.');
    }
};

const handleToggleWishlist = () => {
    if (!product.value) return;
    wishlistStore.toggleWishlist(product.value.id);
};

// ── Reviews State ──
const reviewFilter = ref(null); // null = all, 1-5 = filter by stars
const reviewSort = ref('recent'); // 'recent' or 'highest'

const filteredReviews = computed(() => {
    if (!product.value?.reviews) return [];
    let list = [...product.value.reviews];
    if (reviewFilter.value !== null) {
        list = list.filter(r => r.rating === reviewFilter.value);
    }
    if (reviewSort.value === 'highest') {
        list.sort((a, b) => b.rating - a.rating);
    } else {
        list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    }
    return list;
});

const ratingDistribution = computed(() => {
    if (!product.value?.rating_distribution) return [];
    const total = product.value.reviews_count || 1;
    return Object.entries(product.value.rating_distribution)
        .map(([star, count]) => ({
            star: Number(star),
            count,
            percentage: Math.round((count / total) * 100)
        }));
});

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(locale.value === 'am' ? 'am-ET' : locale.value === 'or' ? 'or-ET' : 'en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
};

// Scroll to reviews if hash
onMounted(async () => {
    try {
        const { data } = await api.get(`/products/${route.params.id}`);
        product.value = data;
    } catch (e) {
        console.error(e);
        router.push('/');
    } finally {
        loading.value = false;
        if (route.hash === '#reviews') {
            setTimeout(() => {
                document.getElementById('reviews-section')?.scrollIntoView({ behavior: 'smooth' });
            }, 300);
        }
    }
});
</script>

<template>
  <div class="bg-gray-50 min-h-screen">
    
    <div v-if="loading" class="text-center py-20 text-gray-500">Loading product...</div>
    
    <div v-else-if="product" class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
      
      <div class="lg:grid lg:grid-cols-2 lg:gap-x-12">
        
        <!-- Left: Media -->
        <div class="lg:max-w-lg lg:self-start space-y-6">
          <div class="w-full bg-white rounded-2xl overflow-hidden shadow-lg border border-gray-200 relative">
            <div class="aspect-square relative cursor-zoom-in" @click="openLightbox">
              <img :src="currentImage" :alt="product.name" class="w-full h-full object-cover" />
              <button v-if="product.images.length > 1" @click.stop="prevImage" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-lg text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <button v-if="product.images.length > 1" @click.stop="nextImage" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-lg text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
              <div class="absolute bottom-4 left-4 bg-black/50 text-white text-xs px-2 py-1 rounded-full">{{ currentIndex + 1 }} / {{ product.images.length }}</div>
            </div>
            <div v-if="product.images.length > 1" class="p-4 border-t border-gray-100 flex gap-2 overflow-x-auto">
              <div v-for="(img, i) in product.images" :key="i" @click="selectImage(i)" class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden border-2 transition cursor-pointer" :class="i === currentIndex ? 'border-emerald-500' : 'border-transparent hover:border-gray-300'">
                <img :src="getImageUrl(img.image_path || img)" class="w-full h-full object-cover">
              </div>
            </div>
          </div>

          <div v-if="product.ar_model_path" class="w-full bg-white rounded-2xl overflow-hidden shadow-lg border border-purple-200 p-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-bold text-gray-800 flex items-center gap-2">View in 3D / AR</h3>
              <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-medium">Interactive</span>
            </div>
            <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden touch-none">
              <model-viewer :src="getImageUrl(product.ar_model_path)" :alt="product.name" ar ar-modes="webxr scene-viewer quick-look" camera-controls auto-rotate shadow-intensity="1" style="width: 100%; height: 100%; background-color: #f0f0f0;">
                <button slot="ar-button" class="absolute bottom-4 right-4 bg-purple-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg hover:bg-purple-700 flex items-center gap-2">View in AR</button>
              </model-viewer>
            </div>
          </div>
        </div>

        <!-- Right: Product Info -->
        <div class="mt-10 px-4 sm:px-0 sm:mt-16 lg:mt-0">
          <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ product.name }}</h1>
          
          <!-- Rating Summary -->
          <div v-if="product.reviews_count > 0" class="mt-3 flex items-center gap-2">
            <div class="flex items-center">
              <svg v-for="(filled, i) in getStars(product.rating_avg)" :key="i" class="w-5 h-5" :class="filled ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" :fill="filled ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">{{ product.rating_avg }}</span>
            <a href="#reviews" class="text-sm text-emerald-600 hover:text-emerald-800 underline underline-offset-2">{{ product.reviews_count }} {{ t('reviews.reviews') || 'reviews' }}</a>
          </div>

          <div class="mt-4">
            <p class="text-3xl tracking-tight text-emerald-600 font-extrabold">${{ product.price }}</p>
          </div>

          <div class="mt-5">
            <span v-if="product.stock > 0" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">{{ t('reviews.in_stock') || 'In Stock' }}</span>
            <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">{{ t('reviews.out_of_stock') || 'Out of Stock' }}</span>
          </div>

          <div class="mt-6 text-base text-gray-700 space-y-6" v-html="product.description"></div>

          <div class="mt-8 border-t border-gray-200 pt-8">
            <p class="text-sm text-gray-500 mb-4">
              {{ t('reviews.sold_by') || 'Sold by' }}: <span class="text-gray-900 font-medium">{{ product.artisan?.shop || 'Handy Artisan' }}</span>
            </p>

            <div class="flex items-center gap-4 mb-6">
              <span class="text-gray-700 font-medium">{{ t('reviews.quantity') || 'Quantity' }}:</span>
              <div class="flex items-center border rounded-lg overflow-hidden">
                <button @click="quantity > 1 && quantity--" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800">-</button>
                <span class="px-6 py-2 bg-white text-gray-900 font-bold">{{ quantity }}</span>
                <button @click="quantity++" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800">+</button>
              </div>
            </div>

            <div class="flex gap-4">
              <button @click="handleAddToCart" :disabled="!product.stock || product.stock <= 0" class="flex-1 flex items-center justify-center rounded-md bg-emerald-600 px-8 py-4 text-lg font-medium text-white hover:bg-emerald-700 disabled:bg-gray-400 shadow-lg transition">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                {{ product.stock > 0 ? 'Add to Cart' : 'Out of Stock' }}
              </button>
              <button @click="handleToggleWishlist" class="p-4 rounded-md border-2 transition" :class="isWishlisted ? 'border-red-500 bg-red-50 text-red-500' : 'border-gray-200 text-gray-400 hover:border-red-300 hover:text-red-400'">
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════ -->
      <!-- CUSTOMER REVIEWS SECTION              -->
      <!-- ═══════════════════════════════════ -->
      <div id="reviews-section" class="mt-16 max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          
          <!-- Reviews Header -->
          <div class="p-6 sm:p-8 border-b border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('reviews.title') || 'Customer Reviews' }}</h2>
            
            <div class="flex flex-col sm:flex-row sm:items-center gap-6">
              <!-- Big Rating -->
              <div class="text-center sm:text-left sm:pr-8 sm:border-r sm:border-gray-100">
                <div class="text-5xl font-extrabold text-gray-900">{{ product.rating_avg }}</div>
                <div class="flex items-center justify-center sm:justify-start mt-1">
                  <svg v-for="(filled, i) in getStars(product.rating_avg)" :key="i" class="w-6 h-6" :class="filled ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" :fill="filled ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ product.reviews_count }} {{ t('reviews.reviews') || 'reviews' }}</p>
              </div>

              <!-- Distribution Bars -->
              <div class="flex-grow space-y-2">
                <div v-for="bar in ratingDistribution" :key="bar.star" class="flex items-center gap-3">
                  <span class="text-sm text-gray-600 font-medium w-8 text-right">{{ bar.star }}★</span>
                  <div class="flex-grow h-2.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-400 rounded-full transition-all duration-500" :style="{ width: bar.percentage + '%' }"></div>
                  </div>
                  <span class="text-xs text-gray-400 w-8">{{ bar.count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- No Reviews -->
          <div v-if="product.reviews_count === 0" class="p-8 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-1.085M3 16.5c0-2.5 1-4.5 3-6l3.5-3.5m10 10a9 9 0 01-9-9 9 9 0 019 9z"/></svg>
            <p class="font-medium">{{ t('reviews.no_reviews') || 'No reviews yet' }}</p>
            <p class="text-sm mt-1">{{ t('reviews.be_first') || 'Be the first to review this product' }}</p>
          </div>

          <!-- Reviews List -->
          <div v-else>
            <!-- Filters & Sort -->
            <div class="px-6 sm:px-8 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div class="flex items-center gap-2 overflow-x-auto">
                <button
                  @click="reviewFilter = null"
                  class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition"
                  :class="reviewFilter === null ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                >
                  {{ t('reviews.all') || 'All' }}
                </button>
                <button
                  v-for="star in [5,4,3,2,1]" :key="star"
                  @click="reviewFilter = reviewFilter === star ? null : star"
                  class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition flex items-center gap-1"
                  :class="reviewFilter === star ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                >
                  {{ star }}★
                </button>
              </div>
              <select v-model="reviewSort" class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="recent">{{ t('reviews.sort_recent') || 'Most Recent' }}</option>
                <option value="highest">{{ t('reviews.sort_highest') || 'Highest Rated' }}</option>
              </select>
            </div>

            <!-- Review Cards -->
            <div class="divide-y divide-gray-100">
              <div v-for="review in filteredReviews" :key="review.id" class="p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                  <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                      <span class="text-emerald-700 font-bold text-sm">{{ review.user_name?.charAt(0)?.toUpperCase() || '?' }}</span>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-900 text-sm">{{ review.user_name }}</p>
                      <div class="flex items-center gap-1 mt-0.5">
                        <svg v-for="(filled, i) in getStars(review.rating)" :key="i" class="w-3.5 h-3.5" :class="filled ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" :fill="filled ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="text-xs text-gray-400 ml-1">{{ review.rating }}/5</span>
                      </div>
                    </div>
                  </div>
                  <span class="text-xs text-gray-400 flex-shrink-0 mt-1">{{ formatDate(review.created_at) }}</span>
                </div>
                <p v-if="review.comment" class="mt-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ review.comment }}</p>
              </div>
            </div>

            <!-- No matching filter -->
            <div v-if="filteredReviews.length === 0 && product.reviews_count > 0" class="p-8 text-center text-gray-400 text-sm">
              {{ t('reviews.no_matching') || 'No reviews match this filter' }}
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Lightbox -->
    <div v-if="isLightboxOpen" class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center" @click="closeLightbox">
      <button @click="closeLightbox" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
      <div class="relative max-w-5xl max-h-[90vh] w-full flex items-center justify-center p-12">
        <img :src="currentImage" class="max-h-full max-w-full object-contain" @click.stop>
        <button v-if="product.images.length > 1" @click.stop="prevImage" class="absolute left-4 text-white bg-black/50 p-3 rounded-full"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
        <button v-if="product.images.length > 1" @click.stop="nextImage" class="absolute right-4 text-white bg-black/50 p-3 rounded-full"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
      </div>
    </div>
  </div>
</template>