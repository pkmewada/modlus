<?php

/*
|--------------------------------------------------------------------------
| Social Content Production Engine
|--------------------------------------------------------------------------
|
| The "manufacturing" stage between clientSocialContent (raw material) and
| socialPosts (final dispatch, handled entirely by SocialPostEngine.php —
| this engine never touches it). One row in socialContentProduction tracks
| the production workflow for one clientSocialContent entry; every
| assignment/status change is appended to socialContentProductionHistory,
| never overwritten.
|
| Status lifecycle:
|   NEW -> ASSIGNED -> IN_PROGRESS -> SUBMITTED -> APPROVED -> PRODUCTION_READY
|                                         |  ^
|                                         v  |
|                                     CORRECTION
|
*/

class SocialContentProductionEngine
{
    private $con;

    private const TRANSITIONS = [
        'NEW' => ['ASSIGNED'],
        'ASSIGNED' => ['IN_PROGRESS', 'ASSIGNED'],
        'IN_PROGRESS' => ['SUBMITTED'],
        'SUBMITTED' => ['APPROVED', 'CORRECTION'],
        'CORRECTION' => ['IN_PROGRESS', 'CORRECTION'],
        'APPROVED' => ['PRODUCTION_READY'],
        'PRODUCTION_READY' => [],
    ];

    // statuses a task can be (re)assigned from
    private const ASSIGNABLE_STATUSES = ['NEW', 'ASSIGNED', 'IN_PROGRESS', 'CORRECTION'];

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * Send one clientSocialContent row into production.
     * @throws Exception if the source doesn't exist, or a task already exists for it
     */
    public function createTask($clientSocialContentId, $performedBy, $performedByType, $remark = null)
    {
        $clientSocialContentId = (int)$clientSocialContentId;
        if ($clientSocialContentId <= 0) {
            throw new Exception('A content entry is required.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id, contentDate FROM clientSocialContent WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $clientSocialContentId);
        mysqli_stmt_execute($stmt);
        $sourceEntry = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$sourceEntry) {
            throw new Exception('Content entry not found.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE clientSocialContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $clientSocialContentId);
        mysqli_stmt_execute($stmt);
        $clash = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($clash) {
            throw new Exception('This entry has already been sent to production.');
        }

        $createdBy = (int)$performedBy;
        $status = 'NEW';
        // TAT = the day before the planned content date, 5:00 PM — never
        // pushed forward if that already falls in the past; an already-late
        // task is meant to show as overdue immediately, not get a fresh clock.
        $dueAt = date('Y-m-d', strtotime($sourceEntry['contentDate'] . ' -1 day')) . ' 17:00:00';
        $stmt = mysqli_prepare($this->con, 'INSERT INTO socialContentProduction (clientSocialContentId, status, dueAt, createdBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, NOW(), NOW())');
        mysqli_stmt_bind_param($stmt, 'issi', $clientSocialContentId, $status, $dueAt, $createdBy);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to create production task: ' . mysqli_error($this->con));
        }
        $id = mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        $this->logHistory($id, 'created', null, 'NEW', $remark, $performedBy, $performedByType);

        return $this->getTask($id);
    }

    /**
     * Looks up the production task for a given source entry, if one exists —
     * used for idempotent "ensure a task exists" callers (e.g. Complete
     * Entry) so they never need to duplicate createTask()'s own clash check.
     */
    public function getTaskByContentId($clientSocialContentId)
    {
        $clientSocialContentId = (int)$clientSocialContentId;
        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE clientSocialContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $clientSocialContentId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->getTask((int)$row['id']) : null;
    }

    /**
     * Assign or reassign a task to a Video Editor.
     * @throws Exception on invalid state or invalid editor
     */
    public function assign($id, $editorId, $performedBy, $performedByType, $dueAt = null, $remark = null)
    {
        $editorId = (int)$editorId;
        if ($editorId <= 0) {
            throw new Exception('An editor must be selected.');
        }

        $task = $this->lockTask($id);
        if (!in_array($task['status'], self::ASSIGNABLE_STATUSES, true)) {
            throw new Exception('This task can no longer be (re)assigned in its current status.');
        }

        $stmt = mysqli_prepare($this->con, "SELECT id FROM employeeusers WHERE id = ? AND employmentStatus = 'Active' AND (designationName = 'Video Editor' OR designationName = 'Graphic Executive')");
        mysqli_stmt_bind_param($stmt, 'i', $editorId);
        mysqli_stmt_execute($stmt);
        $editor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$editor) {
            throw new Exception('Selected editor is not a valid, active Video Editor or Graphic Executive.');
        }

        $oldStatus = $task['status'];
        $newStatus = $oldStatus === 'NEW' ? 'ASSIGNED' : $oldStatus;
        $action = $task['assignedEditorId'] === null ? 'assigned' : 'reassigned';

        $dueAtValue = $dueAt ? trim($dueAt) : null;

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET assignedEditorId = ?, status = ?, assignedAt = NOW(), dueAt = COALESCE(?, dueAt), updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'issi', $editorId, $newStatus, $dueAtValue, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to assign task: ' . mysqli_error($this->con));
        }
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType);

