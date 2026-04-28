<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/plugins/axios';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { usePermissions } from '@/composables/usePermissions';

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const revenue = ref({ total: 0, today: 0 })
const artisans = ref([])
const loadingArtisans = ref(false)
const searchQuery = ref('')

const { 
    canManageProducts, 
    canHideProduct,
    canBlockArtisan,
    canHandleReports,
    canApproveDelivery, 
    canAssignDriver, 
    canViewOrders,
    canApproveWithdrawal
} = usePermissions();

// ==========================================
// STATE
// ==========================================
const stats = ref({ pending_artisans: 0, total_buyers: 0, total_artisans: 0, pending_delivery: 0 });
const pendingArtisans = ref([]);
const pendingDrivers = ref([]);
const approvedDrivers = ref([]);
const readyOrders = ref([]);
const withdrawals = ref([]);

// New sections
const buyers = ref({ data: [], current_page: 1, last_page: 1 });
const products = ref({ data: [], current_page: 1, last_page: 1 });
const reports = ref({ data: [], current_page: 1, last_page: 1 });
const reportStats = ref({ pending: 0, resolved_today: 0, total: 0 });

// Search & filter
const buyerSearch = ref('');
const productSearch = ref('');
const productStatusFilter = ref('');
const reportStatusFilter = ref('');

// Loading
const isLoading = ref(true);
const buyersLoading = ref(false);
const productsLoading = ref(false);
const reportsLoading = ref(false);

// Modals
const showRejectModal = ref(false);
const rejectForm = ref({ id: null, type: null, reason: '' });
const showResolveModal = ref(false);
const resolveForm = ref({ id: null, action: 'resolve', resolution_note: '', block_user: false, hide_product: false });

// ==========================================
// COMPUTED: Notification Summary
// ==========================================
const notificationItems = computed(() => {
    const items = [];
    if (canManageProducts.value && stats.value.pending_artisans > 0) {
        items.push({ key: 'artisans', color: 'amber', text: `${stats.value.pending_artisans} pending artisan application(s) need review` });
    }
    if (canApproveDelivery.value && stats.value.pending_delivery > 0) {
        items.push({ key: 'drivers', color: 'purple', text: `${stats.value.pending_delivery} pending delivery application(s) need review` });
    }
    if (canAssignDriver.value && readyOrders.value.length > 0) {
        items.push({ key: 'orders', color: 'blue', text: `${readyOrders.value.length} order(s) ready for driver assignment` });
    }
    if (canApproveWithdrawal.value && withdrawals.value.length > 0) {
        items.push({ key: 'withdrawals', color: 'red', text: `${withdrawals.value.length} pending withdrawal request(s) need processing` });
    }
    if (canHandleReports.value && reportStats.value.pending > 0) {
        items.push({ key: 'reports', color: 'orange', text: `${reportStats.value.pending} unresolved report(s)` });
    }
    return items;
});

const hasNotifications = computed(() => notificationItems.value.length > 0);

// ==========================================
// API CALLS: Core Dashboard
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
    } catch (e) { console.error("Failed to fetch pending drivers", e); }
};

const fetchApprovedDrivers = async () => {
    try {
        const res = await api.get('/admin/drivers/approved');
        approvedDrivers.value = res.data.drivers;
    } catch (e) { console.error("Failed to fetch approved drivers", e); }
};

const fetchReadyOrders = async () => {
    try {
        const res = await api.get('/admin/orders-ready');
        readyOrders.value = res.data;
        await fetchApprovedDrivers();
    } catch (e) { console.error("Failed to fetch orders", e); }
};

const fetchWithdrawals = async () => {
    try {
        const res = await api.get('/admin/withdrawals');
        withdrawals.value = res.data.data;
    } catch (e) { console.error("Failed to fetch withdrawals", e); }
};

// ==========================================
// API CALLS: Buyers
// ==========================================

const fetchBuyers = async (page = 1) => {
    buyersLoading.value = true;
    try {
        const params = { page };
        if (buyerSearch.value.trim()) params.search = buyerSearch.value.trim();
        const res = await api.get('/admin/buyers', { params });
        buyers.value = res.data;
    } catch (e) { console.error("Failed to fetch buyers", e); }
    finally { buyersLoading.value = false; }
};

