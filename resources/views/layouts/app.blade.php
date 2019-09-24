<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'GTrader') }}</title>

    <!-- Styles -->
    @yield('stylesheets')
    <link href="/css/app.css" rel="stylesheet">

    <!-- Scripts -->
    <script>
        window.Laravel = {!! json_encode([
            'csrfToken' => csrf_token(),
        ]) !!};
    </script>
    <script src="{{ mix('/js/app.js') }}"></script>
    @yield('scripts_top')
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-default navbar-static-top">
            <div class="container-fluid">

                <div class="navbar-header">

                    <!-- Collapsed Hamburger -->
                    <button type="button"
                            class="navbar-toggle collapsed"
                            data-toggle="collapse"
                            data-target="#app-navbar-collapse">
                        <span class="sr-only">Toggle Navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>

                </div>

                <div class="collapse navbar-collapse" id="app-navbar-collapse">
                    <!-- Left Side Of Navbar -->

                    <!-- Nav tabs -->
                    <ul class="nav navbar-nav nav-tabs" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#chartTab"
                                aria-controls="chartTab"
                                role="tab"
                                data-toggle="tab">Chart</a>
                        </li>
                        <li role="presentation">
                            <a href="#strategyTab"
                                aria-controls="strategyTab"
                                role="tab"
                                data-toggle="tab">Strategies</a>
                        </li>
                        <li role="presentation">
                            <a href="#settingsTab"
                                aria-controls="settingsTab"
                                role="tab"
                                data-toggle="tab">Settings</a>
                        </li>
                        <li role="presentation">
                            <a href="#botTab"
                                aria-controls="botTab"
                                role="tab"
                                data-toggle="tab">Bots</a>
                        </li>
                        @env('local')
                        <li role="presentation">
                            <a href="#devTab"
                                aria-controls="devTab"
                                role="tab"
                                data-toggle="tab">Development Tools</a>
                        </li>
                        @endenv
                    </ul>



                    <!-- Right Side Of Navbar -->
                    <ul class="nav navbar-nav navbar-right">
                        <!-- Authentication Links -->
                        @if (Auth::guest())
                            <li><a href="{{ url('/login') }}">Login</a></li>
                            <li><a href="{{ url('/register') }}">Register</a></li>
                        @else
                            <li class="dropdown">
                                <a href="#"
                                    class="dropdown-toggle"
                                    data-toggle="dropdown"
                                    role="button"
                                    aria-expanded="false">
                                    {{ Auth::user()->name }} <span class="caret"></span>
                                </a>

                                <ul class="dropdown-menu" role="menu">
                                    <li>
                                        <a href="#"
                                            onClick="return window.GTrader.request(
                                                'password', 'change', null, 'GET', 'settings_content'
                                            )"
                                            data-toggle="modal"
                                            data-target=".bs-modal-lg">
                                            Change Password
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/logout') }}"
                                            onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                            Logout
                                        </a>

                                        <form id="logout-form"
                                                action="{{ url('/logout') }}"
                                                method="POST"
                                                style="display: none;">
                                            {{ csrf_field() }}
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>

        @yield('content')
    </div>

    <!-- Scripts -->
    @yield('scripts_bottom')
</body>
</html>
