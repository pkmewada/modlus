<?php
/*
|--------------------------------------------------------------------------
| Social Media — Client Health Overview (DUMMY / FRONTEND ONLY)
|--------------------------------------------------------------------------
|
| UI prototype only. All data below is fabricated in DUMMY_DB in the
| script — there are no AJAX calls and no DB writes.
|
| Problem this solves: pages/social-data-entry.php is a per-client editor.
| At 50+ clients x 5 platforms x 2+ features, nobody should have to open
| every client to find out who is behind. This page is the bird's-eye
| dashboard that sits in FRONT of that editor:
|
|   1. Client Health Matrix — client x platform completion heatmap.
|   2. Pending Queue        — every unfilled slot, across every client,
|                             sorted soonest-first.
|
| "Filled" here means a content record exists (data entry done), same
| definition social-data-entry.php uses — it is not about publish status.
|
*/
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* ==========================================================================
   Social Overview — scoped styles (sov-*). Theme tokens only.
   ========================================================================== */

.sov-filterbar label {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.2rem;
}

.sov-filterbar .form-select,
.sov-filterbar .form-control { border-radius: 30px; }

.sov-check {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: var(--default-text-color);
    white-space: nowrap;
}

/* ---------- KPI chips ---------- */
.sov-kpis {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.sov-kpi {
    border: 1px solid var(--default-border);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    background: var(--custom-white);
    cursor: pointer;
    transition: 0.15s;
}

.sov-kpi:hover { border-color: var(--primary-border); background: var(--primary005, rgba(var(--primary-rgb), 0.04)); }
.sov-kpi.active { border-color: var(--primary-color); background: var(--primary01); }

.sov-kpi .num { font-size: 1.4rem; font-weight: 700; line-height: 1.1; color: var(--default-text-color); }
.sov-kpi .lbl { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
.sov-kpi.good .num { color: rgb(var(--success-rgb)); }
.sov-kpi.warn .num { color: rgb(var(--warning-rgb)); }
.sov-kpi.bad  .num { color: rgb(var(--danger-rgb)); }

@media (max-width: 991.98px) {
    .sov-kpis { grid-template-columns: repeat(2, 1fr); }
}

/* ---------- Matrix ---------- */
.sov-matrix-wrap { overflow-x: auto; }

.sov-matrix {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
    min-width: 720px;
}

.sov-matrix thead th {
    position: sticky;
    top: 0;
    background: var(--default-background);
    border-bottom: 2px solid var(--default-border);
    padding: 0.55rem 0.7rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-muted);
    white-space: nowrap;
    z-index: 1;
}

.sov-matrix th.sortable { cursor: pointer; user-select: none; }
.sov-matrix th.sortable:hover { color: var(--primary-color); }
.sov-matrix th .sov-sort-icon { font-size: 0.65rem; margin-left: 3px; opacity: 0.5; }
.sov-matrix th.sort-active .sov-sort-icon { opacity: 1; color: var(--primary-color); }

.sov-matrix td {
    padding: 0.5rem 0.7rem;
    border-bottom: 1px solid var(--default-border);
    vertical-align: middle;
}

.sov-matrix tbody tr:hover { background: var(--primary005, rgba(var(--primary-rgb), 0.035)); }

.sov-client-cell {
    cursor: pointer;
    min-width: 190px;
}

.sov-client-name { font-weight: 600; color: var(--default-text-color); }
.sov-client-code { font-size: 0.7rem; color: var(--text-muted); }

.sov-cell {
    text-align: center;
    cursor: pointer;
    border-radius: 8px;
}

.sov-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.18rem 0.6rem;
    border-radius: 30px;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
}

.sov-pill.good { background: rgba(var(--success-rgb), 0.13); color: rgb(var(--success-rgb)); }
.sov-pill.warn { background: rgba(var(--warning-rgb), 0.15); color: rgb(var(--warning-rgb)); }
.sov-pill.bad  { background: rgba(var(--danger-rgb), 0.12);  color: rgb(var(--danger-rgb)); }
.sov-pill.na   { background: var(--default-background); color: var(--text-muted); border: 1px dashed var(--default-border); }

.sov-overall-cell { min-width: 150px; }
.sov-overall-bar {
    margin-top: 4px;
    height: 5px;
    border-radius: 5px;
    background: var(--default-border);
    overflow: hidden;
}
.sov-overall-bar span { display: block; height: 100%; border-radius: 5px; }
.sov-overall-bar.good span { background: rgb(var(--success-rgb)); }
.sov-overall-bar.warn span { background: rgb(var(--warning-rgb)); }
.sov-overall-bar.bad span  { background: rgb(var(--danger-rgb)); }

/* ---------- scope chip above the queue ---------- */
.sov-scope-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary01);
    border: 1px solid var(--primary-border);
    color: var(--primary-color);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.3rem 0.6rem 0.3rem 0.9rem;
    border-radius: 30px;
}

.sov-scope-chip button {
    border: none;
    background: none;
    color: inherit;
    font-size: 0.95rem;
    line-height: 1;
    cursor: pointer;
    opacity: 0.75;
}
.sov-scope-chip button:hover { opacity: 1; }

/* ---------- Queue ---------- */
.sov-queue-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    align-items: center;
}

.sov-queue-toolbar .form-select,
.sov-queue-toolbar .form-control {
    border-radius: 30px;
    font-size: 0.82rem;
}

.sov-queue-table-wrap { overflow-x: auto; }

.sov-queue {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.83rem;
    min-width: 760px;
}

