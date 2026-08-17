<?php

class Project
{
    public function displayPage(): string
    {
        $op = $_GET['op'] ?? 'project';

        switch ($op) {
            case 'add_project':
                return $this->add();
            case 'archive_project':
                return $this->archive();
            case 'delete_project':
                return $this->delete();
            default:
                return $this->view();
        }
    }

    /**
     * Display a single project's keywords dashboard.
     */
    private function view(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $projectId = (int) ($_GET['id'] ?? 0);

        if ($projectId <= 0) {
            header('Location: ' . APP_URL . '/index.php?op=dashboard');
            exit;
        }

        /* Verify project exists and belongs to user */
        $project = $db->fetchOne(
            'SELECT * FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
            [$projectId, $userId]
        );

        if (!$project) {
            header('Location: ' . APP_URL . '/index.php?op=dashboard');
            exit;
        }

        /* Store project_id in session for AJAX handlers */
        $_SESSION['project_id'] = $projectId;

        $csrfToken = generateCsrfToken();
        $isArchived = $project['status'] === 'archived';
        $statusBadge = $isArchived
            ? '<span class="badge badge-muted">Archived</span>'
            : '<span class="badge badge-success">Active</span>';
        $domainDisplay = $project['domain'] !== '' ? ' <span class="project-domain">(' . sanitize($project['domain']) . ')</span>' : '';

        /* Fetch keyword statistics for this project */
        $kwStats = getProjectKeywordStats($projectId);

        /* Build top keyword display */
        $topDisplay = '—';
        if (!empty($kwStats['top_keywords'])) {
            $topKw = $kwStats['top_keywords'][0];
            $topDisplay = sanitize($topKw['name']) . ' (#' . $topKw['position'] . ')';
        }

        /* Build trending display */
        $trendingDisplay = '—';
        if ($kwStats['best_trending']) {
            $trendingDisplay = sanitize($kwStats['best_trending']);
        }

        /* Build archived banner */
        $archivedBanner = '';
        if ($isArchived) {
            $archivedBanner = '
            <div class="archived-banner">
                This project is archived. Keyword creation and position refresh are disabled.
                <a href="' . sanitize(APP_URL . '/index.php?op=archive_project&id=' . $projectId) . '"
                    class="underline ml-2 font-medium">Restore Project</a>
            </div>';
        }

        /* Build action buttons */
        $addBtnDisabled = $isArchived ? 'disabled' : '';
        $addBtnClass = $isArchived ? 'btn btn-disabled' : 'btn btn-success';

        $refreshBtnDisabled = $isArchived ? 'disabled' : '';
        $refreshBtnClass = $isArchived ? 'btn btn-disabled' : 'btn btn-primary';

        return '
        <div class="container">
            <!-- Header -->
            <div class="mb-6">
                <a href="' . sanitize(APP_URL . '/index.php?op=dashboard') . '"
                    class="back-link">&larr; Back to Projects Dashboard</a>
                <div class="project-header">
                    <div class="project-header-info">
                        <h1 class="page-title">' . sanitize($project['name']) . $domainDisplay . ' ' . $statusBadge . '</h1>
                    </div>
                    <div class="btn-group">
                        <button id="btn-refresh" ' . $refreshBtnDisabled . '
                            class="' . $refreshBtnClass . '">Refresh Positions</button>
                    </div>
                </div>
            </div>

            <!-- Keyword Stats Cards -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Keywords</div>
                    <div id="pm-total" class="stat-value">' . $kwStats['total_keywords'] . '</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Top Keyword</div>
                    <div id="pm-top" class="stat-value stat-value--primary">' . $topDisplay . '</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Trending</div>
                    <div id="pm-trending" class="stat-value stat-value--success">' . $trendingDisplay . '</div>
                </div>
            </div>

            ' . $archivedBanner . '

            <!-- Toolbar -->
            <div class="toolbar">
                <button id="btn-add" ' . $addBtnDisabled . '
                    class="' . $addBtnClass . '">+ Add Keyword</button>
                <input type="text" id="search-input" placeholder="Search keywords..."
                    class="form-input form-input--flex">
                <select id="filter-select"
                    class="form-select" style="width: auto; min-width: 140px;">
                    <option value="">All Trends</option>
                    <option value="improved">Improved</option>
                    <option value="declined">Declined</option>
                    <option value="stable">Stable</option>
                </select>
                <select id="sort-select"
                    class="form-select" style="width: auto; min-width: 180px;">
                    <option value="updated_at_desc" selected>Recently Updated</option>
                    <option value="updated_at_asc">Oldest Updated</option>
                    <option value="name_asc">Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                    <option value="position_asc">Position Low-High</option>
                    <option value="position_desc">Position High-Low</option>
                </select>
            </div>

            <!-- Keywords Table -->
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-index">#</th>
                            <th class="col-auto">Keyword</th>
                            <th class="col-position">Position</th>
                            <th class="col-status">Trend</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="keywords-table-body" data-col-span="5">
                        <tr><td colspan="5" class="table-empty-cell">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="dashboard-pagination" class="pagination"></div>
            </div>

            <div class="export-area">
                <button id="btn-export"
                    class="btn btn-secondary">Export CSV</button>
            </div>
        </div>

        <!-- Add Keyword Modal -->
        <div id="add-modal" class="modal-overlay">
            <div class="modal">
                <div class="modal-body">
                    <h2 class="modal-title">Add Keyword</h2>
                    <div id="add-error" class="is-hidden alert alert-error"></div>
                    <form id="add-form">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                        <div class="form-group">
                            <label class="form-label" for="add-name">Keyword Phrase</label>
                            <input type="text" id="add-name" name="name" required
                                class="form-input"
                                placeholder="Enter keyword phrase">
                        </div>
                        <div class="modal-actions">
                            <button type="button" id="add-cancel"
                                class="btn btn-ghost">Cancel</button>
                            <button type="submit"
                                class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Keyword Modal -->
        <div id="edit-modal" class="modal-overlay">
            <div class="modal">
                <div class="modal-body">
                    <h2 class="modal-title">Edit Keyword</h2>
                    <div id="edit-error" class="is-hidden alert alert-error"></div>
                    <form id="edit-form">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                        <input type="hidden" id="edit-id" name="id">
                        <div class="form-group">
                            <label class="form-label" for="edit-name">Keyword Phrase</label>
                            <input type="text" id="edit-name" name="name" required
                                class="form-input">
                        </div>
                        <div class="modal-actions">
                            <button type="button" id="edit-cancel"
                                class="btn btn-ghost">Cancel</button>
                            <button type="submit"
                                class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>window.__PROJECT_ID__ = ' . (int) $projectId . ';</script>
        <script>window.__PROJECT_STATS__ = ' . json_encode($kwStats) . ';</script>';
    }

    /**
     * Redirect: add_project is handled via AJAX, fallback to dashboard.
     */
    private function add(): string
    {
        header('Location: ' . APP_URL . '/index.php?op=dashboard');
        exit;
        return '';
    }

    /**
     * Redirect: archive_project is handled via AJAX, fallback to dashboard.
     */
    private function archive(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $projectId = (int) ($_GET['id'] ?? 0);

        if ($projectId > 0) {
            $project = $db->fetchOne(
                'SELECT `project_id`, `status` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
                [$projectId, $userId]
            );

            if ($project) {
                $newStatus = $project['status'] === 'active' ? 'archived' : 'active';
                $db->update('projects', [
                    'status' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], '`project_id` = ? AND `user_id` = ?', [$projectId, $userId]);
            }
        }

        header('Location: ' . APP_URL . '/index.php?op=dashboard');
        exit;
        return '';
    }

    /**
     * Redirect: delete_project is handled via AJAX, fallback to dashboard.
     */
    private function delete(): string
    {
        header('Location: ' . APP_URL . '/index.php?op=dashboard');
        exit;
        return '';
    }
}