        return $this->getTask($task['id']);
    }

    /**
     * Manager sets/updates the due date without changing status or assignment.
     */
    public function setDueAt($id, $dueAt, $performedBy, $performedByType, $remark = null)
    {
        $task = $this->lockTask($id);
        if ($task['status'] === 'PRODUCTION_READY') {
            throw new Exception('This task is already production-ready.');
        }

        $dueAtValue = $dueAt ? trim($dueAt) : null;
        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET dueAt = ?, updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $dueAtValue, $task['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], 'due_updated', $task['status'], $task['status'], $remark, $performedBy, $performedByType);

        return $this->getTask($task['id']);
    }

    /**
     * Editor starts work. Ownership-checked: $editorId must be the assignee.
     */
    public function start($id, $editorId)
    {
        $task = $this->lockOwnTask($id, $editorId);
        $this->transition($task, 'IN_PROGRESS', 'started', null, $editorId, 'employee');
        return $this->getTask($task['id']);
    }

    /**
     * Editor submits the finished production — a Google Drive link or an
     * uploaded file. Ownership-checked. A submission is mandatory: this is
     * the only path from IN_PROGRESS to SUBMITTED, and it hard-rejects an
     * empty/invalid one regardless of what the caller sent.
     *
     * submissionType/submissionUrl on the row always hold the LATEST
     * submission (overwritten on resubmission after a correction — no
     * version table). The history remark below records what was submitted
     * at THIS point in time, so the full sequence stays visible in
     * socialContentProductionHistory even though the live columns don't.
     *
     * @param string $submissionType 'drive' | 'media'
     * @throws Exception on invalid state, ownership mismatch, or missing/invalid submission
     */
    public function submitProduction($id, $editorId, $submissionType, $submissionUrl, $remark = null)
    {
        $task = $this->lockOwnTask($id, $editorId);

        $submissionType = trim((string)$submissionType);
        $submissionUrl = trim((string)$submissionUrl);
        if (!in_array($submissionType, ['drive', 'media'], true) || $submissionUrl === '') {
            throw new Exception('A Google Drive link or an uploaded file is required before submitting.');
        }

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET submissionType = ?, submissionUrl = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ssi', $submissionType, $submissionUrl, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save submission: ' . $error);
        }
        mysqli_stmt_close($stmt);

        $historyRemark = 'Submitted via ' . ($submissionType === 'drive' ? 'Google Drive link' : 'uploaded media') . ": $submissionUrl";
        $remark = trim((string)$remark);
        if ($remark !== '') {
            $historyRemark .= "\nNote: $remark";
        }

        $this->transition($task, 'SUBMITTED', 'submitted', $historyRemark, $editorId, 'employee');

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET submittedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $task['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $this->getTask($task['id']);
    }

    /**
     * Manager reviews a submitted task.
     * @param string $decision 'approve' | 'request_correction'
     * @throws Exception if not SUBMITTED, or correction requested without a remark
     */
    public function review($id, $decision, $performedBy, $performedByType, $remark = null)
    {
        $task = $this->lockTask($id);
        if ($task['status'] !== 'SUBMITTED') {
            throw new Exception('Only submitted work can be reviewed.');
        }

        if ($decision === 'approve') {
            $this->transition($task, 'APPROVED', 'approved', $remark, $performedBy, $performedByType);
            $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET approvedAt = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $task['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($decision === 'request_correction') {
            $remark = trim((string)$remark);
            if ($remark === '') {
                throw new Exception('A correction remark is required.');
            }
            $this->transition($task, 'CORRECTION', 'correction_requested', $remark, $performedBy, $performedByType);
        } else {
            throw new Exception('Invalid review decision.');
        }

        return $this->getTask($task['id']);
    }

    /**
     * Manager marks approved work as production-ready. Terminal for this phase —
     * does NOT create/touch anything in socialPosts.
     */
    public function markReady($id, $performedBy, $performedByType)
    {
        $task = $this->lockTask($id);
        $this->transition($task, 'PRODUCTION_READY', 'production_ready', null, $performedBy, $performedByType);
        return $this->getTask($task['id']);
    }

    public function getTask($id)
    {
        $id = (int)$id;
        $sql = "SELECT
                    p.id, p.clientSocialContentId, p.assignedEditorId, p.status,
                    p.assignedAt, p.dueAt, p.submissionType, p.submissionUrl, p.submittedAt, p.approvedAt,
                    p.createdBy, p.createdAt, p.updatedAt,
                    c.clientId, c.platformId, c.featureId, c.contentDate, c.title, c.rawContent,
                    c.caption, c.contentDescription, c.songUrl, c.ideaReference, c.referenceLink,
                    c.socialMediaHandle, c.postType, c.remarks AS contentRemarks,
                    cm.clientCode, l.fullName AS clientName,
                    dp.platformName, df.featureName,
                    e.fullName AS editorName
                FROM socialContentProduction p
                INNER JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                INNER JOIN clientMaster cm ON cm.id = c.clientId
                INNER JOIN leads l ON l.id = cm.leadId
                INNER JOIN deliverablePlatforms dp ON dp.id = c.platformId
                INNER JOIN deliverableFeatures df ON df.id = c.featureId
                LEFT JOIN employeeusers e ON e.id = p.assignedEditorId
                WHERE p.id = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            return null;
        }

        $row['history'] = $this->getHistory($id);
        return $this->castTask($row);
    }

    /**
     * @param array $filters status, editorId, clientId, month ('YYYY-MM'), overdue (bool)
     */
    public function listForManager($filters = [])
    {
        return $this->listTasks($filters, null);
    }

    /**
     * @param int $editorId hard server-side scope — always applied, never optional
     */
    public function listForEditor($editorId, $filters = [])
    {
        return $this->listTasks($filters, (int)$editorId);
    }

    public function getHistory($productionId)
    {
        $productionId = (int)$productionId;
        $stmt = mysqli_prepare($this->con, "SELECT h.id, h.productionId, h.action, h.oldStatus, h.newStatus, h.remark,
                                                    h.performedBy, h.performedByType, h.createdAt,
                                                    COALESCE(u.fullName, eu.fullName) AS performedByName
                                             FROM socialContentProductionHistory h
                                             LEFT JOIN users u ON u.id = h.performedBy AND h.performedByType = 'admin'
                                             LEFT JOIN employeeusers eu ON eu.id = h.performedBy AND h.performedByType = 'employee'
                                             WHERE h.productionId = ?
                                             ORDER BY h.id ASC");
        mysqli_stmt_bind_param($stmt, 'i', $productionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['productionId'] = (int)$row['productionId'];
            $row['performedBy'] = (int)$row['performedBy'];
            $history[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $history;
    }

    /**
     * Phase 6: server-side operational summary for the manager queue —
     * status breakdown, an overdue count, and per-active-Video-Editor
     * workload. Never trust client-side counts of an already-filtered
     * table; this always re-queries the database directly. Accepts the
     * same clientId/platformId/month filters listTasks() does (not
     * status/editorId/overdue -- those are the dimensions being counted).
     *
     * @param array $filters clientId, platformId, month ('YYYY-MM')
     */
    public function getProductionSummary($filters = [])
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['clientId'])) {
            $where[] = 'c.clientId = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['platformId'])) {
            $where[] = 'c.platformId = ?';
            $types .= 'i';
            $params[] = (int)$filters['platformId'];
        }
        if (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) {
            $where[] = "DATE_FORMAT(c.contentDate, '%Y-%m') = ?";
            $types .= 's';
            $params[] = $filters['month'];
        }
        $whereSql = implode(' AND ', $where);

        $statusCounts = [
            'NEW' => 0, 'ASSIGNED' => 0, 'IN_PROGRESS' => 0, 'SUBMITTED' => 0,
            'CORRECTION' => 0, 'APPROVED' => 0, 'PRODUCTION_READY' => 0,
        ];

        $sql = "SELECT p.status, COUNT(*) AS total
                FROM socialContentProduction p
                INNER JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                WHERE $whereSql
                GROUP BY p.status";
        $stmt = mysqli_prepare($this->con, $sql);
        $this->bindIfAny($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (array_key_exists($row['status'], $statusCounts)) {
                $statusCounts[$row['status']] = (int)$row['total'];
            }
        }
        mysqli_stmt_close($stmt);

        $overdueSql = "SELECT COUNT(*) AS total
                FROM socialContentProduction p
                INNER JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                WHERE $whereSql
                AND p.dueAt IS NOT NULL AND p.dueAt < NOW()
                AND p.status NOT IN ('APPROVED','PRODUCTION_READY')";
        $stmt = mysqli_prepare($this->con, $overdueSql);
        $this->bindIfAny($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $overdueCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
        mysqli_stmt_close($stmt);

        // Editor workload: every active Video Editor, including those with
        // zero assigned tasks right now (LEFT JOIN) -- inactive/non-editor
        // employeeusers rows are excluded entirely, never just hidden.
        // Filtering happens INSIDE the subquery so a non-matching production
        // simply doesn't exist for this purpose -- the outer LEFT JOIN from
        // employeeusers then naturally gives every active Video Editor a
        // row (zero counts if none of their work matches the filter),
        // rather than dropping an editor who has real but non-matching work.
        $workloadSql = "SELECT
                    e.id AS editorId, e.fullName AS editorName,
                    SUM(CASE WHEN p.status = 'ASSIGNED' THEN 1 ELSE 0 END) AS assignedCount,
                    SUM(CASE WHEN p.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS inProgressCount,
                    SUM(CASE WHEN p.status = 'SUBMITTED' THEN 1 ELSE 0 END) AS submittedCount,
                    SUM(CASE WHEN p.status = 'CORRECTION' THEN 1 ELSE 0 END) AS correctionCount,
                    SUM(CASE WHEN p.dueAt IS NOT NULL AND p.dueAt < NOW() AND p.status NOT IN ('APPROVED','PRODUCTION_READY') THEN 1 ELSE 0 END) AS overdueCount
                FROM employeeusers e
                LEFT JOIN (
                    SELECT p.id, p.assignedEditorId, p.status, p.dueAt
                    FROM socialContentProduction p
                    INNER JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                    WHERE $whereSql
                ) p ON p.assignedEditorId = e.id
                WHERE e.employmentStatus = 'Active' AND (e.designationName = 'Video Editor' OR e.designationName = 'Graphic Executive')
                GROUP BY e.id, e.fullName
                ORDER BY e.fullName";
        $stmt = mysqli_prepare($this->con, $workloadSql);
        $this->bindIfAny($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $editorWorkload = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $editorWorkload[] = [
                'editorId' => (int)$row['editorId'],
                'editorName' => $row['editorName'],
                'assignedCount' => (int)$row['assignedCount'],
                'inProgressCount' => (int)$row['inProgressCount'],
                'submittedCount' => (int)$row['submittedCount'],
                'correctionCount' => (int)$row['correctionCount'],
                'overdueCount' => (int)$row['overdueCount'],
            ];
        }
        mysqli_stmt_close($stmt);

        return [
            'statusCounts' => $statusCounts,
            'overdueCount' => $overdueCount,
            'unassignedCount' => $statusCounts['NEW'],
            'editorWorkload' => $editorWorkload,
        ];
    }

    /**
     * Phase 6: lets a caller OUTSIDE this engine (specifically
     * SocialAutomationHandoffEngine, which owns the PRODUCTION_READY ->
     * Automation boundary) append one append-only history row for an event
     * that happened to a production task without going through this
     * engine's own status-transition machinery -- the automation handoff
     * doesn't change socialContentProduction.status, so transition()'s
     * validation doesn't apply. This is the one, narrow, intentional
     * exception to "only this engine writes its own history": the
     * dependency direction stays one-way (the caller pushes an event in;
     * this engine never reaches out to or knows about the caller), so the
     * existing isolation between Production and Automation is preserved.
     */
    public function recordExternalEvent($productionId, $action, $remark, $performedBy, $performedByType)
    {
        $productionId = (int)$productionId;
        if ($productionId <= 0) {
            return;
        }

        $this->logHistory($productionId, $action, null, null, $remark, $performedBy, $performedByType);
    }

    private function bindIfAny($stmt, $types, $params)
    {
        if ($types === '') {
            return;
        }
        $bindParams = [$stmt, $types];
        foreach ($params as $key => $val) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
    }

    // --- internal helpers ---------------------------------------------

    private function listTasks($filters, $forceEditorId)
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if ($forceEditorId !== null) {
            $where[] = 'p.assignedEditorId = ?';
            $types .= 'i';
            $params[] = $forceEditorId;
        } elseif (!empty($filters['editorId'])) {
            $where[] = 'p.assignedEditorId = ?';
            $types .= 'i';
            $params[] = (int)$filters['editorId'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['clientId'])) {
            $where[] = 'c.clientId = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['platformId'])) {
            $where[] = 'c.platformId = ?';
            $types .= 'i';
            $params[] = (int)$filters['platformId'];
        }
        if (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) {
            $where[] = "DATE_FORMAT(c.contentDate, '%Y-%m') = ?";
            $types .= 's';
            $params[] = $filters['month'];
        }
        if (!empty($filters['overdue'])) {
            $where[] = "p.dueAt IS NOT NULL AND p.dueAt < NOW() AND p.status NOT IN ('APPROVED','PRODUCTION_READY')";
        }

        $sql = "SELECT
                    p.id, p.clientSocialContentId, p.assignedEditorId, p.status,
                    p.assignedAt, p.dueAt, p.submissionType, p.submissionUrl, p.submittedAt, p.approvedAt,
                    p.createdBy, p.createdAt, p.updatedAt,
                    c.clientId, c.platformId, c.featureId, c.contentDate, c.title, c.rawContent,
                    c.caption, c.contentDescription, c.songUrl, c.ideaReference, c.referenceLink,
                    c.socialMediaHandle, c.postType, c.remarks AS contentRemarks,
                    cm.clientCode, l.fullName AS clientName,
                    dp.platformName, df.featureName,
                    e.fullName AS editorName,
                    (SELECT h.remark FROM socialContentProductionHistory h
                       WHERE h.productionId = p.id AND h.remark IS NOT NULL AND h.remark <> ''
                       ORDER BY h.id DESC LIMIT 1) AS lastRemark
                FROM socialContentProduction p
                INNER JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                INNER JOIN clientMaster cm ON cm.id = c.clientId
                INNER JOIN leads l ON l.id = cm.leadId
                INNER JOIN deliverablePlatforms dp ON dp.id = c.platformId
                INNER JOIN deliverableFeatures df ON df.id = c.featureId
                LEFT JOIN employeeusers e ON e.id = p.assignedEditorId
                WHERE " . implode(' AND ', $where) . "
                ORDER BY (p.dueAt IS NULL), p.dueAt ASC, p.createdAt DESC";

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            $bindParams = [$stmt, $types];
            foreach ($params as $key => $val) {
                $bindParams[] = &$params[$key];
            }
            call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $tasks = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tasks[] = $this->castTask($row);
        }
        mysqli_stmt_close($stmt);

        return $tasks;
    }

    // fetches the task row for update, throwing if it doesn't exist —
    // callers then validate/perform the transition
    private function lockTask($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare($this->con, 'SELECT id, status, assignedEditorId FROM socialContentProduction WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            throw new Exception('Production task not found.');
        }
        return $row;
    }

    // same as lockTask(), but also re-verifies the task belongs to $editorId —
    // never trusts a bare id from an editor's request
    private function lockOwnTask($id, $editorId)
    {
        $id = (int)$id;
        $editorId = (int)$editorId;
        $stmt = mysqli_prepare($this->con, 'SELECT id, status, assignedEditorId FROM socialContentProduction WHERE id = ? AND assignedEditorId = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $editorId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            throw new Exception('Task not found, or not assigned to you.');
        }
        return $row;
    }

    private function transition($task, $newStatus, $action, $remark, $performedBy, $performedByType)
    {
        $oldStatus = $task['status'];
        $allowed = self::TRANSITIONS[$oldStatus] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new Exception("Cannot move from $oldStatus to $newStatus.");
        }

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET status = ?, updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update status: ' . mysqli_error($this->con));
        }
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType);
    }

    private function logHistory($productionId, $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType)
    {
        $remark = $remark !== null ? trim((string)$remark) : null;
        if ($remark === '') {
            $remark = null;
        }
        $performedBy = (int)$performedBy;

        $stmt = mysqli_prepare($this->con, 'INSERT INTO socialContentProductionHistory
            (productionId, action, oldStatus, newStatus, remark, performedBy, performedByType, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        mysqli_stmt_bind_param($stmt, 'issssis', $productionId, $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function castTask($row)
    {
        $row['id'] = (int)$row['id'];
        $row['clientSocialContentId'] = (int)$row['clientSocialContentId'];
        $row['assignedEditorId'] = $row['assignedEditorId'] !== null ? (int)$row['assignedEditorId'] : null;
        $row['clientId'] = (int)$row['clientId'];
        $row['platformId'] = (int)$row['platformId'];
        $row['featureId'] = (int)$row['featureId'];
        $row['createdBy'] = $row['createdBy'] !== null ? (int)$row['createdBy'] : null;
        return $row;
    }
}
