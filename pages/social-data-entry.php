<?php
/*
|--------------------------------------------------------------------------
| Social Media — Data Entry (DUMMY / FRONTEND ONLY)
|--------------------------------------------------------------------------
|
| UI prototype only. Every list on this page is served from the DUMMY_DB
| object in the script below — there are no AJAX calls and no DB writes.
|
| Intended real-world flow (mirrors pages/calendar.php):
|   calendar.php    -> decides WHICH dates a client/platform/feature runs on
|                      (clientCalendarPlans.selectedDates)
|   this page       -> fills in WHAT goes out on each of those dates
|
| Dimensions handled here: date x client x platform x feature.
|
*/
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* ==========================================================================
   Social Data Entry — scoped styles (sde-*)
   Theme tokens only, so light/dark mode both stay correct.
   ========================================================================== */

.sde-filterbar .form-select,
.sde-filterbar .form-control {
    border-radius: 30px;
}

.sde-filterbar label {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.2rem;
}

/* ---------- Left rail : the date list ---------- */
.sde-rail-card .card-body {
    padding: 0.5rem;
}

.sde-rail {
    max-height: 62vh;
    overflow-y: auto;
    padding-right: 2px;
}

.sde-rail::-webkit-scrollbar { width: 5px; }
.sde-rail::-webkit-scrollbar-thumb {
    background: var(--default-border);
    border-radius: 10px;
}

.sde-date-item {
    width: 100%;
    display: grid;
    grid-template-columns: 44px 1fr auto;
    align-items: center;
    gap: 0.65rem;
    text-align: left;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    padding: 0.5rem 0.6rem;
    margin-bottom: 2px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.sde-date-item:hover {
    background: var(--primary005, rgba(var(--primary-rgb), 0.05));
    border-color: var(--default-border);
}

.sde-date-item.active {
    background: var(--primary01);
    border-color: var(--primary-border);
}

.sde-date-num {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--default-background);
    border: 1px solid var(--default-border);
    line-height: 1.05;
}

.sde-date-item.active .sde-date-num {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #fff;
}

.sde-date-num b { font-size: 1rem; font-weight: 700; }
.sde-date-num span { font-size: 0.6rem; text-transform: uppercase; opacity: 0.75; }

.sde-date-dow {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--default-text-color);
}

.sde-date-progress {
    margin-top: 5px;
    height: 4px;
    border-radius: 4px;
    background: var(--default-border);
    overflow: hidden;
}

.sde-date-progress span {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: var(--primary-color);
}

.sde-date-progress.is-done span { background: rgb(var(--success-rgb)); }
.sde-date-progress.is-empty span { background: rgb(var(--warning-rgb)); }

.sde-date-count {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.55rem;
    border-radius: 30px;
    background: var(--default-background);
    border: 1px solid var(--default-border);
    color: var(--default-text-color);
    white-space: nowrap;
}

/* ---------- Right panel : the day board ---------- */
.sde-day-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.sde-day-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--default-text-color);
    margin: 0;
}

.sde-stat {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.2rem 0.7rem;
    border-radius: 30px;
    border: 1px solid var(--default-border);
    background: var(--default-background);
    color: var(--text-muted);
}

.sde-stat b { color: var(--default-text-color); }
.sde-stat i { font-size: 0.55rem; }

/* platform group */
.sde-group {
    border: 1px solid var(--default-border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 0.9rem;
}

.sde-group-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    background: var(--default-background);
    border-bottom: 1px solid var(--default-border);
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--default-text-color);
}

.sde-group-head i.sde-plat-icon {
    font-size: 1.05rem;
    color: var(--primary-color);
}

/* slot rows */
.sde-slot-head,
.sde-slot {
    display: grid;
    grid-template-columns: 150px 1fr 108px 150px 78px;
    gap: 0.75rem;
    align-items: center;
    padding: 0.6rem 0.9rem;
    border-bottom: 1px solid var(--default-border);
}

.sde-slot:last-child { border-bottom: none; }

.sde-slot-head {
    padding-top: 0.4rem;
    padding-bottom: 0.4rem;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-muted);
    background: var(--custom-white);
}

.sde-slot:hover { background: var(--primary005, rgba(var(--primary-rgb), 0.04)); }

.sde-slot.is-empty { background: rgba(var(--warning-rgb), 0.04); }

.sde-feature {
    display: inline-block;
    max-width: 100%;
    padding: 0.15rem 0.7rem;
    border-radius: 30px;
    background: var(--primary01);
    color: var(--primary-color);
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sde-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--default-text-color);
    margin-bottom: 1px;
}

