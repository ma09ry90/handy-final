<script setup>
import { ref, watch, onMounted, computed, nextTick, onUpdated } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import api from '@/plugins/axios';
import { useCartStore } from '@/stores/cart';
import { useWishlistStore } from '@/stores/wishlist';
import { useI18n } from 'vue-i18n';

// ... rest of your code remains the same
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const product = ref(null);
const loading = ref(true);
const shouldScrollToReviews = ref(false);
const isAddingToCart = ref(false);
const cartSuccess = ref(false);
const cartError = ref('');

const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/400';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

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
// ── Cart & Wishlist ──
// ── Cart & Wishlist ──
const cartStore = useCartStore();
const wishlistStore = useWishlistStore();
const quantity = ref(1);
const isWishlisted = computed(() => wishlistStore.likedIds.includes(product.value?.id));

// Get the selected version (with fallback to product itself)
const selectedVersion = computed(() => {
    if (!product.value) return null;
    
    // If versions exist, use the first one
    if (product.value.versions?.length) {
        return product.value.versions[0];
    }
    
    // FALLBACK: Use product itself as the "version" 
    // (for simple products without variants)
    return {
        id: product.value.id,
        stock: product.value.stock,
        price: product.value.price
    };
});

// Stock calculation with fallback
const availableStock = computed(() => {
    if (!product.value) return 0;
    return Number(selectedVersion.value?.stock) || Number(product.value.stock) || 0;
});

const isInStock = computed(() => availableStock.value > 0);
const maxQuantity = computed(() => availableStock.value);

const decrementQuantity = () => {
    if (quantity.value > 1) {
        quantity.value--;
        cartError.value = '';
    }
};

const incrementQuantity = () => {
    if (quantity.value < maxQuantity.value) {
        quantity.value++;
        cartError.value = '';
    }
};

const handleAddToCart = async () => {
    console.log('🛒 handleAddToCart triggered');
    
    cartError.value = '';
    cartSuccess.value = false;

    if (!product.value) {
        cartError.value = 'Product not loaded.';
        return;
    }

    if (!isInStock.value) {
        cartError.value = 'This product is out of stock.';
        return;
    }

    // Get version ID - fallback to product ID if no versions
    const versionId = selectedVersion.value?.id || product.value.id;
    
    console.log('✅ Adding to cart:', {
        productId: product.value.id,
        versionId: versionId,
        quantity: quantity.value
    });

    if (quantity.value > availableStock.value) {
        cartError.value = `Only ${availableStock.value} items available.`;
        quantity.value = availableStock.value;
        return;
    }

    isAddingToCart.value = true;

    try {
        await cartStore.addToCart(
            product.value.id,
            versionId,
            quantity.value
        );
        
        console.log('✅ Successfully added to cart');
        cartSuccess.value = true;
        setTimeout(() => { cartSuccess.value = false; }, 3000);
        
    } catch (error) {
        console.error('❌ Add to cart failed:', error.response?.data || error);
        const apiMessage = error.response?.data?.message;
        const apiErrors = error.response?.data?.errors;
        
        if (apiErrors) {
            cartError.value = Object.values(apiErrors).flat().join(', ');
        } else if (apiMessage) {
            cartError.value = apiMessage;
        } else {
            cartError.value = 'Failed to add to cart. Please try again.';
        }
    } finally {
        isAddingToCart.value = false;
    }
};

// Reset quantity when product changes
watch(() => product.value?.id, () => {
    quantity.value = 1;
    cartError.value = '';
    cartSuccess.value = false;
});

const handleToggleWishlist = () => {
    if (!product.value) return;
    wishlistStore.toggleWishlist(product.value.id);
};

