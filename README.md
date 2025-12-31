# Office Flow - WordPress Office Management Plugin

**Office Flow** (formerly Office Utilities) is a modern, single-page application (SPA) plugin for WordPress designed to streamline internal office operations. It helps small to medium businesses manage employee requests, track expenses, and handle team communications efficiently directly from the WordPress dashboard.

## 🎥 Demo Video
[![Watch the Demo](https://img.youtube.com/vi/YOUR_VIDEO_ID_HERE/0.jpg)](https://www.youtube.com/watch?v=YOUR_VIDEO_ID_HERE)

*(Click the image above to watch the walkthrough)*

## 🚀 Key Features

### 📋 Request Management
*   **Create Requests**: Employees can submit requests for office supplies, leave, IT support, or maintenance.
*   **Track Status**: Real-time status updates (Pending, In Progress, Resolved, Rejected).
*   **Priority Levels**: Set urgency (Low, Medium, High).
*   **Interactive Notes**: Comment threads on requests for clarification between HR and employees.
*   **Reminders**: Set automated follow-up reminders for pending requests.

### 💰 Expense Tracking
*   **Digital Claims**: Employees can submit expense claims with amount, category (Food, Travel, etc.), and description.
*   **Receipt Scanning (OCR)**: Built-in **AI Scanner** (Tesseract.js) to extract amounts from receipt images automatically.
*   **Currency Support**: Optimized for **BDT (৳)** transactions.
*   **Approval Workflow**: Admins can Approve, Reject, and Mark as Reimbursed.

### 👥 Team Management
*   **Role-Based Access**: 
    *   **Admins/HR**: Full access to all requests, expenses, and settings.
    *   **Employees**: View and manage only their own submissions.
*   **Easy Onboarding**: Add existing WordPress users to the "Office Team" with a single click.

### 🔔 Smart Notifications (Telegram Integration)
*   **Instant Alerts**: Receive Telegram messages for new requests, status updates, and reminders.
*   **Interactive Buttons**: Admins can **Approve** or **Reject** requests directly from the Telegram chat!
*   **Email Fallback**: Standard email notifications for all key actions.

### 📊 HR Dashboard
*   **Overview Stats**: Visual summary of total spending, pending requests, and active employees.
*   **Recent Activity**: Quick view of the latest submissions.

---

## 🛠️ Technology Stack
*   **Frontend**: Vue.js 3, Tailwind CSS (bundled via Vite)
*   **Backend**: PHP, WordPress REST API
*   **Database**: Custom WordPress Tables (`wp_ou_requests`, `wp_ou_expenses`, `wp_ou_notes`)
*   **Integrations**: Telegram Bot API, Tesseract.js (OCR)

---

## 📦 Installation

### Option 1: Upload Zip (For Users)
1.  Download the latest `office-utilities.zip` from the [Releases](https://github.com/BipulKarmokar/Office-Flow/releases) page (or build it yourself).
2.  Go to your WordPress Admin > **Plugins** > **Add New** > **Upload Plugin**.
3.  Upload the zip file and click **Activate**.
4.  The "Office Utilities" menu will appear in your sidebar.

### Option 2: From Source (For Developers)
1.  Clone this repository into your `wp-content/plugins/` directory:
    ```bash
    git clone https://github.com/BipulKarmokar/Office-Flow.git office-utilities
    cd office-utilities
    ```
2.  Install PHP dependencies:
    ```bash
    composer install
    ```
3.  Install Node dependencies and build assets:
    ```bash
    npm install
    npm run build
    ```
4.  Activate the plugin in WordPress.

---

## ⚙️ Configuration Guide

### 1. Telegram Bot Setup (Crucial for Notifications)
To enable instant notifications and interactive buttons:

1.  **Create a Bot**: Talk to [@BotFather](https://t.me/BotFather) on Telegram and create a new bot. Get the **API Token**.
2.  **Configure Plugin**:
    *   Go to **Office Utilities > Settings** in WordPress.
    *   Scroll to **Admin: Bot Configuration**.
    *   Paste your **Bot Token** and save.
3.  **Set Webhook**:
    *   **Live Site**: Click the "Set Webhook" button. Ensure your site uses **HTTPS**.
    *   **Localhost**: You MUST use a tunnel like [ngrok](https://ngrok.com). 
        *   Run `ngrok http 80`.
        *   Paste the ngrok URL (e.g., `https://xxxx.ngrok.io/wp-json/office-utilities/v1/telegram/webhook`) into the "Webhook URL" field and click Set.
4.  **Link Your Account**:
    *   Scroll to the top of Settings.
    *   Click **Generate Link Command**.
    *   Send the command (e.g., `/start 123456`) to your bot in Telegram.
    *   Click **Refresh Status**. It should turn Green.

### 2. Email Setup (SMTP)
WordPress emails often go to spam. It is highly recommended to install a dedicated SMTP plugin like **WP Mail SMTP** or **FluentSMTP** to ensure reliable delivery of email notifications.

---

## 🏗️ Development

### Project Structure
```
office-utilities/
├── app/                  # PHP Backend Logic
│   ├── Http/Controllers  # API Endpoints
│   ├── Services/         # Notification & Business Logic
│   └── Routes/           # REST API Routes
├── resources/            # Vue.js Frontend source
│   ├── js/components     # Vue Components
│   └── scss/             # Styles
├── assets/dist/          # Compiled Assets (Built by Vite)
└── office-utilities.php  # Main Plugin File
```

### Commands
*   `npm run dev`: Start Vite development server (Hot Module Replacement).
*   `npm run build`: Build production assets.
*   `./build-plugin.sh`: Create a deployable `.zip` file (Linux/Mac).

---

## 📝 License
This project is open-source software licensed under the MIT license.
