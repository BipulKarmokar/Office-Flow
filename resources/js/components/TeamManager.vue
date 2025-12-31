<template>
  <div class="team-manager">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Team Management</h2>
      <button
        @click="showAddModal = true"
        class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition flex items-center"
      >
        <span class="mr-2">+</span> Add Member
      </button>
    </div>

    <!-- User List -->
    <div v-if="loading" class="text-center py-8 text-gray-500">Loading team members...</div>
    
    <div v-else class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="member in users" :key="member.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                                {{ getInitials(member.name) }}
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">{{ member.name }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ member.email }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            {{ member.roles[0] || 'subscriber' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button @click="removeMember(member)" class="text-red-600 hover:text-red-900">Remove</button>
                    </td>
                </tr>
                <tr v-if="users.length === 0">
                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                        No team members added yet. Add existing WordPress users to the team.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Add Member Modal -->
    <div v-if="showAddModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-xl">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Add Existing User to Team</h3>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search User</label>
            <input 
                v-model="searchTerm" 
                @input="searchUsers"
                type="text" 
                placeholder="Type name or email..." 
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2"
            >
        </div>

        <!-- Search Results -->
        <div v-if="searchResults.length > 0" class="mb-4 max-h-60 overflow-y-auto border rounded-md">
            <ul class="divide-y divide-gray-200">
                <li v-for="user in searchResults" :key="user.id" class="p-3 hover:bg-gray-50 flex justify-between items-center">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                        <div class="text-xs text-gray-500">{{ user.email }}</div>
                    </div>
                    <button 
                        @click="addMember(user)" 
                        :disabled="addingUser === user.id"
                        class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded text-xs font-medium hover:bg-indigo-200"
                    >
                        {{ addingUser === user.id ? 'Adding...' : 'Add' }}
                    </button>
                </li>
            </ul>
        </div>
        <div v-else-if="searchTerm && !searching" class="text-sm text-gray-500 mb-4">
            No users found matching "{{ searchTerm }}".
        </div>

        <div class="flex justify-end mt-6">
            <button type="button" @click="closeModal" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

const apiRoot = window.OfficeUtilities?.root || '/wp-json/office-utilities/v1';
const nonce = window.OfficeUtilities?.nonce || '';

// Simple debounce function
function debounceFn(func, wait) {
  let timeout;
  return function(...args) {
    const context = this;
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(context, args), wait);
  };
}

export default {
  name: 'TeamManager',
  data() {
    return {
      users: [],
      loading: true,
      showAddModal: false,
      searchTerm: '',
      searchResults: [],
      searching: false,
      addingUser: null
    };
  },
  created() {
    this.fetchUsers();
    // Debounce the search
    this.debouncedSearch = debounceFn(this.performSearch, 300);
  },
  methods: {
    async fetchUsers() {
      try {
        const response = await axios.get(`${apiRoot}/users`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        this.users = response.data;
      } catch (error) {
        console.error('Error fetching users:', error);
      } finally {
        this.loading = false;
      }
    },
    searchUsers() {
        if (this.searchTerm.length < 2) {
            this.searchResults = [];
            return;
        }
        this.searching = true;
        this.debouncedSearch();
    },
    async performSearch() {
        try {
            const response = await axios.get(`${apiRoot}/users/search`, {
                params: { term: this.searchTerm },
                headers: { 'X-WP-Nonce': nonce }
            });
            this.searchResults = response.data;
        } catch (error) {
            console.error('Error searching users:', error);
        } finally {
            this.searching = false;
        }
    },
    async addMember(user) {
        this.addingUser = user.id;
        try {
            const response = await axios.post(`${apiRoot}/users`, { user_id: user.id }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            this.users.unshift(response.data);
            // Remove from search results
            this.searchResults = this.searchResults.filter(u => u.id !== user.id);
        } catch (error) {
            console.error('Error adding member:', error);
            alert('Failed to add member.');
        } finally {
            this.addingUser = null;
        }
    },
    async removeMember(user) {
        if (!confirm(`Remove ${user.name} from the team?`)) return;
        try {
            await axios.delete(`${apiRoot}/users/${user.id}`, {
                headers: { 'X-WP-Nonce': nonce }
            });
            this.users = this.users.filter(u => u.id !== user.id);
        } catch (error) {
            console.error('Error removing member:', error);
            alert('Failed to remove member.');
        }
    },
    closeModal() {
        this.showAddModal = false;
        this.searchTerm = '';
        this.searchResults = [];
    },
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    },
    getInitials(name) {
        return name ? name.charAt(0).toUpperCase() : 'U';
    }
  }
};
</script>