// ── Fetch Product - Try with versions included ──
onMounted(async () => {
    if (route.hash === '#reviews') {
        shouldScrollToReviews.value = true;
    }

    try {
        // OPTION 1: Try fetching WITH versions (common Laravel pattern)
        let data;
        try {
            const response = await api.get(`/products/${route.params.id}`, {
                params: { include: 'versions,images,reviews' }
            });
            data = response.data;
        } catch (includeError) {
            // OPTION 2: If include param fails, try normal fetch
            console.log('Include param failed, trying normal fetch...');
            const response = await api.get(`/products/${route.params.id}`);
            data = response.data;
        }
        
        console.log('📦 Product loaded:', {
            id: data.id,
            name: data.name,
            hasVersions: !!data.versions?.length,
            versionsCount: data.versions?.length || 0
        });
        
        product.value = data;
    } catch (e) {
        console.error('Failed to load product:', e);
        router.push('/');
    } finally {
        loading.value = false;
        if (shouldScrollToReviews.value) {
            await nextTick();
            scrollToReviews();
        }
    };

const handleToggleWishlist = () => {
    if (!product.value) return;
    wishlistStore.toggleWishlist(product.value.id);
};

// ── Reviews ──
const reviewFilter = ref(null);
const reviewSort = ref('recent');

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
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
};

// ── Scroll to reviews ──
const scrollToReviews = async () => {
    await nextTick();
    requestAnimationFrame(() => {
        const el = document.getElementById('reviews-section');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            shouldScrollToReviews.value = false;
        }
    });
};

onUpdated(() => {
    if (shouldScrollToReviews.value && product.value) {
        scrollToReviews();
    }
});

onMounted(async () => {
    if (route.hash === '#reviews') {
        shouldScrollToReviews.value = true;
    }

    try {
        const { data } = await api.get(`/products/${route.params.id}`);
        product.value = data;
    } catch (e) {
        console.error(e);
        router.push('/');
    } finally {
        loading.value = false;
        if (shouldScrollToReviews.value) {
            await nextTick();
            scrollToReviews();
        }
    }
});
</script>

