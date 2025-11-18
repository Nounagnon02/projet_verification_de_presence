<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12">
        <div class="flex justify-between h-16 md:h-20 lg:h-24">
            <div class="flex items-center">
                <!-- Logo/Brand -->
                <div class="flex-shrink-0">
                    <a href="{{ route('dashboard') }}" class="flex items-center">
                        <div class="w-8 h-8 md:w-10 md:h-10 bg-gray-800 rounded-lg flex items-center justify-center mr-2 md:mr-3">
                            <svg class="w-4 h-4 md:w-5 md:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="text-lg md:text-xl lg:text-2xl font-bold text-gray-900 hidden sm:block">Présence</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex md:space-x-4 lg:space-x-8 md:ml-8 lg:ml-12">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="text-sm md:text-base lg:text-lg px-3 py-2">
                        {{ __('Ajouter') }}
                    </x-nav-link>
                    <x-nav-link :href="route('dashboardV')" :active="request()->routeIs('dashboardV')" class="text-sm md:text-base lg:text-lg px-3 py-2">
                        {{ __('Vérifier') }}
                    </x-nav-link>
                    <x-nav-link :href="route('membres')" :active="request()->routeIs('membres*')" class="text-sm md:text-base lg:text-lg px-3 py-2">
                        {{ __('Membres') }}
                    </x-nav-link>
                    <x-nav-link :href="route('statistiques')" :active="request()->routeIs('statistiques')" class="text-sm md:text-base lg:text-lg px-3 py-2">
                        {{ __('Statistiques') }}
                    </x-nav-link>
                    <x-nav-link :href="route('statistiques.avancees')" :active="request()->routeIs('statistiques.avancees')" class="text-sm md:text-base lg:text-lg px-3 py-2">
                        {{ __('Analytics') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:ml-6">
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-4 py-2 border border-transparent text-sm md:text-base leading-4 font-medium rounded-lg text-gray-700 bg-gray-50 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200">
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-2">
                                <span class="text-sm font-semibold text-gray-700">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>
                            <div class="hidden lg:block">{{ Auth::user()->name }}</div>
                            <div class="ml-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                        </div>
                        <x-dropdown-link :href="route('profile.edit')" class="flex items-center px-4 py-3">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            {{ __('Profil') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                {{ __('Déconnexion') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="flex items-center md:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-3 rounded-lg text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-200">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden md:hidden bg-white border-t border-gray-100">
        <div class="pt-4 pb-3 space-y-2 px-4">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="flex items-center py-3 px-4 rounded-lg">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('Ajouter un membre') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('dashboardV')" :active="request()->routeIs('dashboardV')" class="flex items-center py-3 px-4 rounded-lg">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ __('Vérifier la présence') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('membres')" :active="request()->routeIs('membres*')" class="flex items-center py-3 px-4 rounded-lg">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                {{ __('Liste des membres') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('statistiques')" :active="request()->routeIs('statistiques')" class="flex items-center py-3 px-4 rounded-lg">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                {{ __('Statistiques') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('statistiques.avancees')" :active="request()->routeIs('statistiques.avancees')" class="flex items-center py-3 px-4 rounded-lg">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                {{ __('Analytics') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-4 border-t border-gray-200 bg-gray-50">
            <div class="px-4 mb-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                        <span class="text-lg font-semibold text-gray-700">{{ substr(Auth::user()->name, 0, 1) }}</span>
                    </div>
                    <div>
                        <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                    </div>
                </div>
            </div>

            <div class="space-y-2 px-4">
                <x-responsive-nav-link :href="route('profile.edit')" class="flex items-center py-3 px-4 rounded-lg">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    {{ __('Profil') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" class="flex items-center py-3 px-4 rounded-lg text-red-600"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        {{ __('Déconnexion') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
