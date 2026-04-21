import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function usePermissions() {
    const authStore = useAuthStore()

    // Super Admin
    const canViewReports = computed(() => authStore.hasPermission('view_reports'))

    // Delivery Admin Permissions
    const canAssignDriver = computed(() => authStore.hasPermission('assign_driver'))
    const canViewOrders = computed(() => authStore.hasPermission('view_orders'))
    const canApproveDelivery = computed(() => authStore.hasPermission('approve_delivery_person'))
    const canManageDeliveryStatus = computed(() => authStore.hasPermission('manage_delivery_status'))

    // Finance Admin Permissions
    const canApproveWithdrawal = computed(() => authStore.hasPermission('approve_withdrawal'))
    const canViewTransactions = computed(() => authStore.hasPermission('view_transactions'))

    // Operations Admin Permissions
    const canManageProducts = computed(() => authStore.hasPermission('manage_products'))
    const canHideProduct = computed(() => authStore.hasPermission('hide_product'))
    const canBlockArtisan = computed(() => authStore.hasPermission('block_artisan'))
    const canHandleReports = computed(() => authStore.hasPermission('handle_reports'))

    return {
        canViewReports,
        canAssignDriver,
        canViewOrders,
        canApproveDelivery,
        canManageDeliveryStatus,
        canApproveWithdrawal,
        canViewTransactions,
        canManageProducts,
        canHideProduct,
        canBlockArtisan,
        canHandleReports,
    }
}