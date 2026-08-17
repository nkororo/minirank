document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var state = {
        allKeywords: [],
        currentSort: { field: 'updated_at', direction: 'desc' },
        currentFilter: { search: '', trend: '' }
    };

    var dom = {
        tableBody: document.getElementById('keywords-table-body'),
        paginationContainer: document.getElementById('dashboard-pagination'),
        searchInput: document.getElementById('search-input'),
        filterSelect: document.getElementById('filter-select'),
        sortSelect: document.getElementById('sort-select'),
        btnAdd: document.getElementById('btn-add'),
        btnRefresh: document.getElementById('btn-refresh'),
        btnExport: document.getElementById('btn-export'),
        addModal: document.getElementById('add-modal'),
        addForm: document.getElementById('add-form'),
        addError: document.getElementById('add-error'),
        addName: document.getElementById('add-name'),
        addCancel: document.getElementById('add-cancel'),
        editModal: document.getElementById('edit-modal'),
        editForm: document.getElementById('edit-form'),
        editError: document.getElementById('edit-error'),
        editCancel: document.getElementById('edit-cancel'),
        editId: document.getElementById('edit-id'),
        editName: document.getElementById('edit-name')
    };

    /* Guard: only run on project page where these elements exist */
    if (!dom.tableBody) {
        return;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getCsrfToken() {
        var tokenInput = dom.addForm.querySelector('input[name="csrf_token"]');
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

    function getFilteredKeywords() {
        var filtered = state.allKeywords.slice();

        if (state.currentFilter.search) {
            var search = state.currentFilter.search.toLowerCase();
            filtered = filtered.filter(function (kw) {
                return kw.name.toLowerCase().indexOf(search) !== -1;
            });
        }

        if (state.currentFilter.trend) {
            filtered = filtered.filter(function (kw) {
                return kw.trend === state.currentFilter.trend;
            });
        }

        var sortField = state.currentSort.field;
        var sortDir = state.currentSort.direction;
        filtered.sort(function (a, b) {
            var valA, valB;
            if (sortField === 'name') {
                valA = a.name.toLowerCase();
                valB = b.name.toLowerCase();
            } else if (sortField === 'updated_at') {
                valA = a.updated_at || '';
                valB = b.updated_at || '';
            } else {
                valA = a.current_position === null ? Infinity : a.current_position;
                valB = b.current_position === null ? Infinity : b.current_position;
            }
            if (valA < valB) return sortDir === 'asc' ? -1 : 1;
            if (valA > valB) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });

        return filtered;
    }

    function getTrendBadge(trend) {
        var classes = {
            improved: 'badge badge-improved',
            declined: 'badge badge-declined',
            stable: 'badge badge-stable'
        };
        var cls = classes[trend] || classes.stable;
        return '<span class="' + cls + '">' + escapeHtml(trend) + '</span>';
    }

    function renderKeywordRow(kw, globalIndex) {
        var html = '<tr data-id="' + kw.k_id + '">';
        html += '<td class="col-index table-row-number">' + (globalIndex + 1) + '</td>';
        html += '<td class="col-auto"><a href="index.php?op=positions&id=' + kw.k_id + '" class="link font-medium keyword-link">' + escapeHtml(kw.name) + '</a></td>';
        html += '<td class="col-position">' + (kw.current_position !== null ? kw.current_position : '-') + '</td>';
        html += '<td class="col-status">' + getTrendBadge(kw.trend) + '</td>';
        html += '<td class="col-actions">';
        html += '<div class="dropdown">';
        html += '<button class="dropdown-toggle">Actions &#9662;</button>';
        html += '<div class="dropdown-menu">';
        html += '<a href="index.php?op=positions&id=' + kw.k_id + '" class="dropdown-item">Details</a>';
        html += '<button class="edit-btn dropdown-item" data-id="' + kw.k_id + '" data-name="' + escapeHtml(kw.name) + '">Edit</button>';
        html += '<button class="delete-btn dropdown-item dropdown-item--danger" data-id="' + kw.k_id + '">Delete</button>';
        html += '</div></div>';
        html += '</td></tr>';
        return html;
    }

    var paginator = new TablePaginator({
        tableBody: dom.tableBody,
        paginationContainer: dom.paginationContainer,
        pageSize: 10,
        renderRow: renderKeywordRow
    });

    function renderTable() {
        var filtered = getFilteredKeywords();
        paginator.setData(filtered);
    }

    /* Update the keyword metrics displayed in the stat cards */
    function updateProjectMetrics() {
        var pmTotal = document.getElementById('pm-total');
        var pmTop = document.getElementById('pm-top');
        var pmTrending = document.getElementById('pm-trending');
        if (!pmTotal) {
            return;
        }

        var total = state.allKeywords.length;
        pmTotal.textContent = total;

        /* Compute top keyword by lowest current position > 0 */
        var withPosition = state.allKeywords.filter(function (kw) {
            return kw.current_position !== null && kw.current_position > 0;
        });
        withPosition.sort(function (a, b) {
            return a.current_position - b.current_position;
        });

        if (withPosition.length > 0) {
            pmTop.textContent = escapeHtml(withPosition[0].name) + ' (#' + withPosition[0].current_position + ')';
        } else {
            pmTop.textContent = '\u2014';
        }

        /* best_trending requires 30-day history; keep server value, refresh on position refresh */
    }

    async function loadKeywords() {
        try {
            var result = await apiCall('ajax/get-keywords.php');
            state.allKeywords = result.data;
            renderTable();
        } catch (err) {
            dom.tableBody.innerHTML = '<tr><td colspan="5" class="table-empty-cell" style="color: var(--color-danger);">Failed to load keywords.</td></tr>';
        }
    }

    async function addKeyword(e) {
        e.preventDefault();
        hideError(dom.addError);

        var formData = new FormData(dom.addForm);

        try {
            var result = await apiCall('ajax/add-keyword.php', {
                method: 'POST',
                body: formData
            });
            state.allKeywords.unshift(result.data);
            renderTable();
            updateProjectMetrics();
            dom.addModal.classList.remove('is-active');
            dom.addForm.reset();
        } catch (err) {
            showError(dom.addError, err.message);
        }
    }

    async function editKeyword(e) {
        e.preventDefault();
        hideError(dom.editError);

        var formData = new FormData(dom.editForm);

        try {
            var result = await apiCall('ajax/edit-keyword.php', {
                method: 'POST',
                body: formData
            });
            var id = parseInt(dom.editId.value, 10);
            for (var i = 0; i < state.allKeywords.length; i++) {
                if (state.allKeywords[i].k_id === id) {
                    state.allKeywords[i].name = result.data.name;
                    state.allKeywords[i].updated_at = result.data.updated_at;
                    var edited = state.allKeywords.splice(i, 1)[0];
                    state.allKeywords.unshift(edited);
                    break;
                }
            }
            renderTable();
            updateProjectMetrics();
            dom.editModal.classList.remove('is-active');
        } catch (err) {
            showError(dom.editError, err.message);
        }
    }

    async function deleteKeyword(id) {
        var formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', getCsrfToken());

        try {
            await apiCall('ajax/delete-keyword.php', {
                method: 'POST',
                body: formData
            });
            state.allKeywords = state.allKeywords.filter(function (kw) {
                return kw.k_id !== id;
            });
            renderTable();
            updateProjectMetrics();
        } catch (err) {
            var row = dom.tableBody.querySelector('tr[data-id="' + id + '"]');
            if (row) {
                var errorDiv = document.createElement('div');
                errorDiv.className = 'row-error';
                errorDiv.textContent = err.message;
                row.querySelector('td').appendChild(errorDiv);
            }
        }
    }

    async function refreshPositions() {
        dom.btnRefresh.disabled = true;
        dom.btnRefresh.textContent = 'Refreshing...';

        var formData = new FormData();
        formData.append('csrf_token', getCsrfToken());
        formData.append('project_id', window.__PROJECT_ID__ || '');

        try {
            await apiCall('ajax/refresh-positions.php', {
                method: 'POST',
                body: formData
            });
            await loadKeywords();

            /* Fetch updated trending keyword from server */
            var statsResult = await apiCall('ajax/get-project-stats.php');
            var pmTrending = document.getElementById('pm-trending');
            if (pmTrending) {
                pmTrending.textContent = statsResult.data.best_trending
                    ? escapeHtml(statsResult.data.best_trending)
                    : '\u2014';
            }
        } catch (err) {
            showError(dom.addError, err.message);
        } finally {
            dom.btnRefresh.disabled = false;
            dom.btnRefresh.textContent = 'Refresh Positions';
        }
    }

    function showAddModal() {
        hideError(dom.addError);
        dom.addForm.reset();
        dom.addModal.classList.add('is-active');
        dom.addName.focus();
    }

    function hideAddModal() {
        dom.addModal.classList.remove('is-active');
        hideError(dom.addError);
    }

    function showEditModal(id, name) {
        hideError(dom.editError);
        dom.editId.value = id;
        dom.editName.value = name;
        dom.editModal.classList.add('is-active');
        dom.editName.focus();
    }

    function hideEditModal() {
        dom.editModal.classList.remove('is-active');
        hideError(dom.editError);
    }

    function closeAllDropdowns() {
        var menus = document.querySelectorAll('.dropdown-menu.show');
        for (var i = 0; i < menus.length; i++) {
            menus[i].classList.remove('show');
        }
    }

    dom.btnAdd.addEventListener('click', showAddModal);
    dom.addCancel.addEventListener('click', hideAddModal);
    dom.addForm.addEventListener('submit', addKeyword);

    dom.editCancel.addEventListener('click', hideEditModal);
    dom.editForm.addEventListener('submit', editKeyword);

    dom.btnRefresh.addEventListener('click', refreshPositions);

    dom.btnExport.addEventListener('click', function () {
        window.location.href = 'ajax/export.php';
    });

    dom.searchInput.addEventListener('input', function () {
        var self = this;
        clearTimeout(self.debounceTimer);
        self.debounceTimer = setTimeout(function () {
            state.currentFilter.search = self.value.toLowerCase();
            renderTable();
        }, 300);
    });

    dom.filterSelect.addEventListener('change', function () {
        state.currentFilter.trend = this.value;
        renderTable();
    });

    dom.sortSelect.addEventListener('change', function () {
        var parts = this.value.split('_');
        var direction = parts.pop();
        state.currentSort.field = parts.join('_');
        state.currentSort.direction = direction;
        renderTable();
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

        var editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            showEditModal(parseInt(editBtn.dataset.id, 10), editBtn.dataset.name);
            return;
        }

        var deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            if (confirm('Are you sure you want to delete this keyword?')) {
                deleteKeyword(parseInt(deleteBtn.dataset.id, 10));
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

    dom.addModal.addEventListener('click', function (e) {
        if (e.target === dom.addModal) {
            hideAddModal();
        }
    });

    dom.editModal.addEventListener('click', function (e) {
        if (e.target === dom.editModal) {
            hideEditModal();
        }
    });

    loadKeywords();
});
