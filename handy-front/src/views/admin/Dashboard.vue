<script setup>
import { ref, onMounted } from 'vue';
import api from '@/plugins/axios';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { usePermissions } from '@/composables/usePermissions';

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();

const { 
    canManageProducts, 
    canApproveDelivery, 
    canAssignDriver, 
    canViewOrders,
    canApproveWithdrawal
} = usePermissions();

// State
const stats = ref({ pending_artisans: 0, total_buyers: 0, total_artisans: 0, pending_delivery: 0 });
const pendingArtisans = ref([]);
const pendingDrivers = ref([]);
const readyOrders = ref([]);
const approvedDrivers = ref([]);
const withdrawals = ref([]);
const isLoading = ref(true);
const showRejectModal = ref(false);
const rejectForm = ref({ id: null, type: null, reason: '' });

// ==========================================
// API CALLS (Must be declared before onMounted)
// ==========================================

const fetchDashboard = async () => {
    try {
        const res = await api.get('/admin/dashboard');
        stats.value = res.data.stats;
        pendingArtisans.value = res.data.pendingArtisans;
    } catch (e) { console.error("Failed to load dashboard", e); }
    finally { isLoading.value = false; }
};

const fetchPendingDrivers = async () => {
    try {
        const res = await api.get('/admin/delivery-pending');
        pendingDrivers.value = res.data;
    } catch (e) { console.error("Failed to fetch drivers", e); }
};

const fetchApprovedDrivers = async () => {
    try {
        const res = await api.get('/admin/drivers/approved');
        approvedDrivers.value = res.data.drivers; // Updated: access 'drivers' key
    } catch (e) { 
        console.error("Failed to fetch approved drivers", e); 
    }
};

const fetchReadyOrders = async () => {
    try {
        const res = await api.get('/admin/orders-ready');
        readyOrders.value = res.data;
        await fetchApprovedDrivers(); 
    } catch (e) { console.error("Failed to fetch orders", e); }
};

const fetchWithdrawals = async () => {
    if (!canApproveWithdrawal.value) return;
    try {
        const res = await api.get('/admin/withdrawals');
        withdrawals.value = res.data.data;
    } catch (e) { console.error("Failed to fetch withdrawals"); }
};

// ==========================================
// ACTIONS
// ==========================================

const openRejectModal = (id, type) => {
    rejectForm.value = { id, type, reason: '' };
    showRejectModal.value = true;
};

const submitReject = async () => {
    if (!rejectForm.value.reason.trim()) return alert("Reason is required");
    try {
        let endpoint = '';
        if ($type === 'artisan') endpoint = `/admin/reject/${rejectForm.value.type}/${rejectForm.value.id}`;
        else if ($type === 'delivery') endpoint = `/admin/reject/${rejectForm.value.type}/${rejectForm.value.id}`;
        else if ($type === 'withdrawal') endpoint = `/admin/withdrawals/${rejectForm.value.id}/reject`;
        
        await api.post(endpoint, { rejection_reason: rejectForm.value.reason });
        
        showRejectModal.value = false;
        alert("Rejected successfully!");
        
        if (rejectForm.value.type === 'artisan') fetchDashboard();
        else if (rejectForm.value.type === 'delivery') fetchPendingDrivers();
        else if (rejectForm.value.type === 'withdrawal') fetchWithdrawals();
    } catch (e) { 
        alert(e.response?.data?.message || "Failed to reject"); 
    }
};

const approveItem = async (type, id) => {
    if (!confirm(`Approve this ${type}?`)) return;
    try {
        let endpoint = '';
        if (type === 'artisan') endpoint = `/admin/approve/${type}/${id}`;
        else if (type === 'delivery') endpoint = `/admin/delivery-approve/${id}`;
        else if (type === 'withdrawal') endpoint = `/admin/withdrawals/${id}/approve`;
        
        await api.post(endpoint);
        alert("Approved!");
        
        if (type === 'withdrawal') fetchWithdrawals();
        else if (type === 'artisan') fetchDashboard();
        else if (type === 'delivery') fetchPendingDrivers();
    } catch (e) { 
        alert(e.response?.data?.message || "Failed to approve."); 
    }
};

const assignDriver = async (orderId) => {
    const selectEl = document.getElementById(`driver-${orderId}`);
    const driverId = selectEl?.value;
    if (!driverId) return alert("Please select a driver from the list.");
    
    try {
        await api.post(`/admin/orders/${orderId}/assign`, { delivery_person_id: driverId });
        alert("Driver successfully assigned!");
        fetchReadyOrders();
    } catch (e) { alert(e.response?.data?.message || "Failed to assign driver."); }
};

