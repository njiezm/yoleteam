@props(['title', 'crumb' => null, 'back' => null])
@php
    $user = auth()->user();
    $nav = [
        ['dashboard', 'home', 'Tableau de bord', 'dashboard'],
        ['outings.index', 'calendar', 'Sorties', 'outings.*'],
        ['attendance.today', 'check-square', 'Présences', ['attendance.*']],
        ['crew-plans.today', 'boat', 'Plan d’équipage', ['crew-plans.*']],
        ['members.index', 'users', 'Membres', 'members.*'],
        ['boats.index', 'boat', 'Yoles', 'boats.*'],
        ['statistics.index', 'chart', 'Statistiques', 'statistics.*'],
    ];
    $nav = array_values(array_filter($nav, fn ($item) => \Illuminate\Support\Facades\Route::has($item[0])));
    $mobileNav = [
        ['dashboard', 'home', 'Accueil', ['dashboard']],
        ['outings.index', 'calendar', 'Sorties', ['outings.*']],
        ['attendance.today', 'check-square', 'Présences', ['attendance.*']],
        ['crew-plans.today', 'boat', 'Équipage', ['crew-plans.*']],
        ['more', 'more', 'Plus', ['more', 'members.*', 'boats.*', 'statistics.*', 'settings.*', 'sync.*', 'profile.*', 'super-admin.*']],
    ];
    $initials = collect(explode(' ', $user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B2545">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest?v=2">
    <link rel="icon" href="/icons/icon.svg?v=2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.png?v=2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ $title }} — YoleTeam</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-navy-950" data-user-id="{{ $user->id }}" data-sync-url="{{ route('sync.store') }}" data-sync-token-url="{{ route('sync.token') }}" data-sync-page-url="{{ route('sync.index') }}">
<div class="min-h-screen lg:flex">
    <aside class="no-print hidden lg:block w-[264px] shrink-0 bg-navy-900 text-white">
        <div class="sticky top-0 h-screen flex flex-col p-4">
            <div class="px-2 py-2"><x-logo :association="$user->association" /></div>
            <nav class="mt-6 space-y-1 flex-1">
                @foreach ($nav as [$route, $icon, $label, $pattern])
                    <a href="{{ route($route) }}" @class(['nav-link', 'on' => request()->routeIs($pattern)])><x-icon :name="$icon" class="w-[18px] h-[18px]" />{{ $label }}</a>
                @endforeach
                <div class="h-px bg-white/10 my-4"></div>
                <a href="{{ route('sync.index') }}" @class(['nav-link', 'on' => request()->routeIs('sync.*')])><x-icon name="refresh" class="w-[18px] h-[18px]" />Synchronisation<span class="ml-auto hidden chip bg-amber-400 text-navy-950 px-2 py-0.5" data-sync-count></span></a>
                @can('manage')
                    <a href="{{ route('settings.edit') }}" @class(['nav-link', 'on' => request()->routeIs('settings.*')])><x-icon name="settings" class="w-[18px] h-[18px]" />Paramètres</a>
                @endcan
                @can('super-admin')
                    <a href="{{ route('super-admin.dashboard') }}" @class(['nav-link', 'on' => request()->routeIs('super-admin.*')])><x-icon name="lock" class="w-[18px] h-[18px]" />Super admin</a>
                @endcan
            </nav>
            <div class="rounded-2xl bg-white/5 p-3 flex items-center gap-3">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-90" title="Mon profil">
                    <span class="w-10 h-10 shrink-0 rounded-full bg-sun-400 text-navy-950 grid place-items-center font-bold">{{ $initials }}</span>
                    <div class="text-sm leading-tight flex-1 min-w-0"><p class="font-semibold truncate">{{ $user->name }}</p><p class="text-navy-200 text-xs">{{ $user->role->label() }}</p></div>
                </a>
                <form method="POST" action="{{ route('logout') }}" data-logout>
                    @csrf
                    <button class="text-navy-200 hover:text-white cursor-pointer" title="Déconnexion"><x-icon name="logout" class="w-[18px] h-[18px]" /></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
        <div class="no-print hidden bg-amber-400 text-navy-950 text-[13px] font-semibold px-4 py-2 items-center justify-center gap-2 text-center" data-offline-banner>
            <x-icon name="wifi-off" class="w-4 h-4" />Mode hors ligne — l’appel et le plan d’équipage sont enregistrés sur cet appareil et seront synchronisés au retour du réseau.
        </div>
        <header class="no-print sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200/80">
            <div class="h-16 px-4 lg:px-8 flex items-center gap-3">
                @if ($back)
                    <a href="{{ $back }}" class="lg:hidden -ml-1 w-10 h-10 grid place-items-center rounded-xl hover:bg-slate-100" aria-label="Retour"><x-icon name="left" /></a>
                @else
                    <span class="lg:hidden w-9 h-9 rounded-xl bg-navy-900 text-sun-400 grid place-items-center"><x-icon name="boat" class="w-5 h-5" /></span>
                @endif
                <div class="min-w-0 flex-1">
                    @if ($crumb)
                        <p class="hidden lg:block text-xs muted font-medium">{{ $crumb }}</p>
                    @endif
                    <h1 class="font-extrabold tracking-tight text-navy-950 truncate text-[17px] lg:text-xl">{{ $title }}</h1>
                </div>
                <form action="{{ route('members.index') }}" class="hidden md:block w-72">
                    <label class="relative block"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span><input name="q" value="{{ request()->routeIs('members.index') ? request('q') : '' }}" class="input h-10 pl-9 bg-slate-50" placeholder="Rechercher un membre…"></label>
                </form>
                <a href="{{ route('sync.index') }}" class="chip h-8 px-3 bg-emerald-50 text-emerald-700" data-sync-badge title="Synchronisation"><x-icon name="cloud-check" class="w-4 h-4" /><span class="hidden sm:inline" data-sync-badge-text>Synchronisé</span></a>
                @isset($actions)
                    <div class="hidden lg:flex items-center gap-2">{{ $actions }}</div>
                @endisset
            </div>
        </header>

        <main @class(['flex-1 px-4 lg:px-8 py-5 lg:py-7 lg:pb-10', 'pb-44' => isset($sticky), 'pb-28' => ! isset($sticky)])>
            {{ $slot }}
        </main>

        @isset($sticky)
            <div class="no-print lg:hidden fixed inset-x-0 bottom-[68px] z-30 px-4 pb-3 pt-3 bg-gradient-to-t from-sea-50 via-sea-50 to-transparent">{{ $sticky }}</div>
        @endisset

        <nav class="no-print lg:hidden fixed inset-x-0 bottom-0 z-40 bg-white border-t border-slate-200 safe-b">
            <div class="grid grid-cols-5 h-[60px]">
                @foreach ($mobileNav as [$route, $icon, $label, $patterns])
                    @php($on = request()->routeIs(...$patterns))
                    <a href="{{ route($route) }}" @class(['flex flex-col items-center justify-center gap-0.5 text-[11px] font-semibold', 'text-navy-900' => $on, 'text-slate-400' => ! $on])>
                        <span @class(['w-12 h-7 rounded-full grid place-items-center', 'bg-sun-100 text-navy-900' => $on])><x-icon :name="$icon" class="w-5 h-5" /></span>{{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</div>
<x-flash />
@stack('modals')
</body>
</html>
