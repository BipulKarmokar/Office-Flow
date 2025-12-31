<template>
  <div class="request-manager">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Office Requests</h2>
      <button
        v-if="!user.isAdmin"
        @click="showForm = true"
        class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition"
      >
        New Request
      </button>
    </div>

    <!-- Filters for HR -->
    <div v-if="user.isAdmin" class="mb-4 flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4 bg-white p-3 rounded-lg shadow-sm">
        <select v-model="filterStatus" class="border-gray-300 rounded-md text-sm w-full md:w-auto">
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
        </select>
        <select v-model="filterPriority" class="border-gray-300 rounded-md text-sm w-full md:w-auto">
             <option value="all">All Priorities</option>
             <option value="high">High</option>
             <option value="medium">Medium</option>
             <option value="low">Low</option>
        </select>
    </div>

    <!-- Request List -->
    <div v-if="loading" class="text-center py-8 text-gray-500">Loading requests...</div>
    
    <div v-else class="space-y-4">
        <div v-for="request in filteredRequests" :key="request.id" class="bg-white shadow rounded-lg p-4 transition duration-150 ease-in-out">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h3 class="text-base font-medium text-indigo-600 truncate">{{ request.title }}</h3>
                    <div v-if="user.isAdmin" class="text-xs text-gray-500 mt-1">
                        by {{ request.user_name }}
                    </div>
                </div>
                <span :class="statusClass(request.status)" class="px-2 py-1 text-xs font-semibold rounded-full shrink-0 ml-2">
                    {{ request.status }}
                </span>
            </div>
            
            <p class="text-sm text-gray-600 mb-3">{{ request.description }}</p>
            
            <div class="flex flex-wrap items-center justify-between text-xs text-gray-500 border-t pt-3 mt-3">
                <div class="flex items-center space-x-3 mb-2 sm:mb-0">
                    <span class="flex items-center">
                        <span class="font-medium mr-1">Priority:</span> 
                        <span :class="priorityClass(request.priority)">{{ request.priority }}</span>
                    </span>
                    <span>{{ formatDate(request.created_at) }}</span>
                </div>
                
                <div class="flex items-center w-full sm:w-auto justify-between sm:justify-end mt-2 sm:mt-0">
                     <!-- HR Actions -->
                    <div v-if="user.isAdmin" class="flex space-x-2 mr-3">
                        <select 
                            @change="updateStatus(request, $event.target.value)" 
                            :value="request.status"
                            class="text-xs border-gray-300 rounded shadow-sm py-1 px-1 h-7"
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Prog</option>
                            <option value="resolved">Resolved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <button @click="toggleNotes(request)" class="text-indigo-600 hover:text-indigo-800 focus:outline-none font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        {{ request.showNotes ? 'Hide' : 'Notes' }}
                    </button>
                </div>
            </div>

            <!-- Notes Section -->
            <div v-if="request.showNotes" class="mt-3 pt-3 bg-gray-50 -mx-4 -mb-4 px-4 pb-4 rounded-b-lg border-t border-gray-100">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Comments</h4>
                
                <div v-if="request.notesLoading" class="text-center py-2 text-xs text-gray-500">Loading...</div>
                
                <ul v-else class="space-y-3 mb-4 max-h-60 overflow-y-auto">
                    <li v-for="note in request.notes" :key="note.id" class="text-sm bg-white p-2 rounded shadow-sm">
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-medium text-gray-900 text-xs">{{ note.user_name }}</span>
                            <span class="text-xs text-gray-400">{{ formatDate(note.created_at) }}</span>
                        </div>
                        <p class="text-gray-700">{{ note.note }}</p>
                    </li>
                    <li v-if="!request.notes || request.notes.length === 0" class="text-xs text-center text-gray-400 italic py-2">No notes yet.</li>
                </ul>
                
                <!-- Add Note Form -->
                <div class="flex gap-2">
                    <input 
                        v-model="request.newNote" 
                        type="text" 
                        placeholder="Type a note..." 
                        class="flex-1 rounded-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-4"
                        @keyup.enter="addNote(request)"
                    >
                    <button 
                        @click="addNote(request)" 
                        :disabled="request.submittingNote"
                        class="bg-indigo-600 text-white rounded-full p-2 hover:bg-indigo-700 disabled:opacity-50 flex-shrink-0"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <div v-if="filteredRequests.length === 0" class="text-center py-10 text-gray-500 bg-white rounded-lg shadow">
            No requests found.
        </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showForm" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Submit New Request</h3>
        <form @submit.prevent="submitRequest">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Title (Item/Issue)</label>
            <input v-model="form.title" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea v-model="form.description" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2"></textarea>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Priority</label>
            <select v-model="form.priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
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

          <div class="flex justify-end space-x-3">
            <button type="button" @click="showForm = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Cancel</button>
            <button type="submit" :disabled="submitting" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                {{ submitting ? 'Submitting...' : 'Submit' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

// Configure axios base URL for WP REST API
const apiRoot = window.OfficeUtilities?.root || '/wp-json/office-utilities/v1';
const nonce = window.OfficeUtilities?.nonce || '';

export default {
  name: 'RequestManager',
  props: {
      user: {
          type: Object,
          default: () => ({ isAdmin: false })
      }
  },
  data() {
    return {
      requests: [],
      loading: true,
      showForm: false,
      submitting: false,
      remindersEnabled: false,
      filterStatus: 'all',
      filterPriority: 'all',
      form: {
        title: '',
        description: '',
        priority: 'medium',
        setReminder: false,
        reminderDays: 3
      }
    };
  },
  computed: {
    filteredRequests() {
        return this.requests.filter(req => {
            const statusMatch = this.filterStatus === 'all' || req.status === this.filterStatus;
            const priorityMatch = this.filterPriority === 'all' || req.priority === this.filterPriority;
            return statusMatch && priorityMatch;
        });
    }
  },
  created() {
    this.fetchRequests();
    if (window.OfficeUtilities && window.OfficeUtilities.settings) {
        this.remindersEnabled = window.OfficeUtilities.settings.remindersEnabled;
    }
  },
  methods: {
    async fetchRequests() {
      try {
        const response = await axios.get(`${apiRoot}/requests`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        // Add reactive properties for notes
        this.requests = response.data.map(req => ({
            ...req,
            showNotes: false,
            notes: [],
            notesLoading: false,
            newNote: '',
            submittingNote: false
        }));
      } catch (error) {
        console.error('Error fetching requests:', error);
      } finally {
        this.loading = false;
      }
    },
    async submitRequest() {
      this.submitting = true;
      try {
        const payload = { ...this.form };
        if (this.remindersEnabled && payload.setReminder) {
            payload.reminder_days = payload.reminderDays;
        } else {
            delete payload.reminder_days;
        }

        const response = await axios.post(`${apiRoot}/requests`, payload, {
            headers: { 'X-WP-Nonce': nonce }
        });
        this.requests.unshift({
            ...response.data,
            showNotes: false,
            notes: [],
            notesLoading: false,
            newNote: '',
            submittingNote: false
        });
        this.showForm = false;
        this.resetForm();
      } catch (error) {
        console.error('Error submitting request:', error);
        alert('Failed to submit request.');
      } finally {
        this.submitting = false;
      }
    },
    async updateStatus(request, newStatus) {
        if (!confirm(`Change status to ${newStatus}?`)) return;
        try {
            const response = await axios.put(`${apiRoot}/requests/${request.id}`, { status: newStatus }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            // Update local state
            const index = this.requests.findIndex(r => r.id === request.id);
            if (index !== -1) {
                this.requests[index] = response.data;
            }
        } catch (error) {
            console.error('Error updating status:', error);
            alert('Failed to update status.');
        }
    },
    async updatePriority(request, newPriority) {
        try {
            const response = await axios.put(`${apiRoot}/requests/${request.id}`, { priority: newPriority }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            const index = this.requests.findIndex(r => r.id === request.id);
            if (index !== -1) {
                // Preserve local state
                this.requests[index] = { ...this.requests[index], ...response.data };
            }
        } catch (error) {
            console.error('Error updating priority:', error);
        }
    },
    async toggleNotes(request) {
        request.showNotes = !request.showNotes;
        if (request.showNotes && request.notes.length === 0) {
            this.fetchNotes(request);
        }
    },
    async fetchNotes(request) {
        request.notesLoading = true;
        try {
            const response = await axios.get(`${apiRoot}/requests/${request.id}/notes`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            request.notes = response.data;
        } catch (error) {
            console.error('Error fetching notes:', error);
        } finally {
            request.notesLoading = false;
        }
    },
    async addNote(request) {
        if (!request.newNote.trim()) return;
        
        request.submittingNote = true;
        try {
            const response = await axios.post(`${apiRoot}/requests/${request.id}/notes`, { note: request.newNote }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            request.notes.push(response.data);
            request.newNote = '';
        } catch (error) {
            console.error('Error adding note:', error);
            alert('Failed to send note.');
        } finally {
            request.submittingNote = false;
        }
    },
    resetForm() {
      this.form = {
        title: '',
        description: '',
        priority: 'medium',
        setReminder: false,
        reminderDays: 3
      };
    },
    statusClass(status) {
      const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        resolved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800'
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    },
    priorityClass(priority) {
      const classes = {
        high: 'text-red-600 font-bold',
        medium: 'text-yellow-600',
        low: 'text-green-600'
      };
      return classes[priority] || '';
    },
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
  }
};
</script>
