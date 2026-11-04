# QR Visitor Registration & Synchronized TV Display

A QR-based visitor management system that allows visitors to register by scanning a QR code. Visitors enter their name, phone number, and photo. Their details appear simultaneously on every connected office TV for 5 minutes. After the visitor session expires, the TVs automatically display random images from an admin-managed gallery.

## Features

- **QR Visitor Registration:** Visitors can register using a QR code.
- **Visitor Details:** Collects visitor name, phone number, and photo.
- **Synchronized TV Display:** Displays the same visitor information across all connected TVs.
- **Automatic Expiry:** Visitor information remains visible for 5 minutes.
- **Admin Gallery:** Admins can manage images displayed after visitor sessions expire.
- **Automatic Gallery Rotation:** TVs display random gallery images when no active visitor is present.
- **Admin Dashboard:** Provides administrative access and management.
- **Real-Time Synchronization:** TVs poll the server every 2 seconds to stay synchronized.

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | Core PHP 8.2+ |
| Database | MongoDB |
| Frontend | React 18 |
| Build Tool | Vite |
| Styling | Tailwind CSS 3 |
| Animations | Framer Motion |
| Icons | Lucide |
| API Communication | REST API |
| Synchronization | Server-side state + polling every 2 seconds |
| Dependency Management | Composer, npm |

## Application URLs

| Module | URL | Description |
|---|---|---|
| Landing Page | `/` | Main application page |
| Visitor Registration | `/register` | Visitor registration form (QR code destination) |
| TV Display | `/display?name=Lobby` | Display visitor information or gallery images |
| Admin Dashboard | `/admin` | Administrative panel |
| API | `/api/...` | Backend API endpoints |

For complete API details, see [API Documentation](docs/api.md).

## Project Structure

```text
visitor-management/
│
├── backend/
│   ├── app/                  # PHP application logic
│   ├── public/               # Public entry point and built frontend
│   │   └── index.php         # API front controller
│   ├── scripts/              # Setup and testing scripts
│   ├── storage/              # Uploaded files
│   ├── vendor/               # Composer dependencies
│   └── .env.example          # Environment configuration example
│
├── frontend/
│   ├── src/                  # React application source
│   ├── public/               # Static frontend assets
│   ├── package.json
│   └── vite.config.js
│
├── docs/
│   ├── installation.md       # Installation instructions
│   ├── api.md                # API documentation
│   ├── deployment.md         # Deployment guide
│   ├── ARCHITECTURE.md       # System architecture
│   ├── ASSUMPTIONS.md        # Project assumptions
│   ├── AI_LOG.md             # AI development log
│   └── testing.md            # Testing plan and checklist
│
└── README.md
```

## Installation & Setup

### Prerequisites

- PHP 8.2 or later
- Composer
- MongoDB
- Node.js and npm
- XAMPP (for Windows Apache/PHP setup)

### Step 1: Clone the Repository

```powershell
git clone https://github.com/jugkalaria116-create/visitercheck_in.git
cd visitercheck_in
```

### Step 2: Configure the Backend

```powershell
cd backend
composer install
copy .env.example .env
```

Edit the `.env` file and configure your MongoDB connection and application settings.

### Step 3: Initialize the Database

Make sure MongoDB is running, then execute:

```powershell
php scripts\setup-indexes.php
php scripts\create-admin.php
```

These scripts initialize the required database indexes/settings and create the first administrator account.

### Step 4: Install and Build the Frontend

Open a terminal in the project root:

```powershell
cd frontend
npm install
npm run build
```

The production build is generated in `backend/public`.

### Step 5: Start the Backend

```powershell
cd ..\backend
php -S 0.0.0.0:8000 -t public public\index.php
```

Open the application:

**http://localhost:8000**

For Apache/XAMPP and production deployment, refer to [Deployment Guide](docs/deployment.md).

### Development Mode

For frontend development with hot reload:

**Terminal 1 — Backend**

