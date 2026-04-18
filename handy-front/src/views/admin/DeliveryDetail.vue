<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const delivery = ref(null);
const isLoading = ref(true);
const rejectionReason = ref('');

// Removed URL helpers to match the Reels pattern: 
// The backend directly provides the full absolute URL.

onMounted(async () => {
    const deliveryId = route.params.id;
    if (!deliveryId) {
        alert("No Delivery ID found.");
        router.push('/admin/dashboard');
        return;
    }

    try {
        const response = await api.get(`/admin/deliveries/${deliveryId}`);
        delivery.value = response.data.delivery;
    } catch (error) {
        console.error('Failed to load delivery details', error);
        alert('Could not load data.');
        router.push('/admin/dashboard');
    } finally {
        isLoading.value = false;
    }
});

const statusBadge = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const approve = async () => {
    if (!confirm('Are you sure you want to approve this delivery personnel?')) return;
    try {
        await api.post(`/admin/delivery-approve/${delivery.value.id}`);
        alert('Approved Successfully!');
        router.push('/admin/dashboard');
    } catch (error) {
        console.error(error);
        alert(error.response?.data?.message || 'Error approving.');
    }
};

const reject = async () => {
    if (!rejectionReason.value) {
        alert('Please provide a reason for rejection.');
        return;
    }
    try {
        await api.post(`/admin/delivery-reject/${delivery.value.id}`, {
            rejection_reason: rejectionReason.value
        });
        alert('Rejected successfully.');
        router.push('/admin/dashboard');
    } catch (error) {
        console.error(error);
        alert(error.response?.data?.message || 'Error rejecting.');
    }
};

