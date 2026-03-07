<template>
  <div class="min-h-screen flex flex-col bg-white">
    
    <!-- Navigation Bar -->
    <header class="bg-white border-b border-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
          
          <!-- Logo -->
          <Link href="/" class="flex items-center">
            <span class="text-2xl font-bold text-emerald-600">Artisan</span>
            <span class="text-2xl font-bold text-amber-400">Hub</span>
          </Link>

          <!-- Right Side Links -->
          <div class="flex items-center space-x-4">
            <Link 
              href="/login" 
              class="text-gray-600 hover:text-emerald-600 font-medium transition duration-150"
            >
              Sign In
            </Link>
            
            <Link 
              href="/register/buyer" 
              class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold py-2 px-5 rounded-full transition duration-300 shadow-sm"
            >
              Register
            </Link>
          </div>
        </div>
      </div>
    </header>

    <!-- Hero Section -->
    <main class="flex-grow bg-gradient-to-b from-white to-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-32">
        <div class="text-center">
          
          <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
            <span class="block">Discover Unique</span>
            <span class="block text-emerald-600">Handmade Treasures</span>
          </h1>
          
          <p class="mt-3 max-w-md mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
            Join our community of artisans and buyers.
          </p>

          <div class="mt-10 flex justify-center gap-4">
            <Link 
              href="/register/buyer" 
              class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition duration-300"
            >
              Start Shopping
            </Link>
            
            <Link 
              href="/register/artisan" 
              class="border-2 border-gray-300 text-gray-700 hover:border-emerald-600 hover:text-emerald-600 font-bold py-3 px-8 rounded-full transition duration-300 bg-white"
            >
              Become a Seller
            </Link>
          </div>
          
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-50 border-t border-gray-100">
      <div class="max-w-7xl mx-auto py-6 px-4 text-center text-gray-400 text-sm">
        © 2023 ArtisanHub.
      </div>
    </footer>

  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>