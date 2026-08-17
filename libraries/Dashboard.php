<?php

class Dashboard
{
    public function displayPage(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $csrfToken = generateCsrfToken();

        /* Fetch all projects for this user with keyword counts */
        $projects = $db->fetchAll(
            'SELECT
                p.`project_id`,
                p.`name`,
                p.`domain`,
                p.`status`,
                p.`created_at`,
                COUNT(k.`k_id`) AS `keywords_count`
            FROM `projects` p
            LEFT JOIN `keywords` k ON k.`project_id` = p.`project_id`
            WHERE p.`user_id` = ?
            GROUP BY 
                p.`project_id`,
                p.`name`,
                p.`domain`,
                p.`status`,
                p.`created_at`
            ORDER BY p.`created_at` DESC',
            [$userId]
        );

        /* Calculate stats */
        $totalProjects = count($projects);
        $activeProjects = 0;
        $archivedProjects = 0;
        foreach ($projects as $p) {
            if ($p['status'] === 'active') {
                $activeProjects++;
            } else {
                $archivedProjects++;
            }
        }

        return '
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Projects Dashboard</h1>
                <button id="btn-new-project"
                    class="btn btn-success">+ New Project</button>
            </div>

            <!-- Stats Cards -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Projects</div>
                    <div class="stat-value">' . $totalProjects . '</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active</div>
                    <div class="stat-value stat-value--success">' . $activeProjects . '</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Archived</div>
                    <div class="stat-value stat-value--muted">' . $archivedProjects . '</div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab filter-tab--active" data-filter="all">All</button>
                <button class="filter-tab filter-tab--inactive" data-filter="active">Active</button>
                <button class="filter-tab filter-tab--inactive" data-filter="archived">Archived</button>
            </div>

            <!-- Projects Table -->
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="col-index">#</th>
                            <th class="col-auto">Project Name & Domain</th>
                            <th class="col-position">Keywords</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="projects-table-body" data-col-span="5">
                        <tr><td colspan="5" class="table-empty-cell">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="dashboard-pagination" class="pagination"></div>
            </div>
        </div>

        <!-- New Project Modal -->
        <div id="project-modal" class="modal-overlay">
            <div class="modal">
                <div class="modal-body">
                    <h2 class="modal-title">New Project</h2>
                    <div id="project-error" class="is-hidden alert alert-error"></div>
                    <form id="project-form">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                        <div class="form-group">
                            <label class="form-label" for="project-name">Project Name</label>
                            <input type="text" id="project-name" name="name" required
                                class="form-input"
                                placeholder="My Website">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="project-domain">Domain</label>
                            <input type="text" id="project-domain" name="domain"
                                class="form-input"
                                placeholder="example.com">
                        </div>
                        <div class="modal-actions">
                            <button type="button" id="project-cancel"
                                class="btn btn-ghost">Cancel</button>
                            <button type="submit"
                                class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>window.__PROJECTS_DATA__ = ' .json_encode($projects) . ';</script>';
    }
}
