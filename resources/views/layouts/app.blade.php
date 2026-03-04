<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lung AI Platform')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('head')
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg fixed-top app-topbar" aria-label="Top navigation">
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex align-items-center gap-2 gap-lg-3 me-3">
            <button class="btn btn-outline-primary btn-sm app-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-expanded="true" aria-label="Toggle sidebar">
                <span class="fw-bold">☰</span>
            </button>
            <a class="navbar-brand app-brand" href="{{ route('predictions.index') }}">
                <span class="app-brand-title">LungCare AI</span>
                <span class="app-brand-subtitle">Clinical Decision Support</span>
            </a>
        </div>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#appTopNav" aria-controls="appTopNav" aria-expanded="false" aria-label="Toggle top navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="appTopNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 app-top-links">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('predictions.index') ? 'active' : '' }}" href="{{ route('predictions.index') }}">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('predictions.statistics') ? 'active' : '' }}" href="{{ route('predictions.statistics') }}">Analytics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('predictions.comparison') ? 'active' : '' }}" href="{{ route('predictions.comparison') }}">Models</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 gap-lg-3 app-top-actions">
                <span class="badge rounded-pill text-bg-light border text-secondary px-3 py-2 d-none d-lg-inline">Production</span>
                @auth
                    <a href="{{ route('scans.create') }}" class="btn btn-primary btn-sm px-3">New Upload</a>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm rounded-circle d-inline-flex align-items-center justify-content-center profile-menu-btn"
                                type="button"
                                id="profileDropdown"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="Open profile menu">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5 6a5 5 0 1 1 10 0H3Z"/>
                            </svg>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><h6 class="dropdown-header">{{ auth()->user()->name }}</h6></li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm px-3">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm px-3">Register</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<div class="d-flex" style="padding-top: 56px; min-height: 100vh;">
    <div class="collapse collapse-horizontal show app-sidebar-shell position-fixed top-0 start-0" id="appSidebar" style="margin-top: 56px; height: calc(100vh - 56px); z-index: 1020;">
        <aside class="d-flex flex-column overflow-auto app-sidebar" style="width: 260px; height: calc(100vh - 56px);">
            <div class="app-sidebar-header px-3 py-3 border-bottom">
                <h2 class="h6 text-uppercase mb-1">Workspace</h2>
                <p class="text-muted small mb-0">Clinical Navigation</p>
            </div>

            <div class="p-3">
                <nav class="nav flex-column app-sidebar-nav" aria-label="Sidebar navigation">
                    <a class="nav-link {{ request()->routeIs('predictions.index') ? 'active' : '' }}" href="{{ route('predictions.index') }}">Prediction Dashboard</a>
                    <a class="nav-link {{ request()->routeIs('predictions.comparison') ? 'active' : '' }}" href="{{ route('predictions.comparison') }}">Model Comparison</a>
                    <a class="nav-link {{ request()->routeIs('predictions.audit') ? 'active' : '' }}" href="{{ route('predictions.audit') }}">FP/FN Audit</a>
                    <a class="nav-link {{ request()->routeIs('predictions.statistics') ? 'active' : '' }}" href="{{ route('predictions.statistics') }}">Statistics</a>
                    <a class="nav-link {{ request()->routeIs('predictions.two-pass') ? 'active' : '' }}" href="{{ route('predictions.two-pass') }}">Two-Pass Review</a>
                    <a class="nav-link {{ request()->routeIs('scans.create') ? 'active' : '' }}" href="{{ route('scans.create') }}">Upload Scan</a>
                    <a class="nav-link {{ request()->routeIs('patients.index') || request()->routeIs('patients.history') ? 'active' : '' }}" href="{{ route('patients.index') }}">Patient History</a>

                    @if (isset($prediction) && $prediction->scan?->patient)
                        <a class="nav-link {{ request()->routeIs('patients.history') ? 'active' : '' }}" href="{{ route('patients.history', $prediction->scan->patient) }}">Current Patient History</a>
                    @endif
                    @if (isset($patient))
                        <a class="nav-link {{ request()->routeIs('patients.history') ? 'active' : '' }}" href="{{ route('patients.history', $patient) }}">Current Patient History</a>
                    @endif
                </nav>

                @if (isset($prediction))
                    <div class="app-sidebar-context mt-4">
                        <div class="app-sidebar-section-title">Current Case</div>
                        <nav class="nav flex-column app-sidebar-nav" aria-label="Current case navigation">
                            <a class="nav-link {{ request()->routeIs('predictions.show') ? 'active' : '' }}" href="{{ route('predictions.show', $prediction) }}">Prediction Detail</a>
                            <a class="nav-link {{ request()->routeIs('predictions.report') ? 'active' : '' }}" href="{{ route('predictions.report', $prediction) }}">Diagnostic Report</a>
                        </nav>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <main class="flex-grow-1 p-4 p-lg-5 app-main-content">
        @yield('content')
    </main>