const toggleBlockBuyer = async (buyerId) => {
    if (!confirm("Are you sure you want to toggle this buyer's account status?")) return;
    try {
        const res = await api.post(`/admin/buyers/${buyerId}/toggle-block`);
        const action = res.data.account_status === 'blocked' ? 'blocked' : 'unblocked';
        alert(`Buyer account ${action}.`);
        await fetchBuyers(buyers.value.current_page);
    } catch (e) { alert(e.response?.data?.message || "Failed to toggle buyer."); }
};

// ==========================================
// API CALLS: Products
// ==========================================

const fetchProducts = async (page = 1) => {
    productsLoading.value = true;
    try {
        const params = { page };
        if (productSearch.value.trim()) params.search = productSearch.value.trim();
        if (productStatusFilter.value) params.status = productStatusFilter.value;
        const res = await api.get('/admin/products', { params });
        products.value = res.data;
    } catch (e) { console.error("Failed to fetch products", e); }
    finally { productsLoading.value = false; }
};

const toggleHideProduct = async (productId) => {
    try {
        const res = await api.post(`/admin/products/${productId}/toggle-hide`);
        const action = res.data.status === 'hidden_by_admin' ? 'hidden' : 'unhidden';
        alert(`Product ${action}.`);
        await fetchProducts(products.value.current_page);
    } catch (e) { alert(e.response?.data?.message || "Failed to toggle product."); }
};

// ==========================================
// API CALLS: Reports
// ==========================================

const fetchReports = async (page = 1) => {
    reportsLoading.value = true;
    try {
        const params = { page };
        if (reportStatusFilter.value) params.status = reportStatusFilter.value;
        const res = await api.get('/admin/reports', { params });
        reports.value = res.data;
    } catch (e) { console.error("Failed to fetch reports", e); }
    finally { reportsLoading.value = false; }
};

const fetchReportStats = async () => {
    try {
        const res = await api.get('/admin/reports/stats');
        reportStats.value = res.data;
    } catch (e) { console.error("Failed to fetch report stats", e); }
};

const openResolveModal = (report) => {
    resolveForm.value = {
        id: report.id,
        action: 'resolve',
        resolution_note: '',
        block_user: false,
        hide_product: report.reported_product_id ? false : null,
    };
    showResolveModal.value = true;
};

const fetchRevenue = async () => {
  try {
    const res = await api.get('/admin/revenue')
    revenue.value.total = res.data.total_revenue
    revenue.value.today = res.data.today_revenue
  } catch (e) {
    console.error("Failed to fetch revenue", e)
  }
};
const fetchArtisans = async () => {
  loadingArtisans.value = true
  try {
    const params = {}
    if (searchQuery.value) params.search = searchQuery.value
    
    const res = await api.get('/admin/artisans-list', { params })
    artisans.value = res.data.data
  } catch (e) {
    console.error("Failed to fetch artisans", e)
  } finally {
    loadingArtisans.value = false
  }
};

const submitResolve = async () => {
    if (!resolveForm.value.resolution_note.trim()) return alert("Resolution note is required.");
    try {
        const payload = {
            action: resolveForm.value.action,
            resolution_note: resolveForm.value.resolution_note,
        };
        if (resolveForm.value.block_user) payload.block_user = true;
        if (resolveForm.value.hide_product) payload.hide_product = true;

        await api.post(`/admin/reports/${resolveForm.value.id}/resolve`, payload);
        showResolveModal.value = false;
        alert("Report resolved successfully.");
        await fetchReports(reports.value.current_page);
        await fetchReportStats();
    } catch (e) { alert(e.response?.data?.message || "Failed to resolve report."); }
};

// ==========================================
// ACTIONS: Approve / Reject / Assign
// ==========================================

const openRejectModal = (id, type) => {
    rejectForm.value = { id, type, reason: '' };
    showRejectModal.value = true;
};

const submitReject = async () => {
    if (!rejectForm.value.reason.trim()) return alert("Reason is required");
    const type = rejectForm.value.type;
    const id = rejectForm.value.id;
    try {
        let endpoint = '';
        if (type === 'artisan') endpoint = `/admin/reject/${type}/${id}`;
        else if (type === 'delivery') endpoint = `/admin/reject/${type}/${id}`;
        else if (type === 'withdrawal') endpoint = `/admin/withdrawals/${id}/reject`;
        
        await api.post(endpoint, { rejection_reason: rejectForm.value.reason });
        showRejectModal.value = false;
        alert("Rejected successfully!");
        
        if (type === 'artisan') await fetchDashboard();
        else if (type === 'delivery') { await fetchPendingDrivers(); await fetchDashboard(); }
        else if (type === 'withdrawal') await fetchWithdrawals();
    } catch (e) { alert(e.response?.data?.message || "Failed to reject"); }
};