.sov-queue thead th {
    padding: 0.5rem 0.7rem;
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-muted);
    border-bottom: 2px solid var(--default-border);
    white-space: nowrap;
}

.sov-queue td {
    padding: 0.55rem 0.7rem;
    border-bottom: 1px solid var(--default-border);
    vertical-align: middle;
}

.sov-queue tbody tr:hover { background: var(--primary005, rgba(var(--primary-rgb), 0.035)); }
.sov-queue tbody tr.is-overdue { background: rgba(var(--danger-rgb), 0.045); }

.sov-flag {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.6rem;
    border-radius: 30px;
    white-space: nowrap;
}
.sov-flag.overdue  { background: rgba(var(--danger-rgb), 0.13); color: rgb(var(--danger-rgb)); }
.sov-flag.today    { background: rgba(var(--warning-rgb), 0.16); color: rgb(var(--warning-rgb)); }
.sov-flag.upcoming { background: rgba(var(--info-rgb), 0.13); color: rgb(var(--info-rgb)); }
.sov-flag.filled   { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }

.sov-feature-chip {
    display: inline-block;
    padding: 0.1rem 0.6rem;
    border-radius: 30px;
    background: var(--primary01);
    color: var(--primary-color);
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}

.sov-icon-btn {
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
.sov-icon-btn:hover { background: var(--primary01); color: var(--primary-color); border-color: var(--primary-border); }
.sov-icon-btn.danger:hover { background: rgba(var(--danger-rgb), 0.1); color: rgb(var(--danger-rgb)); border-color: rgba(var(--danger-rgb), 0.3); }

.sov-actions { display: flex; gap: 0.25rem; justify-content: flex-end; }

.sov-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--text-muted);
}
.sov-empty i { font-size: 2.4rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }

.sov-loadmore-wrap { text-align: center; padding: 0.9rem 0; }

