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
        <div class="max-w-6xl mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Projects Dashboard</h1>
                <button id="btn-new-project"
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">+ New Project</button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Total Projects</div>
                    <div class="text-2xl font-bold">' . $totalProjects . '</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Active</div>
                    <div class="text-2xl font-bold text-green-600">' . $activeProjects . '</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Archived</div>
                    <div class="text-2xl font-bold text-gray-400">' . $archivedProjects . '</div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="flex gap-2 mb-4">
                <button class="filter-tab px-4 py-2 rounded text-sm font-medium bg-blue-500 text-white" data-filter="all">All</button>
                <button class="filter-tab px-4 py-2 rounded text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300" data-filter="active">Active</button>
                <button class="filter-tab px-4 py-2 rounded text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300" data-filter="archived">Archived</button>
            </div>

            <!-- Projects Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-3 px-4 text-left w-16">#</th>
                            <th class="py-3 px-4 text-left">Project Name & Domain</th>
                            <th class="py-3 px-4 text-left">Keywords</th>
                            <th class="py-3 px-4 text-left">Status</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="projects-table-body" data-col-span="5">
                        <tr><td colspan="5" class="py-8 px-4 text-center text-gray-500">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="dashboard-pagination" class="flex justify-between items-center px-6 py-4 border-t"></div>
            </div>
        </div>

        <!-- New Project Modal -->
        <div id="project-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">New Project</h2>
                    <div id="project-error" class="hidden mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded"></div>
                    <form id="project-form">
                        <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1" for="project-name">Project Name</label>
                            <input type="text" id="project-name" name="name" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="My Website">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1" for="project-domain">Domain</label>
                            <input type="text" id="project-domain" name="domain"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="example.com">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" id="project-cancel"
                                class="px-4 py-2 text-gray-500 hover:underline">Cancel</button>
                            <button type="submit"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>window.__PROJECTS_DATA__ = ' .json_encode($projects) . ';</script>';
    }
}