.sde-caption {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.sde-badge {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.65rem;
    border-radius: 30px;
    white-space: nowrap;
}

.sde-badge.draft     { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
.sde-badge.ready     { background: rgba(var(--info-rgb), 0.12);      color: rgb(var(--info-rgb)); }
.sde-badge.scheduled { background: rgba(var(--warning-rgb), 0.14);   color: rgb(var(--warning-rgb)); }
.sde-badge.posted    { background: rgba(var(--success-rgb), 0.12);   color: rgb(var(--success-rgb)); }
.sde-badge.pending   { background: rgba(var(--danger-rgb), 0.10);    color: rgb(var(--danger-rgb)); }

.sde-meta {
    font-size: 0.7rem;
    color: var(--text-muted);
    line-height: 1.4;
}

.sde-actions {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

.sde-icon-btn {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--default-border);
    background: var(--custom-white);
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1;
    transition: 0.15s;
}

.sde-icon-btn:hover { background: var(--primary01); color: var(--primary-color); border-color: var(--primary-border); }
.sde-icon-btn.danger:hover { background: rgba(var(--danger-rgb), 0.1); color: rgb(var(--danger-rgb)); border-color: rgba(var(--danger-rgb), 0.3); }

.sde-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--text-muted);
}

.sde-empty i { font-size: 2.4rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }

/* the entry form */
.sde-form label.form-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--default-text-color);
}

.sde-form .form-control,
.sde-form .form-select { font-size: 0.85rem; }

.sde-scope {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.6rem 0.8rem;
    border-radius: 12px;
    background: var(--default-background);
    border: 1px solid var(--default-border);
    margin-bottom: 1rem;
}

.sde-scope span {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
}

.sde-scope b { color: var(--default-text-color); }

/* responsive */
@media (max-width: 1199.98px) {
    .sde-rail { max-height: 260px; }
}

@media (max-width: 991.98px) {
    .sde-slot-head { display: none; }
    .sde-slot {
        grid-template-columns: 1fr;
        gap: 0.4rem;
    }
    .sde-actions { justify-content: flex-start; }
}

/* ---------- full-page "Social Media Overview" modal ---------- */
.sde-overview-body {
    padding: 0;
    overflow: hidden;
}

