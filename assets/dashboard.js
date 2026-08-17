document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var state = {
        allProjects: [],
        currentFilter: 'all'
    };

    var dom = {
        tableBody: document.getElementById('projects-table-body'),
        paginationContainer: document.getElementById('dashboard-pagination'),
        btnNewProject: document.getElementById('btn-new-project'),
        projectModal: document.getElementById('project-modal'),
        projectForm: document.getElementById('project-form'),
        projectError: document.getElementById('project-error'),
        projectName: document.getElementById('project-name'),
        projectCancel: document.getElementById('project-cancel'),
        filterTabs: document.querySelectorAll('.filter-tab')
    };

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getCsrfToken() {
        var tokenInput = dom.projectForm.querySelector('input[name="csrf_token"]');
        return tokenInput ? tokenInput.value : '';
    }

    function showError(element, message) {
        element.textContent = message;
        element.classList.remove('hidden');
    }

    function hideError(element) {
        element.textContent = '';
        element.classList.add('hidden');
    }

    async function apiCall(url, options) {
        var response = await fetch(url, options);
        var data = await response.json();
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    function getStatusBadge(status) {
        if (status === 'active') {
            return '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>';
        }
        return '<span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Archived</span>';
    }

    function getFilteredProjects() {
        if (state.currentFilter === 'all') {
            return state.allProjects;
        }
        return state.allProjects.filter(function (p) {
            return p.status === state.currentFilter;
        });
    }

    function renderProjectRow(project, globalIndex) {
        var domainHtml = project.domain
            ? '<span class="text-gray-400 text-sm ml-1">(' + escapeHtml(project.domain) + ')</span>'
            : '';

        var nameLink = '<a href="index.php?op=project&id=' + project.project_id + '" class="text-blue-600 hover:underline font-medium">'
            + escapeHtml(project.name) + '</a>' + domainHtml;

        var toggleLabel = project.status === 'active' ? 'Archive' : 'Restore';
        var toggleClass = project.status === 'active'
            ? 'archive-btn text-orange-600 hover:text-orange-800'
            : 'restore-btn text-green-600 hover:text-green-800';

        var html = '<tr class="border-b hover:bg-gray-50" data-id="' + project.project_id + '">';
        html += '<td class="py-3 px-4 text-gray-500">' + (globalIndex + 1) + '</td>';
        html += '<td class="py-3 px-4">' + nameLink + '</td>';
        html += '<td class="py-3 px-4">' + project.keywords_count + '</td>';
        html += '<td class="py-3 px-4">' + getStatusBadge(project.status) + '</td>';
        html += '<td class="py-3 px-4">';
        html += '<div class="relative inline-block">';
        html += '<button class="dropdown-toggle text-gray-600 hover:text-gray-900 px-2 py-1 rounded border">Actions ▾</button>';
        html += '<div class="dropdown-menu hidden absolute right-0 mt-1 w-36 bg-white border rounded-lg shadow-lg z-10">';
        html += '<a href="index.php?op=project&id=' + project.project_id + '" class="block px-4 py-2 text-sm hover:bg-gray-100">View</a>';
        html += '<button class="' + toggleClass + ' block w-full text-left px-4 py-2 text-sm hover:bg-gray-100" data-id="' + project.project_id + '">' + toggleLabel + '</button>';
        html += '<button class="delete-project-btn block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100" data-id="' + project.project_id + '">Delete</button>';
        html += '</div></div>';
        html += '</td></tr>';
        return html;
    }

    var paginator = new TablePaginator({
        tableBody: dom.tableBody,
        paginationContainer: dom.paginationContainer,
        pageSize: 10,
        renderRow: renderProjectRow
    });

    function renderTable() {
        var filtered = getFilteredProjects();
        paginator.setData(filtered);
    }

    async function loadProjects() {
        try {
            /* Projects are embedded in the page via PHP, parse from DOM */
            var rows = dom.tableBody.querySelectorAll('tr[data-id]');
            /* If loading fresh, fetch via a dedicated endpoint or parse embedded data */
            state.allProjects = window.__PROJECTS_DATA__ || [];
            renderTable();
        } catch (err) {
            dom.tableBody.innerHTML = '<tr><td colspan="5" class="py-8 px-4 text-center text-red-500">Failed to load projects.</td></tr>';
        }
    }

    async function addProject(e) {
        e.preventDefault();
        hideError(dom.projectError);

        var formData = new FormData(dom.projectForm);

        try {
            var result = await apiCall('ajax/add-project.php', {
                method: 'POST',
                body: formData
            });
            state.allProjects.unshift(result.data);
            renderTable();
            dom.projectModal.classList.add('hidden');
            dom.projectModal.classList.remove('flex');
            dom.projectForm.reset();
        } catch (err) {
            showError(dom.projectError, err.message);
        }
    }

    async function archiveProject(projectId) {
        var formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('csrf_token', getCsrfToken());

        try {
            var result = await apiCall('ajax/archive-project.php', {
                method: 'POST',
                body: formData
            });
            for (var i = 0; i < state.allProjects.length; i++) {
                if (state.allProjects[i].project_id === projectId) {
                    state.allProjects[i].status = result.data.status;
                    break;
                }
            }
            renderTable();
        } catch (err) {
            alert(err.message);
        }
    }

    async function deleteProject(projectId) {
        var formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('csrf_token', getCsrfToken());

        try {
            await apiCall('ajax/delete-project.php', {
                method: 'POST',
                body: formData
            });
            state.allProjects = state.allProjects.filter(function (p) {
                return p.project_id !== projectId;
            });
            renderTable();
        } catch (err) {
            alert(err.message);
        }
    }

    function showProjectModal() {
        hideError(dom.projectError);
        dom.projectForm.reset();
        dom.projectModal.classList.remove('hidden');
        dom.projectModal.classList.add('flex');
        dom.projectName.focus();
    }

    function hideProjectModal() {
        dom.projectModal.classList.add('hidden');
        dom.projectModal.classList.remove('flex');
        hideError(dom.projectError);
    }

    function closeAllDropdowns() {
        var menus = document.querySelectorAll('.dropdown-menu');
        for (var i = 0; i < menus.length; i++) {
            menus[i].classList.add('hidden');
        }
    }

    /* Event listeners */
    dom.btnNewProject.addEventListener('click', showProjectModal);
    dom.projectCancel.addEventListener('click', hideProjectModal);
    dom.projectForm.addEventListener('submit', addProject);

    dom.filterTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state.currentFilter = this.dataset.filter;
            dom.filterTabs.forEach(function (t) {
                t.classList.remove('bg-blue-500', 'text-white');
                t.classList.add('bg-gray-200', 'text-gray-700');
            });
            this.classList.remove('bg-gray-200', 'text-gray-700');
            this.classList.add('bg-blue-500', 'text-white');
            renderTable();
        });
    });

    dom.tableBody.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('.dropdown-toggle');
        if (toggleBtn) {
            var menu = toggleBtn.nextElementSibling;
            closeAllDropdowns();
            menu.classList.toggle('hidden');
            return;
        }

        var archiveBtn = e.target.closest('.archive-btn');
        if (archiveBtn) {
            archiveProject(parseInt(archiveBtn.dataset.id, 10));
            return;
        }

        var restoreBtn = e.target.closest('.restore-btn');
        if (restoreBtn) {
            archiveProject(parseInt(restoreBtn.dataset.id, 10));
            return;
        }

        var deleteBtn = e.target.closest('.delete-project-btn');
        if (deleteBtn) {
            if (confirm('Are you sure you want to delete this project and all its keywords?')) {
                deleteProject(parseInt(deleteBtn.dataset.id, 10));
            }
        }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.relative')) {
            closeAllDropdowns();
        }
    });

    dom.projectModal.addEventListener('click', function (e) {
        if (e.target === dom.projectModal) {
            hideProjectModal();
        }
    });

    /* Load embedded projects data */
    loadProjects();
});
