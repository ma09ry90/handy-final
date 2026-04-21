<script setup>
import { ref, onMounted, nextTick } from 'vue';
import api from '@/plugins/axios';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const videos = ref([]);

// Comment System State
const showComments = ref(false);
const currentVideo = ref(null);
const comments = ref([]);
const commentText = ref('');
const loadingComments = ref(false);

// Mobile Audio State (Start muted to allow autoplay)
const globalMuted = ref(true);

// --- FIX: Universal Media URL Helper ---
// Automatically optimizes Cloudinary URLs for mobile browsers
const getMediaUrl = (path, type = 'image') => {
    if (!path) return null;
    
    // 1. If it's already a full URL (like Cloudinary)
    if (path.startsWith('http')) {
        if (path.includes('res.cloudinary.com')) {
            if (type === 'video') {
                // vc_auto: optimizes video codec for mobile
                // q_auto: compresses to save mobile data
                return path.replace('/upload/', '/upload/vc_auto,q_auto/');
            } else {
                // f_auto: converts to WebP (best for mobile images)
                // q_auto: compresses to save mobile data
                return path.replace('/upload/', '/upload/f_auto,q_auto/');
            }
        }
        return path; // Return normal URLs as-is
    }

    // 2. If it's a local relative path (e.g., old uploads)
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

// Fetch Feed
const fetchVideos = async () => {
    try {
        const res = await api.get('/videos');
        videos.value = res.data.data;
        nextTick(() => initObserver());
    } catch (e) {
        console.error("Failed to fetch videos", e);
    }
};

// --- ACTIONS ---

const toggleLike = async (video) => {
    if (!auth.isAuthenticated) return alert("Please login to like videos");
    try {
        const res = await api.post(`/videos/${video.id}/like`);
        video.is_liked = res.data.status === 'liked';
        video.likes_count = res.data.likes_count; 
    } catch (e) {
        console.error("Like failed", e);
    }
};

const openComments = async (video) => {
    currentVideo.value = video;
    showComments.value = true;
    await fetchComments(video.id);
};

const fetchComments = async (videoId) => {
    loadingComments.value = true;
    try {
        const res = await api.get(`/videos/${videoId}/comments`);
        comments.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingComments.value = false;
    }
};

const postComment = async () => {
    if (!commentText.value.trim()) return;
    try {
        const res = await api.post(`/videos/${currentVideo.value.id}/comment`, { comment: commentText.value });
        comments.value.unshift(res.data.comment);
        currentVideo.value.comments_count++;
        commentText.value = ''; 
    } catch (e) {
        alert("Failed to post comment");
    }
};

const copyLink = (videoId) => {
    const url = `${window.location.origin}/reels?video=${videoId}`;
    navigator.clipboard.writeText(url);
    alert("Link copied!");
};

const toggleMute = () => {
    globalMuted.value = !globalMuted.value;
    document.querySelectorAll('.reel-video').forEach(v => { v.muted = globalMuted.value; });
};

// --- MOBILE VIDEO LOGIC ---

const togglePlay = (event) => {
    const video = event.target;
    if (video.paused) {
        video.play();
    } else {
        video.pause();
    }
};

const handleIntersection = (entries) => {
    entries.forEach(entry => {
        const videoEl = entry.target.querySelector('video');
        if (!videoEl) return;

        if (entry.isIntersecting) {
            const videoId = entry.target.dataset.id;
            // View count
            api.post(`/videos/${videoId}/view`).catch(() => {});
            
            // Attempt Play
            videoEl.play().catch(error => {
                console.log("Autoplay blocked, user needs to tap");
            });
        } else {
            videoEl.pause();
        }
    });
};

const initObserver = () => {
    const observer = new IntersectionObserver(handleIntersection, { threshold: 0.6 });
    document.querySelectorAll('.reel-container').forEach(el => {
        observer.observe(el);
    });
};

onMounted(fetchVideos);
</script>

<template>
  <div class="h-screen w-full bg-black overflow-y-scroll snap-y snap-mandatory">
    
    <!-- Video Loop -->
    <div v-for="video in videos" :key="video.id" 
         class="h-screen w-full snap-start relative flex items-center justify-center reel-container bg-black"
         :data-id="video.id">
        
        <!-- Video Element -->
        <video 
            :src="getMediaUrl(video.video_path, 'video')" 
            loop 
            :muted="globalMuted" 
            playsinline 
            webkit-playsinline
            x5-video-player-type="h5"
            x5-video-player-fullscreen="true"
            preload="auto"
            class="h-full w-full object-contain reel-video bg-black"
            @click="togglePlay"
        ></video>

        <!-- GRADIENT OVERLAY -->
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/60 pointer-events-none"></div>

        <!-- ================= SIDEBAR (Right) ================= -->
        <div class="absolute right-3 bottom-20 flex flex-col items-center gap-5 z-20">
            
            <!-- Shop Logo -->
            <router-link :to="`/artisan/shop/${video.shop?.id}`" class="relative flex flex-col items-center">
                <div class="w-10 h-10 rounded-full border-2 border-white overflow-hidden bg-gray-800 flex items-center justify-center">
                    <!-- ✅ Updated to getMediaUrl -->
                    <img v-if="video.shop?.logo" :src="getMediaUrl(video.shop.logo, 'image')" class="w-full h-full object-cover" alt="Shop">
                    <span v-else class="text-white font-bold text-sm">{{ video.shop?.name?.charAt(0) }}</span>
                </div>
            </router-link>

            <!-- Like -->
            <button @click="toggleLike(video)" class="flex flex-col items-center active:scale-125 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" 
                     :class="video.is_liked ? 'text-red-500' : 'text-white'" 
                     class="w-8 h-8 drop-shadow-lg">
                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                </svg>
                <span class="text-white text-xs font-semibold mt-1">{{ video.likes_count }}</span>
            </button>

            <!-- Comment -->
            <button @click="openComments(video)" class="flex flex-col items-center active:scale-125 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white drop-shadow-lg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z" />
                </svg>
                <span class="text-white text-xs font-semibold mt-1">{{ video.comments_count }}</span>
            </button>

            <!-- Share -->
            <button @click="copyLink(video.id)" class="flex flex-col items-center active:scale-125 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white drop-shadow-lg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                </svg>
            </button>
            
            <!-- Mute -->
            <button @click="toggleMute" class="flex flex-col items-center active:scale-125 transition-transform mt-2">
                 <svg v-if="globalMuted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white drop-shadow-lg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6l2.25-2.25L6.75 9.75H4.5v4.5h2.25l4.5 4.5V9.75z" />
                 </svg>
                 <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white drop-shadow-lg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                 </svg>
            </button>
        </div>

        <!-- BOTTOM INFO -->
        <div class="absolute bottom-4 left-4 right-16 text-white z-10">
             <div class="flex items-center gap-2 mb-1">
                <router-link :to="`/artisan/shop/${video.shop?.id}`" class="font-bold text-sm hover:underline">
                    {{ video.shop?.name }}
                </router-link>
                <span class="bg-blue-500 text-[10px] px-1 rounded">Shop</span>
             </div>
             <p class="text-xs text-gray-100 leading-snug mb-2 line-clamp-2">
                {{ video.caption }}
             </p>
             <!-- Product Link -->
             <a v-if="video.product_url" :href="video.product_url" target="_blank" 
                class="inline-flex items-center gap-1 text-[11px] text-emerald-400 font-medium hover:underline bg-black/30 px-2 py-1 rounded-full">
                🛒 Shop Product
             </a>
        </div>
    </div>

    <!-- COMMENT MODAL -->
    <div v-if="showComments" class="fixed inset-0 bg-black/40 z-50" @click.self="showComments = false">
        <div class="absolute bottom-0 left-0 right-0 bg-white h-1/2 rounded-t-2xl flex flex-col shadow-2xl">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Comments</h3>
                <button @click="showComments = false" class="text-gray-500 text-2xl">&times;</button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div v-if="loadingComments" class="text-center text-gray-400 text-sm py-10">Loading...</div>
                <div v-if="!loadingComments && comments.length === 0" class="text-center text-gray-400 text-sm py-10">No comments yet.</div>
                <div v-for="comment in comments" :key="comment.id" class="flex gap-3 items-start">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex-shrink-0 flex items-center justify-center text-xs font-bold">
                        {{ comment.user?.name?.charAt(0) }}
                    </div>
                    <div>
                        <p class="text-xs text-gray-800">
                            <span class="font-bold mr-1">{{ comment.user?.name }}</span>
                            {{ comment.comment }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t bg-gray-50">
                <form @submit.prevent="postComment" class="flex gap-2">
                    <input v-model="commentText" type="text" placeholder="Add a comment..." class="flex-1 border border-gray-300 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500" />
                    <button type="submit" class="text-emerald-600 font-bold text-sm px-2">Post</button>
                </form>
            </div>
        </div>
    </div>

  </div>
</template>