const approveItem = async (type, id) => {
    const label = type === 'withdrawal' ? 'withdrawal request' : `${type} application`;
    if (!confirm(`Approve this ${label}?`)) return;
    try {
        let endpoint = '';
        if (type === 'artisan') endpoint = `/admin/approve/${type}/${id}`;
        else if (type === 'delivery') endpoint = `/admin/delivery-approve/${id}`;
        else if (type === 'withdrawal') endpoint = `/admin/withdrawals/${id}/approve`;
        
        await api.post(endpoint);
        alert("Approved successfully!");
        
        if (type === 'withdrawal') await fetchWithdrawals();
        else if (type === 'artisan') await fetchDashboard();
        else if (type === 'delivery') { await fetchPendingDrivers(); await fetchDashboard(); }
    } catch (e) { alert(e.response?.data?.message || "Failed to approve."); }
};

const assignDriver = async (orderId) => {
    const selectEl = document.getElementById(`driver-${orderId}`);
    const driverId = selectEl?.value;
    if (!driverId) return alert("Please select a driver from the list.");
    try {
        await api.post(`/admin/orders/${orderId}/assign`, { delivery_person_id: driverId });
        alert("Driver assigned successfully!");
        await fetchReadyOrders();
    } catch (e) { alert(e.response?.data?.message || "Failed to assign driver."); }
};

const logout = () => authStore.logout();

// ==========================================
// LIFECYCLE
// ==========================================

