@php($user = filament()->auth()->user())

{{-- Name and role beside the user-menu avatar. Pressing it presses the avatar (the dropdown opens on mousedown);
     .stop keeps the click from reaching the dropdown's click-away listener. The avatar stays the keyboard/screen-reader control. --}}
<button
    type="button"
    tabindex="-1"
    aria-hidden="true"
    class="asset-user-chip"
    x-data
    x-on:mousedown="if ($event.button === 0) $el.parentElement.querySelector('.fi-user-menu .fi-dropdown-trigger')?.dispatchEvent(new MouseEvent('mousedown', { button: 0 }))"
    x-on:click.stop
>
    <span class="asset-user-chip-name">{{ $user->name }}</span>
    @if ($user->role->getLabel() !== $user->name)
        <span class="asset-user-chip-role">{{ $user->role->getLabel() }}</span>
    @endif
</button>
