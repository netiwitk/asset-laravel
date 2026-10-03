@php($user = filament()->auth()->user())

<span class="asset-menu-who">
    <x-filament-panels::avatar.user :user="$user" size="lg" />
    <span class="asset-menu-who-text">
        <span class="asset-menu-who-name">{{ $user->name }}</span>
        <span class="asset-menu-who-meta">{{ $user->email }}</span>
        @if ($user->department)
            <span class="asset-menu-who-meta">{{ $user->department->name }}</span>
        @endif
    </span>
</span>