</div>

<style>
    .app-topbar {
        background: #ffffff;
        border-bottom: 1px solid #dee2e6;
        min-height: 64px;
        box-shadow: 0 0.125rem 0.75rem rgba(15, 23, 42, 0.06);
        z-index: 1040;
    }

    .app-brand {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
        text-decoration: none;
    }

    .app-brand-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .app-brand-subtitle {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
    }

    .app-menu-toggle {
        border-color: #ced4da;
        color: #334155;
        background: #f8fafc;
    }

    .app-menu-toggle:hover {
        background: #eef2f7;
        border-color: #cbd5e1;
        color: #1e293b;
    }

    .app-top-links .nav-link {
        color: #475569;
        font-weight: 500;
        padding: 0.5rem 0.85rem;
        border-radius: 0.45rem;
    }

    .app-top-links .nav-link:hover,
    .app-top-links .nav-link.active {
        color: #0f172a;
        background: #eef2ff;
    }

    .app-top-actions .btn {
        font-weight: 600;
    }

    .profile-menu-btn {
        width: 34px;
        height: 34px;
        padding: 0;
    }

    .app-sidebar-shell {
        background: #ffffff;
        border-right: 1px solid #dee2e6;
        box-shadow: 0.125rem 0 0.75rem rgba(15, 23, 42, 0.04);
    }

    .app-sidebar {
        background: #ffffff;
    }

    .app-sidebar-header h2 {
        color: #0f172a;
        font-weight: 700;
        letter-spacing: 0.03em;
    }

    .app-sidebar-nav {
        gap: 0.25rem;
    }

    .app-sidebar-nav .nav-link {
        color: #334155;
        border-radius: 0.5rem;
        font-weight: 500;
        padding: 0.55rem 0.75rem;
        border: 1px solid transparent;
        transition: all 0.15s ease-in-out;
    }

    .app-sidebar-nav .nav-link:hover {
        color: #0f172a;
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    .app-sidebar-nav .nav-link.active {
        color: #1d4ed8;
        background: #eff6ff;
        border-color: #bfdbfe;
        font-weight: 600;
    }

    .app-sidebar-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.5rem;
        padding-left: 0.15rem;
    }

    .app-sidebar-context {
        border-top: 1px solid #e2e8f0;
        padding-top: 1rem;
    }

    .app-main-content {
        margin-left: 260px;
    }

    #appSidebar.collapsing,
    #appSidebar:not(.show) {
        width: 0;
    }

    #appSidebar:not(.show) + .app-main-content {
        margin-left: 0 !important;
    }

    @media (max-width: 991.98px) {
        .app-topbar {
            min-height: 56px;
        }

        .app-brand-subtitle {
            display: none;
        }

        .app-main-content {
            margin-left: 0;
        }

        #appSidebar {
            width: 260px;
            box-shadow: 0.25rem 0 1rem rgba(15, 23, 42, 0.12);
        }

        #appSidebar.show + .app-main-content {
            margin-left: 0;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
