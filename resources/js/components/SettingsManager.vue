<template>
  <div class="settings-manager">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Settings</h2>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Email Notifications</h3>
      
      <div class="flex items-center justify-between mb-8">
        <div>
          <p class="text-gray-700 font-medium">Enable Email Notifications</p>
          <p class="text-sm text-gray-500">Receive emails when your request status changes.</p>
        </div>
        
        <button 
            @click="toggleNotifications" 
            :class="[enabled ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']"
        >
            <span aria-hidden="true" :class="[enabled ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200']"></span>
        </button>
      </div>

      <!-- Telegram Integration -->
      <div v-if="user.isAdmin" class="mt-8 border-t pt-6">
        <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-lg font-medium text-gray-900">Telegram Notifications</h3>
              <p class="text-sm text-gray-500">Receive instant alerts on Telegram.</p>
            </div>
            <button 
                @click="toggleTelegram" 
                :class="[telegramEnabled ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']"
            >
                <span aria-hidden="true" :class="[telegramEnabled ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200']"></span>
            </button>
        </div>

        <div v-if="telegramEnabled" class="bg-blue-50 p-4 rounded-md">
            <div class="flex justify-between items-start">
                <div>
                    <p v-if="telegramChatId" class="text-green-700 text-sm font-medium flex items-center mb-2">
                        ✅ Your Account is Connected! <br>
                        <span class="text-xs font-normal text-gray-600 ml-1">(Chat ID: {{ telegramChatId }})</span>
                    </p>
                    <div v-else>
                        <p class="text-sm text-blue-800 mb-2 font-bold">⚠️ Action Required: Link your Telegram Account</p>
                        <p class="text-sm text-blue-700 mb-2">To receive notifications, you must link your specific Telegram user to this dashboard.</p>
                        <ol class="list-decimal list-inside text-sm text-blue-700 space-y-1 mb-3">
                            <li>Open our bot in Telegram.</li>
                            <li>Click <strong>Start</strong> (or type <code>/start</code>).</li>
                            <li>Send this unique command:</li>
                        </ol>
                        
                        <div v-if="linkToken" class="flex items-center space-x-2 mb-3">
                            <code class="bg-white px-3 py-2 rounded border border-blue-200 font-mono text-lg font-bold select-all">/start {{ linkToken }}</code>
                        </div>
                        
                        <button v-if="!linkToken" @click="generateLinkToken" class="text-sm bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 shadow-sm">
                            Generate Link Command
                        </button>
                    </div>
                </div>
                
                <!-- Refresh Button -->
                <button @click="fetchSettings" class="text-xs flex items-center text-gray-500 hover:text-indigo-600 underline ml-4" title="Check if linked">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh Status
                </button>
            </div>
            
            <p v-if="!telegramChatId && linkToken" class="text-xs text-gray-500 mt-2">
                After sending the command in Telegram, click <strong>Refresh Status</strong> above.
            </p>
        </div>
      </div>

      <!-- Admin Only: Bot Configuration -->
      <div v-if="user.isAdmin" class="mt-8 border-t pt-6 bg-gray-50 p-4 rounded-md border border-gray-200">
          <h3 class="text-lg font-medium text-gray-900 mb-2">🤖 Admin: Bot Configuration</h3>
          <p class="text-sm text-gray-600 mb-4 border-b pb-4">
             These settings control the plugin's ability to send messages. <br>
             <strong>Note:</strong> Saving the Token here does <em>not</em> automatically link your personal account above. You must still perform the linking step.
          </p>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Telegram Bot Token</label>
            <div class="mt-1 flex rounded-md shadow-sm">
                <input type="password" v-model="telegramBotToken" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-gray-300 border">
                <button @click="saveAdminSettings" class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Save Token
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Get this from <a href="https://t.me/BotFather" target="_blank" class="text-indigo-600 hover:underline">@BotFather</a></p>
          </div>
          
          <div class="mb-4">
             <label class="block text-sm font-medium text-gray-700">Webhook URL</label>
             <p class="text-xs text-gray-500 mb-2">
                 <strong>Crucial Step:</strong> Telegram needs this URL to send messages back to your site. <br>
                 - If on <strong>Live Site</strong>: Ensure it matches your domain (https). <br>
                 - If on <strong>Localhost</strong>: You MUST use <a href="https://ngrok.com" target="_blank" class="text-indigo-600 underline">ngrok</a> or similar to expose your site.
             </p>
             <div class="mt-1 flex items-center space-x-2">
                 <input type="text" v-model="webhookUrl" placeholder="https://your-site.com/wp-json/office-utilities/v1/telegram/webhook" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-gray-300 border">
                 <button @click="setWebhook" class="px-3 py-2 border border-transparent rounded-md bg-green-600 text-white text-sm hover:bg-green-700 whitespace-nowrap">
                     Set Webhook
                 </button>
             </div>
             <p v-if="webhookSuccess" class="text-xs text-green-600 mt-1 font-bold">✅ Webhook successfully set!</p>
          </div>
          
          <div class="mb-4 pt-4 border-t border-gray-100">
             <h4 class="text-sm font-medium text-gray-900 mb-2">Troubleshooting</h4>
             <button @click="testWebhook" class="text-xs bg-gray-100 text-gray-700 px-3 py-2 rounded hover:bg-gray-200 border border-gray-300">
                 🔍 Test Webhook Connection
             </button>
             <div v-if="webhookDebug" class="mt-2 p-3 bg-gray-100 rounded text-xs font-mono text-gray-700 whitespace-pre-wrap border border-gray-300">
                 {{ webhookDebug }}
             </div>
          </div>
      </div>

      <!-- Admin Only: Global Features -->
      <div v-if="user.isAdmin" class="mt-8 border-t pt-6 bg-white p-4 rounded-md">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Feature Controls</h3>
          
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-gray-700 font-medium">Allow Employee Reminders</p>
              <p class="text-sm text-gray-500">Enable employees to set reminders for pending requests/expenses.</p>
            </div>
            
            <button 
                @click="toggleReminders" 
                :class="[remindersEnabled ? 'bg-indigo-600' : 'bg-gray-200', 'relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']"
            >
                <span aria-hidden="true" :class="[remindersEnabled ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200']"></span>
            </button>
          </div>
      </div>

      <div v-if="user.isAdmin" class="mt-8 border-t pt-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">SMTP Configuration (Admin Only)</h3>
          <p class="text-sm text-gray-600 mb-4">
              To ensure emails are delivered reliably, it is highly recommended to configure SMTP.
              By default, WordPress uses PHP mail(), which often goes to spam.
          </p>
          <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
              <div class="flex">
                  <div class="flex-shrink-0">
                      <!-- Info Icon -->
                      <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                      </svg>
                  </div>
                  <div class="ml-3">
                      <p class="text-sm text-blue-700">
                          We recommend installing a dedicated SMTP plugin like <strong>WP Mail SMTP</strong> or <strong>FluentSMTP</strong>.
                          These plugins allow you to connect to services like Gmail, Outlook, or Amazon SES easily.
                      </p>
                      <p class="text-sm text-blue-700 mt-2">
                          Once installed and configured, this plugin will automatically use that connection to send emails. No further setup is needed here.
                      </p>
                  </div>
              </div>
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
  name: 'SettingsManager',
  props: {
      user: Object
  },
  data() {
    return {
      enabled: true,
      telegramEnabled: false,
      telegramChatId: '',
      telegramBotToken: '',
      webhookUrl: '',
      remindersEnabled: false,
      linkToken: '',
      loading: true,
      webhookSuccess: false,
      webhookDebug: null
    };
  },
  created() {
    this.fetchSettings();
  },
  methods: {
    async generateLinkToken() {
        try {
            const response = await axios.post(`${apiRoot}/settings/notifications`, { generate_telegram_token: true }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            this.linkToken = response.data.token;
        } catch (error) {
            console.error('Error generating token:', error);
            alert('Failed to generate token.');
        }
    },
    async fetchSettings() {
      try {
        const response = await axios.get(`${apiRoot}/settings/notifications`, {
            headers: { 'X-WP-Nonce': nonce }
        });
        this.enabled = response.data.enabled;
        this.telegramEnabled = response.data.telegram_enabled;
        this.telegramChatId = response.data.telegram_chat_id || '';
        this.webhookUrl = response.data.webhook_url || '';
        if (this.user.isAdmin) {
            this.telegramBotToken = response.data.telegram_bot_token || '';
            this.remindersEnabled = response.data.reminders_enabled || false;
        }
      } catch (error) {
        console.error('Error fetching settings:', error);
      } finally {
        this.loading = false;
      }
    },
    async setWebhook() {
        if (!this.telegramBotToken) {
            alert('Please save the Bot Token first.');
            return;
        }
        if (!this.webhookUrl) {
            alert('Please enter a Webhook URL.');
            return;
        }
        try {
            // Using a hidden iframe or simple fetch to avoid opening new tab if possible, 
            // but Telegram API requires GET/POST. Opening in new tab is safest to see output.
            // Better user experience: Call backend to do it? 
            // For now, let's open it so user sees "Webhook was set"
            const url = `https://api.telegram.org/bot${this.telegramBotToken}/setWebhook?url=${this.webhookUrl}`;
            window.open(url, '_blank');
            this.webhookSuccess = true;
        } catch (error) {
            alert('Error setting webhook');
        }
    },
    async toggleNotifications() {
        const newValue = !this.enabled;
        try {
            await axios.post(`${apiRoot}/settings/notifications`, { enabled: newValue }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            this.enabled = newValue;
        } catch (error) {
            console.error('Error updating settings:', error);
            alert('Failed to update settings.');
        }
    },
    async toggleReminders() {
        const newValue = !this.remindersEnabled;
        try {
            await axios.post(`${apiRoot}/settings/notifications`, { reminders_enabled: newValue }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            this.remindersEnabled = newValue;
        } catch (error) {
            console.error('Error updating settings:', error);
            alert('Failed to update settings.');
        }
    },
    async toggleTelegram() {
        const newValue = !this.telegramEnabled;
        try {
            await axios.post(`${apiRoot}/settings/notifications`, { telegram_enabled: newValue }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            this.telegramEnabled = newValue;
        } catch (error) {
            console.error('Error updating settings:', error);
            alert('Failed to update settings.');
        }
    },
    async saveTelegramSettings() {
        try {
            await axios.post(`${apiRoot}/settings/notifications`, { telegram_chat_id: this.telegramChatId }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            alert('Telegram Chat ID saved.');
        } catch (error) {
            console.error('Error saving Telegram ID:', error);
            alert('Failed to save Telegram ID.');
        }
    },
    async saveAdminSettings() {
        if (!confirm('Warning: Changing the Bot Token will affect all users. Continue?')) return;
        try {
            await axios.post(`${apiRoot}/settings/notifications`, { telegram_bot_token: this.telegramBotToken }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            alert('Bot Token updated successfully.');
        } catch (error) {
            console.error('Error saving Bot Token:', error);
            alert('Failed to save Bot Token.');
        }
    },
    async testWebhook() {
        this.webhookDebug = 'Checking...';
        try {
            const response = await axios.post(`${apiRoot}/settings/notifications`, { test_webhook: true }, {
                headers: { 'X-WP-Nonce': nonce }
            });
            
            if (response.data.ok) {
                const info = response.data.result;
                this.webhookDebug = `✅ Telegram API Response:\nURL: ${info.url}\nPending Updates: ${info.pending_update_count}\nLast Error: ${info.last_error_message || 'None'}\nLast Error Date: ${info.last_error_date ? new Date(info.last_error_date * 1000).toLocaleString() : 'N/A'}`;
            } else {
                 this.webhookDebug = `❌ Telegram Error:\n${response.data.description}`;
            }
        } catch (error) {
            console.error('Error testing webhook:', error);
            this.webhookDebug = '❌ Failed to connect to server or no token saved.';
        }
    }
  }
};
</script>
