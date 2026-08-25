<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Instagram Automation</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item active" aria-current="page">Instagram Automation</li>
                </ol>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="setupWarning">
            Saving new Meta API credentials will be used for all future Instagram account connections.
        </div>

        <div class="row g-4 mb-1">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
                        <label class="form-label mb-0" for="clientSelect">Client</label>
                        <select class="form-select form-select-sm" id="clientSelect" style="max-width: 320px;">
                            <option value="">Select Client</option>
                        </select>
                        <span class="text-muted fs-13">Instagram account connection is per client — select a client to connect or manage their account.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-7">
                <form id="instagramSettingsForm" autocomplete="off">
                    <?= getCsrfInput() ?>
                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-0">Meta API Configuration</h5>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="metaAppId">Meta App ID</label>
                                    <input type="text" class="form-control" id="metaAppId" name="metaAppId" maxlength="191" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="metaAppSecret">Meta App Secret</label>
                                    <input type="password" class="form-control" id="metaAppSecret" name="metaAppSecret" maxlength="255" autocomplete="new-password" placeholder="">
                                    <div class="form-text" id="metaAppSecretHint">Never displayed once saved. Leave blank to keep the existing secret.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="redirectUrl">Redirect URL</label>
                                    <input type="url" class="form-control" id="redirectUrl" name="redirectUrl" maxlength="255">
                                    <div class="form-text">Add this exact URL to your Meta App's Valid OAuth Redirect URIs.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Webhook Callback URL</label>
                                    <input type="text" class="form-control" id="webhookCallbackUrl" readonly>
                                    <div class="form-text">Enter this as the Callback URL when subscribing to Instagram webhooks in your Meta App.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="webhookVerifyToken">Webhook Verify Token</label>
                                    <input type="text" class="form-control" id="webhookVerifyToken" name="webhookVerifyToken" maxlength="191" readonly>
                                    <div class="form-text">Auto-generated on first save. Enter this as the Verify Token when subscribing to Instagram webhooks in your Meta App.</div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary" id="saveInstagramSettingsBtn">
                                Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-xl-5">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Connect Instagram Account</h5>
                    </div>

                    <div class="card-body d-flex flex-column gap-3">
                        <p class="mb-0 text-muted">
                            Connect a Facebook Page with a linked Instagram Business account via Meta OAuth, for the client selected above.
                        </p>

                        <button type="button" class="btn btn-outline-primary" id="connectInstagramBtn">
                            <i class="ti ti-brand-instagram me-1"></i>
                            Connect Instagram Account
                        </button>

                        <div class="alert alert-info mb-0 d-none" id="connectDisabledHint">
                            Save your Meta App ID and App Secret, and select a client above, to enable account connection.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="mb-0">Connected Instagram Accounts</h5>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Facebook Page ID</th>
                                        <th>Status</th>
                                        <th>Connected On</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="instagramAccountsBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Select a client above to view their connected accounts.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let hasUnsavedInstagramChanges = false;
let instagramSettingsLoaded = null;

$(function() {
    loadInstagramClients();
    loadInstagramSettings();

    $('#instagramSettingsForm').on('change keyup', 'input', function() {
        hasUnsavedInstagramChanges = true;
        $('#setupWarning').removeClass('d-none');
    });

    $('#instagramSettingsForm').on('submit', function(e) {
        e.preventDefault();
        saveInstagramSettings();
    });

    $('#clientSelect').on('change', function() {
        loadInstagramAccountsForSelectedClient();
        updateConnectButtonState();
    });

    $('#connectInstagramBtn').on('click', function() {
        const clientId = $('#clientSelect').val();

        if (!clientId) {
            window.showToast && window.showToast('warning', 'Please select a client first.');
            return;
        }

        window.location.href = API_BASE + '/instagramOauthStart.php?clientId=' + encodeURIComponent(clientId);
    });

    $('#instagramAccountsBody').on('click', '.disconnect-instagram-account', function() {
        const accountId = $(this).data('account-id');
        disconnectInstagramAccount(accountId);
    });

    window.addEventListener('beforeunload', function(e) {
        if (!hasUnsavedInstagramChanges) {
            return;
        }

        e.preventDefault();
        e.returnValue = '';
    });

    handleInstagramOauthRedirectStatus();
});

function handleInstagramOauthRedirectStatus() {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('igStatus');
    const message = params.get('igMessage');
    const clientId = params.get('clientId');

    if (clientId) {
        // Set once the client dropdown has loaded (loadInstagramClients selects it too if present).
        $('#clientSelect').data('pending-select', clientId);
    }

    if (!status) {
        return;
    }

    window.showToast && window.showToast(status === 'success' ? 'success' : 'danger', message || 'Instagram connection update.');

    params.delete('igStatus');
    params.delete('igMessage');
    params.delete('clientId');

    const newQuery = params.toString();
    const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
    window.history.replaceState({}, document.title, newUrl);
}

