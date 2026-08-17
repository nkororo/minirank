document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var state = {
        allKeywords: [],
        currentSort: { field: 'name', direction: 'asc' },
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
            improved: 'bg-green-100 text-green-800',
            declined: 'bg-red-100 text-red-800',
            stable: 'bg-gray-100 text-gray-800'
        };
        var cls = classes[trend] || classes.stable;
        return '<span class="px-2 py-1 rounded-full text-xs font-medium ' + cls + '">' + escapeHtml(trend) + '</span>';
    }

    function renderKeywordRow(kw, globalIndex) {
        var html = '<tr class="border-b hover:bg-gray-50" data-id="' + kw.k_id + '">';
        html += '<td class="py-3 px-4 text-gray-500">' + (globalIndex + 1) + '</td>';
        html += '<td class="py-3 px-4">' + escapeHtml(kw.name) + '</td>';
        html += '<td class="py-3 px-4">' + (kw.current_position !== null ? kw.current_position : '-') + '</td>';
        html += '<td class="py-3 px-4">' + getTrendBadge(kw.trend) + '</td>';
        html += '<td class="py-3 px-4">';
        html += '<div class="relative inline-block">';
        html += '<button class="dropdown-toggle text-gray-600 hover:text-gray-900 px-2 py-1 rounded border">Actions ▾</button>';
        html += '<div class="dropdown-menu hidden absolute right-0 mt-1 w-36 bg-white border rounded-lg shadow-lg z-10">';
        html += '<a href="index.php?op=positions&id=' + kw.k_id + '" class="block px-4 py-2 text-sm hover:bg-gray-100">Details</a>';
        html += '<button class="edit-btn block w-full text-left px-4 py-2 text-sm hover:bg-gray-100" data-id="' + kw.k_id + '" data-name="' + escapeHtml(kw.name) + '">Edit</button>';
        html += '<button class="delete-btn block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100" data-id="' + kw.k_id + '">Delete</button>';
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

    async function loadKeywords() {
        try {
            var result = await apiCall('ajax/get-keywords.php');
            state.allKeywords = result.data;
            renderTable();
        } catch (err) {
            dom.tableBody.innerHTML = '<tr><td colspan="5" class="py-8 px-4 text-center text-red-500">Failed to load keywords.</td></tr>';
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
            dom.addModal.classList.add('hidden');
            dom.addModal.classList.remove('flex');
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
                    break;
                }
            }
            renderTable();
            dom.editModal.classList.add('hidden');
            dom.editModal.classList.remove('flex');
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
        } catch (err) {
            var row = dom.tableBody.querySelector('tr[data-id="' + id + '"]');
            if (row) {
                var errorDiv = document.createElement('div');
                errorDiv.className = 'text-red-500 text-sm mt-1';
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
        dom.addModal.classList.remove('hidden');
        dom.addModal.classList.add('flex');
        dom.addName.focus();
    }

    function hideAddModal() {
        dom.addModal.classList.add('hidden');
        dom.addModal.classList.remove('flex');
        hideError(dom.addError);
    }

    function showEditModal(id, name) {
        hideError(dom.editError);
        dom.editId.value = id;
        dom.editName.value = name;
        dom.editModal.classList.remove('hidden');
        dom.editModal.classList.add('flex');
        dom.editName.focus();
    }

    function hideEditModal() {
        dom.editModal.classList.add('hidden');
        dom.editModal.classList.remove('flex');
        hideError(dom.editError);
    }

    function closeAllDropdowns() {
        var menus = document.querySelectorAll('.dropdown-menu');
        for (var i = 0; i < menus.length; i++) {
            menus[i].classList.add('hidden');
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
        var val = this.value.split('_');
        state.currentSort.field = val[0];
        state.currentSort.direction = val[1];
        renderTable();
    });

    dom.tableBody.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('.dropdown-toggle');
        if (toggleBtn) {
            var menu = toggleBtn.nextElementSibling;
            closeAllDropdowns();
            menu.classList.toggle('hidden');
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
        if (!e.target.closest('.relative')) {
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
