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
    /* the page-hero must keep its side padding + top breathing room even on full-bleed kanban pages */
    .fi-main:has(.sb-fullbleed) .pg-head { padding: 24px 32px 16px !important; margin-bottom: 14px !important; }

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
    /* Polished section look inside modals: a charcoal header bar per section, clean spacing,
       no nested card. This is the canonical GQS modal/form look. The background selector is
       deliberately high-specificity (3 classes) so it beats Filament's own section rules;
       a 2-class selector lost the cascade and left white text on a white bar. */
    .fi-modal-window .fi-section .fi-section-header,
    .fi-modal-window section.fi-section > .fi-section-header,
    .fi-modal-window .fi-section-header {
        padding: 9px 14px !important;
        margin: 0 0 14px !important;
        background: #26262C !important;
        border-radius: 9px !important;
    }
    .fi-modal-window .fi-section-header * { color: #fff !important; }
    .fi-modal-window .fi-section-header .fi-section-header-heading,
    .fi-modal-window .fi-section-header-heading {
        color: #fff !important;
        font-weight: 700 !important;
        font-size: 13.5px !important;
        letter-spacing: .01em !important;
    }
    .fi-modal-window .fi-section-header .fi-icon,
    .fi-modal-window .fi-section-header svg { color: #E8C24A !important; }
    .fi-modal-window .fi-section-header-description { color: #C9C9D2 !important; font-size: 12px !important; }
    .fi-modal-window .fi-section + .fi-section { margin-top: 18px !important; }
    /* generous breathing room between fields in the modal */
    .fi-modal-window .fi-fieldset, .fi-modal-window .fi-fo-field-wrp { margin-bottom: 2px; }

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

    /* Attendance / roster marking rows (shared by Class Scheduler + Run Day Roster) */
    .att-list{display:flex;flex-direction:column;gap:8px;}
    .att-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:11px 14px;border:1px solid var(--gqs-border,#E6E6EA);border-radius:10px;background:var(--gqs-surface,#fff);}
    .dark .att-row{background:#23232B;border-color:#34343E;}
    .att-who{flex:1;min-width:200px;}
    .att-name{font-weight:700;font-size:13.5px;color:var(--gqs-text,#1A1A1F);}
    .dark .att-name{color:#ECECF0;}
    .att-eid{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:3px;}
    .att-toggles{display:flex;gap:6px;flex-wrap:wrap;}
    .att-tog{font-size:12.5px;font-weight:700;padding:7px 14px;border-radius:8px;border:1.5px solid var(--gqs-border,#D6D6DC);background:transparent;color:var(--gqs-text-dim,#6A6A72);cursor:pointer;}
    .att-tog.att-att.on{background:#2E7D5B;border-color:#2E7D5B;color:#fff;}
    .att-tog.att-no.on{background:#C8102E;border-color:#C8102E;color:#fff;}
    .att-tog.att-att:hover{border-color:#2E7D5B;color:#2E7D5B;}
    .att-tog.att-no:hover{border-color:#C8102E;color:#C8102E;}
    .att-tog.att-att.on:hover,.att-tog.att-no.on:hover{color:#fff;}
    .att-tog.att-res{border-style:dashed;}
    .att-tog.att-res:hover{border-color:#A4123F;color:#A4123F;}
    /* Dark-theme: make the attendance card and its controls stand out from the page */
    .dark .att-row{background:#2A2A33;border-color:#43434F;box-shadow:0 1px 3px rgba(0,0,0,.35);}
    .dark .att-tog{border-color:#54545F;color:#D2D2DA;}
    .dark .att-eid{color:#A6A6B0;}
    /* EM- worklist field: fixed prefix, user types only the number */
    .att-wl-wrap{display:flex;align-items:stretch;min-width:170px;}
    .att-wl-px{display:flex;align-items:center;padding:0 9px;font-size:12.5px;font-weight:700;color:var(--gqs-text-dim,#6A6A72);background:var(--gqs-surface-2,#F1F1F4);border:1px solid var(--gqs-border,#D6D6DC);border-right:none;border-radius:8px 0 0 8px;}
    .dark .att-wl-px{background:#1A1A20;border-color:#43434F;color:#B8B8C2;}
    .att-wl{flex:1;min-width:90px;padding:7px 11px;border:1px solid var(--gqs-border,#D6D6DC);border-radius:0 8px 8px 0;font-size:12.5px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);}
    .dark .att-wl{background:#1A1A20;border-color:#43434F;color:#ECECF0;}
    /* Run progress pips (●●○ for a 3-run cycle) */
    .run-pips{display:inline-flex;gap:4px;align-items:center;}
    .run-pip{width:9px;height:9px;border-radius:50%;border:1.5px solid var(--gqs-text-dim,#9A9AA4);box-sizing:border-box;}
    .run-pip.done{background:#2E7D5B;border-color:#2E7D5B;}
    .run-pip.cur{border-color:#A4123F;box-shadow:0 0 0 2px rgba(164,18,63,.18);}
    .dark .run-pip{border-color:#6A6A74;}
    /* Checkbox indicator inside attendance toggle buttons */
    .att-box{display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;border:1.5px solid currentColor;border-radius:4px;margin-right:7px;font-size:11px;line-height:1;flex:0 0 auto;}
    .att-tog .att-box::after{content:'';}
    .att-tog.on .att-box::after{content:'\2713';font-weight:900;}
    .att-tog{display:inline-flex;align-items:center;}
    /* Card turns a "complete" tint once attendance is marked */
    .att-row.att-done{background:#ECF8F1;border-color:#BFE6CE;}
    .dark .att-row.att-done{background:#16291F;border-color:#2E6B47;}
    .att-row.att-absent{background:#FBEDEF;border-color:#E9B8C2;}
    .dark .att-row.att-absent{background:#2A171C;border-color:#7A2230;}
    .att-row.att-resched{background:#FBF4E6;border-color:#E8D6A8;}
    .dark .att-row.att-resched{background:#2A2413;border-color:#6E5C24;}
    .att-note{flex:0 1 240px;min-width:150px;max-width:260px;padding:7px 11px;border:1px solid var(--gqs-border,#D6D6DC);border-radius:8px;font-size:12.5px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);}
    .dark .att-note{background:#1A1A20;border-color:#34343E;color:#ECECF0;}
    .att-state{min-width:110px;}
    .att-note-ro{flex:1;font-size:12px;color:var(--gqs-text-dim,#6A6A72);font-style:italic;}
    .att-wl{width:140px;padding:6px 9px;border:1px solid var(--gqs-border,#D6D6DC);border-radius:8px;font-size:12px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);}
    .dark .att-wl{background:#1A1A20;border-color:#34343E;color:#ECECF0;}
    .att-hint{font-size:12px;color:var(--gqs-text-dim,#6A6A72);}
    .gqs-tbl { width:100%; border-collapse:collapse; font-size:13.5px; }
    .gqs-tbl th { text-align:left; padding:9px 16px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#fff; background:#26262C; border-bottom:none; }
    .gqs-tbl thead th:first-child { border-top-left-radius:0; }
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

    /* ===== TIGHTEN top-of-page wasted space ===== */
    .fi-page-header-main-ctn { padding-top: 12px !important; }
    .fi-main { padding-top: 8px !important; }

    /* ===== TIGHTEN VERTICAL RHYTHM (kill wasted gaps page-wide) =====
       The page-hero, the Filament content gap, and the table's filter/search bar
       were each adding space. Compress them so tables/kanbans sit close to the header. */
    .pg-head { padding: 4px 0 10px !important; margin: 0 0 12px !important; }
    /* Filament stacks page content with a vertical gap; shrink it */
    .fi-page-content { gap: 0.75rem !important; }
    .fi-page > section.fi-page-content > * + * { margin-top: 0 !important; }
    /* table toolbar (search + filter triggers) - trim its padding */
    .fi-ta-header-ctn { padding-top: 0 !important; }
    .fi-ta-header { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
    /* the gap the empty header leaves above our hero */
    .fi-page:has(.pg-head) .fi-page-header-main-ctn { padding-top: 10px !important; }

    /* ===== KANBAN HEADER ROW: hero + filters on ONE line (filters are secondary) ===== */
    .sb-headrow { display:flex; align-items:center; justify-content:space-between; gap:18px;
        flex-wrap:wrap; padding:6px 0 12px; margin-bottom:14px; margin-top:4px;
        border-bottom:2px solid var(--gqs-border,#DADADF); }
    .sb-headrow-title { display:flex; align-items:center; gap:14px; min-width:0; flex:1; }
    .sb-headrow-filters { display:flex; align-items:center; gap:8px; flex:0 0 auto; flex-wrap:wrap; }
    .sb-hf-search { width:200px !important; height:36px !important; }
    .sb-hf-sel { width:auto !important; min-width:150px; height:36px !important; }
    @media (max-width:820px){ .sb-headrow-filters{ width:100%; } .sb-hf-search{ flex:1; width:auto !important; } }

    /* ===== LIGHT-THEME MENU/DROPDOWN CONTRAST (comprehensive) =====
       The dark-topbar text rule (.fi-topbar a {color:#ECECF0}) bleeds into menus that
       open from the topbar (Manage, avatar/user menu, theme switcher, notifications),
       making text/icons invisible on the white panel. Force dark text+icons in light theme.
       Scoped to the PANELS (not the topbar triggers) so the dark topbar itself is unaffected. */

    /* Manage dropdown (our custom) */
    html:not(.dark) .gqs-manage-menu { background: #fff !important; }
    html:not(.dark) .gqs-manage-sec { color: #8A8A93 !important; border-top-color: #ECECEF !important; }
    html:not(.dark) .gqs-manage-link { color: #1A1A1F !important; }
    html:not(.dark) .gqs-manage-link:hover { background: #F1F1F4 !important; }

    /* Manage sub-dropdowns (collapsible sections) */
    .gqs-manage-divider { height:1px; background:var(--gqs-border,#ECECEF); margin:6px 4px; }
    html:not(.dark) .gqs-manage-divider { background:#ECECEF; }
    .gqs-manage-sub { display:block; }
    .gqs-manage-subbtn {
        display:flex; align-items:center; gap:8px; width:100%;
        padding:10px 12px; border:none; background:transparent; cursor:pointer;
        font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
        color:var(--gqs-text-dim,#8A8A93); border-radius:8px; text-align:left;
    }
    .gqs-manage-subbtn:hover { background:var(--gqs-surface-2,#F1F1F4); }
    html:not(.dark) .gqs-manage-subbtn { color:#8A8A93; }
    html:not(.dark) .gqs-manage-subbtn:hover { background:#F1F1F4; }
    .gqs-manage-subbtn > span:first-child { flex:1; }
    .gqs-manage-subcount {
        font-size:10px; font-weight:700; background:var(--astellas-magenta,#A4123F); color:#fff;
        border-radius:9px; padding:1px 7px; letter-spacing:0;
    }
    .gqs-manage-subchev { width:14px; height:14px; transition:transform .15s; opacity:.7; }
    .gqs-manage-subitems { padding-left:6px; margin:2px 0 4px; }
    .gqs-manage-sublink { padding-left:18px !important; font-size:13.5px !important; }
    .gqs-manage-sublink::before {
        content:''; position:absolute; left:9px; width:2px; height:18px;
        background:var(--gqs-border,#E2E2E6); border-radius:2px;
    }
    .gqs-manage-sublink { position:relative; }

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
    /* Page hero header (shared by the partial and resource-list view) */
    .pg-head{display:flex;align-items:center;gap:16px;padding:8px 0 16px;margin-bottom:18px;margin-top:6px;border-bottom:2px solid var(--gqs-border,#DADADF);}
    .pg-head-ico{width:46px;height:46px;flex:0 0 46px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#A4123F,#850F33);box-shadow:0 3px 10px rgba(164,18,63,.28);}
    .pg-head-ico svg{width:24px;height:24px;color:#fff;}
    .pg-head-tx h1{font-size:22px;font-weight:800;margin:0;color:var(--gqs-text,#1A1A1F);}
    .pg-head-tx p{margin:3px 0 0;color:var(--gqs-text-dim,#5A5A62);font-size:14px;}
    /* Tabs (team views, messages, notification settings) */
    .gqs-tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--gqs-border,#E2E2E6);flex-wrap:wrap;}
    .gqs-tab{background:none;border:none;padding:9px 16px;font-size:13.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;}
    .gqs-tab.on{color:#A4123F;border-bottom-color:#A4123F;}
    /* header-integrated tabs (in .pg-head-r): pill style instead of underline */
    .pg-head-r .gqs-tab{border:1px solid var(--gqs-border,#D6D6DC);border-radius:8px;padding:7px 14px;font-size:13px;margin:0;}
    .pg-head-r .gqs-tab.active,.pg-head-r .gqs-tab.on{background:#A4123F;border-color:#A4123F;color:#fff;}
    .dark .pg-head-r .gqs-tab{border-color:#3A3A44;color:#C9C9D2;}
    /* Form controls (modals, messages, notification settings, calendar) */
    /* Capitalize Filament action button labels (auto-generated names like 'no show' -> 'No Show') */
    .fi-btn .fi-btn-label, .fi-link .fi-link-label, .fi-dropdown-list-item-label { text-transform: capitalize; }
    .gqs-flbl{display:block;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);margin-bottom:5px;}
    .gqs-fld{width:100%;padding:9px 11px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:8px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);font-size:14px;font-family:inherit;}
    .gqs-fld:focus{outline:none;border-color:#A4123F;box-shadow:0 0 0 3px rgba(164,18,63,.12);}
    .dark .gqs-fld{background:#23232B;border-color:#3A3A44;color:#ECECF0;}

    /* Shared clean modal (matches the wizard look: one container, generous spacing, no card-in-card) */
    .gqs-modal-overlay{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(20,20,24,.55);backdrop-filter:blur(2px);padding:20px;}
    .gqs-modal{background:var(--gqs-surface,#fff);border-radius:16px;width:480px;max-width:96vw;max-height:90vh;overflow:auto;box-shadow:0 24px 70px rgba(0,0,0,.35);}
    .dark .gqs-modal{background:#1C1C22;}
    .gqs-modal-head{display:flex;align-items:center;gap:10px;padding:15px 22px;border-radius:16px 16px 0 0;font-weight:800;font-size:15px;background:#26262C;color:#fff;}
    .gqs-modal-head .gqs-modal-ico{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:#E8C24A;color:#26262C;flex-shrink:0;}
    .gqs-modal-head .gqs-modal-ico svg{width:17px;height:17px;}
    .gqs-modal-body{padding:20px 22px;display:flex;flex-direction:column;gap:15px;}
    .gqs-modal-body .gqs-flbl{margin-bottom:4px;}
    .gqs-modal-foot{display:flex;justify-content:flex-end;gap:9px;padding:16px 22px;border-top:1px solid var(--gqs-border,#ECECEF);}
    .dark .gqs-modal-foot{border-color:#2C2C34;}
    .gqs-btn{padding:9px 18px;border-radius:9px;font-weight:700;font-size:13.5px;cursor:pointer;border:none;}
    .gqs-btn-primary{background:#A4123F;color:#fff;}
    .gqs-btn-primary:hover{background:#85102F;}
    .gqs-btn-ghost{background:transparent;border:1px solid var(--gqs-border,#C4C4CC);color:var(--gqs-text,#1A1A1F);}
    .dark .gqs-btn-ghost{color:#ECECF0;border-color:#3A3A44;}
    /* small inline action buttons used across boards/rosters */
    .sb-act{font-size:12px;font-weight:700;padding:5px 13px;border-radius:7px;border:none;cursor:pointer;margin-left:6px;color:#fff;background:#6A6A72;}
    .sb-act-green{background:#2E7D5B;} .sb-act-green:hover{background:#246148;}
    .sb-act-red{background:#C8102E;} .sb-act-red:hover{background:#9A0C23;}
    .sb-act-magenta{background:#A4123F;} .sb-act-magenta:hover{background:#85102F;}
    .rd-act{font-size:12px;font-weight:700;padding:5px 12px;border-radius:7px;border:none;cursor:pointer;color:#fff;background:#6A6A72;margin-left:4px;}
    .rd-act-magenta{background:#A4123F;} .rd-act-magenta:hover{background:#85102F;}
    .rd-act-green{background:#2E7D5B;} .rd-act-green:hover{background:#246148;}
    .dark .gqs-btn-ghost{color:#ECECF0;border-color:#3A3A44;}
    .gqs-mini-btn{background:#A4123F;color:#fff;border:none;border-radius:7px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;}
    .gqs-mini-btn:hover{background:#850F33;}
    /* flatpickr (vendored locally; no CDN) accent to match the Astellas palette */
    .flatpickr-day.selected,.flatpickr-day.selected:hover{background:#A4123F;border-color:#A4123F;}
    .flatpickr-day.today{border-color:#A4123F;}
    .flatpickr-input[readonly]{background:var(--gqs-surface,#fff);}
</style>
<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
