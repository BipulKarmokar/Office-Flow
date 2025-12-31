<template>
  <div class="hr-dashboard">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-800">HR Dashboard</h2>
    </div>
    
    <div v-if="loading" class="text-center py-10 text-gray-500">Loading stats...</div>

    <div v-else>
        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                <div class="text-gray-500 text-sm">Total Employees</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ stats.overview.employees || '0' }}</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-yellow-500">
                <div class="text-gray-500 text-sm">Pending Requests</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ stats.overview.pending_requests }}</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
                <div class="text-gray-500 text-sm">Pending Expenses</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ stats.overview.pending_expenses }}</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
                <div class="text-gray-500 text-sm">Total Spent</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ formatCurrency(stats.overview.total_spent) }}</div>
            </div>
        </div>

        <!-- Recent Activity Sections -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Recent Requests -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 uppercase">Recent Requests</h3>
                <ul class="divide-y divide-gray-200">
                    <li v-for="req in stats.recent_requests" :key="req.id" class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-indigo-600 truncate">{{ req.title }}</p>
                            <p class="text-xs text-gray-500">{{ req.user_name }} • {{ formatDate(req.created_at) }}</p>
                        </div>
                        <span :class="statusClass(req.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                            {{ req.status }}
                        </span>
                    </li>
                    <li v-if="!stats.recent_requests || stats.recent_requests.length === 0" class="py-4 text-center text-sm text-gray-500">
                        No recent requests.
                    </li>
                </ul>
            </div>

            <!-- Recent Expenses -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 uppercase">Recent Expenses</h3>
                <ul class="divide-y divide-gray-200">
                    <li v-for="exp in stats.recent_expenses" :key="exp.id" class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ formatCurrency(exp.amount, exp.currency) }} - {{ exp.category }}</p>
                            <p class="text-xs text-gray-500">{{ exp.user_name }} • {{ formatDate(exp.created_at) }}</p>
                        </div>
                        <span :class="statusClass(exp.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                            {{ exp.status }}
                        </span>
                    </li>
                    <li v-if="!stats.recent_expenses || stats.recent_expenses.length === 0" class="py-4 text-center text-sm text-gray-500">
                        No recent expenses.
                    </li>
                </ul>
            </div>
        </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

const apiRoot = window.OfficeUtilities?.root || '/wp-json/office-utilities/v1';
const nonce = window.OfficeUtilities?.nonce || '';

export default {
  name: 'HrDashboard',
  props: {
    user: Object
  },
  data() {
    return {
      loading: true,
      stats: {
          overview: {},
          recent_requests: [],
          recent_expenses: []
      }
    };
  },
  created() {
    this.fetchStats();
  },
  methods: {
    async fetchStats() {
      try {
        const response = await axios.get(`${apiRoot}/dashboard/stats`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        this.stats = response.data;
      } catch (error) {
        console.error("Error fetching stats:", error);
      } finally {
        this.loading = false;
      }
    },
    formatCurrency(amount, currency = 'BDT') {
        return new Intl.NumberFormat('en-BD', { style: 'currency', currency: currency }).format(amount || 0);
    },
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    },
    statusClass(status) {
      const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        approved: 'bg-green-100 text-green-800',
        reimbursed: 'bg-green-100 text-green-800',
        resolved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800'
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    }
  }
};
</script>
