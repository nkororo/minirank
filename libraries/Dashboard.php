<?php

class Dashboard
{
    public function displayPage(): string
    {
        $csrfToken = generateCsrfToken();
        $appUrl = APP_URL;

        return '
        <div class="max-w-6xl mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <button id="btn-refresh"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Refresh Positions</button>
            </div>

            <div class="flex flex-wrap gap-4 mb-6">
                <button id="btn-add"
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">+ Add Keyword</button>
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
        </div>';
    }
}
