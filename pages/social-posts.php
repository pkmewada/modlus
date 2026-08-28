<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Instagram Posts</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item active" aria-current="page">Instagram Posts</li>
                </ol>
            </div>
            <div>
                <a href="instagram-create-post" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>
                    Create Post
                </a>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="noAccountWarning">
            No Instagram account is connected. Scheduled posts will not publish until you <a href="instagram-automation">connect an account</a>.
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">All Posts</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <select class="form-select form-select-sm" id="clientFilter" style="min-width: 200px;">
                                <option value="">All Clients</option>
                            </select>
                            <select class="form-select form-select-sm" id="statusFilter" style="min-width: 180px;">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="publishing">Publishing</option>
                                <option value="published">Published</option>
                                <option value="partial">Partial</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Media</th>
                                        <th>Type</th>
                                        <th>Caption</th>
                                        <th>Platforms</th>
                                        <th>Status</th>
                                        <th>Scheduled / Published</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="instagramPostsBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Loading posts...</td>
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
let instagramClientLabels = {};

$(function() {
    loadInstagramClients(loadInstagramPosts);
    checkInstagramAccountConnected();

    $('#statusFilter, #clientFilter').on('change', function() {
        loadInstagramPosts();
        checkInstagramAccountConnected();
    });

    $('#instagramPostsBody').on('click', '.delete-instagram-post', function() {
        const postId = $(this).data('post-id');

        if (!confirm('Delete this Instagram post? This cannot be undone.')) {
            return;
        }

        deleteInstagramPost(postId);
    });

    $('#instagramPostsBody').on('click', '.view-instagram-post-error', function() {
        const message = $(this).data('error-message') || 'No error details available.';
        window.showToast && window.showToast('danger', message);
    });
});

function loadInstagramClients(onDone) {
    $.ajax({
        url: API_BASE + '/getClients.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const clients = (response && response.data) || [];
            let options = '<option value="">All Clients</option>';

            instagramClientLabels = {};
            clients.forEach(function(client) {
                const label = client.fullName + ' (' + client.clientCode + ')';
                instagramClientLabels[client.id] = label;
                options += `<option value="${client.id}">${$('<div>').text(label).html()}</option>`;
            });

            $('#clientFilter').html(options);
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to load clients.');
        },
        complete: function() {
            if (typeof onDone === 'function') {
                onDone();
            }
        }
    });
}

function checkInstagramAccountConnected() {
    const clientId = $('#clientFilter').val();

    $.getJSON(API_BASE + '/getInstagramSettings.php', clientId ? { clientId: clientId } : {})
        .done(function(res) {
            const accounts = (res && res.data && res.data.instagramAccounts) || [];
            const hasConnected = accounts.some(a => a.status === 'connected');
            $('#noAccountWarning').toggleClass('d-none', hasConnected);
        });
}

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function statusBadge(status) {
    const map = {
        draft: 'secondary',
        scheduled: 'info',
        publishing: 'warning',
        published: 'success',
        partial: 'orange',
        failed: 'danger',
    };
    const color = map[status] || 'secondary';
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';

    return `<span class="badge bg-${color}-transparent">${label}</span>`;
}

function typeBadge(mediaType) {
    const labels = { image: 'Image', reel: 'Reel', carousel: 'Carousel' };
    return `<span class="badge bg-primary-transparent">${labels[mediaType] || mediaType}</span>`;
}

function platformsBadges(post) {
    const platforms = (post.platforms || 'instagram').split(',').map(p => p.trim()).filter(Boolean);
    const facebookColors = { pending: 'info', published: 'success', failed: 'danger', not_applicable: 'secondary' };

    return platforms.map(function(platform) {
        if (platform === 'instagram') {
            return '<span class="badge bg-primary-transparent me-1">Instagram</span>';
        }

        if (platform === 'facebook') {
            const color = facebookColors[post.facebookStatus] || 'secondary';
            return `<span class="badge bg-${color}-transparent me-1">Facebook</span>`;
        }

        return `<span class="badge bg-secondary-transparent me-1">${escapeHtml(platform)}</span>`;
    }).join('');
}

function mediaThumb(post) {
    const urls = post.mediaUrls || [];

    if (!urls.length) {
        return '<span class="text-muted">—</span>';
    }

    if (post.mediaType === 'reel') {
        return '<i class="ti ti-video fs-20 text-muted" title="Video"></i>';
    }

    return `<img src="${escapeHtml(urls[0])}" alt="media" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">`;
}

function loadInstagramPosts() {
    const status = $('#statusFilter').val();
    const clientId = $('#clientFilter').val();

    $.getJSON(API_BASE + '/getInstagramPosts.php', { status: status, clientId: clientId })
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load Instagram posts.');
                return;
            }

            renderInstagramPosts(res.data.posts || []);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load Instagram posts.');
        });
}

function renderInstagramPosts(posts) {
    const $body = $('#instagramPostsBody');
    $body.empty();

    if (!posts.length) {
        $body.append('<tr><td colspan="8" class="text-center text-muted">No Instagram posts found.</td></tr>');
        return;
    }

    posts.forEach(function(post) {
        const canEdit = ['draft', 'scheduled', 'failed', 'partial'].indexOf(post.status) !== -1;
        const canDelete = ['draft', 'scheduled', 'failed', 'partial'].indexOf(post.status) !== -1;
        const caption = post.caption ? post.caption.substring(0, 60) + (post.caption.length > 60 ? '…' : '') : '<span class="text-muted">No caption</span>';
        const scheduleInfo = post.status === 'published'
            ? escapeHtml(post.publishedAt || '—')
            : escapeHtml(post.scheduledAt || '—');
        const clientLabel = post.clientId ? (instagramClientLabels[post.clientId] || ('Client #' + post.clientId)) : 'Unassigned';

        let actions = '';

        if (canEdit) {
            actions += `<a href="instagram-create-post?postId=${post.id}" class="btn btn-sm btn-outline-primary me-1">Edit</a>`;
        }

        if (post.status === 'failed' || post.status === 'partial' || post.facebookStatus === 'failed') {
            const errorParts = [];
            if (post.errorMessage) { errorParts.push('Instagram: ' + post.errorMessage); }
            if (post.facebookErrorMessage) { errorParts.push('Facebook: ' + post.facebookErrorMessage); }
            actions += `<button type="button" class="btn btn-sm btn-outline-warning me-1 view-instagram-post-error" data-error-message="${escapeHtml(errorParts.join('\n') || 'No error details recorded.')}">View Error</button>`;
        }

        if (canDelete) {
            actions += `<button type="button" class="btn btn-sm btn-outline-danger delete-instagram-post" data-post-id="${post.id}">Delete</button>`;
        }

        $body.append(`
            <tr>
                <td>${escapeHtml(clientLabel)}</td>
                <td>${mediaThumb(post)}</td>
                <td>${typeBadge(post.mediaType)}</td>
                <td class="text-wrap" style="max-width: 320px;">${caption}</td>
                <td>${platformsBadges(post)}</td>
                <td>${statusBadge(post.status)}</td>
                <td>${scheduleInfo}</td>
                <td class="text-end">${actions}</td>
            </tr>
        `);
    });
}

function deleteInstagramPost(postId) {
    if (!postId) {
        return;
    }

    $.ajax({
        url: API_BASE + '/deleteInstagramPost.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: { postId: postId, csrfToken: CSRF_TOKEN },
        dataType: 'json',
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Unable to delete post.'
            );

            if (res && res.success) {
                loadInstagramPosts();
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to delete post.');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