<template>
  <div class="bg-gray-50 min-h-screen">
    
    <div v-if="loading" class="text-center py-20 text-gray-500">Loading product...</div>
    
    <div v-else-if="product" class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
      
      <div class="lg:grid lg:grid-cols-2 lg:gap-x-12">
        
        <!-- Left Column: Media -->
        <div class="lg:max-w-lg lg:self-start space-y-6">
            
            <!-- Image Gallery -->
            <div class="w-full bg-white rounded-2xl overflow-hidden shadow-lg border border-gray-200 relative">
                <div class="aspect-square relative cursor-zoom-in" @click="openLightbox">
                    <img :src="currentImage" :alt="product.name" class="w-full h-full object-cover" />
                    
                    <button v-if="product.images.length > 1" @click.stop="prevImage" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-lg text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    
                    <button v-if="product.images.length > 1" @click.stop="nextImage" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-lg text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <div class="absolute bottom-4 left-4 bg-black/50 text-white text-xs px-2 py-1 rounded-full">
                        {{ currentIndex + 1 }} / {{ product.images.length }}
                    </div>
                </div>

                <div v-if="product.images.length > 1" class="p-4 border-t border-gray-100 flex gap-2 overflow-x-auto">
                    <div v-for="(img, i) in product.images" :key="i" @click="selectImage(i)" class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden border-2 transition cursor-pointer" :class="i === currentIndex ? 'border-emerald-500' : 'border-transparent hover:border-gray-300'">
                        <img :src="getImageUrl(img.image_path || img)" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>

            <!-- AR 3D Viewer -->
            <div v-if="product.ar_model_path" class="w-full bg-white rounded-2xl overflow-hidden shadow-lg border border-purple-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1v-2.5M4 14l2-1m-2 1v-2.5M6 18l2-1m-2 1v-2.5"/></svg>
                        View in 3D / AR
                    </h3>
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-medium">Interactive</span>
                </div>
                
                <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden touch-none">
                    <model-viewer
                        :src="getImageUrl(product.ar_model_path)"
                        :alt="product.name"
                        ar
                        ar-modes="webxr scene-viewer quick-look"
                        camera-controls
                        auto-rotate
                        shadow-intensity="1"
                        style="width: 100%; height: 100%; background-color: #f0f0f0;"
                    >
                        <button slot="ar-button" class="absolute bottom-4 right-4 bg-purple-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg hover:bg-purple-700 flex items-center gap-2">
                            View in AR
                        </button>
                    </model-viewer>
                </div>
            </div>
        </div>

        <!-- Right Column: Product Info -->
        <div class="mt-10 px-4 sm:px-0 sm:mt-16 lg:mt-0">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ product.name }}</h1>
            
            <!-- Rating Summary -->
            <div v-if="product.reviews_count > 0" class="mt-3 flex items-center gap-2">
                <div class="flex items-center">
                    <svg v-for="(filled, i) in getStars(product.rating_avg)" :key="i" class="w-5 h-5" :class="filled ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" :fill="filled ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <span class="text-sm font-semibold text-gray-800">{{ product.rating_avg }}</span>
                <a href="#reviews" @click.prevent="shouldScrollToReviews = true; scrollToReviews()" class="text-sm text-emerald-600 hover:text-emerald-800 underline underline-offset-2">{{ product.reviews_count }} reviews</a>
            </div>

            <!-- No reviews yet prompt -->
            <div v-else class="mt-3">
                <span class="text-sm text-gray-400 italic">No reviews yet</span>
            </div>

            <div class="mt-4">
                <p class="text-3xl tracking-tight text-emerald-600 font-extrabold">${{ product.price }}</p>
            </div>

            <div class="mt-5">
                <span v-if="product.stock > 0" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    <svg class="w-4 h-4 mr-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    In Stock ({{ product.stock }})
                </span>
                <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    <svg class="w-4 h-4 mr-1 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Out of Stock
                </span>
            </div>

            <div class="mt-6 text-base text-gray-700 space-y-6 leading-relaxed" v-html="product.description"></div>

            <!-- Seller & Add to Cart -->
            <div class="mt-8 border-t border-gray-200 pt-8">
                <p class="text-sm text-gray-500 mb-6">
                    Sold by: <span class="text-gray-900 font-medium">{{ product.artisan?.shop || 'Handy Artisan' }}</span>
                </p>

                <!-- Quantity Selector -->
