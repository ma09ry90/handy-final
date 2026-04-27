import { createRouter, createWebHistory } from 'vue-router'
import ArtisanLayout from '@/layouts/ArtisanLayout.vue'
import HomeView from '../views/HomeView.vue'
import ArtisanRegister from '../views/auth/register/ArtisanRegister.vue'
import ArtisanDetail from '../views/admin/ArtisanDetail.vue'
import DeliveryDetail from '../views/admin/DeliveryDetail.vue'
import ReelsView from '../views/ReelsView.vue';
import ArtisanVideoManager from '../views/artisan/VideoManager.vue';
import { useAuthStore } from '@/stores/auth';
import EmailVerified from '../views/EmailVerified.vue';
import ForgotPassword from '../views/auth/ForgotPassword.vue';
import ResetPassword from '../views/auth/ResetPassword.vue';
import Profile from '../views/artisan/Profile.vue' // ✅ Added Profile

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/about', name: 'about', component: () => import('../views/AboutView.vue') },
    
    { path: '/login', name: 'login', component: () => import('../views/auth/LoginView.vue'), meta: { guest: true } },
    { path: '/register/artisan', name: 'register-artisan', component: ArtisanRegister, meta: { guest: true } },
    { path: '/register/buyer', name: 'register-buyer', component: () => import('../views/auth/register/BuyerRegister.vue'), meta: { guest: true } },
    { path: '/register/delivery', name: 'DeliveryRegister', component: () => import('../views/auth/register/DeliveryRegister.vue') },
    {
      path: '/email-verified',
      name: 'EmailVerified',
      component: EmailVerified,
      meta: { requiresAuth: false } // Important: accessible without login
      },
      
        {
      path: '/forgot-password',
      name: 'ForgotPassword',
      component: ForgotPassword,
      meta: { requiresAuth: false }
    },
    {
      path: '/reset-password/:token',
      name: 'ResetPassword',
      component: ResetPassword,
      meta: { requiresAuth: false }
    },

    { path: '/terms-and-conditions', name: 'terms', component: () => import('@/views/terms/delivery/TermsAndConditions.vue') },
    { path: '/privacy-policy', name: 'privacy', component: () => import('@/views/terms/delivery/privacy-policy.vue') },
    { path: '/delivery-guidelines', name: 'delivery-guidelines', component: () => import('@/views/terms/delivery/delivery-guidelines.vue') },

    { path: '/reels', name: 'reels', component: ReelsView, meta: { requiresAuth: true } },

    // Admin Routes
    { path: '/admin/dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/Dashboard.vue'), meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/artisans/:id', name: 'admin-artisan-detail', component: ArtisanDetail, meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/deliveries/:id', name: 'admin-deliveries-detail', component: DeliveryDetail, meta: { requiresAuth: true, role: 'admin' } },

    // Buyer Routes
    { path: '/account', name: 'my profile', component: () => import('../views/Account.vue'), meta: { requiresAuth: true } },
    { path: '/product/:id', name: 'product-detail', component: () => import('../views/ProductDetail.vue') },
    { path: '/cart', name: 'Cart', component: () => import('../views/buyer/CartView.vue') },
    { path: '/wishlist', name: 'Wishlist', component: () => import('../views/buyer/MyWishlist.vue') },
    { path: '/checkout', name: 'Checkout', component: () => import('../views/buyer/CheckoutView.vue'), meta: { requiresAuth: true } },
    { path: '/payment/success', name: 'payment.success', component: () => import('../views/buyer/PaymentSuccessView.vue') },
    { path: '/orders', name: 'orders', component: () => import('../views/buyer/OrdersView.vue'), meta: { requiresAuth: true }, props: true },
    
    // Delivery Routes
    { path: '/delivery/dashboard', name: 'DeliveryDashboard', component: () => import('../views/delivery/DeliveryDashboard.vue'), meta: { requiresAuth: true, role: 'delivery' } },

    // Artisan Routes
    {
      path: '/artisan',
      component: ArtisanLayout,
      meta: { requiresAuth: true, role: 'artisan' },
      children: [
        { path: 'dashboard', name: 'ArtisanDashboard', component: () => import('../views/artisan/DashboardHome.vue'), meta: { title: 'Dashboard' } },
        { path: 'products', name: 'ArtisanProducts', component: () => import('../views/artisan/ProductList.vue'), meta: { title: 'My Products' } },
        { path: 'products/create', name: 'ArtisanProductCreate', component: () => import('../views/artisan/ProductForm.vue'), meta: { title: 'Add new product' } },
        { path: 'products/:id/edit', name: 'ArtisanProductEdit', component: () => import('../views/artisan/ProductForm.vue'), meta: { title: 'Edit product' } },
        { path: 'orders', name: 'ArtisanOrders', component: () => import('../views/artisan/Orders.vue'), meta: { title: 'My Orders' } },
        { path: 'wallet', name: 'Artisanwallet', component: () => import('../views/artisan/ArtisanWallet.vue'), meta: { title: 'My Wallet' } },
        { path: 'videos', name: 'ArtisanVideos', component: ArtisanVideoManager, meta: { title: 'My Videos' } },
        { path: 'profile', name: 'artisan.profile', component: Profile }
      ]
    },
  ]
});

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore();

  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      return next({ name: 'login' });
    }

    const requiredRole = to.meta.role;

    if (requiredRole) {
      // ✅ FIXED: Admin = has ANY admin permission (role_id doesn't matter)
      if (requiredRole === 'admin' && authStore.isAdmin) {
        next();
      } else if (requiredRole === 'artisan' && authStore.userRole === 2) {
        next();
      } else if (requiredRole === 'delivery' && authStore.userRole === 3) {
        next();
      } else {
        return next({ name: 'home' });
      }
    } else {
      next();
    }
  }

  if (to.meta.guest) {
    if (authStore.isAuthenticated) {
      return next({ name: 'home' });
    }
  }

  next();
});

export default router;