const logout = () => {
    authStore.logout();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        
        <!-- Admin Navigation -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-emerald-600">Handy</span>
                    <span class="text-2xl font-bold text-amber-400">Store</span>
                    <span class="ml-4 text-xs font-semibold text-gray-400 uppercase tracking-widest">Admin</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.push('/admin/dashboard')" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                        &larr; Back to Dashboard
                    </button>
                    <button @click="logout" class="text-red-500 hover:text-red-700 text-sm font-medium">
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="py-10">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div v-if="isLoading" class="text-center py-20 text-gray-400">Loading details...</div>

                <template v-else-if="delivery">
                    
                    <!-- Header -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                        <div class="flex items-center gap-4">
                            <!-- Avatar / Profile Image (Direct binding like Reels) -->
                            <div v-if="delivery.profile_image" class="w-16 h-16 rounded-full overflow-hidden border-2 border-amber-300 flex-shrink-0">
                                <img :src="delivery.profile_image" class="w-full h-full object-cover" alt="Profile">
                            </div>
                            <div v-else class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-2xl font-bold text-amber-700">
                                    {{ delivery.user?.first_name?.charAt(0) }}{{ delivery.user?.last_name?.charAt(0) }}
                                </span>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">
                                    {{ delivery.user?.first_name }} {{ delivery.user?.last_name }}
                                </h1>
                                <p class="text-gray-500">Submitted on {{ new Date(delivery.submitted_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                        <span :class="statusBadge(delivery.approval_status)" class="px-3 py-1 text-sm font-semibold rounded-full">
                            {{ delivery.approval_status?.toUpperCase() }}
                        </span>
                    </div>

                    <!-- Personal Information Card -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Personal Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <span class="block text-gray-500">Email Address</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.user?.email }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Phone Number</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.user?.phone_number }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Date of Birth</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.user?.birthdate || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Gender</span>
                                    <span class="font-semibold text-gray-900 capitalize">{{ delivery.user?.gender || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">National ID Number</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.national_id_number }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Driving License #</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.driving_license_number || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">License Expiry</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.driving_license_expiry || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Emergency Contact</span>
                                    <span class="font-semibold text-gray-900">
                                        {{ delivery.emergency_contact_name || 'N/A' }} 
                                        <span v-if="delivery.emergency_contact_phone" class="text-gray-500">({{ delivery.emergency_contact_phone }})</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Information Card -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Vehicle Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <span class="block text-gray-500">Vehicle Type</span>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 mt-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold uppercase">
                                        {{ delivery.vehicle_type }}
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Plate Number</span>
                                    <span class="font-semibold text-gray-900 font-mono tracking-wide">{{ delivery.vehicle_plate_number }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Vehicle Model</span>
                                    <span class="font-semibold text-gray-900">{{ delivery.vehicle_model || 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Vehicle Color</span>
                                    <span class="font-semibold text-gray-900 capitalize">{{ delivery.vehicle_color || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Card (If exists) -->
                    <div v-if="delivery.user?.addresses?.length > 0" class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Registered Address</h3>
                        </div>
                        <div class="p-6 text-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="addr in delivery.user.addresses" :key="addr.id">
                                    <span class="block text-gray-500">City / Subcity / Woreda</span>
                                    <span class="font-semibold text-gray-900">
                                        {{ addr.city?.name || 'N/A' }} 
                                        <span v-if="addr.subcity">, {{ addr.subcity.name }}</span>
                                        <span v-if="addr.woreda">, {{ addr.woreda.name }}</span>
                                    </span>
                                    <span class="block text-gray-500 mt-3">Street / Landmark</span>
                                    <span class="font-semibold text-gray-900">
                                        {{ addr.street }}
                                        <span v-if="addr.landmark" class="text-gray-500"> (Near: {{ addr.landmark }})</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Card -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Uploaded Documents</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            
                            <!-- National ID Doc (Direct binding like Reels) -->
                            <div class="border rounded-lg p-4 bg-gray-50 text-center">
                                <p class="font-semibold text-sm text-gray-700 mb-2">National ID</p>
                                <a v-if="delivery.national_id_document" 
                                   :href="delivery.national_id_document.path" 
                                   target="_blank"
                                   class="inline-block px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md text-sm font-medium hover:bg-emerald-100">
                                    View File
                                </a>
                                <p v-else class="text-gray-400 text-xs">Not uploaded</p>
                            </div>

                            <!-- Driving License Doc -->
                            <div class="border rounded-lg p-4 bg-gray-50 text-center">
                                <p class="font-semibold text-sm text-gray-700 mb-2">Driving License</p>
                                <a v-if="delivery.driving_license_document" 
                                   :href="delivery.driving_license_document.path" 
                                   target="_blank"
                                   class="inline-block px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md text-sm font-medium hover:bg-emerald-100">
                                    View File
                                </a>
                                <p v-else class="text-gray-400 text-xs">Not uploaded</p>
                            </div>

                            <!-- Vehicle Registration Doc -->
                            <div class="border rounded-lg p-4 bg-gray-50 text-center">
                                <p class="font-semibold text-sm text-gray-700 mb-2">Vehicle Registration</p>
                                <a v-if="delivery.vehicle_registration_document" 
                                   :href="delivery.vehicle_registration_document.path" 
                                   target="_blank"
                                   class="inline-block px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md text-sm font-medium hover:bg-emerald-100">
                                    View File
                                </a>
                                <p v-else class="text-gray-400 text-xs">Not uploaded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div v-if="delivery.approval_status === 'pending'" class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Actions</h3>
                        </div>
                        <div class="p-6">
                            <button @click="approve" 
                                class="w-full md:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-md shadow-sm transition">
                                Approve Application
                            </button>

                            <div class="mt-8 border-t pt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Rejection Reason (Required if rejecting)
                                </label>
                                <textarea v-model="rejectionReason" 
                                    class="w-full border border-gray-300 rounded-md shadow-sm p-3 focus:ring-red-500 focus:border-red-500" 
                                    rows="3" 
                                    placeholder="e.g., Driving license is expired or blurry."></textarea>
                                <button @click="reject" 
                                    class="mt-4 bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-md transition">
                                    Reject Application
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason Display (If already rejected) -->
                    <div v-if="delivery.approval_status === 'rejected' && delivery.rejection_reason" class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-bold text-red-800 mb-1">Rejection Reason:</h4>
                        <p class="text-sm text-red-700">{{ delivery.rejection_reason }}</p>
                    </div>

                </template>

                <div v-else class="text-center py-20 text-gray-400">No data found.</div>

            </div>
        </main>
    </div>
</template>