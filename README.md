# 💧 THIWASCO Management Information System (MIS)

**THIWASCO MIS** is a enterprise-grade Water Management Information System built for **Thika Water & Sewerage Company (THIWASCO)**. The platform streamlines municipal water distribution, billing operations, revenue protection, non-revenue water (NRW) reduction, infrastructure asset tracking, and staff role management.

---

## 🚀 Key Modules & CRUD Operations

| Module | Features & Capabilities | CRUD Support |
|---|---|---|
| 👥 **Customers** | Customer directory, onboarding, KYC data, zone allocation, tariff mapping, GPS coordinates, arrears & connection status. | **Create, Read, Update, Delete** (with cascade safeguards) |
| 📟 **Meters & Readings** | Meter register, serial numbers, bulk meters, manual & mobile reading entries, anomaly detection, consumption tracking. | **Create, Read, Update, Delete** |
| 💰 **Billing & Tariffs** | WASREB tiered tariff rate schedules, automated cycle bill generation, sewer surcharge calculations, printable invoice generator. | **Create, Read, Update, Delete** |
| 💳 **Payments & Receipts** | Payment receipting, cash/cheque/bank slip records, M-Pesa C2B / STK integration simulator, admin receipt reversal. | **Create, Read, Reverse / Soft Delete** |
| 🚰 **NRW & Production** | Bulk intake abstraction tracking, treatment plant discharge logs, pumping hours, energy efficiency, IWA water balance calculation. | **Create, Read, Update, Delete** |
| 🛡️ **Revenue Protection** | Field audit logging, meter tampering investigation, illegal connections, evidence photos, penalty assessment & invoicing. | **Create, Read, Update, Delete** |
| 📊 **Executive Reports** | Revenue performance, billing collection efficiency, zonal NRW audit, defaulters aging analysis, exportable reports. | **Read & Analytics** |
| ⚙️ **User Management** | Staff accounts, RBAC permission roles (Administrator, Billing, Cashier, Inspector, Meter Reader, Manager), password resets. | **Create, Read, Update, Delete** |
| 📦 **Stores & Inventory (Optional)** | Pipes, fittings, meters, chemical supplies, reorder level alerts, stock issue/receive ledger. | **Create, Read, Update, Delete** |
| 🏗️ **Capital Works & Assets (Optional)** | High-lift pumps, boreholes, reservoirs, pipeline network expansion projects, contractor milestones & budgets. | **Create, Read, Update, Delete** |

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.0+ / 8.2 (Vanilla, Fast & Self-Contained)
- **Database**: Dual-Driver PDO Engine (**MySQL / MariaDB** and **SQLite** zero-config fallback)
- **UI / Frontend**: Responsive CSS3 Design System with Royal Blue & Gold aesthetic, FontAwesome 6, Chart.js
- **Containerization**: Docker / Apache with dynamic port binding for cloud hosting

---

## 🔐 Default Admin Credentials

- **Username**: `admin`
- **Password**: `Admin@2026` *(or `password`)*
- **Role**: System Administrator

---

## 💻 Local Setup (XAMPP / PHP Dev Server)

### Option A: Using PHP Built-in Server
```bash
# Clone the repository
git clone <your-repo-url>
cd THIWASCO

# Start the built-in server
php -S localhost:8080
```
Open your browser at `http://localhost:8080/login.php`.

### Option B: Using XAMPP / Apache + MySQL
1. Copy the folder to `c:/xampp/htdocs/THIWASCO`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Access `http://localhost/THIWASCO/install.php` to run the database installer or import `database/thiwasco.sql`.
4. Access `http://localhost/THIWASCO/login.php`.

---

## ☁️ Deploying to Render (Hosting Guide)

### Step 1: Push Repository to GitHub
```bash
git init
git add .
git commit -m "Initial commit of THIWASCO Water MIS"
git branch -M main
git remote add origin https://github.com/<your-username>/<your-repo-name>.git
git push -u origin main
```

### Step 2: Create Web Service on Render
1. Log in to [Render.com](https://render.com).
2. Click **New +** → **Web Service**.
3. Connect your GitHub repository.
4. Set the following settings:
   - **Environment**: `Docker`
   - **Plan**: `Free`
   - **Region**: `Oregon` (or closest to you)
5. Under **Environment Variables**, add:
   - `APP_NAME` = `THIWASCO MIS`
   - `DB_CONNECTION` = `sqlite`
6. Click **Create Web Service**.

Render will automatically build the Docker container and deploy the application with a public `https://<app-name>.onrender.com` URL.

---

## 📄 License & Ownership
Copyright © 2026 Thika Water & Sewerage Company (THIWASCO). All Rights Reserved.
