{{-- Critical admin overrides injected directly into <head>, after Filament's stylesheet,
     so they win regardless of cascade layers / compiled-CSS quirks. --}}
<style>
    /* Dark charcoal header in BOTH themes */
    .fi-topbar {
        background-color: #1C1C21 !important;
        border-bottom: 2px solid #A4123F !important;
    }
    .fi-topbar .fi-icon-btn,
    .fi-topbar .fi-icon-btn svg,
    .fi-topbar .fi-dropdown-trigger,
    .fi-topbar .fi-user-menu-trigger,
    .fi-topbar a,
    .fi-topbar .fi-global-search-field input {
        color: #ECECF0 !important;
    }
    .fi-topbar .fi-global-search-field input::placeholder { color: #9A9AA4 !important; }
    .fi-topbar .fi-global-search-field {
        background-color: rgba(255,255,255,.07) !important;
        border-radius: 9px;
    }
    .fi-topbar .fi-icon-btn:hover { background-color: rgba(255,255,255,.12) !important; }

    /* subtle star shimmer across the dark header */
    .fi-topbar { position: relative; overflow: hidden; }
    .fi-topbar::before {
        content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background-image:
            radial-gradient(1.5px 1.5px at 12% 40%, rgba(255,255,255,.9), transparent),
            radial-gradient(1px 1px at 28% 70%, rgba(232,194,74,.9), transparent),
            radial-gradient(1.5px 1.5px at 45% 30%, rgba(255,255,255,.8), transparent),
            radial-gradient(1px 1px at 63% 60%, rgba(185,140,224,.9), transparent),
            radial-gradient(1.5px 1.5px at 78% 35%, rgba(255,255,255,.85), transparent),
            radial-gradient(1px 1px at 90% 65%, rgba(232,194,74,.8), transparent);
        animation: gqsHeadTw 4s ease-in-out infinite;
    }
    @keyframes gqsHeadTw { 0%,100%{opacity:.35} 50%{opacity:.9} }
    .fi-topbar > * { position: relative; z-index: 1; }

    /* Back To Login link on reset page - force Title Case appearance */
    .fi-simple-main a[href*="login"] { text-transform: capitalize; }
</style>
