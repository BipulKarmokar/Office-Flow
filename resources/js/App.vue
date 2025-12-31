<script>
import RequestManager from './components/RequestManager.vue';
import ExpenseManager from './components/ExpenseManager.vue';
import TeamManager from './components/TeamManager.vue';
import HrDashboard from './components/HrDashboard.vue';
import SettingsManager from './components/SettingsManager.vue';

export default {
  name: 'App',
  components: {
    RequestManager,
    ExpenseManager,
    TeamManager,
    HrDashboard,
    SettingsManager
  },
  data() {
    return {
      activeTab: 'dashboard',
      user: window.OfficeUtilities?.user || {
        id: 0,
        name: 'Guest',
        roles: [],
        isAdmin: false,
        isMember: false
      },
      isMobileMenuOpen: false
    };
  },
  computed: {
    tabs() {
      // Base tabs for everyone (or just employees)
      let tabs = [
        { id: 'requests', label: 'Requests', icon: '📝' },
        { id: 'expenses', label: 'Expenses', icon: '💰' },
        { id: 'settings', label: 'Settings', icon: '⚙️' }
      ];

      // Admin / HR tabs
      if (this.user.isAdmin) {
        tabs.unshift({ id: 'dashboard', label: 'Dashboard', icon: '📊' });
        tabs.splice(3, 0, { id: 'team', label: 'Team', icon: '👥' });
      } else {
         // Employee View: Default to requests if dashboard not available
         if (this.activeTab === 'dashboard') this.activeTab = 'requests';
      }

      return tabs;
    }
  }
};
</script>

<template>
  <div class="office-utilities-app bg-gray-50 min-h-screen pb-20 md:pb-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
      <header class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center">
        <div class="mb-4 md:mb-0">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">Office Utilities</h1>
            <p class="text-sm md:text-base text-gray-600">Manage office requests & expenses.</p>
        </div>
        <div v-if="user.isAdmin" class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-xs font-semibold self-start md:self-auto">
            HR / Admin Mode
        </div>
      </header>

      <!-- Desktop Navigation -->
      <div class="hidden md:block bg-white rounded-lg shadow mb-6">
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'w-1/4 py-4 px-1 text-center border-b-2 font-medium text-sm'
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>
      </div>

      <!-- Main Content -->
      <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div v-if="activeTab === 'dashboard'" class="px-4 sm:px-0"><HrDashboard :user="user" /></div>
        <div v-if="activeTab === 'requests'" class="px-4 sm:px-0"><RequestManager :user="user" /></div>
        <div v-if="activeTab === 'expenses'" class="px-4 sm:px-0"><ExpenseManager :user="user" /></div>
        <div v-if="activeTab === 'team'" class="px-4 sm:px-0"><TeamManager /></div>
        <div v-if="activeTab === 'settings'" class="px-4 sm:px-0"><SettingsManager :user="user" /></div>
      </main>

      <!-- Mobile Bottom Navigation -->
      <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around py-3 pb-safe z-50">
          <button 
            v-for="tab in tabs" 
            :key="tab.id" 
            @click="activeTab = tab.id" 
            class="flex flex-col items-center" 
            :class="activeTab === tab.id ? 'text-indigo-600' : 'text-gray-500'"
          >
              <span class="text-xl">{{ tab.icon }}</span>
              <span class="text-xs mt-1">{{ tab.label }}</span>
          </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
// Scoped styles if needed
</style>