const logout = () => authStore.logout();

// ==========================================
// LIFECYCLE (Everything is safely inside here now)
// ==========================================

onMounted(async () => {
    const hasAnyAdminAccess = canManageProducts.value || 
                              canApproveDelivery.value || 
                              canAssignDriver.value ||
                              canApproveWithdrawal.value;
    
    if (!authStore.isAuthenticated || !hasAnyAdminAccess) {
        router.push('/login');
        return;
    }

    await fetchDashboard(); 

    // Fetch data based on permissions
    if (canApproveDelivery.value) await fetchPendingDrivers();
    if (canViewOrders.value) await fetchReadyOrders();
    
    // ✅ FIX: Moved inside onMounted!
    if (canApproveWithdrawal.value) await fetchWithdrawals();
});
</script>

<template>
    <!-- Template remains EXACTLY the same as your last paste, it is correct! -->
    <div class="min-h-screen bg-gray-50">
        
        <!-- Rejection Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-2xl">
                <h3 class="text-lg font-bold text-gray-900 mb-4">{{ t('admin.rejection_reason') || 'Provide Rejection Reason' }}</h3>
                <textarea v-model="rejectForm.reason" :placeholder="t('admin.rejection_placeholder') || 'Enter reason...'" class="w-full border p-3 rounded-lg mb-4 h-28 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none"></textarea>
                <div class="flex justify-end gap-3">
                    <button @click="showRejectModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">{{ t('admin.cancel') || 'Cancel' }}</button>
                    <button @click="submitReject" class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700">{{ t('admin.submit_reject') || 'Reject' }}</button>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-emerald-600">Handy</span>
                    <span class="text-2xl font-bold text-amber-400">Store</span>
                    <span class="ml-4 text-xs font-semibold text-gray-400 uppercase tracking-widest">Admin</span>
                </div>
                <div class="flex items-center gap-4">
                    <LanguageSwitcher />
                    <span class="text-gray-600 text-sm hidden sm:block">Welcome, {{ authStore.user?.name }}</span>
                    <button @click="logout" class="text-red-500 hover:text-red-700 text-sm font-medium">Logout</button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">{{ t('admin.dashboard_title') || 'Dashboard Overview' }}</h1>
                    <p class="mt-1 text-gray-500">{{ t('admin.dashboard_sub') || 'Manage users, approvals, and platform activity.' }}</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <div v-if="canManageProducts" class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-amber-400 p-5">
                        <dt class="text-sm font-medium text-gray-500 truncate">{{ t('admin.pending_approvals') || 'Pending Artisans' }}</dt>
                        <dd class="text-3xl font-semibold text-gray-900 mt-1">{{ stats.pending_artisans }}</dd>
                    </div>
                    
                    <div v-if="canManageProducts" class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-blue-400 p-5">
                        <dt class="text-sm font-medium text-gray-500 truncate">{{ t('admin.total_buyers') || 'Total Buyers' }}</dt>
                        <dd class="text-3xl font-semibold text-gray-900 mt-1">{{ stats.total_buyers }}</dd>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-emerald-400 p-5">
                        <dt class="text-sm font-medium text-gray-500 truncate">{{ t('admin.total_artisans') || 'Total Artisans' }}</dt>
                        <dd class="text-3xl font-semibold text-gray-900 mt-1">{{ stats.total_artisans }}</dd>
                    </div>

                    <div v-if="canApproveDelivery" class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-purple-400 p-5">
                        <dt class="text-sm font-medium text-gray-500 truncate">{{ t('admin.pending_drivers') || 'Pending Drivers' }}</dt>
                        <dd class="text-3xl font-semibold text-gray-900 mt-1">{{ stats.pending_delivery }}</dd>
                    </div>
                </div>

                <!-- OPERATIONS SECTION -->
                <div v-if="canManageProducts" class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900">{{ t('admin.pending_approvals') || 'Pending Artisan Applications' }}</h3>
                        <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-1 rounded-full">Needs Review</span>
                    </div>
                    <div v-if="isLoading" class="p-10 text-center text-gray-500">Loading data...</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Shop Details</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Owner</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Submitted</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="artisan in pendingArtisans" :key="artisan.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs">LOGO</div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ artisan.shop_name }}</div>
                                            <div class="text-sm text-gray-500">{{ artisan.slang || 'No slang' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ artisan.user?.name || 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(artisan.submitted_at).toLocaleDateString() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center space-x-2">
                                    <button @click="approveItem('artisan', artisan.id)" class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-bold hover:bg-emerald-200">{{ t('admin.approve') || 'Approve' }}</button>
                                    <button @click="openRejectModal(artisan.id, 'artisan')" class="px-3 py-1 bg-red-100 text-red-700 rounded text-xs font-bold hover:bg-red-200">{{ t('admin.reject') || 'Reject' }}</button>
                                    <button @click="router.push('/admin/artisans/' + artisan.id)" class="px-3 py-1 bg-gray-800 text-white rounded text-xs font-bold hover:bg-gray-900">{{ t('admin.view_verify') || 'View Details' }}</button>
                                </td>
                            </tr>
                            <tr v-if="pendingArtisans.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-400">{{ t('admin.no_pending_artisan') || 'No pending artisan applications.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- DELIVERY SECTION: Drivers -->
                <div v-if="canApproveDelivery" class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900">{{ t('admin.drivers_table_title') || 'Pending Delivery Applications' }}</h3>
                        <span class="bg-purple-100 text-purple-700 text-xs font-bold px-2 py-1 rounded-full">Needs Review</span>
                    </div>
                    <div v-if="isLoading" class="p-10 text-center text-gray-500">Loading drivers...</div>
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Driver Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vehicle</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Submitted</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="driver in pendingDrivers" :key="driver.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ driver.user?.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ driver.vehicle_type }} ({{ driver.vehicle_plate_number }})</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ new Date(driver.submitted_at).toLocaleDateString() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center space-x-2">
                                    <button @click="approveItem('delivery', driver.id)" class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-bold hover:bg-emerald-200">{{ t('admin.approve') || 'Approve' }}</button>
                                    <button @click="openRejectModal(driver.id, 'delivery')" class="px-3 py-1 bg-red-100 text-red-700 rounded text-xs font-bold hover:bg-red-200">{{ t('admin.reject') || 'Reject' }}</button>
                                    <button @click="router.push('/admin/deliveries/' + driver.id)" class="px-3 py-1 bg-gray-800 text-white rounded text-xs font-bold hover:bg-gray-900">{{ t('admin.view_verify') || 'View Details' }}</button>
                                </td>
                            </tr>
                            <tr v-if="pendingDrivers.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-400">{{ t('admin.no_pending_drivers') || 'No pending delivery applications.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Replace your existing DELIVERY SECTION: Orders block with this -->
                <div v-if="canAssignDriver" class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900">Orders Ready for Assignment</h3>
                        <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-1 rounded-full">{{ readyOrders.length }} Pending</span>
                    </div>
                    <div v-if="isLoading" class="p-10 text-center text-gray-500">Loading orders...</div>
                    
                    <div v-else-if="readyOrders.length === 0" class="p-10 text-center text-gray-400 border-t">
                        No orders waiting for drivers.
                    </div>
                    
                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Order / Seller</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dropoff</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Distance/Fee</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Assign Driver</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="order in readyOrders" :key="order.id" class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <p class="text-sm font-bold text-gray-900">{{ order.order_number }}</p>
                                    <p class="text-xs text-gray-500">{{ order.seller?.name }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    {{ order.delivery_address?.street }}
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    {{ order.distance_km }} km / {{ order.delivery_fee }} ETB
                                </td>
                                <td class="px-4 py-4">
                                    <select :id="`driver-${order.id}`" 
                                            class="border border-gray-300 rounded p-1.5 text-sm w-full bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                        <option value="">-- Select Driver --</option>
                                        <option v-for="driver in approvedDrivers" :key="driver.id" :value="driver.id">
                                            {{ driver.name }} - {{ driver.phone_number }} 
                                            ({{ driver.delivery_profile?.vehicle_type }} | {{ driver.delivery_profile?.vehicle_plate_number }} | {{ driver.delivery_profile?.assigned_zone }})
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button 
                                        @click="assignDriver(order.id)" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded text-xs font-bold hover:bg-blue-700 transition"
                                    >
                                        Assign
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
                        <!-- FINANCE SECTION: Withdrawals -->
            <div v-if="canApproveWithdrawal" class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">💸 Pending Withdrawal Requests</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Artisan / Bank</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="w in withdrawals" :key="w.id">
                            <td class="px-6 py-4 text-sm">
                                <p class="font-medium text-gray-900">{{ w.user?.name }}</p>
                                <p class="text-xs text-gray-500">{{ w.bank_name }} - {{ w.bank_account_number }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-900">{{ w.amount }} ETB</td>
                            <td class="px-6 py-4 text-center space-x-2">
                                <button @click="approveItem('withdrawal', w.id)" class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-bold hover:bg-emerald-200">Approve</button>
                                <button @click="openRejectModal(w.id, 'withdrawal')" class="px-3 py-1 bg-red-100 text-red-700 rounded text-xs font-bold hover:bg-red-200">Reject</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>