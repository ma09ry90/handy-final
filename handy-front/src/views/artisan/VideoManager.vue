<script setup>
import { ref, onMounted } from 'vue';
import api from '@/plugins/axios';

const form = ref({
    video: null,
    caption: '',
    product_url: '',
    profile_url: '' 
});

// --- FIX: Media URL Helper ---
const getImageUrl = (path) => {
    if (!path) return null;
    if (path.startsWith('http')) return path; 
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

const myVideos = ref([]);
const loading = ref(false);
const editMode = ref(false); 
const editId = ref(null);    

const fetchMyVideos = async () => {
    try {
        const res = await api.get('/artisan/videos');
        myVideos.value = res.data;
    } catch (e) {
        console.error(e);
    }
};

const handleFileChange = (e) => {
    form.value.video = e.target.files[0];
};

const resetForm = () => {
    form.value = { video: null, caption: '', product_url: '', profile_url: '' };
    editMode.value = false;
    editId.value = null;
    const fileInput = document.getElementById('video-file');
    if (fileInput) fileInput.value = '';
};

const editVideo = (video) => {
    editMode.value = true;
    editId.value = video.id;
    form.value.caption = video.caption;
    form.value.product_url = video.product_url || '';
    form.value.profile_url = video.profile_url || '';
    form.value.video = null; 
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// FIX: Send JSON instead of FormData
const submitVideo = async () => {
    if (!editMode.value && !form.value.video) {
        return alert("Please select a video file");
    }
    if (!form.value.caption) {
        return alert("Caption is required");
    }

    const data = { ...form.value };
    if (editMode.value) {
        data._method = 'PUT';
    }

    try {
        loading.value = true;
        await api.post(`/artisan/videos/${editId.value}`, data, {
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        
        alert(editMode.value ? 'Video updated successfully!' : 'Video uploaded successfully!');
        resetForm();
        fetchMyVideos(); 
        
    } catch (e) {
        alert(e.response?.data?.message || 'Operation failed');
    } finally {
        loading.value = false;
    }
};

const deleteVideo = async (id) => {
    if(confirm('Are you sure you want to delete this video?')) {
        try {
            await api.delete(`/artisan/videos/${id}`);
            fetchMyVideos();
        } catch (e) {
            alert('Delete failed');
        }
    }
};

onMounted(fetchMyVideos);
</script>

<template>
  <div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 text-gray-800">Manage Your Reels</h1>
    
    <!-- Upload/Edit Form -->
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
        <h2 class="text-lg font-bold mb-4 text-gray-700">
            {{ editMode ? 'Edit Video Details' : 'Upload New Video' }}
        </h2>
        
        <div class="space-y-4">
            <!-- Video File Input -->
            <div v-if="!editMode">
                <label class="block text-sm font-medium text-gray-600 mb-1">Video File (MP4/MOV)</label>
                <input 
                    id="video-file"
                    type="file" 
                    @change="handleFileChange" 
                    accept="video/*" 
                    class="mb-4 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                />
            </div>
            
            <!-- Caption -->
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Caption</label>
                <textarea v-model="form.caption" placeholder="Write a caption..." rows="3" class="w-full border-gray-300 rounded-lg border p-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
            </div>
            
            <!-- Product Link -->
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Product Link (Optional)</label>
                <input v-model="form.product_url" type="url" placeholder="https://yoursite.com/product/1" class="w-full border-gray-300 rounded-lg border p-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent"/>
            </div>

            <!-- Profile Link -->
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">External Profile Link (Optional)</label>
                <input v-model="form.profile_url" type="url" placeholder="https://instagram.com/yourshop" class="w-full border-gray-300 rounded-lg border p-2 focus:ring-2 focus:ring-emerald-500 focus:border-transparent"/>
                <p class="text-xs text-gray-400 mt-1">Link to your Instagram, Facebook, or external portfolio.</p>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4">
                <button 
                    @click="submitVideo" 
                    :disabled="loading"
                    class="bg-emerald-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-emerald-700 transition disabled:opacity-50"
                >
                    {{ loading ? 'Saving...' : (editMode ? 'Update Video' : 'Post Video') }}
                </button>

                <button 
                    v-if="editMode"
                    @click="resetForm"
                    class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-semibold hover:bg-gray-300 transition"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Video List -->
    <div v-if="myVideos.length > 0">
        <h3 class="text-lg font-bold mb-4 text-gray-700">Your Videos</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div v-for="video in myVideos" :key="video.id" class="bg-white shadow-sm rounded-lg overflow-hidden border relative group">
                <!-- FIX: Added getImageUrl for Videos -->
                <video 
                    :src="getImageUrl(video.video_path)" 
                    class="w-full h-48 object-cover bg-black" 
                    controls
                    preload="metadata"
                ></video>
                
                <!-- Overlay Info -->
                <div class="p-3">
                    <p class="text-xs text-gray-500 truncate">{{ video.caption }}</p>
                    
                    <!-- External Links Indicators (Escaped for Vite HMR) -->
                    <div class="flex gap-2 mt-1">
                        <span v-if="video.product_url" class="text-blue-500 text-[10px]">&#128142; Shop Product</span>
                        <span v-if="video.profile_url" class="text-purple-500 text-[10px]">&#128100; Profile</span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-gray-400">{{ video.views_count }} views</span>
                        <div class="flex gap-2">
                            <button @click="editVideo(video)" class="text-blue-500 text-xs font-medium hover:text-blue-700">Edit</button>
                            <button @click="deleteVideo(video.id)" class="text-red-500 text-xs font-medium hover:text-red-700">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div v-else class="text-center py-12 text-gray-400">
        You haven't uploaded any videos yet.
    </div>
  </div>
</template>