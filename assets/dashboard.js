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
        filterTabs: document.querySelectorAll('.filter-tab'),
        statTotal: document.getElementById('stat-total'),
        statActive: document.getElementById('stat-active'),
        statArchived: document.getElementById('stat-archived')
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
        element.classList.remove('is-hidden');
    }

    function hideError(element) {
        element.textContent = '';
        element.classList.add('is-hidden');
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
            return '<span class="badge badge-success">Active</span>';
        }
        return '<span class="badge badge-muted">Archived</span>';
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
            ? '<span class="project-domain ml-1">(' + escapeHtml(project.domain) + ')</span>'
            : '';

        var nameLink = '<a href="index.php?op=project&id=' + project.project_id + '" class="link font-medium">'
            + escapeHtml(project.name) + '</a>' + domainHtml;

        var toggleLabel = project.status === 'active' ? 'Archive' : 'Restore';
        var toggleClass = project.status === 'active'
            ? 'archive-btn link--muted dropdown-item'
            : 'restore-btn link dropdown-item';

        var html = '<tr data-id="' + project.project_id + '">';
        html += '<td class="col-index table-row-number">' + (globalIndex + 1) + '</td>';
        html += '<td class="col-auto">' + nameLink + '</td>';
        html += '<td class="col-position">' + project.keywords_count + '</td>';
        html += '<td class="col-status">' + getStatusBadge(project.status) + '</td>';
        html += '<td class="col-actions">';
        html += '<div class="dropdown">';
        html += '<button class="dropdown-toggle">Actions &#9662;</button>';
        html += '<div class="dropdown-menu">';
        html += '<a href="index.php?op=project&id=' + project.project_id + '" class="dropdown-item">View</a>';
        html += '<button class="' + toggleClass + '" data-id="' + project.project_id + '">' + toggleLabel + '</button>';
        html += '<button class="delete-project-btn dropdown-item dropdown-item--danger" data-id="' + project.project_id + '">Delete</button>';
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

    /* Recalculate and update the stats cards from the local projects array */
    function updateProjectsStats() {
        var total = state.allProjects.length;
        var active = 0;
        var archived = 0;
        for (var i = 0; i < total; i++) {
            if (state.allProjects[i].status === 'active') {
                active++;
            } else {
                archived++;
            }
        }
        dom.statTotal.textContent = total;
        dom.statActive.textContent = active;
        dom.statArchived.textContent = archived;
    }

    async function loadProjects() {
        try {
            /* Projects are embedded in the page via PHP, parse from DOM */
            var rows = dom.tableBody.querySelectorAll('tr[data-id]');
            /* If loading fresh, fetch via a dedicated endpoint or parse embedded data */
            state.allProjects = window.__PROJECTS_DATA__ || [];
            renderTable();
        } catch (err) {
            dom.tableBody.innerHTML = '<tr><td colspan="5" class="table-empty-cell" style="color: var(--color-danger);">Failed to load projects.</td></tr>';
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
            updateProjectsStats();
            dom.projectModal.classList.remove('is-active');
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
                    state.allProjects[i].updated_at = result.data.updated_at;
                    var moved = state.allProjects.splice(i, 1)[0];
                    state.allProjects.unshift(moved);
                    break;
                }
            }
            renderTable();
            updateProjectsStats();
        } catch (err) {
            alert(err.message);
        }
    }

    async function deleteProject(projectId) {
        var formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('csrf_token', getCsrfToken());

        try {
            var result = await apiCall('ajax/delete-project.php', {
                method: 'POST',
                body: formData
            });
            state.allProjects = result.data.projects;
            renderTable();
            updateProjectsStats();
        } catch (err) {
            alert(err.message);
        }
    }

    function showProjectModal() {
        hideError(dom.projectError);
        dom.projectForm.reset();
        dom.projectModal.classList.add('is-active');
        dom.projectName.focus();
    }

    function hideProjectModal() {
        dom.projectModal.classList.remove('is-active');
        hideError(dom.projectError);
    }

    function closeAllDropdowns() {
        var menus = document.querySelectorAll('.dropdown-menu.show');
        for (var i = 0; i < menus.length; i++) {
            menus[i].classList.remove('show');
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
                t.classList.remove('filter-tab--active');
                t.classList.add('filter-tab--inactive');
            });
            this.classList.remove('filter-tab--inactive');
            this.classList.add('filter-tab--active');
            renderTable();
        });
    });

    dom.tableBody.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('.dropdown-toggle');
        if (toggleBtn) {
            var menu = toggleBtn.nextElementSibling;
            var isOpen = menu.classList.contains('show');
            closeAllDropdowns();
            if (!isOpen) {
                menu.classList.add('show');
            }
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
        if (!e.target.closest('.dropdown')) {
            closeAllDropdowns();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
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
