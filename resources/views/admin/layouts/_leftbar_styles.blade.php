<style>
    .custom-ml-15 {
        margin-left: 12px;
    }
    .left-side-menu {
        background-color: #ffffff !important;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05) !important;
    }
    .left-side-menu #sidebar-menu {
        padding: 10px 0;
    }
    /* Normal Link State */
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu ul li a,
    .left-side-menu #sidebar-menu ul li a {
        padding: 10px 16px !important;
        color: #495057 !important;
        display: flex !important;
        align-items: center !important;
        transition: all 0.2s ease-in-out !important;
        border-radius: 6px !important;
        margin: 2px 10px !important;
        font-weight: 500 !important;
        font-size: 0.9rem !important;
        text-decoration: none !important;
    }
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu ul li a i,
    .left-side-menu #sidebar-menu ul li a i {
        font-size: 1.15rem !important;
        margin-right: 10px !important;
        vertical-align: middle !important;
        color: #6c757d !important;
        transition: color 0.2s ease-in-out !important;
    }
    /* Hover State (Light Orange Tint & Orange Text) */
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu ul li a:hover,
    .left-side-menu #sidebar-menu ul li a:hover {
        color: #f06115 !important;
        background-color: #fff4ed !important;
    }
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu ul li a:hover i,
    .left-side-menu #sidebar-menu ul li a:hover i {
        color: #f06115 !important;
    }
    /* Active / Selected State (Vibrant Orange Background & Solid White Text) */
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu .menuitem-active > a,
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu .menuitem-active > a.active,
    .left-side-menu #sidebar-menu ul li.menuitem-active > a,
    .left-side-menu #sidebar-menu ul li a.active {
        color: #ffffff !important;
        font-weight: 600 !important;
        background-color: #f06115 !important;
        box-shadow: 0 3px 8px rgba(240, 97, 21, 0.35) !important;
    }
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu .menuitem-active > a i,
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu .menuitem-active > a.active i,
    .left-side-menu #sidebar-menu ul li.menuitem-active > a i,
    .left-side-menu #sidebar-menu ul li a.active i {
        color: #ffffff !important;
    }
    body[data-leftbar-color=dark] .left-side-menu #sidebar-menu .menuitem-active > a .badge,
    .left-side-menu #sidebar-menu ul li.menuitem-active > a .badge {
        background-color: #ffffff !important;
        color: #f06115 !important;
    }
    /* Sub-menu styling */
    .left-side-menu #sidebar-menu ul li .nav-second-level li a {
        padding: 8px 15px 8px 25px !important;
        font-size: 0.85rem !important;
    }
    .button-menu-mobile {
        height: 70px !important;
        width: 60px !important;
        line-height: 70px !important;
        border: none !important;
        background: transparent !important;
        color: #6c757d !important;
        font-size: 22px !important;
        transition: color 0.2s ease !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .button-menu-mobile:hover {
        color: #f06115 !important;
    }

    /* Brand / User Top-Left Area Styles */
    .logo-box {
        background-color: #ffffff !important;
        height: 70px;
        width: 240px;
        float: left;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0 16px !important;
        box-sizing: border-box;
        border-bottom: 1px solid #f1f3fa;
        border-right: 1px solid #f1f3fa;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease !important;
        overflow: hidden;
        z-index: 1002;
        cursor: pointer !important;
        user-select: none;
    }

    .logo-box:hover {
        background-color: #fafafa !important;
    }

    .logo-user-profile {
        display: flex !important;
        align-items: center !important;
        height: 100% !important;
        width: auto !important;
        line-height: normal !important;
        overflow: hidden;
        cursor: pointer !important;
        text-decoration: none !important;
        transition: all 0.3s ease;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
    }

    .user-avatar-circle {
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        border-radius: 50% !important;
        background: linear-gradient(135deg, #f06115 0%, #ff8a48 100%) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        font-size: 0.88rem !important;
        letter-spacing: 0.5px;
        box-shadow: 0 3px 8px rgba(240, 97, 21, 0.3);
        transition: transform 0.25s ease, box-shadow 0.25s ease, margin 0.3s ease !important;
        margin: 0 !important;
    }

    .logo-box:hover .user-avatar-circle {
        transform: scale(1.06);
        box-shadow: 0 4px 12px rgba(240, 97, 21, 0.4);
    }

    .user-initials {
        line-height: 1;
        user-select: none;
    }

    .user-info-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        white-space: nowrap;
        overflow: hidden;
        max-width: 125px;
        opacity: 1;
        transform: translateX(0);
        transition: max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                    opacity 0.25s ease,
                    transform 0.3s ease,
                    margin 0.3s ease !important;
    }

    .user-full-name {
        color: #2c3e50;
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1.25;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        transition: color 0.2s ease;
    }

    .logo-box:hover .user-full-name {
        color: #f06115;
    }

    .user-role-subtitle {
        color: #8a92a6;
        font-size: 0.72rem;
        font-weight: 500;
        line-height: 1.1;
        margin-top: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .logo-box .btn-sidebar-toggle {
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        line-height: 34px !important;
        border-radius: 6px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: #6c757d !important;
        background: transparent !important;
        transition: all 0.25s ease !important;
        opacity: 1;
        transform: scale(1);
        cursor: pointer !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .logo-box:hover .btn-sidebar-toggle {
        color: #f06115 !important;
        background-color: #fff4ed !important;
    }

    /* COLLAPSED STATE (body[data-leftbar-size=condensed]) */
    body[data-leftbar-size=condensed] .logo-box {
        width: 70px !important;
        padding: 0 !important;
        justify-content: center !important;
    }

    body[data-leftbar-size=condensed] .logo-box .logo-user-profile {
        justify-content: center !important;
        width: 100% !important;
    }

    body[data-leftbar-size=condensed] .logo-box .user-info-text {
        max-width: 0 !important;
        opacity: 0 !important;
        margin: 0 !important;
        transform: translateX(-10px);
        pointer-events: none;
    }

    body[data-leftbar-size=condensed] .logo-box .btn-sidebar-toggle {
        max-width: 0 !important;
        width: 0 !important;
        min-width: 0 !important;
        opacity: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden;
        pointer-events: none;
        transform: scale(0.7);
    }

    body[data-leftbar-size=condensed] .logo-box .user-avatar-circle {
        margin: 0 auto !important;
    }

    /* PERFECTLY CENTERED ICONS & HIGHLIGHTS FOR COLLAPSED SIDEBAR */
    body[data-leftbar-size=condensed] .left-side-menu {
        width: 70px !important;
    }

    body[data-leftbar-size=condensed] .left-side-menu #sidebar-menu > ul > li {
        position: relative;
    }

    body[data-leftbar-size=condensed] .left-side-menu #sidebar-menu ul li a {
        padding: 10px 0 !important;
        margin: 4px 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        border-radius: 6px !important;
        width: 50px !important;
    }

    body[data-leftbar-size=condensed] .left-side-menu #sidebar-menu ul li a i {
        margin-right: 0 !important;
        margin-left: 0 !important;
        font-size: 1.2rem !important;
        vertical-align: middle !important;
        text-align: center !important;
    }

    body[data-leftbar-size=condensed] .left-side-menu #sidebar-menu > ul > li > a > span:not(.badge) {
        display: none !important;
    }

    body[data-leftbar-size=condensed] .left-side-menu #sidebar-menu .menu-title {
        display: none !important;
    }

    body[data-leftbar-size=condensed] .left-side-menu #sidebar-menu .menu-arrow {
        display: none !important;
    }