.sde-overview-frame {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- ============================ PAGE HEADER ============================ -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Social Media Data Entry</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Data Entry</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="calendar" class="btn btn-light btn-sm">
                    <i class="ri-calendar-event-line me-1"></i> Calendar Planner
                </a>
                <button class="btn btn-primary btn-sm" id="sdeAddBtn">
                    <i class="ri-add-line me-1"></i> Add Entry
                </button>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card sde-filterbar">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-3 col-md-6">
                        <label for="sdeClient">Client</label>
                        <select class="form-select form-select-sm" id="sdeClient"></select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label for="sdeMonth">Month</label>
                        <select class="form-select form-select-sm" id="sdeMonth"></select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="sdePlatform">Platform</label>
                        <select class="form-select form-select-sm" id="sdePlatform">
                            <option value="">All Platforms</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="sdeStatus">Status</label>
                        <select class="form-select form-select-sm" id="sdeStatus">
                            <option value="">All Status</option>
                            <option value="pending">Not Filled</option>
                            <option value="draft">Draft</option>
                            <option value="ready">Ready</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="sdeSearch">Search</label>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" id="sdeSearch"
                                   placeholder="Title, caption or feature...">
                            <button class="btn btn-light btn-sm flex-shrink-0" id="sdeResetBtn" title="Reset filters">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================ BOARD ============================ -->
        <div class="row">

            <!-- ---------- date rail ---------- -->
            <div class="col-xl-3 col-lg-4">
                <div class="card custom-card sde-rail-card">
                    <div class="card-header justify-content-between align-items-center py-2">
                        <div class="card-title mb-0 fs-14" id="sdeRailTitle">Dates</div>
                        <span class="badge bg-light text-default" id="sdeRailCount">0</span>
                    </div>
                    <div class="card-body">
                        <div class="sde-rail" id="sdeRail"></div>
                    </div>
                    <div class="card-footer py-2 text-muted fs-11">
                        <i class="ri-information-line me-1"></i>
                        Only dates planned in the Calendar are listed.
                    </div>
                </div>
            </div>

            <!-- ---------- day board ---------- -->
            <div class="col-xl-9 col-lg-8">
                <div class="card custom-card">
                    <div class="card-header py-3">
                        <div class="sde-day-head w-100">
                            <div>
                                <h6 class="sde-day-title mb-1" id="sdeDayTitle">Select a date</h6>
                                <div class="d-flex gap-2 flex-wrap" id="sdeDayStats"></div>
                            </div>
                            <button class="btn btn-primary btn-sm" id="sdeAddForDateBtn" disabled>
                                <i class="ri-add-line me-1"></i> Add Entry for this Date
                            </button>
                        </div>
                    </div>
                    <div class="card-body" id="sdeBoard"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ============================ SOCIAL MEDIA OVERVIEW (full-page) MODAL ============================ -->
<!-- No modal-header here on purpose — the embedded Overview page shows its
     own page title and its own "Close" button beside "Open Client Editor". -->
<div class="modal fade" id="sdeOverviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body sde-overview-body">
                <iframe id="sdeOverviewFrame" class="sde-overview-frame" title="Social Media Overview"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ============================ ENTRY MODAL ============================ -->
<div class="modal fade" id="sdeEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="sdeEntryTitle">
                    <i class="ri-edit-box-line me-2 text-primary"></i> Add Entry
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body sde-form">
                <div class="sde-scope" id="sdeScopeStrip"></div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="sdeFormDate">Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sdeFormDate" placeholder="Select date">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sdeFormPlatform">Platform <span class="text-danger">*</span></label>
                        <select class="form-select" id="sdeFormPlatform"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sdeFormFeature">Feature <span class="text-danger">*</span></label>
                        <select class="form-select" id="sdeFormFeature"></select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label" for="sdeFormTitle">Content Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sdeFormTitle" maxlength="120"
                               placeholder="e.g. Independence Day creative">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sdeFormStatus">Status</label>
                        <select class="form-select" id="sdeFormStatus">
                            <option value="draft">Draft</option>
                            <option value="ready">Ready</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="sdeFormCaption">Caption / Description</label>
                        <textarea class="form-control" id="sdeFormCaption" rows="3"
                                  placeholder="Caption, hashtags, copy notes..."></textarea>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label" for="sdeFormLink">Creative / Reference Link</label>
                        <input type="url" class="form-control" id="sdeFormLink" placeholder="https://drive.google.com/...">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="sdeFormRemarks">Remarks</label>
                        <input type="text" class="form-control" id="sdeFormRemarks" maxlength="120"
                               placeholder="Internal note (optional)">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" id="sdeCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="sdeSaveBtn">
                    <i class="ri-save-line me-1"></i> Save Entry
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ==========================================================================
   FRONTEND-ONLY PROTOTYPE
   All data below is fabricated in the browser. Nothing is persisted.
   Replace DUMMY_DB + the four TODO markers with real endpoints later.
   ========================================================================== */
$(function () {

    // ----------------------------------------------------------------------
    // 1. DUMMY DATA
    // ----------------------------------------------------------------------
    const DUMMY_DB = {
        clients: [
            { id: 1, name: 'Acme Retail Pvt Ltd' },
            { id: 2, name: 'Blue Ocean Foods' },
            { id: 3, name: 'Nova Fitness Studio' }
        ],
        platforms: [
            { id: 1, name: 'Instagram', icon: 'ri-instagram-line' },
            { id: 2, name: 'Facebook',  icon: 'ri-facebook-circle-line' },
            { id: 3, name: 'LinkedIn',  icon: 'ri-linkedin-box-line' }
        ],
        features: {
            1: [ { id: 101, name: 'Static Post' }, { id: 102, name: 'Reel' },      { id: 103, name: 'Story' } ],
            2: [ { id: 201, name: 'Static Post' }, { id: 202, name: 'Video Post' }, { id: 203, name: 'Story' } ],
            3: [ { id: 301, name: 'Article' },     { id: 302, name: 'Carousel' } ]
        }
    };

    const STATUS_LABEL = {
        pending: 'Not Filled', draft: 'Draft', ready: 'Ready',
        scheduled: 'Scheduled', posted: 'Posted'
    };

    const SAMPLE_TITLES = [
        'Festive offer creative', 'Behind the scenes reel', 'Customer testimonial',
        'New arrival teaser', 'Weekend flash sale', 'Product spotlight',
        'Founder story carousel', 'Monthly recap post'
    ];

    const SAMPLE_CAPTIONS = [
        'Final copy approved by the client. Hashtag set A.',
        'Awaiting creative from the design team.',
        'Reshared from the brand handle with a localised caption.',
        'Copy locked, creative link attached below.'
    ];

    // deterministic pseudo-random so the dummy board looks the same on reload
    function seeded(seed) {
        let s = seed % 2147483647;
        if (s <= 0) s += 2147483646;
        return function () { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; };
    }

    const planCache = {};   // clientId|month -> { 'YYYY-MM-DD': [{platformId, featureId}] }
    let entries = [];       // the fake "clientSocialContent" table
    let entrySeq = 1;

    /* TODO(api): replace with GET api/deliverables/get-client-calendar-plan.php (selectedDates) */
    function getClientPlan(clientId, month) {
        const key = clientId + '|' + month;
        if (planCache[key]) return planCache[key];

        const rand = seeded(clientId * 7919 + parseInt(month.replace('-', ''), 10));
        const [y, m] = month.split('-').map(Number);
        const daysInMonth = new Date(y, m, 0).getDate();
        const plan = {};

        for (let d = 1; d <= daysInMonth; d++) {
            if (rand() > 0.42) continue;                       // ~40% of days are planned
            const dateStr = month + '-' + String(d).padStart(2, '0');
            const slots = [];
            DUMMY_DB.platforms.forEach(p => {
                if (rand() > 0.55) return;
                const feats = DUMMY_DB.features[p.id];
                const f = feats[Math.floor(rand() * feats.length)];
                slots.push({ platformId: p.id, featureId: f.id });
            });
            if (slots.length) plan[dateStr] = slots;
        }

        planCache[key] = plan;
        return plan;
    }

    // clientId === '' (All Clients) merges every client's plan for the month,
    // tagging each slot with its clientId so the board can tell them apart.
    function getPlan(clientId, month) {
        if (clientId) {
            const plan = getClientPlan(clientId, month);
            const out = {};
            Object.keys(plan).forEach(date => {
                out[date] = plan[date].map(s => ({ ...s, clientId: clientId }));
            });
            return out;
        }

        const merged = {};
        DUMMY_DB.clients.forEach(c => {
            const plan = getClientPlan(c.id, month);
            Object.keys(plan).forEach(date => {
                const slots = plan[date].map(s => ({ ...s, clientId: c.id }));
                merged[date] = (merged[date] || []).concat(slots);
            });
        });
        return merged;
    }

    /* TODO(api): replace with GET api/getSocialContent.php */
    function seedEntriesForClient(clientId, month) {
        if (entries.some(e => e.clientId === clientId && e.date.startsWith(month))) return;

        const plan = getClientPlan(clientId, month);
        const rand = seeded(clientId * 104729 + parseInt(month.replace('-', ''), 10));
        const statuses = ['draft', 'ready', 'scheduled', 'posted'];

        Object.keys(plan).forEach(date => {
            plan[date].forEach(slot => {
                if (rand() > 0.6) return;                      // ~60% of slots already filled
                entries.push({
                    id: entrySeq++,
                    clientId: clientId,
                    date: date,
                    platformId: slot.platformId,
                    featureId: slot.featureId,
                    title: SAMPLE_TITLES[Math.floor(rand() * SAMPLE_TITLES.length)],
                    caption: SAMPLE_CAPTIONS[Math.floor(rand() * SAMPLE_CAPTIONS.length)],
                    link: '',
                    status: statuses[Math.floor(rand() * statuses.length)],
                    remarks: '',
                    updatedBy: 'Priya K.',
                    updatedAt: date + ' 11:20'
                });
            });
        });
    }

    function seedEntries(clientId, month) {
        if (clientId) { seedEntriesForClient(clientId, month); return; }
        DUMMY_DB.clients.forEach(c => seedEntriesForClient(c.id, month));
    }

    // ----------------------------------------------------------------------
    // 2. HELPERS
    // ----------------------------------------------------------------------
    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function clientById(id) {
        return DUMMY_DB.clients.find(c => c.id === Number(id)) || { name: 'Unknown' };
    }

    function platformById(id) {
        return DUMMY_DB.platforms.find(p => p.id === Number(id)) || { name: 'Unknown', icon: 'ri-apps-line' };
    }

    function featureById(platformId, featureId) {
        const list = DUMMY_DB.features[Number(platformId)] || [];
        return list.find(f => f.id === Number(featureId)) || { name: 'Unknown' };
    }

    function slotKey(date, platformId, featureId) {
        return date + '|' + platformId + '|' + featureId;
    }

    function findEntry(clientId, date, platformId, featureId) {
        return entries.find(e =>
            e.clientId === Number(clientId) &&
            e.date === date &&
            e.platformId === Number(platformId) &&
            e.featureId === Number(featureId)
        );
    }

    function fmtLongDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function notify(type, message) {
        if (window.showToast) window.showToast(type, message);
    }

    function confirmDialog(opts) {
        return Swal.fire({
            title: opts.title,
            html: opts.html || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: opts.color || '#dc3545',
            reverseButtons: true
        });
    }

    // ----------------------------------------------------------------------
    // 3. PAGE STATE
    // ----------------------------------------------------------------------
    const state = {
        clientId: null,
        month: null,
        platform: '',
        status: '',
        search: '',
        activeDate: null,
        editingId: null      // null => insert mode
    };

    let entryModal = null;
    let overviewModal = null;
    let formDirty = false;

    // ----------------------------------------------------------------------
    // 4. BOOTSTRAP THE FILTERS
    // ----------------------------------------------------------------------
    function buildMonthOptions() {
        const now = new Date();
        let html = '';
        for (let i = -2; i <= 4; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
            const val = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            const label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
            html += `<option value="${val}" ${i === 0 ? 'selected' : ''}>${label}</option>`;
        }
        return html;
    }

    $('#sdeClient').html(
        '<option value="">All Clients</option>' +
        DUMMY_DB.clients.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')
    );
    $('#sdeMonth').html(buildMonthOptions());
    $('#sdePlatform').append(DUMMY_DB.platforms.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join(''));

    state.clientId = $('#sdeClient').val() ? Number($('#sdeClient').val()) : '';
    state.month = $('#sdeMonth').val();

    // ----------------------------------------------------------------------
    // 5. RENDER — DATE RAIL
    // ----------------------------------------------------------------------
    function visibleSlots(date) {
        const plan = getPlan(state.clientId, state.month);
        let slots = (plan[date] || []).slice();

        if (state.platform) {
            slots = slots.filter(s => String(s.platformId) === state.platform);
        }

        return slots.map(s => {
            const entry = findEntry(s.clientId, date, s.platformId, s.featureId);
            return { ...s, entry: entry || null, status: entry ? entry.status : 'pending' };
        }).filter(s => {
            if (state.status && s.status !== state.status) return false;
            if (state.search) {
                const q = state.search.toLowerCase();
                const hay = [
                    featureById(s.platformId, s.featureId).name,
                    s.entry ? s.entry.title : '',
                    s.entry ? s.entry.caption : ''
                ].join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });
    }

    function renderRail() {
        const plan = getPlan(state.clientId, state.month);
        const dates = Object.keys(plan).sort();
        const rail = $('#sdeRail');

        const monthLabel = $('#sdeMonth option:selected').text();
        $('#sdeRailTitle').text(monthLabel);

        let totalSlots = 0, totalFilled = 0;

        const rows = dates.map(date => {
            const slots = visibleSlots(date);
            if (!slots.length) return null;
            const filled = slots.filter(s => s.entry).length;
            totalSlots += slots.length;
            totalFilled += filled;
            const pct = Math.round((filled / slots.length) * 100);
            const d = new Date(date + 'T00:00:00');
            const barClass = pct === 100 ? 'is-done' : (pct === 0 ? 'is-empty' : '');

            return `
                <button type="button" class="sde-date-item ${state.activeDate === date ? 'active' : ''}" data-date="${date}">
                    <span class="sde-date-num">
                        <b>${d.getDate()}</b>
                        <span>${d.toLocaleString('en', { month: 'short' })}</span>
                    </span>
                    <span>
                        <span class="sde-date-dow">${d.toLocaleString('en', { weekday: 'long' })}</span>
                        <span class="sde-date-progress ${barClass}"><span style="width:${pct}%"></span></span>
                    </span>
                    <span class="sde-date-count">${filled}/${slots.length}</span>
                </button>
            `;
        }).filter(Boolean);

        $('#sdeRailCount').text(rows.length);

        const allDatesRow = `
            <button type="button" class="sde-date-item ${!state.activeDate ? 'active' : ''}" data-date="">
                <span class="sde-date-num"><b><i class="ri-list-check-2"></i></b></span>
                <span><span class="sde-date-dow">All Dates</span></span>
                <span class="sde-date-count">${totalFilled}/${totalSlots}</span>
            </button>
        `;

        if (!rows.length) {
            rail.html(allDatesRow + `
                <div class="sde-empty py-4">
                    <i class="ri-calendar-close-line"></i>
                    <div class="fs-12">No planned dates match the current filters.</div>
                </div>
            `);
            return;
        }

        rail.html(allDatesRow + rows.join(''));
    }

    // ----------------------------------------------------------------------
    // 6. RENDER — DAY BOARD
    // ----------------------------------------------------------------------
    // groups slots by platform and returns the board HTML for them (used for
    // both the single-date view and the "All Dates" aggregated view)
    function renderSlotGroups(slots) {
        // in "All Clients" mode, split groups per client too, so the header
        // can read e.g. "Instagram - Acme Retail Pvt Ltd" instead of mixing clients
        const groups = {};
        slots.forEach(s => {
            const key = state.clientId ? s.platformId : s.platformId + '|' + s.clientId;
            (groups[key] = groups[key] || []).push(s);
        });

        let html = '';
        Object.keys(groups).forEach(key => {
            const list = groups[key];
            const platform = platformById(list[0].platformId);
            const groupLabel = state.clientId
                ? esc(platform.name)
                : `${esc(platform.name)} - ${esc(clientById(list[0].clientId).name)}`;

            html += `
                <div class="sde-group">
                    <div class="sde-group-head">
                        <i class="${platform.icon} sde-plat-icon"></i>
                        ${groupLabel}
                        <span class="badge bg-light text-default ms-auto fw-normal">${list.length} slot${list.length > 1 ? 's' : ''}</span>
                    </div>
                    <div class="sde-slot-head">
                        <span>Feature</span><span>Content</span><span>Status</span><span>Last Updated</span><span class="text-end">Action</span>
                    </div>
            `;

            list.forEach(s => {
                const feature = featureById(s.platformId, s.featureId);
                const e = s.entry;

                const content = e
                    ? `<div class="sde-title">${esc(e.title)}</div>
                       <div class="sde-caption">${esc(e.caption || '—')}</div>`
                    : `<div class="sde-caption fst-italic">No data captured for this planned slot yet.</div>`;

                const meta = e
                    ? `<div class="sde-meta">${esc(e.updatedBy)}<br>${esc(e.updatedAt)}</div>`
                    : `<div class="sde-meta">—</div>`;

                const actions = e
                    ? `<button class="sde-icon-btn sde-edit" data-id="${e.id}" title="Edit"><i class="ri-pencil-line"></i></button>
                       <button class="sde-icon-btn danger sde-delete" data-id="${e.id}" title="Delete"><i class="ri-delete-bin-line"></i></button>`
                    : `<button class="sde-icon-btn sde-fill" data-platform="${s.platformId}" data-feature="${s.featureId}" title="Add data"><i class="ri-add-line"></i></button>`;

                html += `
                    <div class="sde-slot ${e ? '' : 'is-empty'}">
                        <div><span class="sde-feature" title="${esc(feature.name)}">${esc(feature.name)}</span></div>
                        <div>${content}</div>
                        <div><span class="sde-badge ${s.status}">${STATUS_LABEL[s.status]}</span></div>
                        ${meta}
                        <div class="sde-actions">${actions}</div>
                    </div>
                `;
            });

            html += `</div>`;
        });

        return html;
    }

    function renderBoard() {
        const board = $('#sdeBoard');

        if (!state.activeDate) {
            renderAllDatesBoard();
            return;
        }

        const slots = visibleSlots(state.activeDate);
        const filled = slots.filter(s => s.entry).length;
        const pending = slots.length - filled;

        $('#sdeDayTitle').html(
            `${fmtLongDate(state.activeDate)} <span class="text-muted fw-normal fs-13">· ` +
            `${new Date(state.activeDate + 'T00:00:00').toLocaleString('en', { weekday: 'long' })}</span>`
        );

        $('#sdeDayStats').html(`
            <span class="sde-stat"><i class="ri-circle-fill text-primary"></i> Planned <b>${slots.length}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-success"></i> Filled <b>${filled}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-warning"></i> Pending <b>${pending}</b></span>
        `);

        $('#sdeAddForDateBtn').prop('disabled', false);

        if (!slots.length) {
            board.html(`
                <div class="sde-empty">
                    <i class="ri-filter-off-line"></i>
                    <div>Nothing on this date matches the current filters.</div>
                </div>
            `);
            return;
        }

        board.html(renderSlotGroups(slots));
    }

    // aggregated view across every planned date in the month (and, when
    // "All Clients" is selected, across every client too)
    function renderAllDatesBoard() {
        const board = $('#sdeBoard');
        const plan = getPlan(state.clientId, state.month);
        const dates = Object.keys(plan).sort();

        const dateSlots = dates
            .map(date => ({ date: date, slots: visibleSlots(date) }))
            .filter(d => d.slots.length);

        let totalSlots = 0, totalFilled = 0;
        dateSlots.forEach(d => {
            totalSlots += d.slots.length;
            totalFilled += d.slots.filter(s => s.entry).length;
        });

        $('#sdeDayTitle').text('All Dates');
        $('#sdeDayStats').html(`
            <span class="sde-stat"><i class="ri-circle-fill text-primary"></i> Planned <b>${totalSlots}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-success"></i> Filled <b>${totalFilled}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-warning"></i> Pending <b>${totalSlots - totalFilled}</b></span>
        `);

        // adding an entry needs one specific date, picked from the rail
        $('#sdeAddForDateBtn').prop('disabled', true);

        if (!dateSlots.length) {
            board.html(`
                <div class="sde-empty">
                    <i class="ri-filter-off-line"></i>
                    <div>Nothing matches the current filters.</div>
                </div>
            `);
            return;
        }

        let html = '';
        dateSlots.forEach(d => {
            html += `<div class="mb-3">
                <div class="fw-semibold fs-13 mb-2">${esc(fmtLongDate(d.date))}</div>
                ${renderSlotGroups(d.slots)}
            </div>`;
        });

        board.html(html);
    }

    function renderAll() {
        seedEntries(state.clientId, state.month);

        const plan = getPlan(state.clientId, state.month);
        const dates = Object.keys(plan).sort();

        // drop the active date if it no longer exists under the current filters;
        // otherwise leave it as-is (including null, which means "All Dates")
        if (state.activeDate && dates.indexOf(state.activeDate) === -1) {
            state.activeDate = null;
        }

        renderRail();
        renderBoard();
    }

    // ----------------------------------------------------------------------
    // 7. ENTRY FORM
    // ----------------------------------------------------------------------
    function fillFeatureOptions(platformId, selectedFeatureId) {
        const feats = DUMMY_DB.features[Number(platformId)] || [];
        $('#sdeFormFeature').html(
            feats.map(f => `<option value="${f.id}" ${Number(selectedFeatureId) === f.id ? 'selected' : ''}>${esc(f.name)}</option>`).join('')
        );
    }

    function openEntryModal(mode, payload) {
        payload = payload || {};

        if (!state.clientId) {
            notify('warning', 'Select a client before adding an entry.');
            return;
        }

        state.editingId = mode === 'edit' ? payload.id : null;
        formDirty = false;

        $('#sdeEntryTitle').html(mode === 'edit'
            ? '<i class="ri-edit-box-line me-2 text-primary"></i> Update Entry'
            : '<i class="ri-add-box-line me-2 text-primary"></i> Add Entry');

        $('#sdeScopeStrip').html(`
            <span>Client: <b>${esc($('#sdeClient option:selected').text())}</b></span>
            <span class="text-muted">·</span>
            <span>Month: <b>${esc($('#sdeMonth option:selected').text())}</b></span>
        `);

        $('#sdeFormPlatform').html(
            DUMMY_DB.platforms.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')
        );

        const date = payload.date || state.activeDate || '';
        const platformId = payload.platformId || DUMMY_DB.platforms[0].id;

        $('#sdeFormDate').val(date);
        $('#sdeFormPlatform').val(platformId);
        fillFeatureOptions(platformId, payload.featureId);

        $('#sdeFormTitle').val(payload.title || '');
        $('#sdeFormStatus').val(payload.status || 'draft');
        $('#sdeFormCaption').val(payload.caption || '');
        $('#sdeFormLink').val(payload.link || '');
        $('#sdeFormRemarks').val(payload.remarks || '');

        $('.sde-form .is-invalid').removeClass('is-invalid');

        if (!entryModal) {
            entryModal = new bootstrap.Modal(document.getElementById('sdeEntryModal'));
        }
        entryModal.show();

        // flatpickr is already loaded by includes/footer.php
        const dateInput = document.getElementById('sdeFormDate');
        if (dateInput._flatpickr) dateInput._flatpickr.destroy();
        flatpickr(dateInput, { dateFormat: 'Y-m-d', defaultDate: date || null });
    }

    function validateForm() {
        const errors = [];
        $('.sde-form .is-invalid').removeClass('is-invalid');

        const date = $('#sdeFormDate').val().trim();
        const title = $('#sdeFormTitle').val().trim();

        if (!date) { $('#sdeFormDate').addClass('is-invalid'); errors.push('Date is required.'); }
        if (!title) { $('#sdeFormTitle').addClass('is-invalid'); errors.push('Content title is required.'); }
        if (title && title.length < 3) { $('#sdeFormTitle').addClass('is-invalid'); errors.push('Content title is too short.'); }

        const link = $('#sdeFormLink').val().trim();
        if (link && !/^https?:\/\//i.test(link)) {
            $('#sdeFormLink').addClass('is-invalid');
            errors.push('Reference link must start with http:// or https://');
        }

        return errors;
    }

    /* TODO(api): replace with POST api/saveSocialContent.php */
    function persistEntry(record) {
        if (state.editingId) {
            const idx = entries.findIndex(e => e.id === state.editingId);
            if (idx > -1) entries[idx] = { ...entries[idx], ...record };
            notify('success', 'Entry updated successfully.');
        } else {
            entries.push({ id: entrySeq++, ...record });
            notify('success', 'Entry added successfully.');
        }

        entryModal.hide();
        state.activeDate = record.date;
        state.month = record.date.slice(0, 7);
        if ($('#sdeMonth').val() !== state.month) $('#sdeMonth').val(state.month);
        renderAll();
    }

    function saveEntry() {
        const errors = validateForm();
        if (errors.length) {
            notify('danger', errors[0]);
            return;
        }

        const date = $('#sdeFormDate').val().trim();
        const platformId = Number($('#sdeFormPlatform').val());
        const featureId = Number($('#sdeFormFeature').val());

        // duplicate guard — one entry per date / platform / feature / client
        const clash = findEntry(state.clientId, date, platformId, featureId);
        if (clash && clash.id !== state.editingId) {
            notify('danger', 'An entry already exists for this date, platform and feature. Edit that entry instead.');
            return;
        }

        const record = {
            clientId: state.clientId,
            date: date,
            platformId: platformId,
            featureId: featureId,
            title: $('#sdeFormTitle').val().trim(),
            caption: $('#sdeFormCaption').val().trim(),
            link: $('#sdeFormLink').val().trim(),
            status: $('#sdeFormStatus').val(),
            remarks: $('#sdeFormRemarks').val().trim(),
            updatedBy: 'You',
            updatedAt: new Date().toISOString().slice(0, 16).replace('T', ' ')
        };

        // off-plan guard — warn, but let the user proceed
        const plan = getPlan(state.clientId, date.slice(0, 7));
        const planned = (plan[date] || []).some(s => s.platformId === platformId && s.featureId === featureId);

        if (!planned) {
            confirmDialog({
                title: 'Slot is not in the calendar plan',
                html: `<b>${esc(featureById(platformId, featureId).name)}</b> on <b>${esc(fmtLongDate(date))}</b> ` +
                      `is not planned for this client. Save it anyway as an extra deliverable?`,
                icon: 'warning',
                confirmText: 'Save anyway',
                color: '#f7b731'
            }).then(res => {
                if (!res.isConfirmed) {
                    notify('info', 'Save cancelled. Nothing was changed.');
                    return;
                }
                persistEntry(record);
                notify('warning', 'Saved as an off-plan entry — update the Calendar Planner to keep both in sync.');
            });
            return;
        }

        persistEntry(record);
    }

    /* TODO(api): replace with POST api/deleteSocialContent.php */
    function deleteEntry(id) {
        const entry = entries.find(e => e.id === id);
        if (!entry) {
            notify('danger', 'Entry not found. Refresh and try again.');
            return;
        }

        confirmDialog({
            title: 'Delete this entry?',
            html: `<b>${esc(entry.title)}</b><br><span class="text-muted">` +
                  `${esc(platformById(entry.platformId).name)} · ${esc(featureById(entry.platformId, entry.featureId).name)} · ` +
                  `${esc(fmtLongDate(entry.date))}</span><br><br>This cannot be undone.`,
            icon: 'warning',
            confirmText: 'Delete'
        }).then(res => {
            if (!res.isConfirmed) return;
            entries = entries.filter(e => e.id !== id);
            notify('success', 'Entry deleted. The planned slot is now open again.');
            renderAll();
        });
    }

    // ----------------------------------------------------------------------
    // 8. EVENTS
    // ----------------------------------------------------------------------
    $('#sdeClient').on('change', function () {
        const val = $(this).val();
        state.clientId = val ? Number(val) : '';
        state.activeDate = null;
        renderAll();
        notify('info', 'Switched to ' + $('#sdeClient option:selected').text() + '.');
    });

    $('#sdeMonth').on('change', function () {
        state.month = $(this).val();
        state.activeDate = null;
        renderAll();
    });

    $('#sdePlatform').on('change', function () { state.platform = $(this).val(); renderAll(); });
    $('#sdeStatus').on('change', function () { state.status = $(this).val(); renderAll(); });

    let searchTimer = null;
    $('#sdeSearch').on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.search = val; renderAll(); }, 250);
    });

    $('#sdeResetBtn').on('click', function () {
        $('#sdePlatform').val('');
        $('#sdeStatus').val('');
        $('#sdeSearch').val('');
        state.platform = state.status = state.search = '';
        renderAll();
        notify('info', 'Filters reset.');
    });

    $('#sdeRail').on('click', '.sde-date-item', function () {
        state.activeDate = $(this).data('date') || null;
        renderRail();
        renderBoard();
    });

    $('#sdeAddForDateBtn').on('click', function () {
        openEntryModal('add', { date: state.activeDate });
    });

    // "Add Entry" now opens the full-page Social Media Overview inside a
    // full-screen modal; that page's own "Fill Now" opens its own data-entry
    // modal on top of it — nothing to duplicate here.
    $('#sdeAddBtn').on('click', function () {
        if (!overviewModal) overviewModal = new bootstrap.Modal(document.getElementById('sdeOverviewModal'));
        $('#sdeOverviewFrame').attr('src', 'social-overview?embed=1');
        overviewModal.show();
    });

    $('#sdeOverviewModal').on('hidden.bs.modal', function () {
        $('#sdeOverviewFrame').attr('src', 'about:blank');
    });

    // the embedded Overview page's own "Close" button asks us to close its modal
    window.addEventListener('message', function (e) {
        if (e.origin === window.location.origin && e.data && e.data.type === 'sde-overview-close' && overviewModal) {
            overviewModal.hide();
        }
    });

    $('#sdeBoard').on('click', '.sde-fill', function () {
        openEntryModal('add', {
            date: state.activeDate,
            platformId: $(this).data('platform'),
            featureId: $(this).data('feature')
        });
    });

    $('#sdeBoard').on('click', '.sde-edit', function () {
        const entry = entries.find(e => e.id === $(this).data('id'));
        if (!entry) { notify('danger', 'Entry not found.'); return; }
        openEntryModal('edit', entry);
    });

    $('#sdeBoard').on('click', '.sde-delete', function () {
        deleteEntry($(this).data('id'));
    });

    $('#sdeFormPlatform').on('change', function () { fillFeatureOptions($(this).val()); });
    $('#sdeSaveBtn').on('click', saveEntry);

    // dirty tracking so a half-typed entry is never lost by a stray click
    $('#sdeEntryModal').on('input change', 'input, select, textarea', function () { formDirty = true; });

    $('#sdeCancelBtn').on('click', function () {
        if (!formDirty) { entryModal.hide(); return; }
        confirmDialog({
            title: 'Discard changes?',
            html: 'The details you entered will not be saved.',
            icon: 'question',
            confirmText: 'Discard',
            color: '#dc3545'
        }).then(res => {
            if (res.isConfirmed) { formDirty = false; entryModal.hide(); }
        });
    });

    $('#sdeEntryModal').on('hidden.bs.modal', function () {
        formDirty = false;
        state.editingId = null;
    });

    // ----------------------------------------------------------------------
    // 9. INITIAL RENDER
    // ----------------------------------------------------------------------
    renderAll();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
