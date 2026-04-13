<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

$isActiveRoute = static function (string $route) use ($currentPath): bool {
    $normalizedCurrent = rtrim($currentPath, '/');
    $normalizedRoute = ltrim($route, '/');

    return $normalizedCurrent === '/mamix/' . $normalizedRoute
        || $normalizedCurrent === '/mamix/mamix_esbuild/' . $normalizedRoute;
};
?>
<!-- Start::app-sidebar -->
<aside class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div class="main-sidebar-header">
        <a href="dashboard" class="header-logo">
            <img src="<?= $base ?>assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
            <img src="<?= $base ?>assets/images/brand-logos/toggle-dark.png" alt="logo" class="toggle-dark">
            <img src="<?= $base ?>assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark">
            <img src="<?= $base ?>assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
        </a>
    </div>
    <!-- End::main-sidebar-header -->

    <!-- Start::main-sidebar -->
    <div class="main-sidebar" id="sidebar-scroll">
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                </svg>
            </div>

            <ul class="main-menu">
                <li class="slide__category"><span class="category-name">CRM</span></li>

                <li class="slide has-sub <?= ($isActiveRoute('dashboard') || $isActiveRoute('leads')) ? 'open' : '' ?>">
                    <a href="javascript:void(0);" class="side-menu__item <?= ($isActiveRoute('dashboard') || $isActiveRoute('leads')) ? 'active' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="32" height="32" viewBox="0 0 256 256">
                            <path d="M216,115.54V208a8,8,0,0,1-8,8H160a8,8,0,0,1-8-8V160a8,8,0,0,0-8-8H112a8,8,0,0,0-8,8v48a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V115.54a8,8,0,0,1,2.62-5.92l80-75.54a8,8,0,0,1,10.77,0l80,75.54A8,8,0,0,1,216,115.54Z" opacity="0.2"></path>
                            <path d="M218.83,103.77l-80-75.48a1.14,1.14,0,0,1-.11-.11,16,16,0,0,0-21.53,0l-.11.11L37.17,103.77A16,16,0,0,0,32,115.55V208a16,16,0,0,0,16,16H96a16,16,0,0,0,16-16V160h32v48a16,16,0,0,0,16,16h48a16,16,0,0,0,16-16V115.55A16,16,0,0,0,218.83,103.77ZM208,208H160V160a16,16,0,0,0-16-16H112a16,16,0,0,0-16,16v48H48V115.55l.11-.1L128,40l79.9,75.43.11.1Z"></path>
                        </svg>
                        <span class="side-menu__label">CRM Panel</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1" <?= ($isActiveRoute('dashboard') || $isActiveRoute('leads')) ? 'style="display: block;"' : '' ?>>
                        <li class="slide side-menu__label1">
                            <a href="javascript:void(0)">CRM Panel</a>
                        </li>
                        <li class="slide">
                            <a href="dashboard" class="side-menu__item <?= $isActiveRoute('dashboard') ? 'active' : '' ?>">Dashboard</a>
                        </li>
                        <li class="slide">
                            <a href="leads" class="side-menu__item <?= $isActiveRoute('leads') ? 'active' : '' ?>">Leads</a>
                        </li>
                    </ul>
                </li>

                <li class="slide__category"><span class="category-name">Setup</span></li>

                <li class="slide has-sub <?= $isActiveRoute('setup') ? 'open' : '' ?>">
                    <a href="javascript:void(0);" class="side-menu__item <?= $isActiveRoute('setup') ? 'active' : '' ?>">
                        <i class="ti ti-settings side-menu__icon"></i>
                        <span class="side-menu__label">Setup</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1" <?= $isActiveRoute('setup') ? 'style="display: block;"' : '' ?>>
                        <li class="slide side-menu__label1">
                            <a href="javascript:void(0)">Setup</a>
                        </li>
                        <li class="slide">
                            <a href="setup" class="side-menu__item <?= $isActiveRoute('setup') ? 'active' : '' ?>">Master Setup</a>
                        </li>
                    </ul>
                </li>

                <li class="slide__category"><span class="category-name">Account</span></li>

                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="32" height="32" viewBox="0 0 256 256">
                            <path d="M128,32a96,96,0,1,0,96,96A96,96,0,0,0,128,32Zm0,136a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"></path>
                            <path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,16a87.6,87.6,0,0,1,62.66,26.33A88,88,0,1,1,66.33,190.66,88,88,0,0,1,128,40Zm0,32a56,56,0,1,0,56,56A56.06,56.06,0,0,0,128,72Z"></path>
                        </svg>
                        <span class="side-menu__label">Account</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1">
                            <a href="javascript:void(0)">Account</a>
                        </li>
                        <li class="slide">
                            <a href="logout" class="side-menu__item">Logout</a>
                        </li>
                    </ul>
                </li>
            </ul>

            <div class="slide-right" id="slide-right">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path>
                </svg>
            </div>
        </nav>
    </div>
    <!-- End::main-sidebar -->

</aside>
<!-- End::app-sidebar -->
