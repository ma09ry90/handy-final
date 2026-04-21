import { createRouter, createWebHistory } from 'vue-router'
import ArtisanLayout from '@/layouts/ArtisanLayout.vue'
import HomeView from '../views/HomeView.vue'
import ArtisanRegister from '../views/auth/register/ArtisanRegister.vue'
import ArtisanDetail from '../views/admin/ArtisanDetail.vue'
import DeliveryDetail from '../views/admin/DeliveryDetail.vue'
import ReelsView from '../views/ReelsView.vue';
import ArtisanVideoManager from '../views/artisan/VideoManager.vue';
// REMOVED: import Login ... (We use lazy loading below)
// REMOVED: import store ... (We use Pinia inside the guard below)
import { useAuthStore } from '@/stores/auth'; // Import Pinia store

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // --- Public Routes ---
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    
    // --- Auth Routes ---
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { guest: true }
    },
    {
      path: '/register/artisan',
      name: 'register-artisan',
      component: ArtisanRegister,
      meta: { guest: true }
    },
    {
      path: '/register/buyer',
      name: 'register-buyer',
      component: () => import('../views/auth/register/BuyerRegister.vue'),
      meta: { guest: true }
    },
    {
      path: '/register/delivery',
      name: 'DeliveryRegister',
      component: () => import('../views/auth/register/DeliveryRegister.vue')
    },
    {
      path: '/terms-and-conditions',
      name: 'terms',
      component: () => import('@/views/terms/delivery/TermsAndConditions.vue')
    },
    {
      path: '/privacy-policy',
      name: 'privacy',
      component: () => import('@/views/terms/delivery/privacy-policy.vue')
    },
    {
      path: '/delivery-guidelines',
      name: 'delivery-guidelines',
      component: () => import('@/views/terms/delivery/delivery-guidelines.vue')
    },

    // --- Reels Feed (Accessible by Buyers & Artisans) ---
    {
        path: '/reels',
        name: 'reels',
        component: ReelsView,
        meta: { requiresAuth: true } 
    },

    // --- Admin Routes ---
    {
      path: '/admin/dashboard',
      name: 'admin-dashboard',
      component: () => import('@/views/admin/Dashboard.vue'),
      meta: { requiresAuth: true, role: 'admin' }
    },
    {
      path: '/admin/artisans/:id',
      name: 'admin-artisan-detail',
      component: ArtisanDetail,
      meta: { requiresAuth: true, role: 'admin' }
    },
    {
      path: '/admin/deliveries/:id',
      name: 'admin-deliveries-detail',
      component: DeliveryDetail,
      meta: { requiresAuth: true, role: 'admin' }
    },

    // --- Buyer & General Routes ---
    {
      path: '/account',
      name: 'my profile',
      component: () => import('../views/Account.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/product/:id',
      name: 'product-detail',
      component: () => import('@/views/ProductDetail.vue')
    },
    {
      path: '/cart',
      name: 'Cart',
      component: () => import('../views/buyer/CartView.vue')
    },
    {
      path: '/wishlist',
      name: 'Wishlist',
      component: () => import('../views/buyer/MyWishlist.vue')
    },
    {
      path: '/checkout',
      name: 'Checkout',
      component: () => import('../views/buyer/CheckoutView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/payment/success',
      name: 'payment.success',
      component: () => import('../views/buyer/PaymentSuccessView.vue')
    },
    {
      path: '/orders',
      name: 'orders',
      component: () => import('@/views/buyer/OrdersView.vue'),
      meta: { requiresAuth: true }
    },
    
    // --- Delivery Routes ---
    {
      path: '/delivery/dashboard',
      name: 'DeliveryDashboard',
      component: () => import('../views/delivery/DeliveryDashboard.vue'),
      meta: { requiresAuth: true, role: 'delivery' }
    },
    // --- Artisan Routes (Protected Group) ---
    {
      path: '/artisan',
      component: ArtisanLayout,
      meta: { requiresAuth: true, role: 'artisan' },
      children: [
        {
          path: 'dashboard',
          name: 'ArtisanDashboard',
          component: () => import('../views/artisan/DashboardHome.vue'),
          meta: { title: 'Dashboard' }
        },
        {
          path: 'products',
          name: 'ArtisanProducts',
          component: () => import('../views/artisan/ProductList.vue'),
          meta: { title: 'My Products' }
        },
        {
          path: 'products/create',
          name: 'ArtisanProductCreate',
          component: () => import('../views/artisan/ProductForm.vue'),
          meta: { title: 'Add new product' }
        },
        {
          path: 'products/:id/edit',
          name: 'ArtisanProductEdit',
          component: () => import('../views/artisan/ProductForm.vue'),
          meta: { title: 'Edit product' }
        },
        {
          path: 'orders',
          name: 'ArtisanOrders',
          component: () => import('../views/artisan/Orders.vue'),
          meta: { title: 'My Orders' }
        },
        {
          path: 'wallet',
          name: 'Artisanwallet',
          component: () => import('../views/artisan/ArtisanWallet.vue'),
          meta: { title: 'My Orders' }
        },
        {
          path: 'videos',
          name: 'ArtisanVideos',
          component: ArtisanVideoManager,
          meta: { title: 'My Videos' }
        }
      ]
    },
  ]
});

// --- Navigation Guards (Updated for Pinia) ---
router.beforeEach((to, from, next) => {
  // 1. Initialize Store inside the guard
  const authStore = useAuthStore();

  // 2. Check if route requires auth
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      return next({ name: 'login' });
    }

    // 3. Check Role Based Access
    const userRole = authStore.userRole; // Uses the getter we created
    const requiredRole = to.meta.role;

    if (requiredRole) {
      let hasRole = false;

      // Map string roles to IDs (1=Buyer, 2=Artisan, 3=Delivery, 4=Admin)
      if (requiredRole === 'admin' && userRole === 4) hasRole = true;
      if (requiredRole === 'artisan' && userRole === 2) hasRole = true;
      if (requiredRole === 'delivery' && userRole === 3) hasRole = true;
      
      if (!hasRole) {
        // User is logged in but doesn't have the right role
        return next({ name: 'home' }); 
      }
    }
  }

  // 4. If route is for guests only (like login) and user is already logged in
  if (to.meta.guest) {
    if (authStore.isAuthenticated) {
      return next({ name: 'home' });
    }
  }

  next();
});

export default router;