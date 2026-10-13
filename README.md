# IPRPMS - Integrated Planning, Reporting & Performance Management System

IPRPMS is a premium, HR-driven enterprise platform built for the government bureau to manage personnel, strategic plans, task execution, and performance reporting in a unified ecosystem.

## 🌟 Key Features

### 🏢 HR & Employee Management
- **Centralized Registry**: Every user is linked to an Employee record managed by HR.
- **Bulk Import**: Rapid onboarding using specialized Excel templates.
- **Account Provisioning**: Controlled account creation via System Administrators (no public registration).

### 📈 Strategic Planning (Top-Down)
- **Departmental Hierarchy**: Manage Parent/Sub-units with ease.
- **Scorecard Management**: Set annual strategic objectives, objective weights, and quarterly targets.
- **Baseline Integration**: Track performance against verified historical baselines.

### 🛠️ Task & Evidence Management
- **Task Assignment**: Assign tasks directly linked to Strategic Objectives.
- **Evidence Submission**: Upload files, images, and links as proof of work.
- **Approval Workflow**: Multi-level task approval (Supervisor/Admin).

### 📊 Performance & Analytics
- **Dynamic Scoring**: Objective progress is automatically derived from task completion percentages.
- **Role-Based Dashboards**: Real-time metrics for Admins, HR Managers, and Staff.
- **Audit Logging**: Comprehensive traceability of every critical action.

### 📄 Professional Reporting
- **Narrative Reports**: Combine automated KPI data with high-level executive summaries.
- **PDF Export**: Generate professional, formatted reports for board-level reviews.
- **Comparative Analysis**: Automatic % comparison against previous year performance.

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Composer
- AdminLTE 3 / Bootstrap 4

### Installation
1. Clone the repository.
2. Run `composer install`.
3. Configure your `.env` file with database credentials.
4. Run migrations and seeders:
   ```bash
   php artisan migrate
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```
5. Default Admin: `admin@iprpms.gov.et` / `Admin@2026`

## 🛡️ Security
- **HR-First Model**: Users cannot self-register; HR creates employee records first.
- **Permission Based**: Fine-grained access control (RBAC) across all modules.
- **Audit Trail**: Every plan submission, task approval, and report generation is logged.

---
*Developed for the Bureau Performance Monitoring Initiative.*