```powershell
cd backend
php -S localhost:8000 -t public public\index.php
```

**Terminal 2 — Frontend**

```powershell
cd frontend
npm install
npm run dev
```

Open **http://localhost:5173**.

The Vite development server proxies `/api` requests to the PHP backend.

For detailed setup instructions, see [Installation Guide](docs/installation.md).

## How Synchronization Works

The application uses MongoDB as the central source of truth for synchronized TV displays.

1. **Central Display State:** A single `display_state` MongoDB document stores the current mode, visitor/image information, expiry time (`expires_at`), and version number.
2. **Visitor Registration:** When a visitor registers, the server atomically updates the display state and increments its version.
3. **Automatic Expiry:** The server checks visitor expiry through `GET /api/display/current`.
4. **Compare-and-Set:** Version-based updates prevent an outdated expiry operation from overwriting a newer visitor session.
5. **TV Polling:** Connected TVs request the current display state every 2 seconds.
6. **Synchronized Updates:** TVs update their display when the version changes.
7. **Gallery Display:** After the visitor session expires, the server switches the display to a gallery image.
8. **Refresh Handling:** Refreshing a TV only retrieves the current state; it does not restart the visitor timer.

## Testing

The project includes automated scripts and a manual acceptance checklist.

### Automated Tests

Run the following commands from the project root:

**Display Engine and Phone Validation**

```powershell
php backend\scripts\test-display-engine.php
```

**HTTP Smoke Tests**

Start the backend first, then run:

```powershell
php backend\scripts\smoke-test.php http://localhost:8000
```

These scripts are intended to test display logic, phone validation, API authentication, request validation, upload rejection, and caching headers.

### Manual Testing

Refer to [Testing Documentation](docs/testing.md) for the manual test plan and acceptance checklist.

## Documentation

| Document | Description |
|---|---|
| [Installation Guide](docs/installation.md) | Complete installation and configuration instructions |
| [API Documentation](docs/api.md) | API endpoints, requests, and responses |
| [Deployment Guide](docs/deployment.md) | Deployment and server configuration |
| [Architecture](docs/ARCHITECTURE.md) | System architecture and application workflow |
| [Assumptions](docs/ASSUMPTIONS.md) | Project assumptions and design decisions |
| [AI Development Log](docs/AI_LOG.md) | AI-assisted development activities and troubleshooting |
| [Testing Documentation](docs/testing.md) | Test cases and manual acceptance checklist |

## Environment Configuration

The backend uses environment variables for application and database configuration.

Important variables include:

```env
APP_ENV=development
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
APP_TIMEZONE=Asia/Kolkata

MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=your_database_name

SESSION_SECURE_COOKIE=false
UPLOAD_MAX_SIZE=5M
VISITOR_RETENTION_DAYS=30
```

Use `backend/.env.example` as the configuration template. Update values according to your environment. Never commit your actual `.env` file or database credentials.

## Important Notes

- MongoDB is the application's database.
- Keep the MongoDB service running while using the application.
- The QR code should point to the visitor registration page.
- Each TV should open the display page using the appropriate display name.
- For multiple TVs on a network, configure the application using a reachable server address rather than `localhost`.
- Use Apache or another suitable web server for production deployment.
- Keep uploaded files and sensitive environment configuration protected.

## Future Improvements

- Improved visitor analytics and reporting.
- Advanced visitor search and filtering.
- Exportable visitor reports.
- More gallery management options.
- Enhanced admin dashboard statistics.

## Author

**Jug Kalaria**

GitHub: [jugkalaria116-create](https://github.com/jugkalaria116-create)

Repository: [QR Visitor Management System](https://github.com/jugkalaria116-create/visitercheck_in)
<br><img width="100" height="100" alt="Link QR" src="https://github.com/user-attachments/assets/bf89968d-3e90-40c1-97fe-4257d506c680" />


---

*QR Visitor Registration & Synchronized TV Display — A centralized visitor registration and synchronized display management system.*