function loadInstagramClients() {
    $.ajax({
        url: API_BASE + '/getClients.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const clients = (response && response.data) || [];
            let options = '<option value="">Select Client</option>';

            clients.forEach(function(client) {
                options += `<option value="${client.id}">${$('<div>').text(client.fullName + ' (' + client.clientCode + ')').html()}</option>`;
            });

            $('#clientSelect').html(options);

            const pending = $('#clientSelect').data('pending-select');

            if (pending) {
                $('#clientSelect').val(String(pending)).trigger('change');
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to load clients.');
        }
    });
}

function updateConnectButtonState() {
    const clientId = $('#clientSelect').val();
    const settings = instagramSettingsLoaded || {};
    const ready = !!(clientId && settings.metaAppId && settings.hasAppSecret);

    $('#connectInstagramBtn').prop('disabled', !ready);
    $('#connectDisabledHint').toggleClass('d-none', ready);
}

function loadInstagramAccountsForSelectedClient() {
    const clientId = $('#clientSelect').val();

    if (!clientId) {
        $('#instagramAccountsBody').html('<tr><td colspan="5" class="text-center text-muted">Select a client above to view their connected accounts.</td></tr>');
        return;
    }

    $.getJSON(API_BASE + '/getInstagramSettings.php', { clientId: clientId })
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load connected accounts.');
                return;
            }

            renderInstagramAccounts(res.data.instagramAccounts || []);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load connected accounts.');
        });
}

function bindInstagramSettings(settings) {
    instagramSettingsLoaded = settings;

    $('#metaAppId').val(settings.metaAppId || '');
    $('#redirectUrl').val(settings.redirectUrl || settings.defaultRedirectUrl || '');
    $('#metaAppSecret').val('').attr('placeholder', settings.hasAppSecret ? 'Saved — leave blank to keep current secret' : '');
    $('#webhookCallbackUrl').val(BASE_URL + '/api/instagramWebhook.php');
    $('#webhookVerifyToken').val(settings.webhookVerifyToken || '(will be generated when you save)');

    updateConnectButtonState();
}

function renderInstagramAccounts(accounts) {
    const $body = $('#instagramAccountsBody');
    $body.empty();

    if (!accounts || !accounts.length) {
        $body.append('<tr><td colspan="5" class="text-center text-muted">No Instagram accounts connected yet.</td></tr>');
        return;
    }

    accounts.forEach(function(account) {
        const statusBadge = account.status === 'connected'
            ? '<span class="badge bg-success-transparent">Connected</span>'
            : '<span class="badge bg-secondary-transparent">Disconnected</span>';

        const actionCell = account.status === 'connected'
            ? `<button type="button" class="btn btn-sm btn-outline-danger disconnect-instagram-account" data-account-id="${account.id}">Disconnect</button>`
            : '';

        const username = account.username ? '@' + account.username : '—';

        $body.append(`
            <tr>
                <td>${$('<div>').text(username).html()}</td>
                <td>${$('<div>').text(account.facebookPageId || '—').html()}</td>
                <td>${statusBadge}</td>
                <td>${$('<div>').text(account.createdAt || '—').html()}</td>
                <td class="text-end">${actionCell}</td>
            </tr>
        `);
    });
}

function validateInstagramSettings() {
    const metaAppId = $('#metaAppId').val().trim();
    const redirectUrl = $('#redirectUrl').val().trim();

    if (!metaAppId) {
        return 'Meta App ID is required.';
    }

    if (redirectUrl && !/^https?:\/\/.+/i.test(redirectUrl)) {
        return 'Please enter a valid redirect URL.';
    }

    return '';
}

function loadInstagramSettings() {
    $.getJSON(API_BASE + '/getInstagramSettings.php')
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load Instagram settings.');
                return;
            }

            const settings = res.data.instagramSettings || {};
            settings.defaultRedirectUrl = res.data.defaultRedirectUrl || '';

            bindInstagramSettings(settings);
            hasUnsavedInstagramChanges = false;
            $('#setupWarning').addClass('d-none');
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load Instagram settings.');
        });
}

function saveInstagramSettings() {
    const error = validateInstagramSettings();

    if (error) {
        window.showToast && window.showToast('warning', error);
        return;
    }

    const $btn = $('#saveInstagramSettingsBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
        url: API_BASE + '/saveInstagramSettings.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: {
            metaAppId: $('#metaAppId').val().trim(),
            metaAppSecret: $('#metaAppSecret').val(),
            redirectUrl: $('#redirectUrl').val().trim(),
            csrfToken: CSRF_TOKEN,
        },
        dataType: 'json',
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Invalid server response.'
            );

            if (res && res.success) {
                bindInstagramSettings(res.data.instagramSettings || {});
                hasUnsavedInstagramChanges = false;
                $('#setupWarning').addClass('d-none');
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to save Instagram settings.');
        },
        complete: function() {
            $btn.prop('disabled', false).text('Save Settings');
        }
    });
}

function disconnectInstagramAccount(accountId) {
    if (!accountId) {
        return;
    }

    $.ajax({
        url: API_BASE + '/disconnectInstagramAccount.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: { accountId: accountId, csrfToken: CSRF_TOKEN },
        dataType: 'json',
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Unable to disconnect account.'
            );

            if (res && res.success) {
                renderInstagramAccounts(res.data.instagramAccounts || []);
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to disconnect account.');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
