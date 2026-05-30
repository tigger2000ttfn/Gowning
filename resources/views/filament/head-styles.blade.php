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

    /* Back To Login link on reset page - force Title Case appearance */
    .fi-simple-main a[href*="login"] { text-transform: capitalize; }

    /* DASHBOARD: zero the content padding/max-width + kill header gap so hero connects to header & spans full width */
    .fi-page-dashboard .fi-page-content,
    .fi-page-dashboard section.fi-page-content,
    .fi-page-dashboard > section {
        padding: 0 !important;
        max-width: none !important;
        width: 100% !important;
        gap: 0 !important;
    }
    .fi-page-dashboard .fi-header { display: none !important; }
    .fi-page-dashboard .fi-main { max-width: none !important; }

    /* Smaller sidebar font + narrower so it takes less space, esp on small screens */
    .fi-sidebar-nav .fi-sidebar-item-label { font-size: 13px !important; }
    .fi-sidebar-nav .fi-sidebar-group-label { font-size: 11px !important; }
    @media (max-width: 1024px) {
        .fi-sidebar { width: 15rem !important; }
    }
</style>
