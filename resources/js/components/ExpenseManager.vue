<template>
  <div class="expense-manager">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-xl font-semibold text-gray-800">
          {{ user.isAdmin ? 'All Expenses' : 'My Expenses' }}
      </h2>
      <button
        @click="showForm = true"
        class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition flex items-center"
      >
        <span class="mr-2">+</span> New Expense
      </button>
    </div>

    <!-- Stats / Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-yellow-500">
            <div class="text-gray-500 text-sm">Pending Approval</div>
            <div class="text-2xl font-bold text-gray-800">{{ formatCurrency(pendingTotal) }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
            <div class="text-gray-500 text-sm">Reimbursed (This Month)</div>
            <div class="text-2xl font-bold text-gray-800">{{ formatCurrency(reimbursedTotal) }}</div>
        </div>
    </div>

    <!-- Expense List -->
    <div v-if="loading" class="text-center py-8 text-gray-500">Loading expenses...</div>
    
    <div v-else class="space-y-4">
        <!-- Card View for all screens (cleaner than table for mobile) -->
        <div v-for="expense in expenses" :key="expense.id" class="bg-white shadow rounded-lg p-4 transition duration-150 ease-in-out">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <div class="flex items-center">
                        <span class="font-bold text-gray-900 text-lg mr-2">{{ formatCurrency(expense.amount, expense.currency) }}</span>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">{{ expense.category }}</span>
                    </div>
                    <div v-if="user.isAdmin" class="text-xs text-gray-500 mt-1">
                        by {{ expense.user_name }}
                    </div>
                </div>
                <span :class="statusClass(expense.status)" class="px-2 py-1 text-xs font-semibold rounded-full shrink-0">
                    {{ expense.status }}
                </span>
            </div>
            
            <p class="text-sm text-gray-600 mb-3">{{ expense.description }}</p>
            
            <div class="flex flex-wrap items-center justify-between text-xs text-gray-500 border-t pt-3 mt-3">
                <div class="flex items-center space-x-3 mb-2 sm:mb-0">
                    <span>{{ formatDate(expense.created_at) }}</span>
                    <a v-if="expense.receipt_url" :href="expense.receipt_url" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        Receipt
                    </a>
                </div>
                
                <div v-if="user.isAdmin" class="flex space-x-2 mt-2 sm:mt-0 w-full sm:w-auto justify-end">
                    <div v-if="expense.status === 'pending'">
                         <button @click="updateStatus(expense, 'approved')" class="text-blue-600 hover:text-blue-900 mr-3 font-medium">Approve</button>
                         <button @click="updateStatus(expense, 'rejected')" class="text-red-600 hover:text-red-900 font-medium">Reject</button>
                    </div>
                    <div v-else-if="expense.status === 'approved'">
                        <button @click="updateStatus(expense, 'reimbursed')" class="text-green-600 hover:text-green-900 font-bold">Reimburse</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="expenses.length === 0" class="text-center py-10 text-gray-500 bg-white rounded-lg shadow">
            No expenses recorded yet.
        </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showForm" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-medium text-gray-900 mb-4">New Expense</h3>
        <form @submit.prevent="submitExpense">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Amount (BDT)</label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 sm:text-sm">৳</span>
                </div>
                <input v-model="form.amount" type="number" step="0.01" required class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md border p-2">
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <select v-model="form.category" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="Food">Food</option>
                <option value="Travel">Travel</option>
                <option value="Office Supplies">Office Supplies</option>
                <option value="Hotel">Hotel</option>
                <option value="Other">Other</option>
            </select>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea v-model="form.description" rows="3" required placeholder="Dinner with client..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2"></textarea>
          </div>

          <!-- Reminder Toggle -->
          <div v-if="remindersEnabled" class="mb-4">
              <div class="flex items-center justify-between">
                  <span class="flex-grow flex flex-col">
                      <span class="text-sm font-medium text-gray-900">Set Reminder</span>
                      <span class="text-sm text-gray-500">Notify Admin if pending after X days</span>
                  </span>
                  <button 
                      type="button"
                      @click="form.setReminder = !form.setReminder" 
                      :class="[form.setReminder ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']"
                  >
                      <span aria-hidden="true" :class="[form.setReminder ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200']"></span>
                  </button>
              </div>
          </div>

          <div v-if="remindersEnabled && form.setReminder" class="mb-6">
              <label class="block text-sm font-medium text-gray-700">Days until reminder</label>
              <input v-model.number="form.reminderDays" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700">Receipt (Photo or PDF)</label>
            <div class="mt-1 flex items-center space-x-3">
                <input type="file" @change="handleFileUpload" accept="image/*,application/pdf" capture="environment" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                
                <button 
                    v-if="form.receipt_file && !scanning" 
                    type="button" 
                    @click="scanReceipt"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                >
                    ✨ AI Scan
                </button>
                <span v-if="scanning" class="text-sm text-indigo-600 animate-pulse">Scanning...</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Upload an image to auto-fill amount.</p>
          </div>

          <div class="flex justify-end space-x-3">
            <button type="button" @click="showForm = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Cancel</button>
            <button type="submit" :disabled="submitting" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 disabled:opacity-50">
                {{ submitting ? 'Saving...' : 'Submit Expense' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import OcrService from '../services/OcrService';

const apiRoot = window.OfficeUtilities?.root || '/wp-json/office-utilities/v1';
const nonce = window.OfficeUtilities?.nonce || '';

export default {
  name: 'ExpenseManager',
  props: {
      user: {
          type: Object,
          default: () => ({ isAdmin: false })
      }
  },
  data() {
    return {
      expenses: [],
      loading: true,
      showForm: false,
      submitting: false,
      scanning: false, // New scanning state
      remindersEnabled: false,
      form: {
        amount: '',
        currency: 'BDT',
        category: 'Food',
        description: '',
        receipt_file: null,
        setReminder: false,
        reminderDays: 3
      }
    };
  },
  computed: {
    pendingTotal() {
        return this.expenses
            .filter(e => e.status === 'pending')
            .reduce((sum, e) => sum + parseFloat(e.amount), 0);
    },
    reimbursedTotal() {
        return this.expenses
            .filter(e => e.status === 'reimbursed')
            .reduce((sum, e) => sum + parseFloat(e.amount), 0);
    }
  },
  created() {
    this.fetchExpenses();
    if (window.OfficeUtilities && window.OfficeUtilities.settings) {
        this.remindersEnabled = window.OfficeUtilities.settings.remindersEnabled;
    }
  },
  methods: {
    handleFileUpload(event) {
        this.form.receipt_file = event.target.files[0];
    },
    async scanReceipt() {
        if (!this.form.receipt_file) return;
        
        // Only scan images
        if (!this.form.receipt_file.type.startsWith('image/')) {
            alert("Auto-scan only supports images (JPG/PNG). Please enter amount manually.");
            return;
        }

        this.scanning = true;
        try {
            const result = await OcrService.scanReceipt(this.form.receipt_file);
            if (result.amount) {
                this.form.amount = result.amount;
                alert(`✨ Scanned Amount: ${result.amount}`);
            } else {
                alert("Could not detect amount clearly. Please enter manually.");
            }
        } catch (e) {
            console.error(e);
            alert("Scan failed.");
        } finally {
            this.scanning = false;
        }
    },
    async fetchExpenses() {
      try {
        const response = await axios.get(`${apiRoot}/expenses`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        this.expenses = response.data;
      } catch (error) {
        console.error('Error fetching expenses:', error);
      } finally {
        this.loading = false;
      }
    },
    async submitExpense() {
      this.submitting = true;
      try {
        const formData = new FormData();
        formData.append('amount', this.form.amount);
        formData.append('currency', this.form.currency);
        formData.append('category', this.form.category);
        formData.append('description', this.form.description);
        if (this.form.receipt_file) {
            formData.append('receipt', this.form.receipt_file);
        }
        if (this.remindersEnabled && this.form.setReminder) {
            formData.append('reminder_days', this.form.reminderDays);
        }

        const response = await axios.post(`${apiRoot}/expenses`, formData, {
            headers: { 
                'X-WP-Nonce': nonce,
                'Content-Type': 'multipart/form-data'
            }
        });
        this.expenses.unshift(response.data);
        this.showForm = false;
        this.resetForm();
      } catch (error) {
        console.error('Error submitting expense:', error);
        alert('Failed to submit expense.');
      } finally {
        this.submitting = false;
      }
    },
    async updateStatus(expense, newStatus) {
        if (!confirm(`Mark expense as ${newStatus}?`)) return;
        try {
             const response = await axios.put(`${apiRoot}/expenses/${expense.id}`, { status: newStatus }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const index = this.expenses.findIndex(e => e.id === expense.id);
            if (index !== -1) {
                this.expenses[index] = response.data;
            }
        } catch (error) {
            console.error('Error updating status:', error);
            alert('Failed to update status.');
        }
    },
    resetForm() {
      this.form = {
        amount: '',
        currency: 'BDT',
        category: 'Food',
        description: '',
        receipt_file: null,
        setReminder: false,
        reminderDays: 3
      };
    },
    statusClass(status) {
      const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-blue-100 text-blue-800',
        reimbursed: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800'
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    },
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    },
    formatCurrency(amount, currency = 'BDT') {
        return new Intl.NumberFormat('en-BD', { style: 'currency', currency: currency }).format(amount);
    }
  }
};
</script>
