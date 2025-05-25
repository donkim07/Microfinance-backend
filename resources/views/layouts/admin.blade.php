<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'e-MKOPO') }} - @yield('title')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    
    <!-- Theme CSS (Light/Dark) -->
    <link id="theme-style" href="{{ asset('css/theme-light.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="antialiased" data-theme="{{ session('theme', 'light') }}">
    <div class="admin-container">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>e-MKOPO</h3>
            </div>

            <ul class="list-unstyled components">
                <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <a href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> {{ __('app.dashboard') }}
                    </a>
                </li>
                
                @can('view-loan-applications')
                <li class="{{ request()->routeIs('admin.loans*') ? 'active' : '' }}">
                    <a href="#loansSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                        <i class="bi bi-cash-coin"></i> {{ __('app.loans') }}
                    </a>
                    <ul class="collapse list-unstyled {{ request()->routeIs('admin.loans*') ? 'show' : '' }}" id="loansSubmenu">
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.view') }} {{ __('app.loans') }}</a>
                        </li>
                        @can('create-loan-applications')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.create') }} {{ __('app.loan_application') }}</a>
                        </li>
                        @endcan
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.reports') }}</a>
                        </li>
                    </ul>
                </li>
                @endcan
                
                @can('view-employees')
                <li class="{{ request()->routeIs('admin.employees*') ? 'active' : '' }}">
                    <a href="#employeesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                        <i class="bi bi-people-fill"></i> {{ __('app.employees') }}
                    </a>
                    <ul class="collapse list-unstyled {{ request()->routeIs('admin.employees*') ? 'show' : '' }}" id="employeesSubmenu">
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.view') }} {{ __('app.employees') }}</a>
                        </li>
                        @can('create-employees')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.create') }} {{ __('app.employee_details') }}</a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                
                @can('view-fsps')
                <li class="{{ request()->routeIs('admin.fsps*') ? 'active' : '' }}">
                    <a href="#fspsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                        <i class="bi bi-bank"></i> {{ __('app.fsp') }}
                    </a>
                    <ul class="collapse list-unstyled {{ request()->routeIs('admin.fsps*') ? 'show' : '' }}" id="fspsSubmenu">
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.view') }} {{ __('app.fsp') }}</a>
                        </li>
                        @can('create-fsps')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.create') }} {{ __('app.fsp') }}</a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                
                @can('view-loan-products')
                <li class="{{ request()->routeIs('admin.products*') ? 'active' : '' }}">
                    <a href="#productsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                        <i class="bi bi-box-seam"></i> {{ __('app.products') }}
                    </a>
                    <ul class="collapse list-unstyled {{ request()->routeIs('admin.products*') ? 'show' : '' }}" id="productsSubmenu">
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.view') }} {{ __('app.products') }}</a>
                        </li>
                        @can('create-loan-products')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.create') }} {{ __('app.product_details') }}</a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                
                @can('view-users')
                <li class="{{ request()->routeIs('admin.users*') || request()->routeIs('admin.roles*') ? 'active' : '' }}">
                    <a href="#usersSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                        <i class="bi bi-person-badge"></i> {{ __('app.users') }}
                    </a>
                    <ul class="collapse list-unstyled {{ request()->routeIs('admin.users*') || request()->routeIs('admin.roles*') ? 'show' : '' }}" id="usersSubmenu">
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.view') }} {{ __('app.users') }}</a>
                        </li>
                        @can('create-users')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.create') }} {{ __('app.users') }}</a>
                        </li>
                        @endcan
                        @can('view-roles')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">{{ __('app.roles') }}</a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                
                @can('manage-settings')
                <li class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                    <a href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-gear-fill"></i> {{ __('app.settings') }}
                    </a>
                </li>
                @endcan
            </ul>
        </nav>

        <!-- Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto">
                            <!-- Language Dropdown -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-globe"></i> {{ __('app.language') }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('language.switch', 'en') }}">
                                            {{ __('app.en') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('language.switch', 'sw') }}">
                                            {{ __('app.sw') }}
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            
                            <!-- Theme Toggle -->
                            <li class="nav-item">
                                <a class="nav-link" href="#" id="themeToggle">
                                    <i class="bi bi-circle-half"></i> {{ __('app.theme') }}
                                </a>
                            </li>
                            
                            <!-- User Dropdown -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-person-circle"></i> {{ Auth::user()->name ?? 'User' }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                            <i class="bi bi-person-fill"></i> {{ __('app.profile') }}
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="bi bi-box-arrow-right"></i> {{ __('app.logout') }}
                                        </a>
                                    </li>
                                    <form id="logout-form" action="{{ route('admin.dashboard') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="py-4">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Theme Switching Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const themeStyle = document.getElementById('theme-style');
            const body = document.body;
            
            themeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get current theme
                const currentTheme = body.getAttribute('data-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                // Set new theme
                body.setAttribute('data-theme', newTheme);
                themeStyle.href = "{{ asset('css/theme-') }}" + newTheme + ".css";
                
                // Store theme preference
                fetch('{{ route("admin.dashboard") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ theme: newTheme })
                });
            });
            
            // Sidebar toggle functionality
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const sidebar = document.getElementById('sidebar');
            
            sidebarCollapse.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        });
    </script>

    @stack('scripts')
</body>
</html> 