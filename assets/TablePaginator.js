/**
 * TablePaginator - Reusable pagination helper for data tables.
 *
 * @param {Object} options
 *   - tableBody {HTMLElement}          - The <tbody> element to render rows into.
 *   - paginationContainer {HTMLElement} - Container element for pagination controls.
 *   - pageSize {number}                - Rows per page (default 10).
 *   - renderRow {Function}             - Callback(item, index) returning an HTML string for a <tr>.
 */
function TablePaginator(options) {
    this.tableBody = options.tableBody;
    this.paginationContainer = options.paginationContainer;
    this.pageSize = options.pageSize || 10;
    this.renderRow = options.renderRow;
    this.data = [];
    this.currentPage = 1;
}

/**
 * Set new data, reset to page 1, and re-render.
 */
TablePaginator.prototype.setData = function (data) {
    this.data = data || [];
    this.currentPage = 1;
    this.render();
};

/**
 * Navigate to a specific page and re-render.
 */
TablePaginator.prototype.goToPage = function (page) {
    var totalPages = this.getTotalPages();
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    this.currentPage = page;
    this.render();
};

/**
 * Calculate total pages from the current dataset.
 */
TablePaginator.prototype.getTotalPages = function () {
    return Math.max(1, Math.ceil(this.data.length / this.pageSize));
};

/**
 * Render the current page of rows and update pagination controls.
 */
TablePaginator.prototype.render = function () {
    var total = this.data.length;
    var totalPages = this.getTotalPages();

    /* Clamp currentPage if dataset shrank */
    if (this.currentPage > totalPages) {
        this.currentPage = totalPages;
    }

    /* Render table rows */
    if (total === 0) {
        var colSpan = this.tableBody.getAttribute('data-col-span') || 5;
        this.tableBody.innerHTML =
            '<tr><td colspan="' + colSpan + '" class="py-8 px-4 text-center text-gray-500">No data found.</td></tr>';
        this.renderControls();
        return;
    }

    var start = (this.currentPage - 1) * this.pageSize;
    var end = Math.min(start + this.pageSize, total);
    var slice = this.data.slice(start, end);

    var html = '';
    for (var i = 0; i < slice.length; i++) {
        /* Pass the global row index so the callback can compute the # column */
        html += this.renderRow(slice[i], start + i);
    }
    this.tableBody.innerHTML = html;

    this.renderControls();
};

/**
 * Build and render pagination controls inside the container.
 * Disabled when total items < pageSize or no data.
 */
TablePaginator.prototype.renderControls = function () {
    var total = this.data.length;
    var totalPages = this.getTotalPages();
    var container = this.paginationContainer;

    /* Hide controls only when there is no data */
    if (total === 0) {
        container.innerHTML = '';
        return;
    }

    var self = this;

    /* Previous button */
    var prevBtn = document.createElement('button');
    prevBtn.textContent = 'Previous';
    prevBtn.className = 'px-3 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed';
    prevBtn.disabled = this.currentPage <= 1;
    prevBtn.addEventListener('click', function () {
        if (self.currentPage > 1) {
            self.currentPage--;
            self.render();
        }
    });

    /* Page numbers container */
    var pageNumbersWrap = document.createElement('div');
    pageNumbersWrap.className = 'flex gap-1';

    for (var i = 1; i <= totalPages; i++) {
        (function (page) {
            var btn = document.createElement('button');
            btn.textContent = page;
            btn.className = 'px-3 py-1 text-sm border rounded ' +
                (page === self.currentPage ? 'bg-blue-500 text-white' : 'hover:bg-gray-50');
            btn.addEventListener('click', function () {
                self.currentPage = page;
                self.render();
            });
            pageNumbersWrap.appendChild(btn);
        })(i);
    }

    /* Next button */
    var nextBtn = document.createElement('button');
    nextBtn.textContent = 'Next';
    nextBtn.className = 'px-3 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed';
    nextBtn.disabled = this.currentPage >= totalPages;
    nextBtn.addEventListener('click', function () {
        if (self.currentPage < totalPages) {
            self.currentPage++;
            self.render();
        }
    });

    /* Page info label */
    var info = document.createElement('span');
    info.textContent = 'Page ' + this.currentPage + ' of ' + totalPages;
    info.className = 'text-sm text-gray-600 mx-2';

    /* Assemble controls: Prev | info + page numbers | Next */
    container.innerHTML = '';
    container.appendChild(prevBtn);

    /* Center block: info label + page numbers stacked */
    var centerBlock = document.createElement('div');
    centerBlock.className = 'flex flex-col items-center gap-1';
    centerBlock.appendChild(info);
    centerBlock.appendChild(pageNumbersWrap);
    container.appendChild(centerBlock);

    container.appendChild(nextBtn);
};
