{{-- Critical admin overrides injected directly into <head>, after Filament's stylesheet,
     so they win regardless of cascade layers / compiled-CSS quirks. --}}
<style>
    /* Dark charcoal header in BOTH themes */
    .fi-topbar {
        background-color: #1C1C21 !important;
        border-bottom: 2px solid #A4123F !important;
    }
    .fi-topbar > .fi-icon-btn,
    .fi-topbar .fi-icon-btn:not(.fi-dropdown-panel *),
    .fi-topbar .fi-icon-btn:not(.fi-dropdown-panel *) svg,
    .fi-topbar > * .fi-dropdown-trigger,
    .fi-topbar .fi-user-menu-trigger,
    .fi-topbar > nav a,
    .fi-topbar .fi-global-search-field input {
        color: #ECECF0 !important;
    }
    /* but NEVER force light text inside an open dropdown panel */
    .fi-topbar .fi-dropdown-panel a,
    .fi-topbar .fi-dropdown-panel button { color: #1A1A1F !important; }
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

    /* ===== SOLID MAGENTA BUTTONS (no pink shades) - use the sidebar magenta #A4123F ===== */
    .fi-btn.fi-color-primary,
    .fi-btn-color-primary,
    button.fi-btn-color-primary,
    .fi-ac-btn-action.fi-color-primary {
        background-color: #A4123F !important;
        border-color: #A4123F !important;
        color: #fff !important;
        --tw-ring-color: #A4123F !important;
    }
    .fi-btn.fi-color-primary:hover,
    .fi-btn-color-primary:hover,
    button.fi-btn-color-primary:hover {
        background-color: #850F33 !important;
        border-color: #850F33 !important;
    }
    /* primary links/text also magenta not pink */
    .fi-link.fi-color-primary, a.fi-color-primary { color: #A4123F !important; }


    /* ===== KANBAN FULL-BLEED: let board pages bust out of .fi-main padding/max-width
       and scroll the full page width (same technique as the dashboard hero) ===== */
    .fi-main:has(.sb-fullbleed) { padding-left: 0 !important; padding-right: 0 !important; max-width: none !important; }
    .fi-main:has(.sb-fullbleed) .fi-page-content { max-width: none !important; }

    /* ===== FIX CARD-WITHIN-CARD: a Filament Section inside a modal already sits in the
       modal's card, so the section's own border/background creates a nested card. Flatten
       sections when they're inside a modal/slide-over window. ===== */
    .fi-modal-window .fi-section,
    .fi-modal-window .fi-section-content-ctn,
    .fi-modal-window section.fi-section {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        --tw-ring-color: transparent !important;
    }
    .fi-modal-window .fi-section-content { padding: 0 !important; }
    /* keep the section heading (icon+label) but tighten it */
    .fi-modal-window .fi-section-header { padding: 0 0 10px !important; }

    /* ===== GQS SHARED PAGE COMPONENTS (match dashboard look across all pages) ===== */
    .gqs-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:18px; }
    .gqs-stat { position:relative; overflow:hidden; color:#fff; border-radius:14px; padding:18px 18px 16px; box-shadow:0 3px 12px rgba(0,0,0,.12); }
    .gqs-stat .n { font-size:30px; font-weight:800; line-height:1; }
    .gqs-stat .l { font-size:13px; font-weight:600; opacity:.95; margin-top:6px; }
    .gqs-stat .wm { position:absolute; right:-8px; bottom:-10px; width:74px; height:74px; opacity:.16; }
    .gqs-stat .wm svg { width:100%; height:100%; }
    .gqs-stat.red { background:linear-gradient(135deg,#C8102E,#920B22); }
    .gqs-stat.purple { background:linear-gradient(135deg,#6B2C91,#4A1E66); }
    .gqs-stat.green { background:linear-gradient(135deg,#2E7D5B,#1F6147); }
    .gqs-stat.gold { background:linear-gradient(135deg,#C79A2E,#9E7818); }
    .gqs-stat.magenta { background:linear-gradient(135deg,#A4123F,#760D2D); }
    .gqs-stat.charcoal { background:linear-gradient(135deg,#2A2A31,#1C1C21); }

    .gqs-panel { background:var(--gqs-surface,#fff); border:1px solid var(--gqs-border,#DADADF); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .gqs-panel-head { display:flex; align-items:center; gap:9px; padding:13px 16px; font-weight:700; font-size:14px; color:#fff; background:linear-gradient(135deg,#A4123F,#850F33); }
    .gqs-panel-head svg { width:17px; height:17px; }
    .gqs-panel-body { padding:6px 0; }
    .gqs-tbl { width:100%; border-collapse:collapse; font-size:13.5px; }
    .gqs-tbl th { text-align:left; padding:9px 16px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--gqs-text-dim,#6A6A72); border-bottom:1px solid var(--gqs-border,#EEE); }
    .gqs-tbl td { padding:10px 16px; border-bottom:1px solid var(--gqs-border,#F2F2F4); color:var(--gqs-text,#1A1A1F); }
    .gqs-tbl tbody tr:hover { background:rgba(164,18,63,.04); }
    .gqs-tbl tbody tr:last-child td { border-bottom:0; }
    .gqs-pill { display:inline-block; font-size:12px; font-weight:700; padding:3px 11px; border-radius:20px; }
    .gqs-pill-red { background:#FBE3E7; color:#C8102E; }
    .gqs-pill-gold { background:#FBF3DC; color:#8A6D0B; }
    .gqs-pill-green { background:#DDF3E9; color:#1E7A52; }
    .gqs-pill-purple { background:#EFE6F5; color:#6B2C91; }
    .gqs-empty { padding:22px 16px; text-align:center; color:var(--gqs-text-dim,#9A9AA4); font-size:13.5px; }
    html.dark .gqs-tbl td { color:#E4E4E8; }

    /* ===== HIDE EMPTY FILAMENT PAGE HEADER (we use the .pg-head partial instead) =====
       Pages return getHeading('') but Filament still renders an empty .fi-header taking space
       and sometimes a duplicate h1. Hide it on pages that have our own .pg-head. */
    /* Hide Filament's own page header/heading wherever our .pg-head partial is present */
    .fi-page:has(.pg-head) > .fi-header,
    .fi-page:has(.pg-head) .fi-page-header,
    .fi-main:has(.pg-head) > .fi-header,
    .fi-page-content:has(.pg-head) > .fi-header,
    section:has(> .pg-head) > header.fi-header { display: none !important; }
    /* Also kill the top gap the empty header leaves */
    .fi-page:has(.pg-head) .fi-page-header-main-ctn { padding-top: 0 !important; }

    /* ===== TIGHTEN top-of-page wasted space ===== */
    .fi-page-header-main-ctn { padding-top: 12px !important; }
    .fi-main { padding-top: 8px !important; }

    /* ===== LIGHT-THEME MENU/DROPDOWN CONTRAST (comprehensive) =====
       The dark-topbar text rule (.fi-topbar a {color:#ECECF0}) bleeds into menus that
       open from the topbar (Manage, avatar/user menu, theme switcher, notifications),
       making text/icons invisible on the white panel. Force dark text+icons in light theme.
       Scoped to the PANELS (not the topbar triggers) so the dark topbar itself is unaffected. */

    /* Manage dropdown (our custom) */
    html:not(.dark) .gqs-manage-menu { background: #fff !important; }
    html:not(.dark) .gqs-manage-link { color: #1A1A1F !important; }
    html:not(.dark) .gqs-manage-link:hover { background: #F1F1F4 !important; }

    /* Filament dropdown panels: user/avatar menu, notifications, any fi-dropdown */
    html:not(.dark) .fi-dropdown-panel,
    html:not(.dark) .fi-dropdown-list {
        background-color: #fff !important;
    }
    html:not(.dark) .fi-dropdown-panel a,
    html:not(.dark) .fi-dropdown-panel button,
    html:not(.dark) .fi-dropdown-list-item,
    html:not(.dark) .fi-dropdown-list-item-label,
    html:not(.dark) .fi-dropdown-list-item .fi-dropdown-list-item-label,
    html:not(.dark) .fi-dropdown-list a,
    html:not(.dark) .fi-user-menu .fi-dropdown-list-item-label {
        color: #1A1A1F !important;
    }
    /* icons inside light-theme dropdown panels (theme switcher sun/moon, menu icons) */
    html:not(.dark) .fi-dropdown-panel svg,
    html:not(.dark) .fi-dropdown-list-item-icon,
    html:not(.dark) .fi-dropdown-list-item svg {
        color: #5A5A62 !important;
    }
    html:not(.dark) .fi-dropdown-list-item:hover { background-color: #F1F1F4 !important; }

    /* Theme switcher buttons (sun/moon) - they sit in a dropdown panel on white */
    html:not(.dark) .fi-theme-switcher-btn svg { color: #5A5A62 !important; }
    html:not(.dark) .fi-theme-switcher-btn.fi-active svg { color: #A4123F !important; }

    /* Notifications panel text on white */
    html:not(.dark) .fi-no-notification,
    html:not(.dark) .fi-no-notification-title,
    html:not(.dark) .fi-no-notification-body {
        color: #1A1A1F !important;
    }
    html:not(.dark) .fi-no-notification-body { color: #5A5A62 !important; }


    /* Back To Login link on reset page - force Title Case appearance */
    .fi-simple-main a[href*="login"] { text-transform: capitalize; }

    /* DASHBOARD full-bleed: target the ACTUAL wrappers (from DOM inspection) */
    /* .fi-main has 0 32px padding -> the left/right gap */
    .fi-main:has(.dash-hero) { padding-left: 0 !important; padding-right: 0 !important; }
    .fi-page-dashboard .fi-main { padding-left: 0 !important; padding-right: 0 !important; }
    .fi-main:has(.dash-hero) { padding-top: 0 !important; }
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