onMounted(async () => {
    const hasAnyAdminAccess = canManageProducts.value || 
                              canApproveDelivery.value || 
                              canAssignDriver.value ||
                              canApproveWithdrawal.value ||
                              canHandleReports.value;
    
    if (!authStore.isAuthenticated || !hasAnyAdminAccess) {
        router.push('/login');
        return;
    }

    fetchRevenue()
    fetchArtisans()
    await fetchDashboard();
    if (canApproveDelivery.value) await fetchPendingDrivers();
    if (canAssignDriver.value) await fetchReadyOrders();
    if (canApproveWithdrawal.value) await fetchWithdrawals();
    if (canManageProducts.value) await fetchBuyers();
    if (canManageProducts.value || canHideProduct.value) await fetchProducts();
    if (canHandleReports.value) { await fetchReports(); await fetchReportStats(); }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        
        <!-- ============================== -->
        <!-- REJECT MODAL                   -->
        <!-- ============================== -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-2xl">
                <h3 class="text-lg font-bold text-gray-900 mb-1">{{ t('admin.rejection_reason') || 'Provide Rejection Reason' }}</h3>
                <p class="text-xs text-gray-500 mb-4">This reason will be visible to the applicant.</p>
                <textarea v-model="rejectForm.reason" :placeholder="t('admin.rejection_placeholder') || 'Enter reason...'" class="w-full border p-3 rounded-lg mb-4 h-28 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none"></textarea>
                <div class="flex justify-end gap-3">
                    <button @click="showRejectModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm font-medium">{{ t('admin.cancel') || 'Cancel' }}</button>
                    <button @click="submitReject" class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700 text-sm font-medium">{{ t('admin.submit_reject') || 'Reject' }}</button>
                </div>
            </div>
        </div>

        <!-- ============================== -->
        <!-- RESOLVE REPORT MODAL           -->
        <!-- ============================== -->
        <div v-if="showResolveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 shadow-2xl">
                <h3 class="text-lg font-bold text-gray-900 mb-1">Resolve Report</h3>
                <p class="text-xs text-gray-500 mb-4">Choose how to handle this report.</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                        <select v-model="resolveForm.action" class="w-full border p-2.5 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none">
                            <option value="resolve">Resolve (take action)</option>
                            <option value="dismiss">Dismiss (no action needed)</option>
                        </select>
                    </div>
                    <template v-if="resolveForm.action === 'resolve'">
                        <label v-if="resolveForm.hide_product !== null" class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="resolveForm.hide_product" class="w-4 h-4 text-orange-600 rounded border-gray-300">
                            <span class="text-sm text-gray-700">Also hide the reported product</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="resolveForm.block_user" class="w-4 h-4 text-orange-600 rounded border-gray-300">
                            <span class="text-sm text-gray-700">Also block the reported user</span>
                        </label>
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Note <span class="text-red-500">*</span></label>
                        <textarea v-model="resolveForm.resolution_note" placeholder="Describe the action taken..." class="w-full border p-2.5 rounded-lg h-24 text-sm focus:ring-2 focus:ring-orange-500 focus:outline-none"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-5">
                    <button @click="showResolveModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm font-medium">Cancel</button>
                    <button @click="submitResolve" class="px-4 py-2 text-white bg-orange-600 rounded-lg hover:bg-orange-700 text-sm font-medium">Submit</button>
                </div>
            </div>
        </div>

        <!-- ============================== -->
        <!-- HEADER                         -->
        <!-- ============================== -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-emerald-600">Handy</span>
                    <span class="text-2xl font-bold text-amber-400">Store</span>
                    <span class="ml-4 text-xs font-semibold text-gray-400 uppercase tracking-widest">Admin</span>
                </div>
                <div class="flex items-center gap-4">
                    <LanguageSwitcher />
                    <span class="text-gray-600 text-sm hidden sm:block">{{ authStore.user?.name }}</span>
                    <button @click="logout" class="text-red-500 hover:text-red-700 text-sm font-medium">Logout</button>
                </div>
            </div>
        </header>

        <!-- ============================== -->
        <!-- MAIN CONTENT                   -->
        <!-- ============================== -->
        <main class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Page Title -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage approvals, assignments, and platform activity.</p>
                </div>

                <!-- ============================== -->
                <!-- NOTIFICATION BANNER           -->
                <!-- ============================== -->
                <div v-if="hasNotifications && !isLoading" class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div class="flex-grow">
                            <h3 class="text-sm font-semibold text-amber-800">Action Required</h3>
                            <ul class="mt-1.5 space-y-1">
                                <li v-for="n in notificationItems" :key="n.key" class="text-sm text-amber-700 flex items-center gap-1.5">
                                    <span :class="[
                                        'inline-block w-2 h-2 rounded-full flex-shrink-0',
                                        n.color === 'amber' ? 'bg-amber-500' : 
                                        n.color === 'purple' ? 'bg-purple-500' :
                                        n.color === 'blue' ? 'bg-blue-500' : 
                                        n.color === 'orange' ? 'bg-orange-500' : 'bg-red-500'
                                    ]"></span>
                                    {{ n.text }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ============================== -->
                <!-- STATS CARDS                   -->
                <!-- ============================== -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

                    <div v-if="canManageProducts" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending Artisans</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.pending_artisans }}</p>
                            </div>
                            <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                        </div>
                        <div v-if="stats.pending_artisans > 0" class="mt-2 text-xs text-amber-600 font-medium">Needs review</div>
                    </div>

                    <div v-if="canApproveDelivery" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending Drivers</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.pending_delivery }}</p>
                            </div>
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </div>
                        </div>
                        <div v-if="stats.pending_delivery > 0" class="mt-2 text-xs text-purple-600 font-medium">Needs review</div>
                    </div>

                    <div v-if="canAssignDriver" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ready Orders</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ readyOrders.length }}</p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                        </div>
                        <div v-if="readyOrders.length > 0" class="mt-2 text-xs text-blue-600 font-medium">Needs assignment</div>
                    </div>

                    <div v-if="canApproveWithdrawal" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending Withdrawals</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ withdrawals.length }}</p>
                            </div>
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                        </div>
                        <div v-if="withdrawals.length > 0" class="mt-2 text-xs text-red-600 font-medium">Needs processing</div>
                    </div>

                    <div v-if="canHandleReports" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending Reports</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ reportStats.pending }}</p>
                            </div>
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                        </div>
                        <div v-if="reportStats.pending > 0" class="mt-2 text-xs text-orange-600 font-medium">Needs review</div>
                    </div>

                    <div v-if="canManageProducts" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Buyers</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.total_buyers }}</p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                        </div>
                    </div>

                    <div v-if="canManageProducts || canApproveWithdrawal" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Artisans</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.total_artisans }}</p>
                            </div>
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                        </div>
                    </div>

                    <div v-if="canAssignDriver" class="bg-white rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active Drivers</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1">{{ approvedDrivers.length }}</p>
                            </div>
                            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                    </div>
                     <!-- ✅ PASTE REVENUE CARDS HERE AS THEIR OWN SECTION -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl border shadow-sm">
                        <p class="text-sm font-medium text-gray-500">Total Platform Revenue</p>
                        <p class="text-3xl font-extrabold text-gray-900 mt-2">{{ revenue.total.toLocaleString() }} ETB</p>
                    </div>
                    <div class="bg-white p-6 rounded-xl border shadow-sm">
                        <p class="text-sm font-medium text-gray-500">Today's Revenue</p>
                        <p class="text-3xl font-extrabold text-emerald-600 mt-2">{{ revenue.today.toLocaleString() }} ETB</p>
                    </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoading" class="bg-white rounded-xl border p-16 text-center text-gray-400">
                    <svg class="animate-spin h-8 w-8 mx-auto mb-3 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading dashboard...
                </div>

                <template v-else>

                    <!-- ============================== -->
                    <!-- PENDING ARTISANS              -->
                    <!-- ============================== -->
                    <div v-if="canManageProducts" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Pending Artisan Applications</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Review and approve or reject new artisan registrations</p>
                            </div>
                            <span v-if="stats.pending_artisans > 0" class="bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full">{{ stats.pending_artisans }} pending</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Applicant</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Shop</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Submitted</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="artisan in pendingArtisans" :key="artisan.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4"><p class="text-sm font-medium text-gray-900">{{ artisan.user?.name || 'N/A' }}</p></td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-900">{{ artisan.shop_name }}</p>
                                            <p class="text-xs text-gray-500">{{ artisan.slang || 'No slogan' }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-600">{{ artisan.user?.email || 'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ artisan.user?.phone_number || 'N/A' }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ artisan.submitted_at ? new Date(artisan.submitted_at).toLocaleDateString() : 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center space-x-2">
                                            <button @click="approveItem('artisan', artisan.id)" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition">Approve</button>
                                            <button @click="openRejectModal(artisan.id, 'artisan')" class="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-100 transition">Reject</button>
                                            <button @click="router.push('/admin/artisans/' + artisan.id)" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-200 transition">View</button>
                                        </td>
                                    </tr>
                                    <tr v-if="pendingArtisans.length === 0"><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">No pending artisan applications.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- PENDING DRIVERS               -->
                    <!-- ============================== -->
                    <div v-if="canApproveDelivery" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Pending Driver Applications</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Review and approve or reject new delivery personnel</p>
                            </div>
                            <span v-if="stats.pending_delivery > 0" class="bg-purple-100 text-purple-700 text-xs font-bold px-2.5 py-1 rounded-full">{{ stats.pending_delivery }} pending</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Applicant</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vehicle</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Submitted</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="driver in pendingDrivers" :key="driver.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4"><p class="text-sm font-medium text-gray-900">{{ driver.user?.name || 'N/A' }}</p></td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-600">{{ driver.user?.email || 'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ driver.user?.phone_number || 'N/A' }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-900">{{ driver.vehicle_type }}</p>
                                            <p class="text-xs text-gray-500">{{ driver.vehicle_plate_number }} · {{ driver.vehicle_model || 'N/A' }} · {{ driver.vehicle_color || 'N/A' }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ driver.submitted_at ? new Date(driver.submitted_at).toLocaleDateString() : 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center space-x-2">
                                            <button @click="approveItem('delivery', driver.id)" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition">Approve</button>
                                            <button @click="openRejectModal(driver.id, 'delivery')" class="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-100 transition">Reject</button>
                                            <button @click="router.push('/admin/deliveries/' + driver.id)" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-200 transition">View</button>
                                        </td>
                                    </tr>
                                    <tr v-if="pendingDrivers.length === 0"><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">No pending delivery applications.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- ORDERS READY FOR ASSIGNMENT   -->
                    <!-- ============================== -->
                    <div v-if="canAssignDriver" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Orders Ready for Assignment</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Assign available drivers to orders waiting for pickup</p>
                            </div>
                            <span :class="['text-xs font-bold px-2.5 py-1 rounded-full', readyOrders.length > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500']">{{ readyOrders.length }} order(s)</span>
                        </div>
                        <div v-if="readyOrders.length === 0" class="p-12 text-center text-gray-400 text-sm">No orders waiting for driver assignment.</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Order</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Seller</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Delivery To</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Distance / Fee</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase min-w-[220px]">Assign Driver</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="order in readyOrders" :key="order.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-4"><p class="text-sm font-bold text-gray-900">{{ order.order_number }}</p></td>
                                        <td class="px-4 py-4 text-sm text-gray-600">{{ order.seller?.name }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600">{{ order.delivery_address?.street || 'N/A' }}</td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-900">{{ order.distance_km }} km</p>
                                            <p class="text-xs text-gray-500">{{ order.delivery_fee }} ETB</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <select :id="`driver-${order.id}`" class="w-full border border-gray-300 rounded-lg p-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                                <option value="">-- Select Driver --</option>
                                                <option v-for="driver in approvedDrivers" :key="driver.id" :value="driver.id">{{ driver.name }} · {{ driver.phone_number }} · {{ driver.delivery_profile?.vehicle_type }} ({{ driver.delivery_profile?.vehicle_plate_number }})</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <button @click="assignDriver(order.id)" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition">Assign</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- ACTIVE DRIVER FLEET           -->
                    <!-- ============================== -->
                    <div v-if="canAssignDriver && approvedDrivers.length > 0" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="text-base font-bold text-gray-900">Active Driver Fleet</h3>
                            <p class="text-xs text-gray-500 mt-0.5">All approved and available delivery personnel</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Driver</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vehicle</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Details</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Zone</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="driver in approvedDrivers" :key="driver.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ driver.name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ driver.phone_number }}</td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-900">{{ driver.delivery_profile?.vehicle_type || 'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ driver.delivery_profile?.vehicle_plate_number || 'N/A' }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-600">{{ driver.delivery_profile?.vehicle_model || 'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ driver.delivery_profile?.vehicle_color || 'N/A' }} · {{ driver.delivery_profile?.employment_type || 'N/A' }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-block px-2 py-1 bg-emerald-50 text-emerald-700 rounded text-xs font-medium">{{ driver.delivery_profile?.assigned_zone || 'Unassigned' }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- WITHDRAWAL REQUESTS           -->
                    <!-- ============================== -->
                    <div v-if="canApproveWithdrawal" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Pending Withdrawal Requests</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Review and process artisan withdrawal requests</p>
                            </div>
                            <span v-if="withdrawals.length > 0" class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full">{{ withdrawals.length }} pending</span>
                        </div>
                        <div v-if="withdrawals.length === 0" class="p-12 text-center text-gray-400 text-sm">No pending withdrawal requests.</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Artisan</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Bank Details</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Requested</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="w in withdrawals" :key="w.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-gray-900">{{ w.user?.name }}</p>
                                            <p class="text-xs text-gray-500">{{ w.user?.email }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-600">{{ w.bank_name }}</p>
                                            <p class="text-xs text-gray-500 font-mono">{{ w.bank_account_number }}</p>
                                        </td>
                                        <td class="px-6 py-4"><p class="text-sm font-bold text-gray-900">{{ w.amount }} ETB</p></td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ w.created_at ? new Date(w.created_at).toLocaleDateString() : 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center space-x-2">
                                            <button @click="approveItem('withdrawal', w.id)" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition">Approve</button>
                                            <button @click="openRejectModal(w.id, 'withdrawal')" class="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-100 transition">Reject</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- BUYER MANAGEMENT              -->
                    <!-- ============================== -->
                    <div v-if="canManageProducts" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Buyer Management</h3>
                                <p class="text-xs text-gray-500 mt-0.5">{{ stats.total_buyers }} total registered buyers</p>
                            </div>
                            <div class="flex gap-2">
                                <div class="relative">
                                    <input v-model="buyerSearch" @keyup.enter="fetchBuyers()" type="text" placeholder="Search name, email, phone..." class="w-full sm:w-64 pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <button @click="fetchBuyers()" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 text-gray-600 font-medium">Search</button>
                            </div>
                        </div>
                        <div v-if="buyersLoading" class="p-12 text-center text-gray-400 text-sm">Loading buyers...</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Buyer</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Joined</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="buyer in buyers.data" :key="buyer.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ buyer.name }}</td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-600">{{ buyer.email }}</p>
                                            <p class="text-xs text-gray-500">{{ buyer.phone_number || 'N/A' }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span :class="[
                                                'inline-block px-2 py-1 rounded text-xs font-semibold',
                                                buyer.account_status === 'active' ? 'bg-emerald-50 text-emerald-700' :
                                                buyer.account_status === 'blocked' ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-600'
                                            ]">{{ buyer.account_status || 'active' }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ new Date(buyer.created_at).toLocaleDateString() }}</td>
                                        <td class="px-6 py-4 text-center">
                                            <button @click="toggleBlockBuyer(buyer.id)" :class="[
                                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                                buyer.account_status === 'blocked' ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-red-50 text-red-700 hover:bg-red-100'
                                            ]">{{ buyer.account_status === 'blocked' ? 'Unblock' : 'Block' }}</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!buyers.data || buyers.data.length === 0"><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">No buyers found.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="buyers.last_page > 1" class="px-6 py-3 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-gray-500">Page {{ buyers.current_page }} of {{ buyers.last_page }}</span>
                            <div class="flex gap-1">
                                <button @click="fetchBuyers(buyers.current_page - 1)" :disabled="buyers.current_page <= 1" class="px-3 py-1 text-xs border rounded-lg disabled:opacity-40 hover:bg-gray-50">Prev</button>
                                <button @click="fetchBuyers(buyers.current_page + 1)" :disabled="buyers.current_page >= buyers.last_page" class="px-3 py-1 text-xs border rounded-lg disabled:opacity-40 hover:bg-gray-50">Next</button>
                            </div>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- PRODUCT MANAGEMENT            -->
                    <!-- ============================== -->
                    <div v-if="canManageProducts || canHideProduct" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Product Management</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Review, hide, or unhide products from the marketplace</p>
                            </div>
                            <div class="flex gap-2">
                                <select v-model="productStatusFilter" @change="fetchProducts()" class="text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="hidden">Hidden</option>
                                </select>
                                <div class="relative">
                                    <input v-model="productSearch" @keyup.enter="fetchProducts()" type="text" placeholder="Search product or seller..." class="w-full sm:w-56 pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <button @click="fetchProducts()" class="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 text-gray-600 font-medium">Search</button>
                            </div>
                        </div>
                        <div v-if="productsLoading" class="p-12 text-center text-gray-400 text-sm">Loading products...</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Seller</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Listed</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-gray-900 truncate max-w-[200px]">{{ product.name }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ product.artisan?.name || 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ product.price }} ETB</td>
                                        <td class="px-6 py-4">
                                            <span :class="[
                                                'inline-block px-2 py-1 rounded text-xs font-semibold',
                                                product.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'
                                            ]">{{ product.status === 'hidden_by_admin' ? 'Hidden' : product.status }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ new Date(product.created_at).toLocaleDateString() }}</td>
                                        <td class="px-6 py-4 text-center">
                                            <button v-if="canHideProduct" @click="toggleHideProduct(product.id)" :class="[
                                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                                product.status === 'hidden_by_admin' ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                            ]">{{ product.status === 'hidden_by_admin' ? 'Unhide' : 'Hide' }}</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!products.data || products.data.length === 0"><td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">No products found.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="products.last_page > 1" class="px-6 py-3 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-gray-500">Page {{ products.current_page }} of {{ products.last_page }}</span>
                            <div class="flex gap-1">
                                <button @click="fetchProducts(products.current_page - 1)" :disabled="products.current_page <= 1" class="px-3 py-1 text-xs border rounded-lg disabled:opacity-40 hover:bg-gray-50">Prev</button>
                                <button @click="fetchProducts(products.current_page + 1)" :disabled="products.current_page >= products.last_page" class="px-3 py-1 text-xs border rounded-lg disabled:opacity-40 hover:bg-gray-50">Next</button>
                            </div>
                        </div>
                    </div>

                    <!-- Artisans List -->
    <div class="bg-white rounded-xl border shadow-sm mb-8">
      <div class="p-6 border-b flex flex-col sm:flex-row justify-between gap-4">
        <h2 class="text-xl font-bold text-gray-900">All Artisans</h2>
        <input 
          v-model="searchQuery" 
          @input="fetchArtisans()"
          type="text" 
          placeholder="Search by name or email..." 
          class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none w-full sm:w-64"
        />
      </div>
      
      <div v-if="loadingArtisans" class="p-10 text-center text-gray-400">Loading...</div>
      
      <table v-else class="w-full text-sm text-left">
        <thead class="text-xs uppercase text-gray-500 bg-gray-50">
          <tr>
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">Shop Name</th>
            <th class="px-6 py-3">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="artisan in artisans" :key="artisan.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 font-medium text-gray-900">{{ artisan.name }}</td>
            <td class="px-6 py-4 text-gray-500">{{ artisan.email }}</td>
            <td class="px-6 py-4 text-gray-500">{{ artisan.artisan_profile?.shop_name || 'N/A' }}</td>
            <td class="px-6 py-4">
              <span 
                class="px-2.5 py-1 rounded-full text-xs font-bold"
                :class="{
                  'bg-yellow-100 text-yellow-700': artisan.artisan_profile?.approval_status === 'pending',
                  'bg-emerald-100 text-emerald-700': artisan.artisan_profile?.approval_status === 'approved',
                  'bg-red-100 text-red-700': artisan.artisan_profile?.approval_status === 'rejected'
                }"
              >
                {{ artisan.artisan_profile?.approval_status || 'N/A' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

                    <!-- ============================== -->
                    <!-- REPORTS                       -->
                    <!-- ============================== -->
                    <div v-if="canHandleReports" class="bg-white shadow-sm rounded-xl border mb-8">
                        <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">User & Product Reports</h3>
                                <p class="text-xs text-gray-500 mt-0.5">{{ reportStats.total }} total · {{ reportStats.resolved_today }} resolved today</p>
                            </div>
                            <select v-model="reportStatusFilter" @change="fetchReports()" class="text-sm border rounded-lg px-3 py-2 focus:ring-2 focus:ring-orange-500 focus:outline-none">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="resolved">Resolved</option>
                                <option value="dismissed">Dismissed</option>
                            </select>
                        </div>
                        <div v-if="reportsLoading" class="p-12 text-center text-gray-400 text-sm">Loading reports...</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reported By</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Target</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reason</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="report in reports.data" :key="report.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-gray-900">{{ report.reporter?.name }}</p>
                                            <p class="text-xs text-gray-500">{{ report.reporter?.email }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-gray-900">{{ report.target_label }}</p>
                                            <span :class="[
                                                'inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase',
                                                report.target_type === 'user' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600'
                                            ]">{{ report.target_type }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-block px-2 py-1 bg-orange-50 text-orange-700 rounded text-xs font-medium capitalize">{{ report.reason?.replace('_', ' ') }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600 max-w-[200px] truncate">{{ report.description || '—' }}</td>
                                        <td class="px-6 py-4">
                                            <span :class="[
                                                'inline-block px-2 py-1 rounded text-xs font-semibold',
                                                report.status === 'pending' ? 'bg-amber-50 text-amber-700' :
                                                report.status === 'resolved' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                                            ]">{{ report.status }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ new Date(report.created_at).toLocaleDateString() }}</td>
                                        <td class="px-6 py-4 text-center">
                                            <button v-if="report.status === 'pending'" @click="openResolveModal(report)" class="px-3 py-1.5 bg-orange-50 text-orange-700 rounded-lg text-xs font-semibold hover:bg-orange-100 transition">Resolve</button>
                                            <span v-else class="text-xs text-gray-400">Closed</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!reports.data || reports.data.length === 0"><td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">No reports found.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="reports.last_page > 1" class="px-6 py-3 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-gray-500">Page {{ reports.current_page }} of {{ reports.last_page }}</span>
                            <div class="flex gap-1">
                                <button @click="fetchReports(reports.current_page - 1)" :disabled="reports.current_page <= 1" class="px-3 py-1 text-xs border rounded-lg disabled:opacity-40 hover:bg-gray-50">Prev</button>
                                <button @click="fetchReports(reports.current_page + 1)" :disabled="reports.current_page >= reports.last_page" class="px-3 py-1 text-xs border rounded-lg disabled:opacity-40 hover:bg-gray-50">Next</button>
                            </div>
                        </div>
                    </div>

                    <!-- ============================== -->
                    <!-- EMPTY STATE                   -->
                    <!-- ============================== -->
                    <div v-if="!canManageProducts && !canApproveDelivery && !canAssignDriver && !canApproveWithdrawal && !canHandleReports" class="bg-white rounded-xl border p-16 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        <h3 class="text-gray-900 font-semibold mb-1">No Dashboard Sections Available</h3>
                        <p class="text-sm text-gray-500">You don't have the required permissions to view any dashboard sections.</p>
                    </div>

                </template>
            </div>
        </main>
    </div>
</template>