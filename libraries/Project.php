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
            ? '<span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Archived</span>'
            : '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>';
        $domainDisplay = $project['domain'] !== '' ? ' <span class="text-gray-400 text-sm">(' . sanitize($project['domain']) . ')</span>' : '';

        /* Build archived banner */
        $archivedBanner = '';
        if ($isArchived) {
            $archivedBanner = '
            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-300 rounded-lg text-yellow-800">
                This project is archived. Keyword creation and position refresh are disabled.
                <a href="' . sanitize(APP_URL . '/index.php?op=archive_project&id=' . $projectId) . '"
                    class="underline ml-2 font-medium">Restore Project</a>
            </div>';
        }

        /* Build action buttons */
        $addBtnDisabled = $isArchived ? 'disabled' : '';
        $addBtnClasses = $isArchived
            ? 'bg-gray-400 text-white px-4 py-2 rounded cursor-not-allowed'
            : 'bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600';

        $refreshBtnDisabled = $isArchived ? 'disabled' : '';
        $refreshBtnClasses = $isArchived
            ? 'bg-gray-400 text-white px-4 py-2 rounded cursor-not-allowed'
            : 'bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600';

        return '
        <div class="max-w-6xl mx-auto p-6">
            <!-- Header -->
            <div class="mb-6">
                <a href="' . sanitize(APP_URL . '/index.php?op=dashboard') . '"
                    class="text-blue-500 hover:underline text-sm">&larr; Back to Projects Dashboard</a>
                <div class="flex justify-between items-center mt-2">
                    <div>
                        <h1 class="text-2xl font-bold">' . sanitize($project['name']) . $domainDisplay . ' ' . $statusBadge . '</h1>
                    </div>
                    <div class="flex gap-2">
                        <button id="btn-refresh" ' . $refreshBtnDisabled . '
                            class="' . $refreshBtnClasses . '">Refresh Positions</button>
                    </div>
                </div>
            </div>

            ' . $archivedBanner . '

            <!-- Toolbar -->
            <div class="flex flex-wrap gap-4 mb-6">
                <button id="btn-add" ' . $addBtnDisabled . '
                    class="' . $addBtnClasses . '">+ Add Keyword</button>
                <input type="text" id="search-input" placeholder="Search keywords..."
                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 flex-1 min-w-[200px]">
                <select id="filter-select"
                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Trends</option>
                    <option value="improved">Improved</option>
                    <option value="declined">Declined</option>
                    <option value="stable">Stable</option>
                </select>
                <select id="sort-select"
                    class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="name_asc">Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                    <option value="position_asc">Position Low-High</option>
                    <option value="position_desc">Position High-Low</option>
                </select>
            </div>

            <!-- Keywords Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-3 px-4 text-left w-16">#</th>
                            <th class="py-3 px-4 text-left">Keyword</th>
                            <th class="py-3 px-4 text-left">Position</th>
                            <th class="py-3 px-4 text-left">Trend</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="keywords-table-body" data-col-span="5">
                        <tr><td colspan="5" class="py-8 px-4 text-center text-gray-500">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="dashboard-pagination" class="flex justify-between items-center px-6 py-4 border-t"></div>
            </div>

            <div class="mt-4">
                <button id="btn-export"
                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Export CSV</button>
            </div>
        </div>

        <!-- Add Keyword Modal -->
        <div id="add-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">Add Keyword</h2>
                    <div id="add-error" class="hidden mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded"></div>
                    <form id="add-form">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1" for="add-name">Keyword Phrase</label>
                            <input type="text" id="add-name" name="name" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Enter keyword phrase">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" id="add-cancel"
                                class="px-4 py-2 text-gray-500 hover:underline">Cancel</button>
                            <button type="submit"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Keyword Modal -->
        <div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">Edit Keyword</h2>
                    <div id="edit-error" class="hidden mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded"></div>
                    <form id="edit-form">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                        <input type="hidden" id="edit-id" name="id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1" for="edit-name">Keyword Phrase</label>
                            <input type="text" id="edit-name" name="name" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" id="edit-cancel"
                                class="px-4 py-2 text-gray-500 hover:underline">Cancel</button>
                            <button type="submit"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>window.__PROJECT_ID__ = ' . (int) $projectId . ';</script>';
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
                $db->update('projects', ['status' => $newStatus], '`project_id` = ? AND `user_id` = ?', [$projectId, $userId]);
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
