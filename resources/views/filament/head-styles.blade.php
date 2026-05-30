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
    /* Brand text MUST be #444 - override the topbar 'a' color rule above */
    .fi-topbar a .gqs-brand-text,
    .fi-topbar .gqs-brand-text,
    .gqs-brand-text { color: #444 !important; }

    /* Narrower sidebar (was 320px) + smaller nav font */
    /* Narrow the OPEN sidebar via Filament's width variable only.
       Do NOT force width on .fi-sidebar itself - that blocks the collapsed (icon-only) state. */
    .fi-sidebar.fi-sidebar-open { --sidebar-width: 14rem; }
    .fi-main-ctn-sidebar-open { --sidebar-width: 14rem; }
    :root { --sidebar-width: 14rem; }
    .fi-sidebar-item-label { font-size: 12.5px !important; }
    .fi-sidebar-group-label { font-size: 10.5px !important; letter-spacing: .04em; }
    .fi-sidebar-item-icon, .fi-sidebar-item-icon svg { width: 18px !important; height: 18px !important; }

    /* Manage dropdown links: dark text in light theme (the --gqs-text var resolves too light) */
    html:not(.dark) .gqs-manage-link { color: #1A1A1F !important; }
    html:not(.dark) .gqs-manage-menu { background: #fff !important; }
    html:not(.dark) .gqs-manage-link:hover { background: #F1F1F4 !important; }

    /* Dropdown menu items: readable dark text on white (light theme) */
    html:not(.dark) .fi-dropdown-list-item,
    html:not(.dark) .fi-dropdown-list-item-label,
    html:not(.dark) .fi-dropdown-list a,
    html:not(.dark) .fi-dropdown-panel .fi-dropdown-list-item-label {
        color: #1A1A1F !important;
    }
    html:not(.dark) .fi-dropdown-list-item:hover { background-color: #F1F1F4 !important; }


    /* Back To Login link on reset page - force Title Case appearance */
    .fi-simple-main a[href*="login"] { text-transform: capitalize; }

    /* DASHBOARD full-bleed: target the ACTUAL wrappers (from DOM inspection) */
    /* .fi-main has 0 32px padding -> the left/right gap */
    .fi-main:has(.dash-hero) { padding-left: 0 !important; padding-right: 0 !important; }
    .fi-page-dashboard .fi-main { padding-left: 0 !important; padding-right: 0 !important; }
    /* .fi-page-header-main-ctn has 32px top padding -> the gap above the hero */
    .fi-main:has(.dash-hero) .fi-page-header-main-ctn { padding-top: 0 !important; }
    .fi-page-dashboard .fi-page-header-main-ctn { padding-top: 0 !important; }
    /* re-pad the non-hero content so it isn't edge-to-edge (hero handles its own full width) */
    .dash-pad { padding: 0 32px !important; }
    @media (max-width: 640px){ .dash-pad { padding: 0 16px !important; } }
    /* Hero stays full-bleed (.fi-page-content padding:0). Pad ONLY the chart-widget grid,
       not the shared .fi-page-content (which would pad the hero too). */
    .fi-main:has(.dash-hero) .fi-page-content { padding: 0 !important; }
    /* Pad the chart widgets specifically (.fi-wi-widget = chart wrapper; hero is not a widget) */
    .fi-main:has(.dash-hero) .fi-wi-widget { margin: 0 32px !important; }
    .fi-main:has(.dash-hero) .fi-page-footer-widgets,
    .fi-main:has(.dash-hero) section.fi-section { scroll-margin: 0; }
    /* top space above the whole widget row */
    .fi-main:has(.dash-hero) .fi-page-content > .fi-sc:last-child,
    .fi-main:has(.dash-hero) .fi-page-content > div:last-child { padding-top: 24px !important; }
    @media (max-width: 640px){
        .fi-main:has(.dash-hero) .fi-wi-widget { margin: 0 16px !important; }
    }

    /* Smaller sidebar font + narrower so it takes less space, esp on small screens */
    .fi-sidebar-nav .fi-sidebar-item-label { font-size: 13px !important; }
    .fi-sidebar-nav .fi-sidebar-group-label { font-size: 11px !important; }
    @media (max-width: 1024px) {
        .fi-sidebar { width: 15rem !important; }
    }
</style>
