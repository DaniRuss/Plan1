@php
    $themeColor = static function (string $key, string $fallback): string {
        $value = (string) \App\Models\Setting::get($key, $fallback);

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
    };

    $primaryAccent = $themeColor('primary_color', '#007bff');
    $sidebarBackground = $themeColor('sidebar_bg', '#343a40');
    $sidebarText = $themeColor('sidebar_text', '#c2c7d0');
    $navbarColor = $themeColor('navbar_color', '#ffffff');
    $customCss = (string) \App\Models\Setting::get('custom_css', '');
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="{{ asset('js/number-format.js') }}"></script>
    @include('layouts.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ \App\Models\Setting::get('site_name', 'IPRPMS') }}</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">

    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('vendor/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/datatables-responsive/css/responsive.bootstrap4.min.css') }}">

    @stack('styles')

    <style>
        :root {
            --primary-accent: #007bff;
            --sidebar-bg: #343a40;
            --sidebar-text: #c2c7d0;
            --navbar-color: #ffffff;
        }

        .content-wrapper {
            background-color: #f8f9fa;
        }

        .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 0 1px rgba(0, 0, 0, .1);
        }

        .bg-white {
            background-color: #ffffff !important;
        }

        /* Dynamic Customizations */
        .main-sidebar {
            background-color: var(--sidebar-bg) !important;
            height: 100vh !important;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .main-sidebar > .brand-link {
            flex: 0 0 auto;
        }

        .layout-fixed .main-sidebar > .sidebar {
            flex: 1 1 auto;
            min-height: 0;
            height: auto !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
            padding-bottom: 1rem;
        }

        .main-sidebar > .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .main-sidebar > .sidebar::-webkit-scrollbar-thumb {
            background: rgba(128, 128, 128, .55);
            border-radius: 8px;
        }

        .main-sidebar > .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link.active {
            background-color: var(--primary-accent) !important;
        }

        .main-sidebar .nav-link,
        .main-sidebar .brand-text {
            color: var(--sidebar-text) !important;
        }

        .main-header {
            background-color: var(--navbar-color) !important;
        }

        .btn-primary {
            background-color: var(--primary-accent) !important;
            border-color: var(--primary-accent) !important;
        }
    </style>

    @if($customCss !== '')
        <link rel="stylesheet" href="data:text/css;base64,{{ base64_encode($customCss) }}">
    @endif
</head>

<body class="hold-transition sidebar-mini layout-fixed"
    data-primary-accent="{{ $primaryAccent }}"
    data-sidebar-bg="{{ $sidebarBackground }}"
    data-sidebar-text="{{ $sidebarText }}"
    data-navbar-color="{{ $navbarColor }}">
    <script>
        (() => {
            const theme = document.body.dataset;
            const root = document.documentElement.style;

            root.setProperty('--primary-accent', theme.primaryAccent);
            root.setProperty('--sidebar-bg', theme.sidebarBg);
            root.setProperty('--sidebar-text', theme.sidebarText);
            root.setProperty('--navbar-color', theme.navbarColor);
        })();
    </script>
    @guest
        <!-- Guest Layout (for login/register pages) -->
        <div class="wrapper" style="background-color: #f8f9fa; min-height: 100vh;">
            <div class="content-wrapper d-flex align-items-center justify-content-center">
                <section class="content">
                    <div class="container-fluid">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible mx-auto" style="max-width: 500px;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <i class="icon fas fa-check"></i> {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible mx-auto" style="max-width: 500px;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <i class="icon fas fa-ban"></i> {{ session('error') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible mx-auto" style="max-width: 500px;">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <h5><i class="icon fas fa-ban"></i> Validation Error!</h5>
                                <ul>
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @yield('content')
                    </div>
                </section>
            </div>
        </div>
    @else
        <!-- Authenticated User Layout -->
        <div class="wrapper">
            <!-- Navbar -->
            <nav class="main-header navbar navbar-expand navbar-white navbar-light">
                <!-- Left navbar links -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                            <i class="fas fa-bars"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <span class="nav-link font-weight-bold ml-2"
                            style="font-size: 1.1rem; color: var(--primary-accent);">
                            {{ \App\Models\Setting::get('site_abbv', 'IPRPMS') }}
                        </span>
                    </li>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ml-auto">
                    <!-- Notifications Dropdown Menu -->
                    <li class="nav-item dropdown mr-2">
                        <a class="nav-link px-2" data-toggle="dropdown" href="#" aria-expanded="false">
                            <i class="far fa-bell" style="font-size: 1.1rem;"></i>
                            @if(auth()->user()->unreadNotifications->count() > 0)
                                <span class="badge badge-warning navbar-badge shadow-sm" style="top: 5px; right: 2px;">
                                    {{ auth()->user()->unreadNotifications->count() }}
                                </span>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right"
                            style="border: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.15); min-width: 320px;">
                            <div class="dropdown-header bg-light d-flex justify-content-between align-items-center py-3">
                                <span class="font-weight-bold">{{ auth()->user()->unreadNotifications->count() }} New
                                    Notifications</span>
                                @if(auth()->user()->unreadNotifications->count() > 0)
                                    <form action="{{ route('notifications.markAllAsRead') }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-link text-primary p-0">Mark all as
                                            read</button>
                                    </form>
                                @endif
                            </div>
                            <div class="dropdown-divider"></div>

                            <div class="notification-scroll" style="max-height: 300px; overflow-y: auto;">
                                @forelse(auth()->user()->unreadNotifications->take(5) as $notification)
                                    <a href="{{ route('notifications.read', $notification->id) }}" class="dropdown-item py-3">
                                        <div class="media">
                                            <div class="bg-light p-2 rounded-circle mr-3">
                                                <i
                                                    class="{{ $notification->data['icon'] ?? 'fas fa-info-circle' }} text-sm text-info"></i>
                                            </div>
                                            <div class="media-body">
                                                <h3 class="dropdown-item-title font-weight-bold text-sm">
                                                    {{ $notification->data['title'] }}
                                                    <span class="float-right text-muted text-xs">
                                                        <i class="far fa-clock mr-1"></i>
                                                        {{ $notification->created_at->diffForHumans(null, true) }}
                                                    </span>
                                                </h3>
                                                <p class="text-sm text-muted mb-0 text-truncate" style="max-width: 200px;">
                                                    {{ $notification->data['message'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                @empty
                                    <div class="text-center py-4 px-3">
                                        <p class="text-muted text-sm mb-0">No unread notifications</p>
                                    </div>
                                @endforelse
                            </div>

                            <a href="{{ route('notifications.index') }}"
                                class="dropdown-item dropdown-footer bg-light py-2">
                                View All Notifications
                            </a>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link" data-toggle="dropdown" href="#">
                            <i class="far fa-user"></i>
                            {{ Auth::user()->name ?? 'User' }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a href="{{ route('profile.edit') }}" class="dropdown-item">
                                <i class="fas fa-user mr-2"></i> Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </button>
                            </form>
                        </div>
                    </li>
                </ul>
            </nav>

            <!-- Sidebar -->
            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <!-- Brand Logo -->
                <a href="{{ route('dashboard') }}" class="brand-link"
                    style="height: auto; min-height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 5px;">
                    @if(\App\Models\Setting::get('site_logo'))
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}" alt="Logo"
                                style="width: 150px; height: 50px; object-fit: contain; opacity: .9;">
                        </div>
                        <span class="brand-text font-weight-bold text-center text-sm"
                            style="line-height: 1.2; white-space: normal;">
                            {{ \App\Models\Setting::get('site_name', 'IPRPMS') }}
                        </span>
                    @else
                        <div class="d-flex align-items-center">
                            <img src="{{ asset('vendor/adminlte/dist/img/AdminLTELogo.png') }}" alt="IPRPMS Logo"
                                class="brand-image img-circle elevation-3" style="opacity: .8">
                            <span
                                class="brand-text font-weight-light">{{ \App\Models\Setting::get('site_name', 'IPRPMS') }}</span>
                        </div>
                    @endif
                </a>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Sidebar Menu -->
                    <nav class="mt-2">
                        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                            data-accordion="false">
                            @php
                                $navUser = auth()->user();
                            @endphp
                            <li class="nav-item">
                                <a href="{{ route('dashboard') }}"
                                    class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-tachometer-alt"></i>
                                    <p>Dashboard</p>
                                </a>
                            </li>

                            @if($navUser->canAccessReportingModule('monthly'))
                                <li class="nav-item">
                                    <a href="{{ route('monthly.dashboard') }}"
                                        class="nav-link {{ request()->is('monthly*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-layer-group"></i>
                                        <p>Monthly Aggregator</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->canAccessReportingModule('weekly'))
                                <li class="nav-item">
                                    <a href="{{ route('weekly.dashboard') }}"
                                        class="nav-link {{ request()->is('weekly*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-calendar-week"></i>
                                        <p>Weekly Workbench</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->canAccessReportingModule('daily'))
                                <li class="nav-item">
                                    <a href="{{ route('daily.dashboard') }}"
                                        class="nav-link {{ request()->is('daily*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-calendar-day"></i>
                                        <p>Daily Performance</p>
                                    </a>
                                </li>
                            @endif

                            @if(auth()->user()->hasPermission('manage_departments'))
                                <li class="nav-item">
                                    <a href="{{ route('departments.index') }}"
                                        class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-building"></i>
                                        <p>Departments</p>
                                    </a>
                                </li>
                            @endif

                            @if(auth()->user()->hasPermission('view_employees'))
                                <li class="nav-item">
                                    <a href="{{ route('employees.index') }}"
                                        class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-users"></i>
                                        <p>Employees</p>
                                    </a>
                                </li>
                            @endif

                            @if(auth()->user()->hasPermission('manage_users'))
                                <li class="nav-item">
                                    <a href="{{ route('users.index') }}"
                                        class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-user-shield"></i>
                                        <p>Users & Roles</p>
                                    </a>
                                </li>

                            @endif

                            
                            @if(auth()->user()->hasPermission('view_objectives'))
                                <li class="nav-item">
                                    <a href="{{ route('objectives.index') }}"
                                        class="nav-link {{ request()->routeIs('objectives.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-bullseye"></i>
                                        <p>Objectives</p>
                                    </a>
                                </li>
                            @endif

                            @if(auth()->user()->hasPermission('view_plans'))
                                {{-- Plans are now generated automatically when objectives are approved. --}}
                                <li class="nav-item">
                                    @php
                                        $pendingAssignmentsCount = \App\Models\PlanItemAssignment::where('performer_id', auth()->user()->department_id)
                                            ->where('status', 'pending')
                                            ->count();
                                    @endphp
                                    <a href="{{ route('assignments.index') }}"
                                        class="nav-link {{ request()->routeIs('assignments.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-clipboard-check"></i>
                                        <p>
                                            Assigned Objectives
                                            @if($pendingAssignmentsCount > 0)
                                                <span class="badge badge-warning right">{{ $pendingAssignmentsCount }}</span>
                                            @endif
                                        </p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('view_tasks'))
                            <li class="nav-item">
                                <a href="{{ route('tasks.index') }}"
                                    class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
                                    <i class="nav-icon fas fa-tasks"></i>
                                    <p>Tasks</p>
                                </a>
                            </li>
                            @endif

                            @if($navUser->isSystemAdmin() || $navUser->hasPermission('view_scorecard'))
                                <li class="nav-item">
                                    <a href="{{ route('scorecard.leaderboard') }}"
                                        class="nav-link {{ request()->is('scorecard*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-trophy"></i>
                                        <p>Ex. Scorecard</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->isSystemAdmin() || $navUser->hasPermission('manage_quality'))
                                <li class="nav-item">
                                    <a href="{{ route('quality.console') }}"
                                        class="nav-link {{ request()->is('quality*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-shield-alt"></i>
                                        <p>Quality Sentinel</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('view_reports'))
                                <li class="nav-item">
                                    <a href="{{ route('reports.index') }}"
                                        class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-file-contract"></i>
                                        <p>Reports</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('view_performance'))
                                <li class="nav-item">
                                    <a href="{{ route('reports.master') }}"
                                        class="nav-link {{ request()->routeIs('reports.master') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-file-invoice"></i>
                                        <p>Master Report</p>
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a href="{{ route('reports.kpi-dashboard') }}"
                                        class="nav-link {{ request()->routeIs('reports.kpi-dashboard') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-tachometer-alt" style="color:#c8a84b;"></i>
                                        <p>KPI Performance Board</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('view_feedbacks'))
                                <li class="nav-item">
                                    <a href="{{ route('feedbacks.index') }}" class="nav-link {{ request()->routeIs('feedbacks.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-comment-dots" style="color:#0b9386;"></i>
                                        <p>Feedbacks</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('view_performance'))
                                <li class="nav-item">
                                    <a href="{{ route('performance.comparison') }}"
                                        class="nav-link {{ request()->routeIs('performance.comparison') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-exchange-alt" style="color:#37b7a5;"></i>
                                        <p>Performance Comparison</p>
                                    </a>
                                </li>

                                @if(auth()->user()->canReceiveDepartmentPlans() || (auth()->user()->isDepartmentChief() && auth()->user()->department_id))
                                <li class="nav-item">
                                    <a href="{{ route('plan-submissions.index') }}" class="nav-link {{ request()->routeIs('plan-submissions.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-file-upload"></i><p>Department Plan Files</p>
                                    </a>
                                </li>
                            @endif


                                <li class="nav-item">
                                    <a href="{{ route('projects.index') }}"
                                        class="nav-link {{ request()->routeIs('projects.index') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-project-diagram"></i>
                                        <p>Project Registry</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('projects.report') }}"
                                        class="nav-link {{ request()->routeIs('projects.report') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-chart-pie"></i>
                                        <p>Project Report</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('performance.index') }}"
                                        class="nav-link {{ request()->routeIs('performance.index', 'performance.edit', 'performance.report') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-chart-line"></i>
                                        <p>Performance</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('view_audit_logs'))
                                <li class="nav-item">
                                    <a href="{{ route('audit.index') }}"
                                        class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-history"></i>
                                        <p>Audit Logs</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->isSystemAdmin())
                                <li class="nav-header">LOGISTICS &amp; IMPORTS</li>
                                <li class="nav-item">
                                    <a href="{{ route('imports.dashboard') }}"
                                        class="nav-link {{ request()->routeIs('imports.dashboard') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-satellite-dish" style="color:#1565c0;"></i>
                                        <p>Import Control Tower</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('imports.index') }}"
                                        class="nav-link {{ request()->routeIs('imports.*') && !request()->routeIs('imports.dashboard') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-ship" style="color:#e65100;"></i>
                                        <p>Import Jobs</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('trains.index') }}"
                                        class="nav-link {{ request()->routeIs('trains.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-train" style="color:#2ecc71;"></i>
                                        <p>Train Journeys</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->hasPermission('manage_settings'))
                                <li class="nav-item">
                                    <a href="{{ route('settings.index') }}"
                                        class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-cog"></i>
                                        <p>Settings</p>
                                    </a>
                                </li>
                            @endif

                            @if($navUser->isPlanReportHeadOffice())
                                <li class="nav-item">
                                    <a href="{{ route('settings.index') }}#performance-scale"
                                        class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                                        <i class="nav-icon fas fa-traffic-light"></i>
                                        <p>Performance Color Reference</p>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </nav>
                </div>
            </aside>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <!-- Content Header -->
                <div class="content-header">
                    <div class="container-fluid">
                        <div class="row mb-2">
                            <div class="col-sm-6">
                                <h1 class="m-0 text-dark">@yield('title')</h1>
                            </div>
                            <div class="col-sm-6">
                                <ol class="breadcrumb float-sm-right">
                                    @yield('breadcrumb', '')
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <section class="content">
                    <div class="container-fluid">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <i class="icon fas fa-check"></i> {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <i class="icon fas fa-ban"></i> {{ session('error') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <h5><i class="icon fas fa-ban"></i> Validation Error!</h5>
                                <ul>
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @yield('content')
                    </div>
                </section>
            </div>

            <!-- Footer -->
            <footer class="main-footer">
                <strong>IPRPMS v1.0</strong> - Integrated Planning, Reporting & Performance Management System
                <div class="float-right d-none d-sm-inline-block">
                    <b>© {{ date('Y') }} Ethio Djibouti Railway (EDR)</b>
                </div>
            </footer>
        </div>
    @endguest

    <div class="modal fade" id="systemConfirmModal" tabindex="-1" role="dialog" aria-labelledby="systemConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0">
                <div class="d-flex align-items-center"><span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mr-3" style="width:40px;height:40px"><i class="fas fa-question"></i></span><h5 class="modal-title font-weight-bold" id="systemConfirmTitle">Confirm action</h5></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body px-4 py-3"><p id="systemConfirmMessage" class="mb-0 text-dark"></p></div>
            <div class="modal-footer border-0 bg-light"><button type="button" class="btn btn-outline-secondary px-4" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary px-4" id="systemConfirmButton">Confirm</button></div>
        </div></div>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/dist/js/adminlte.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>

    <script>
        window.iprpmsConfirm = function (message, options) {
            options = options || {};
            return new Promise(function (resolve) {
                var $modal = $('#systemConfirmModal'), $button = $('#systemConfirmButton'), settled = false;
                $('#systemConfirmTitle').text(options.title || 'Confirm action');
                $('#systemConfirmMessage').text(message || 'Do you want to continue?');
                $button.text(options.confirmText || 'Confirm').removeClass('btn-primary btn-danger btn-warning btn-success').addClass(options.buttonClass || 'btn-primary');
                $button.off('click.iprpms').on('click.iprpms', function () { settled = true; $modal.modal('hide'); resolve(true); });
                $modal.off('hidden.bs.modal.iprpms').on('hidden.bs.modal.iprpms', function () { if (!settled) resolve(false); }).modal({backdrop: 'static', keyboard: true, show: true});
            });
        };

        $(document).on('submit', 'form[data-confirm]', function (event) {
            var form = this;
            if (form.dataset.confirmed === 'true') { delete form.dataset.confirmed; return; }
            event.preventDefault();
            window.iprpmsConfirm(form.dataset.confirm, {title: form.dataset.confirmTitle, confirmText: form.dataset.confirmText, buttonClass: form.dataset.confirmClass}).then(function (confirmed) {
                if (!confirmed) return;
                form.dataset.confirmed = 'true';
                if (form.requestSubmit) form.requestSubmit(); else form.submit();
            });
        });

        $(document).on('click', 'button[data-confirm], a[data-confirm]', function (event) {
            var element = this;
            if (element.dataset.confirmed === 'true') { delete element.dataset.confirmed; return; }
            event.preventDefault(); event.stopImmediatePropagation();
            window.iprpmsConfirm(element.dataset.confirm, {title: element.dataset.confirmTitle, confirmText: element.dataset.confirmText, buttonClass: element.dataset.confirmClass}).then(function (confirmed) {
                if (!confirmed) return;
                element.dataset.confirmed = 'true';
                if (element.tagName === 'A') window.location.href = element.href;
                else if (element.form && element.form.requestSubmit) element.form.requestSubmit(element);
                else element.click();
            });
        });

        $(document).ready(function () {
            // Initialize Select2
            $('.select2').each(function () {
                const $select = $(this);
                $select.select2({
                    theme: 'bootstrap4',
                    tags: $select.data('tags') === true,
                    width: '100%'
                });
            });

            // Initialize DataTables
            $('.datatable').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
            });

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // Handle URL fragments for tab switching
            function switchTabByHash() {
                var hash = window.location.hash;
                if (hash) {
                    var targetTab = $('.nav-tabs a[href="' + hash + '"], .nav-pills a[href="' + hash + '"]');
                    if (targetTab.length) {
                        targetTab.tab('show');
                    }
                }
            }

            // Run on load
            switchTabByHash();  // Run when hash changes
            $(window).on('hashchange', function () {
                switchTabByHash();
            });

            // Update hash on tab click without page jump
            $('.nav-tabs a, .nav-pills a').on('click', function () {
                var hash = this.hash;
                if (history.pushState) {
                    history.pushState(null, null, hash);
                } else {
                    window.location.hash = hash;
                }
            });
        });
    </script>

    @stack('scripts')
</body>

</html>