/* entry modal scope strip */
.sov-scope-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.6rem 0.8rem;
    border-radius: 12px;
    background: var(--default-background);
    border: 1px solid var(--default-border);
    margin-bottom: 1rem;
}
.sov-scope-strip span { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
.sov-scope-strip b { color: var(--default-text-color); }

@media (max-width: 991.98px) {
    .sov-queue thead th:nth-child(3),
    .sov-queue td:nth-child(3) { display: none; } /* hide Platform col on small screens, still in Feature chip title */
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- ============================ PAGE HEADER ============================ -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Social Media — Client Health Overview</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Overview</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="calendar" class="btn btn-light btn-sm">
                    <i class="ri-calendar-event-line me-1"></i> Calendar Planner
                </a>
                <a href="social-data-entry" class="btn btn-light btn-sm">
                    <i class="ri-edit-box-line me-1"></i> Open Client Editor
                </a>
            </div>
        </div>

        <!-- ============================ KPI CHIPS ============================ -->
        <div class="sov-kpis">
            <div class="sov-kpi" id="kpiTotal" data-kpi="">
                <div class="num" id="kpiTotalNum">0</div>
                <div class="lbl">Total Clients</div>
            </div>
            <div class="sov-kpi good" id="kpiComplete" data-kpi="complete">
                <div class="num" id="kpiCompleteNum">0</div>
                <div class="lbl">Fully Complete</div>
            </div>
            <div class="sov-kpi warn" id="kpiAttention" data-kpi="attention">
                <div class="num" id="kpiAttentionNum">0</div>
                <div class="lbl">Needs Attention</div>
            </div>
            <div class="sov-kpi bad" id="kpiCritical" data-kpi="critical">
                <div class="num" id="kpiCriticalNum">0</div>
                <div class="lbl">Critical (&lt;50%)</div>
            </div>
            <div class="sov-kpi" id="kpiNoPlan" data-kpi="noplan">
                <div class="num" id="kpiNoPlanNum">0</div>
                <div class="lbl">No Calendar Plan</div>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card sov-filterbar">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-2 col-md-4">
                        <label for="sovMonth">Month</label>
                        <select class="form-select form-select-sm" id="sovMonth"></select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="sovPlatform">Platform</label>
                        <select class="form-select form-select-sm" id="sovPlatform">
                            <option value="">All Platforms</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="sovClientSearch">Find Client</label>
                        <input type="text" class="form-control form-control-sm" id="sovClientSearch" placeholder="Search by client name...">
                    </div>
                    <div class="col-xl-3 col-md-6 d-flex align-items-center pt-3">
                        <label class="sov-check mb-0">
                            <input type="checkbox" class="form-check-input" id="sovAttentionOnly">
                            Only clients needing attention
                        </label>
                    </div>
                    <div class="col-xl-2 col-md-6 d-flex justify-content-md-end pt-3">
                        <button class="btn btn-light btn-sm" id="sovResetBtn">
                            <i class="ri-refresh-line me-1"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================ CLIENT HEALTH MATRIX ============================ -->
        <div class="card custom-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
                <div class="card-title mb-0 fs-14">
                    <i class="ri-grid-line me-1 text-primary"></i> Client Health Matrix
                </div>
                <span class="text-muted fs-12" id="sovMatrixHint">Click a row or a cell to filter the queue below.</span>
            </div>
            <div class="card-body">
                <div class="sov-matrix-wrap">
                    <table class="sov-matrix">
                        <thead>
                            <tr id="sovMatrixHeadRow"></tr>
                        </thead>
                        <tbody id="sovMatrixBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================ PENDING QUEUE ============================ -->
        <div class="card custom-card" id="sovQueueSection">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="card-title mb-0 fs-14">
                        <i class="ri-list-check-3 me-1 text-primary"></i> Pending Queue
                    </div>
                    <span class="badge bg-light text-default" id="sovQueueCount">0</span>
                    <span id="sovScopeChipWrap"></span>
                </div>
                <div class="sov-queue-toolbar">
                    <select class="form-select form-select-sm" id="sovQueueClient" style="min-width:170px;">
                        <option value="">All Clients</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" id="sovQueueSearch"
                           placeholder="Search feature or title..." style="min-width:200px;">
                    <label class="sov-check mb-0">
                        <input type="checkbox" class="form-check-input" id="sovShowOnlyPending" checked>
                        Only pending
                    </label>
                </div>
            </div>
            <div class="card-body">
                <div class="sov-queue-table-wrap">
                    <table class="sov-queue">
                        <thead>
                            <tr>
                                <th class="sortable" id="sovSortDate">Date <i class="ri-arrow-up-line sov-sort-icon"></i></th>
                                <th>Client</th>
                                <th>Platform</th>
                                <th>Feature</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="sovQueueBody"></tbody>
                    </table>
                </div>
                <div class="sov-loadmore-wrap" id="sovLoadMoreWrap">
                    <button class="btn btn-outline-primary btn-sm" id="sovLoadMoreBtn">
                        <i class="ri-arrow-down-line me-1"></i> Load More
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================ ENTRY MODAL ============================ -->
<div class="modal fade" id="sovEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="sovEntryTitle">
                    <i class="ri-edit-box-line me-2 text-primary"></i> Add Entry
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="sov-scope-strip" id="sovModalScope"></div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="sovFormTitle">Content Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sovFormTitle" maxlength="120"
                               placeholder="e.g. Independence Day creative">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sovFormStatus">Status</label>
                        <select class="form-select" id="sovFormStatus">
                            <option value="draft">Draft</option>
                            <option value="ready">Ready</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="sovFormCaption">Caption / Description</label>
                        <textarea class="form-control" id="sovFormCaption" rows="3"
                                  placeholder="Caption, hashtags, copy notes..."></textarea>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="sovFormLink">Creative / Reference Link</label>
                        <input type="url" class="form-control" id="sovFormLink" placeholder="https://drive.google.com/...">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="sovFormRemarks">Remarks</label>
                        <input type="text" class="form-control" id="sovFormRemarks" maxlength="120"
                               placeholder="Internal note (optional)">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" id="sovCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="sovSaveBtn">
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
   FRONTEND-ONLY PROTOTYPE — DUMMY_DB is fabricated in the browser.
   Nothing here is persisted. Four TODO(api) markers show where real
   endpoints slot in later.
   ========================================================================== */
$(function () {

    // ----------------------------------------------------------------------
    // 1. DUMMY DATA
    // ----------------------------------------------------------------------
    const PLATFORMS = [
        { id: 1, name: 'Instagram', icon: 'ri-instagram-line' },
        { id: 2, name: 'Facebook',  icon: 'ri-facebook-circle-line' },
        { id: 3, name: 'LinkedIn',  icon: 'ri-linkedin-box-line' },
        { id: 4, name: 'Pinterest', icon: 'ri-pinterest-line' },
        { id: 5, name: 'YouTube',   icon: 'ri-youtube-line' }
    ];

    const FEATURES = {
        1: [ { id: 101, name: 'Static Post' }, { id: 102, name: 'Reel' },  { id: 103, name: 'Story' } ],
        2: [ { id: 201, name: 'Static Post' }, { id: 202, name: 'Video Post' } ],
        3: [ { id: 301, name: 'Article' },     { id: 302, name: 'Carousel' } ],
        4: [ { id: 401, name: 'Pin' },          { id: 402, name: 'Idea Pin' } ],
        5: [ { id: 501, name: 'Video' },        { id: 502, name: 'Shorts' } ]
    };

    function seeded(seed) {
        let s = seed % 2147483647;
        if (s <= 0) s += 2147483646;
        return function () { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; };
    }

    // Deterministic 50-client roster, no manual typing of 50 names.
    const NAME_PREFIX = ['Acme','Blue Ocean','Nova','Silver Leaf','Prime','Sunrise','Urban','Crestline','Bright','Northgate',
                          'Golden','Riverside','Vertex','Skyline','Meadow','Copper','Pinehill','Coastal','Everline','Bright Peak'];
    const NAME_TYPE = ['Retail','Foods','Fitness Studio','Apparel','Realty','Clinic','Academy','Tech','Beauty Bar','Travels',
                        'Interiors','Motors','Bakery','Consulting','Media House'];
    const NAME_SUFFIX = ['Pvt Ltd','& Co','Group','Enterprises','LLP'];

    const CLIENTS = [];
    for (let i = 1; i <= 52; i++) {
        const name = NAME_PREFIX[i % NAME_PREFIX.length] + ' ' +
                     NAME_TYPE[(i * 3) % NAME_TYPE.length] + ' ' +
                     NAME_SUFFIX[(i * 7) % NAME_SUFFIX.length];
        CLIENTS.push({ id: i, name: name, code: 'CL' + String(i).padStart(3, '0') });
    }

    // Each client only runs a subset of the 5 platforms (mirrors real
    // per-client "allowed platforms" from the onboarding form).
    const clientPlatformCache = {};
    function allowedPlatforms(clientId) {
        if (clientPlatformCache[clientId]) return clientPlatformCache[clientId];
        const rand = seeded(clientId * 13);
        const count = 2 + Math.floor(rand() * 3); // 2..4 platforms
        const pool = PLATFORMS.slice();
        const picked = [];
        while (picked.length < count && pool.length) {
            const idx = Math.floor(rand() * pool.length);
            picked.push(pool.splice(idx, 1)[0]);
        }
        picked.sort((a, b) => a.id - b.id);
        clientPlatformCache[clientId] = picked;
        return picked;
    }

    const STATUS_LABEL = { draft: 'Draft', ready: 'Ready', scheduled: 'Scheduled', posted: 'Posted' };

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

    const planCache = {};   // 'clientId|month' -> { 'YYYY-MM-DD': [{platformId, featureId}] }
    let entries = [];       // fake "clientSocialContent" table, shared across all clients
    let entrySeq = 1;
    const seededClientMonths = {};

    /* TODO(api): replace with a bulk GET across clients — e.g.
       api/get-client-calendar-plan.php called per client, or a new
       api/get-calendar-overview.php that returns every client's plan
       for the month in one call. */
    function getPlan(clientId, month) {
        const key = clientId + '|' + month;
        if (planCache[key]) return planCache[key];

        const rand = seeded(clientId * 7919 + parseInt(month.replace('-', ''), 10));
        const [y, m] = month.split('-').map(Number);
        const daysInMonth = new Date(y, m, 0).getDate();
        const plan = {};
        const platforms = allowedPlatforms(clientId);

        for (let d = 1; d <= daysInMonth; d++) {
            if (rand() > 0.42) continue;
            const dateStr = month + '-' + String(d).padStart(2, '0');
            const slots = [];
            platforms.forEach(p => {
                if (rand() > 0.55) return;
                const feats = FEATURES[p.id];
                const f = feats[Math.floor(rand() * feats.length)];
                slots.push({ platformId: p.id, featureId: f.id });
            });
            if (slots.length) plan[dateStr] = slots;
        }

        planCache[key] = plan;
        return plan;
    }

    /* TODO(api): replace with GET api/getSocialContent.php (bulk, all clients for the month) */
    function seedEntries(clientId, month) {
        const seedKey = clientId + '|' + month;
        if (seededClientMonths[seedKey]) return;
        seededClientMonths[seedKey] = true;

        const plan = getPlan(clientId, month);
        const rand = seeded(clientId * 104729 + parseInt(month.replace('-', ''), 10));
        const statuses = ['draft', 'ready', 'scheduled', 'posted'];

        Object.keys(plan).forEach(date => {
            plan[date].forEach(slot => {
                if (rand() > 0.6) return; // ~60% of planned slots already filled
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

    // ----------------------------------------------------------------------
    // 2. HELPERS
    // ----------------------------------------------------------------------
    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }

    function clientById(id) { return CLIENTS.find(c => c.id === Number(id)); }
    function platformById(id) { return PLATFORMS.find(p => p.id === Number(id)) || { name: 'Unknown', icon: 'ri-apps-line' }; }
    function featureById(platformId, featureId) {
        const list = FEATURES[Number(platformId)] || [];
        return list.find(f => f.id === Number(featureId)) || { name: 'Unknown' };
    }

    function findEntry(clientId, date, platformId, featureId) {
        return entries.find(e =>
            e.clientId === Number(clientId) && e.date === date &&
            e.platformId === Number(platformId) && e.featureId === Number(featureId)
        );
    }

    function fmtLongDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    const TODAY = new Date();
    TODAY.setHours(0, 0, 0, 0);

    function pctClass(pct) { return pct >= 90 ? 'good' : (pct >= 50 ? 'warn' : 'bad'); }

    function notify(type, message) { if (window.showToast) window.showToast(type, message); }

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
    // 3. STATE
    // ----------------------------------------------------------------------
    const state = {
        month: null,
        platform: '',          // global: narrows matrix columns + queue
        clientSearch: '',
        attentionOnly: false,
        kpiFilter: '',          // '', 'complete', 'attention', 'critical', 'noplan'
        matrixSortKey: 'overall',
        matrixSortDir: 'asc',   // worst-first by default — the whole point of the page
        scopeClientId: '',      // queue scope, settable from matrix click
        scopePlatformId: '',
        queueSearch: '',
        onlyPending: true,
        queuePage: 1,
        queuePageSize: 15
    };

    let entryModal = null;
    let formDirty = false;
    let activeModalContext = null; // { clientId, date, platformId, featureId, entryId }

    // ----------------------------------------------------------------------
    // 4. BOOTSTRAP FILTER OPTIONS
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

    $('#sovMonth').html(buildMonthOptions());
    $('#sovPlatform').append(PLATFORMS.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join(''));
    $('#sovQueueClient').append(CLIENTS.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join(''));
    state.month = $('#sovMonth').val();

    // ----------------------------------------------------------------------
    // 5. COMPUTE CLIENT HEALTH (matrix rows + pending pool), for the month
    // ----------------------------------------------------------------------
    function computeHealth() {
        const rows = [];
        const pending = [];

        CLIENTS.forEach(client => {
            seedEntries(client.id, state.month);
            const plan = getPlan(client.id, state.month);
            const platforms = allowedPlatforms(client.id);

            const perPlatform = {};
            platforms.forEach(p => { perPlatform[p.id] = { planned: 0, filled: 0 }; });

            let totalPlanned = 0, totalFilled = 0;

            Object.keys(plan).forEach(date => {
                plan[date].forEach(slot => {
                    const entry = findEntry(client.id, date, slot.platformId, slot.featureId);
                    perPlatform[slot.platformId].planned++;
                    totalPlanned++;
                    if (entry) { perPlatform[slot.platformId].filled++; totalFilled++; }

                    pending.push({
                        clientId: client.id,
                        clientName: client.name,
                        date: date,
                        platformId: slot.platformId,
                        featureId: slot.featureId,
                        entry: entry || null
                    });
                });
            });

            rows.push({
                client: client,
                platforms: platforms,
                perPlatform: perPlatform,
                totalPlanned: totalPlanned,
                totalFilled: totalFilled,
                overallPct: totalPlanned ? Math.round((totalFilled / totalPlanned) * 100) : null
            });
        });

        return { rows, pending };
    }

    // ----------------------------------------------------------------------
    // 6. RENDER — KPI CHIPS
    // ----------------------------------------------------------------------
    function renderKpis(rows) {
        const withPlan = rows.filter(r => r.totalPlanned > 0);
        const complete = withPlan.filter(r => r.overallPct === 100).length;
        const critical = withPlan.filter(r => r.overallPct < 50).length;
        const attention = withPlan.filter(r => r.overallPct < 100).length;
        const noPlan = rows.length - withPlan.length;

        $('#kpiTotalNum').text(rows.length);
        $('#kpiCompleteNum').text(complete);
        $('#kpiAttentionNum').text(attention);
        $('#kpiCriticalNum').text(critical);
        $('#kpiNoPlanNum').text(noPlan);

        $('.sov-kpi').removeClass('active');
        if (state.kpiFilter) $(`.sov-kpi[data-kpi="${state.kpiFilter}"]`).addClass('active');
    }

    // ----------------------------------------------------------------------
    // 7. RENDER — MATRIX
    // ----------------------------------------------------------------------
    function visiblePlatformColumns() {
        return state.platform ? PLATFORMS.filter(p => String(p.id) === state.platform) : PLATFORMS;
    }

    function renderMatrixHead() {
        const cols = visiblePlatformColumns();
        let html = `<th class="sortable" id="sovSortClient">Client <i class="ri-arrow-up-line sov-sort-icon"></i></th>`;
        cols.forEach(p => { html += `<th class="text-center">${esc(p.name)}</th>`; });
        html += `<th class="sortable text-center" id="sovSortOverall">Overall <i class="ri-arrow-up-line sov-sort-icon"></i></th>`;
        $('#sovMatrixHeadRow').html(html);

        $('#sovSortClient').toggleClass('sort-active', state.matrixSortKey === 'client');
        $('#sovSortOverall').toggleClass('sort-active', state.matrixSortKey === 'overall');
        const icon = state.matrixSortDir === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line';
        $('#sovSortClient .sov-sort-icon, #sovSortOverall .sov-sort-icon').attr('class', 'sov-sort-icon ' + icon);
    }

    function applyMatrixFilters(rows) {
        let filtered = rows;

        if (state.clientSearch) {
            const q = state.clientSearch.toLowerCase();
            filtered = filtered.filter(r => r.client.name.toLowerCase().indexOf(q) !== -1);
        }
        if (state.attentionOnly) {
            filtered = filtered.filter(r => r.overallPct === null || r.overallPct < 100);
        }
        if (state.kpiFilter === 'complete')  filtered = filtered.filter(r => r.overallPct === 100);
        if (state.kpiFilter === 'attention') filtered = filtered.filter(r => r.totalPlanned > 0 && r.overallPct < 100);
        if (state.kpiFilter === 'critical')  filtered = filtered.filter(r => r.totalPlanned > 0 && r.overallPct < 50);
        if (state.kpiFilter === 'noplan')    filtered = filtered.filter(r => r.totalPlanned === 0);

        filtered.sort((a, b) => {
            let cmp;
            if (state.matrixSortKey === 'client') {
                cmp = a.client.name.localeCompare(b.client.name);
            } else {
                const av = a.overallPct === null ? -1 : a.overallPct;
                const bv = b.overallPct === null ? -1 : b.overallPct;
                cmp = av - bv;
            }
            return state.matrixSortDir === 'asc' ? cmp : -cmp;
        });

        return filtered;
    }

    function renderMatrixBody(rows) {
        const cols = visiblePlatformColumns();
        const filtered = applyMatrixFilters(rows);
        const body = $('#sovMatrixBody');

        if (!filtered.length) {
            body.html(`
                <tr><td colspan="${cols.length + 2}">
                    <div class="sov-empty"><i class="ri-search-eye-line"></i>No clients match the current filters.</div>
                </td></tr>
            `);
            return;
        }

        let html = '';
        filtered.forEach(r => {
            html += `<tr>`;
            html += `
                <td class="sov-client-cell" data-client="${r.client.id}">
                    <div class="sov-client-name">${esc(r.client.name)}</div>
                    <div class="sov-client-code">${esc(r.client.code)}</div>
                </td>
            `;

            cols.forEach(p => {
                const stat = r.perPlatform[p.id];
                if (!stat) {
                    html += `<td class="sov-cell"><span class="sov-pill na">–</span></td>`;
                    return;
                }
                const pct = stat.planned ? Math.round((stat.filled / stat.planned) * 100) : null;
                const cls = pct === null ? 'na' : pctClass(pct);
                const label = pct === null ? 'No plan' : `${stat.filled}/${stat.planned}`;
                html += `
                    <td class="sov-cell" data-client="${r.client.id}" data-platform="${p.id}">
                        <span class="sov-pill ${cls}">${label}</span>
                    </td>
                `;
            });

            const overallCls = r.overallPct === null ? 'na' : pctClass(r.overallPct);
            const overallLabel = r.overallPct === null ? 'No calendar plan' : `${r.overallPct}% · ${r.totalFilled}/${r.totalPlanned}`;
            html += `
                <td class="sov-overall-cell" data-client="${r.client.id}">
                    <span class="sov-pill ${overallCls}">${overallLabel}</span>
                    ${r.overallPct !== null ? `<div class="sov-overall-bar ${overallCls}"><span style="width:${r.overallPct}%"></span></div>` : ''}
                </td>
            `;
            html += `</tr>`;
        });

        body.html(html);
    }

    // ----------------------------------------------------------------------
    // 8. RENDER — SCOPE CHIP
    // ----------------------------------------------------------------------
    function renderScopeChip() {
        if (!state.scopeClientId) { $('#sovScopeChipWrap').empty(); return; }
        const client = clientById(state.scopeClientId);
        const platformLabel = state.scopePlatformId ? ' · ' + platformById(state.scopePlatformId).name : '';
        $('#sovScopeChipWrap').html(`
            <span class="sov-scope-chip">
                Scope: <b>${esc(client ? client.name : 'Unknown')}${esc(platformLabel)}</b>
                <button type="button" id="sovClearScopeBtn" title="Clear scope"><i class="ri-close-line"></i></button>
            </span>
        `);
    }

    // ----------------------------------------------------------------------
    // 9. RENDER — QUEUE
    // ----------------------------------------------------------------------
    function applyQueueFilters(pending) {
        let list = pending;

        if (state.platform) list = list.filter(p => String(p.platformId) === state.platform);
        if (state.scopeClientId) list = list.filter(p => String(p.clientId) === String(state.scopeClientId));
        if (state.scopePlatformId) list = list.filter(p => String(p.platformId) === String(state.scopePlatformId));
        if ($('#sovQueueClient').val()) list = list.filter(p => String(p.clientId) === $('#sovQueueClient').val());
        if (state.onlyPending) list = list.filter(p => !p.entry);

        if (state.queueSearch) {
            const q = state.queueSearch.toLowerCase();
            list = list.filter(p => {
                const feature = featureById(p.platformId, p.featureId).name;
                const title = p.entry ? p.entry.title : '';
                return (feature + ' ' + title).toLowerCase().indexOf(q) !== -1;
            });
        }

        list = list.slice().sort((a, b) => a.date < b.date ? -1 : (a.date > b.date ? 1 : 0));
        return list;
    }

    function flagFor(item) {
        if (item.entry) {
            return { cls: 'filled', label: STATUS_LABEL[item.entry.status] || 'Filled' };
        }
        const d = new Date(item.date + 'T00:00:00');
        if (d < TODAY) return { cls: 'overdue', label: 'Overdue' };
        if (d.getTime() === TODAY.getTime()) return { cls: 'today', label: 'Due Today' };
        return { cls: 'upcoming', label: 'Upcoming' };
    }

    function renderQueue(pending) {
        const list = applyQueueFilters(pending);
        $('#sovQueueCount').text(list.length);

        const visible = list.slice(0, state.queuePage * state.queuePageSize);
        const body = $('#sovQueueBody');

        if (!list.length) {
            body.html(`
                <tr><td colspan="6">
                    <div class="sov-empty">
                        <i class="ri-checkbox-circle-line"></i>
                        Nothing pending here — everything in scope is filled.
                    </div>
                </td></tr>
            `);
            $('#sovLoadMoreWrap').hide();
            return;
        }

        let html = '';
        visible.forEach(item => {
            const client = clientById(item.clientId);
            const platform = platformById(item.platformId);
            const feature = featureById(item.platformId, item.featureId);
            const flag = flagFor(item);
            const isOverdue = flag.cls === 'overdue';

            const actionBtn = item.entry
                ? `<button class="sov-icon-btn sov-edit" data-entry="${item.entry.id}" title="Edit"><i class="ri-pencil-line"></i></button>
                   <button class="sov-icon-btn danger sov-delete" data-entry="${item.entry.id}" title="Delete"><i class="ri-delete-bin-line"></i></button>`
                : `<button class="btn btn-primary btn-sm sov-fill"
                        data-client="${item.clientId}" data-date="${item.date}"
                        data-platform="${item.platformId}" data-feature="${item.featureId}">
                       <i class="ri-add-line"></i> Fill Now
                   </button>`;

            html += `
                <tr class="${isOverdue ? 'is-overdue' : ''}">
                    <td class="text-nowrap">${fmtLongDate(item.date)}</td>
                    <td>${esc(client ? client.name : 'Unknown')}</td>
                    <td><i class="${platform.icon} me-1 text-muted"></i>${esc(platform.name)}</td>
                    <td><span class="sov-feature-chip">${esc(feature.name)}</span></td>
                    <td><span class="sov-flag ${flag.cls}">${flag.label}</span></td>
                    <td><div class="sov-actions">${actionBtn}</div></td>
                </tr>
            `;
        });

        body.html(html);
        $('#sovLoadMoreWrap').toggle(visible.length < list.length);
    }

    // ----------------------------------------------------------------------
    // 10. MASTER RENDER
    // ----------------------------------------------------------------------
    let currentHealth = { rows: [], pending: [] };

    function renderAll() {
        currentHealth = computeHealth();
        renderKpis(currentHealth.rows);
        renderMatrixHead();
        renderMatrixBody(currentHealth.rows);
        renderScopeChip();
        renderQueue(currentHealth.pending);
    }

    // ----------------------------------------------------------------------
    // 11. ENTRY FORM (reused for both "Fill Now" and "Edit")
    // ----------------------------------------------------------------------
    function openEntryModal(mode, ctx) {
        activeModalContext = ctx;
        formDirty = false;

        const client = clientById(ctx.clientId);
        const platform = platformById(ctx.platformId);
        const feature = featureById(ctx.platformId, ctx.featureId);

        $('#sovEntryTitle').html(mode === 'edit'
            ? '<i class="ri-edit-box-line me-2 text-primary"></i> Update Entry'
            : '<i class="ri-add-box-line me-2 text-primary"></i> Add Entry');

        $('#sovModalScope').html(`
            <span>Client: <b>${esc(client ? client.name : 'Unknown')}</b></span>
            <span class="text-muted">·</span>
            <span>Platform: <b>${esc(platform.name)}</b></span>
            <span class="text-muted">·</span>
            <span>Feature: <b>${esc(feature.name)}</b></span>
            <span class="text-muted">·</span>
            <span>Date: <b>${esc(fmtLongDate(ctx.date))}</b></span>
        `);

        $('#sovFormTitle').val(ctx.title || '');
        $('#sovFormStatus').val(ctx.status || 'draft');
        $('#sovFormCaption').val(ctx.caption || '');
        $('#sovFormLink').val(ctx.link || '');
        $('#sovFormRemarks').val(ctx.remarks || '');
        $('.sov-form-field.is-invalid, #sovFormTitle, #sovFormLink').removeClass('is-invalid');

        if (!entryModal) entryModal = new bootstrap.Modal(document.getElementById('sovEntryModal'));
        entryModal.show();
    }

    function validateForm() {
        const errors = [];
        $('#sovFormTitle, #sovFormLink').removeClass('is-invalid');

        const title = $('#sovFormTitle').val().trim();
        if (!title) { $('#sovFormTitle').addClass('is-invalid'); errors.push('Content title is required.'); }
        if (title && title.length < 3) { $('#sovFormTitle').addClass('is-invalid'); errors.push('Content title is too short.'); }

        const link = $('#sovFormLink').val().trim();
        if (link && !/^https?:\/\//i.test(link)) {
            $('#sovFormLink').addClass('is-invalid');
            errors.push('Reference link must start with http:// or https://');
        }
        return errors;
    }

    /* TODO(api): replace with POST api/saveSocialContent.php */
    function saveEntry() {
        const errors = validateForm();
        if (errors.length) { notify('danger', errors[0]); return; }

        const ctx = activeModalContext;
        const record = {
            clientId: ctx.clientId,
            date: ctx.date,
            platformId: ctx.platformId,
            featureId: ctx.featureId,
            title: $('#sovFormTitle').val().trim(),
            caption: $('#sovFormCaption').val().trim(),
            link: $('#sovFormLink').val().trim(),
            status: $('#sovFormStatus').val(),
            remarks: $('#sovFormRemarks').val().trim(),
            updatedBy: 'You',
            updatedAt: new Date().toISOString().slice(0, 16).replace('T', ' ')
        };

        if (ctx.entryId) {
            const idx = entries.findIndex(e => e.id === ctx.entryId);
            if (idx > -1) entries[idx] = { ...entries[idx], ...record };
            notify('success', 'Entry updated successfully.');
        } else {
            entries.push({ id: entrySeq++, ...record });
            notify('success', 'Entry added — matrix and queue updated.');
        }

        entryModal.hide();
        renderAll();
    }

    /* TODO(api): replace with POST api/deleteSocialContent.php */
    function deleteEntry(id) {
        const entry = entries.find(e => e.id === id);
        if (!entry) { notify('danger', 'Entry not found. Refresh and try again.'); return; }

        const client = clientById(entry.clientId);
        confirmDialog({
            title: 'Delete this entry?',
            html: `<b>${esc(entry.title)}</b><br><span class="text-muted">` +
                  `${esc(client ? client.name : '')} · ${esc(platformById(entry.platformId).name)} · ` +
                  `${esc(featureById(entry.platformId, entry.featureId).name)} · ${esc(fmtLongDate(entry.date))}</span>` +
                  `<br><br>This cannot be undone.`,
            icon: 'warning',
            confirmText: 'Delete'
        }).then(res => {
            if (!res.isConfirmed) return;
            entries = entries.filter(e => e.id !== id);
            notify('success', 'Entry deleted. The slot is back in the pending queue.');
            renderAll();
        });
    }

    // ----------------------------------------------------------------------
    // 12. EVENTS — filter bar
    // ----------------------------------------------------------------------
    $('#sovMonth').on('change', function () { state.month = $(this).val(); state.queuePage = 1; renderAll(); });
    $('#sovPlatform').on('change', function () { state.platform = $(this).val(); state.queuePage = 1; renderAll(); });
    $('#sovAttentionOnly').on('change', function () { state.attentionOnly = $(this).is(':checked'); renderMatrixBody(currentHealth.rows); });

    let clientSearchTimer = null;
    $('#sovClientSearch').on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(clientSearchTimer);
        clientSearchTimer = setTimeout(function () { state.clientSearch = val; renderMatrixBody(currentHealth.rows); }, 200);
    });

    $('#sovResetBtn').on('click', function () {
        $('#sovPlatform').val('');
        $('#sovClientSearch').val('');
        $('#sovAttentionOnly').prop('checked', false);
        $('#sovQueueClient').val('');
        $('#sovQueueSearch').val('');
        $('#sovShowOnlyPending').prop('checked', true);
        state.platform = state.clientSearch = state.scopeClientId = state.scopePlatformId = state.queueSearch = '';
        state.attentionOnly = false;
        state.onlyPending = true;
        state.kpiFilter = '';
        state.queuePage = 1;
        renderAll();
        notify('info', 'Filters reset.');
    });

    // ----------------------------------------------------------------------
    // 13. EVENTS — KPI chips
    // ----------------------------------------------------------------------
    $('.sov-kpi').on('click', function () {
        const kpi = $(this).data('kpi');
        state.kpiFilter = (state.kpiFilter === kpi) ? '' : kpi;
        renderKpis(currentHealth.rows);
        renderMatrixBody(currentHealth.rows);
    });

    // ----------------------------------------------------------------------
    // 14. EVENTS — matrix (sort, row click, cell click)
    // ----------------------------------------------------------------------
    $(document).on('click', '#sovSortClient', function () {
        state.matrixSortDir = (state.matrixSortKey === 'client' && state.matrixSortDir === 'asc') ? 'desc' : 'asc';
        state.matrixSortKey = 'client';
        renderMatrixHead(); renderMatrixBody(currentHealth.rows);
    });
    $(document).on('click', '#sovSortOverall', function () {
        state.matrixSortDir = (state.matrixSortKey === 'overall' && state.matrixSortDir === 'asc') ? 'desc' : 'asc';
        state.matrixSortKey = 'overall';
        renderMatrixHead(); renderMatrixBody(currentHealth.rows);
    });

    function scrollToQueue() {
        document.getElementById('sovQueueSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('#sovMatrixBody').on('click', '.sov-cell', function (e) {
        e.stopPropagation();
        state.scopeClientId = $(this).data('client');
        state.scopePlatformId = String($(this).data('platform'));
        state.queuePage = 1;
        $('#sovQueueClient').val('');
        renderScopeChip();
        renderQueue(currentHealth.pending);
        scrollToQueue();
    });

    $('#sovMatrixBody').on('click', '.sov-client-cell, .sov-overall-cell', function () {
        state.scopeClientId = $(this).data('client');
        state.scopePlatformId = '';
        state.queuePage = 1;
        $('#sovQueueClient').val('');
        renderScopeChip();
        renderQueue(currentHealth.pending);
        scrollToQueue();
    });

    $(document).on('click', '#sovClearScopeBtn', function () {
        state.scopeClientId = '';
        state.scopePlatformId = '';
        renderScopeChip();
        renderQueue(currentHealth.pending);
        notify('info', 'Scope cleared — showing all clients again.');
    });

    // ----------------------------------------------------------------------
    // 15. EVENTS — queue toolbar
    // ----------------------------------------------------------------------
    $('#sovQueueClient').on('change', function () {
        if ($(this).val()) { state.scopeClientId = ''; state.scopePlatformId = ''; renderScopeChip(); }
        state.queuePage = 1;
        renderQueue(currentHealth.pending);
    });

    let queueSearchTimer = null;
    $('#sovQueueSearch').on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(queueSearchTimer);
        queueSearchTimer = setTimeout(function () { state.queueSearch = val; state.queuePage = 1; renderQueue(currentHealth.pending); }, 250);
    });

    $('#sovShowOnlyPending').on('change', function () {
        state.onlyPending = $(this).is(':checked');
        state.queuePage = 1;
        renderQueue(currentHealth.pending);
    });

    $('#sovLoadMoreBtn').on('click', function () { state.queuePage++; renderQueue(currentHealth.pending); });

    // ----------------------------------------------------------------------
    // 16. EVENTS — row actions (fill / edit / delete)
    // ----------------------------------------------------------------------
    $('#sovQueueBody').on('click', '.sov-fill', function () {
        openEntryModal('add', {
            clientId: Number($(this).data('client')),
            date: $(this).data('date'),
            platformId: Number($(this).data('platform')),
            featureId: Number($(this).data('feature')),
            entryId: null
        });
    });

    $('#sovQueueBody').on('click', '.sov-edit', function () {
        const entry = entries.find(e => e.id === $(this).data('entry'));
        if (!entry) { notify('danger', 'Entry not found.'); return; }
        openEntryModal('edit', { ...entry, entryId: entry.id });
    });

    $('#sovQueueBody').on('click', '.sov-delete', function () { deleteEntry($(this).data('entry')); });

    // ----------------------------------------------------------------------
    // 17. EVENTS — modal
    // ----------------------------------------------------------------------
    $('#sovSaveBtn').on('click', saveEntry);
    $('#sovEntryModal').on('input change', 'input, select, textarea', function () { formDirty = true; });

    $('#sovCancelBtn').on('click', function () {
        if (!formDirty) { entryModal.hide(); return; }
        confirmDialog({
            title: 'Discard changes?',
            html: 'The details you entered will not be saved.',
            icon: 'question',
            confirmText: 'Discard',
            color: '#dc3545'
        }).then(res => { if (res.isConfirmed) { formDirty = false; entryModal.hide(); } });
    });

    $('#sovEntryModal').on('hidden.bs.modal', function () { formDirty = false; activeModalContext = null; });

    // ----------------------------------------------------------------------
    // 18. INITIAL RENDER
    // ----------------------------------------------------------------------
    renderAll();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