<div class="flex items-center gap-4 mb-4">
    <span class="text-gray-700 font-medium">Quantity:</span>
    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
        <button 
            @click="decrementQuantity" 
            :disabled="quantity <= 1"
            class="px-4 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-800 font-medium transition disabled:opacity-40 disabled:cursor-not-allowed"
        >
            −
        </button>
        <span class="px-6 py-2.5 bg-white text-gray-900 font-bold border-x border-gray-300 min-w-[48px] text-center">
            {{ quantity }}
        </span>
        <button 
            @click="incrementQuantity" 
            :disabled="quantity >= maxQuantity || !isInStock"
            class="px-4 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-800 font-medium transition disabled:opacity-40 disabled:cursor-not-allowed"
        >
            +
        </button>
    </div>
                <span v-if="isInStock" class="text-sm text-gray-500">
                    {{ availableStock }} available
                </span>
            </div>

            <!-- Success Message -->
            <div 
                v-if="cartSuccess" 
                class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700 text-sm flex items-center animate-fade-in"
            >
                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Added to cart successfully!
            </div>

            <!-- Error Message -->
            <div 
                v-if="cartError" 
                class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center"
            >
                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ cartError }}
            </div>

            <!-- Add to Cart Button -->
            <div class="flex gap-3">
                <button 
                    @click="handleAddToCart" 
                    :disabled="!isInStock || isAddingToCart" 
                    class="flex-1 flex items-center justify-center rounded-xl bg-emerald-600 px-8 py-4 text-lg font-bold text-white hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:focus:ring-0 shadow-lg transition-all"
                >
                    <!-- Loading Spinner -->
                    <svg 
                        v-if="isAddingToCart" 
                        class="animate-spin w-6 h-6 mr-2" 
                        fill="none" 
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <!-- Cart Icon -->
                    <svg 
                        v-else 
                        class="w-6 h-6 mr-2" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    {{ isAddingToCart ? 'Adding...' : (isInStock ? 'Add to Cart' : 'Out of Stock') }}
                </button>

                    <button 
                        @click="handleToggleWishlist" 
                        class="p-4 rounded-xl border-2 transition-all"
                        :class="isWishlisted ? 'border-red-500 bg-red-50 text-red-500' : 'border-gray-200 text-gray-400 hover:border-red-300 hover:text-red-400'"
                    >
                        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════ -->
      <!-- CUSTOMER REVIEWS SECTION                  -->
      <!-- ═══════════════════════════════════════ -->
      <div id="reviews-section" class="mt-16 max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          
          <!-- Reviews Header -->
          <div class="p-6 sm:p-8 border-b border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Customer Reviews</h2>
            
            <div class="flex flex-col sm:flex-row sm:items-center gap-6">
              <!-- Big Rating -->
              <div class="text-center sm:text-left sm:pr-8 sm:border-r sm:border-gray-100">
                <div class="text-5xl font-extrabold text-gray-900">{{ product.rating_avg }}</div>
                <div class="flex items-center justify-center sm:justify-start mt-2">
                  <svg v-for="(filled, i) in getStars(product.rating_avg)" :key="i" class="w-6 h-6" :class="filled ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" :fill="filled ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ product.reviews_count }} reviews</p>
              </div>

              <!-- Distribution Bars -->
              <div class="flex-grow space-y-2">
                <div v-for="bar in ratingDistribution" :key="bar.star" class="flex items-center gap-3 cursor-pointer" @click="reviewFilter = reviewFilter === bar.star ? null : bar.star">
                  <span class="text-sm text-gray-600 font-medium w-6 text-right">{{ bar.star }}★</span>
                  <div class="flex-grow h-2.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" :class="reviewFilter === null ? 'bg-amber-400' : reviewFilter === bar.star ? 'bg-emerald-500' : 'bg-gray-300'" :style="{ width: bar.percentage + '%' }"></div>
                  </div>
                  <span class="text-xs text-gray-400 w-8 text-right">{{ bar.count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- No Reviews -->
          <div v-if="product.reviews_count === 0" class="p-8 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-1.085M3 16.5c0-2.5 1-4.5 3-6l3.5-3.5m10 10a9 9 0 01-9-9 9 9 0 019 9z"/></svg>
            <p class="font-medium">No reviews yet</p>
            <p class="text-sm mt-1">Be the first to review this product</p>
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
                  All
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
                <option value="recent">Most Recent</option>
                <option value="highest">Highest Rated</option>
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
                <p v-else class="mt-4 text-sm text-gray-400 italic">No comment provided.</p>
              </div>
            </div>

            <!-- No matching filter results -->
            <div v-if="filteredReviews.length === 0 && product.reviews_count > 0" class="p-8 text-center text-gray-400 text-sm">
              No reviews match this filter
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Lightbox Modal -->
    <div v-if="isLightboxOpen" class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center" @click="closeLightbox">
      <button @click="closeLightbox" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
      <div class="relative max-w-5xl max-h-[90vh] w-full flex items-center justify-center p-12">
        <img :src="currentImage" class="max-h-full max-w-full object-contain" @click.stop>
        <button v-if="product.images.length > 1" @click.stop="prevImage" class="absolute left-4 text-white bg-black/50 p-3 rounded-full hover:bg-black/70">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button v-if="product.images.length > 1" @click.stop="nextImage" class="absolute right-4 text-white bg-black/50 p-3 rounded-full hover:bg-black/70">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>
    </div>
  </div>
</template>