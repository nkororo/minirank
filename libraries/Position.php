<?php

class Position
{
    public function displayPage(): string
    {
        $op = $_GET['op'] ?? '';

        if ($op === 'delete_position') {
            return $this->delete();
        }

        return $this->details();
    }

    private function details(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $kid = (int) ($_GET['id'] ?? 0);

        // Fetch keyword and verify ownership
        $keyword = $db->fetchOne(
            'SELECT k.*, p.project_id, p.name AS project_name, p.domain AS project_domain
            FROM `keywords` k
            INNER JOIN `projects` p ON k.`project_id` = p.`project_id`
            WHERE k.`k_id` = ? AND p.`user_id` = ?',
        [$kid, $userId]
    );
        if (!$keyword) {
            header('Location: ' . APP_URL . '/index.php?op=dashboard');
            exit;
        }

        // Count distinct users tracking this exact keyword phrase
        $adoptionRow = $db->fetchOne(
            'SELECT COUNT(DISTINCT p.`user_id`) AS `count`
            FROM `keywords` k
            INNER JOIN `projects` p ON k.`project_id` = p.`project_id`
            WHERE LOWER(k.`name`) = LOWER(?)',
            [$keyword['name']]
        );
        $adoptionCount = (int) ($adoptionRow['count'] ?? 0);

        // Fetch all position history (ASC for chart)
        $positionsAsc = $db->fetchAll(
            'SELECT `position`, `date` FROM `positions` WHERE `keyword_id` = ? ORDER BY `date` ASC',
            [$kid]
        );

        // Fetch all position history (DESC for table)
        $positionsDesc = $db->fetchAll(
            'SELECT `p_id`, `position`, `date` FROM `positions` WHERE `keyword_id` = ? ORDER BY `date` DESC',
            [$kid]
        );

        $csrfToken = generateCsrfToken();
        $appUrl = APP_URL;
        $keywordName = sanitize($keyword['name']);

        // Prepare chart data as JSON
        $chartLabels = array_map(fn($p) => formatDate($p['date']), $positionsAsc);
        $chartData = array_map(fn($p) => $p['position'], $positionsAsc);
        $chartLabelsJson = json_encode($chartLabels);
        $chartDataJson = json_encode($chartData);

        // Position badge helper values
        $currentPosition = !empty($positionsDesc) ? (int) $positionsDesc[0]['position'] : null;
        $positionBadge = $currentPosition !== null ? $this->getPositionBadge($currentPosition) : '-';

        return '
        <div class="container">
            <div class="mb-6">
                <a href="' . $appUrl . '/index.php?op=dashboard"
                    class="back-link">&larr; Back to Dashboard</a>
            </div>

            <div class="detail-header">
                <div>
                    <h1 class="page-title">' . $keywordName . '</h1>
                    <p class="detail-meta">Current Rank: ' . $positionBadge . '</p>
                </div>
                <div class="detail-stat">
                    <p class="detail-stat-label">Users Tracking</p>
                    <p class="detail-stat-value">' . $adoptionCount . '</p>
                </div>
            </div>

            <div class="chart-container">
                <h2 class="section-title">Ranking History</h2>
                <div id="chart-wrapper" class="chart-wrapper">
                    <canvas id="ranking-chart"></canvas>
                </div>
            </div>

            <div class="table-wrapper">
                <div class="card-header">
                    <h2 class="section-title" style="margin-bottom: 0;">Position History</h2>
                </div>
                <div id="history-empty" class="is-hidden" style="padding: 3rem 1.5rem; text-align: center; color: var(--color-text-muted);">
                    No position records yet. Use Refresh Positions on the dashboard to generate data.
                </div>
                <div id="history-content">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="col-index">#</th>
                                <th class="col-date">Date</th>
                                <th class="col-position">Position</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body"></tbody>
                    </table>
                </div>
                <div id="pagination-controls" class="pagination"></div>
            </div>
        </div>

        <script>
        (function () {
            "use strict";

            var allRows = ' . json_encode($positionsDesc) . ';

            // --- Chart ---
            var chartLabels = ' . $chartLabelsJson . ';
            var chartData = ' . $chartDataJson . ';

            function initChart() {
                var ctx = document.getElementById("ranking-chart");
                if (!ctx || chartLabels.length === 0) {
                    var wrapper = document.getElementById("chart-wrapper");
                    if (wrapper) {
                        wrapper.innerHTML = \'<div class="chart-empty">No data available for chart</div>\';
                    }
                    return;
                }
                if (typeof Chart === "undefined") {
                    var wrapper = document.getElementById("chart-wrapper");
                    if (wrapper) {
                        wrapper.innerHTML = \'<div class="chart-empty">Chart library failed to load</div>\';
                    }
                    return;
                }
                new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: "Rank",
                            data: chartData,
                            borderColor: "#5A2A27",
                            backgroundColor: "rgba(90, 42, 39, 0.1)",
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return "Rank #" + context.parsed.y;
                                    }
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                reverse: true,
                                min: 1,
                                max: 100,
                                title: {
                                    display: true,
                                    text: "Position"
                                },
                                ticks: {
                                    stepSize: 10
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: "Date"
                                }
                            }
                        }
                    }
                });
            }

            // --- Helpers ---
            function escapeHtml(str) {
                var div = document.createElement("div");
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function formatDate(str) {
                var months = [
                    "January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"
                ];
                var parts = str.split("-");
                if (parts.length < 3) return str;
                var day = parseInt(parts[2], 10);
                var month = months[parseInt(parts[1], 10) - 1];
                var year = parts[0];
                return day + " " + month + " " + year;
            }

            function getPositionBadgeHtml(pos) {
                var cls;
                if (pos <= 3) { cls = "badge badge-position-top3"; }
                else if (pos <= 10) { cls = "badge badge-position-top10"; }
                else if (pos <= 20) { cls = "badge badge-position-top20"; }
                else { cls = "badge badge-position-other"; }
                return \'<span class="\' + cls + \'">#\' + pos + \'</span>\';
            }

            // --- Pagination via TablePaginator ---
            var tbody = document.getElementById("history-table-body");
            var paginationControls = document.getElementById("pagination-controls");
            var historyEmpty = document.getElementById("history-empty");
            var historyContent = document.getElementById("history-content");

            function renderRow(row, globalIndex) {
                var html = \'<tr>\';
                html += \'<td class="col-index table-row-number">\' + (globalIndex + 1) + \'</td>\';
                html += \'<td class="col-date">\' + formatDate(row.date) + \'</td>\';
                html += \'<td class="col-position">\' + getPositionBadgeHtml(row.position) + \'</td>\';
                html += \'</tr>\';
                return html;
            }

            function initPagination() {
                if (allRows.length === 0) {
                    historyEmpty.classList.remove("is-hidden");
                    historyContent.classList.add("is-hidden");
                    paginationControls.classList.add("is-hidden");
                    return;
                }
                historyEmpty.classList.add("is-hidden");
                historyContent.classList.remove("is-hidden");
                paginationControls.classList.remove("is-hidden");

                if (typeof TablePaginator !== "undefined") {
                    var paginator = new TablePaginator({
                        tableBody: tbody,
                        paginationContainer: paginationControls,
                        pageSize: 10,
                        renderRow: renderRow
                    });
                    paginator.setData(allRows);
                } else {
                    /* Fallback: render first 10 rows directly if TablePaginator is unavailable */
                    var fallbackSlice = allRows.slice(0, 10);
                    var html = "";
                    for (var i = 0; i < fallbackSlice.length; i++) {
                        html += renderRow(fallbackSlice[i], i);
                    }
                    tbody.innerHTML = html;
                }
            }

            // --- Init ---
            document.addEventListener("DOMContentLoaded", function () {
                initChart();
                initPagination();
            });
        })();
        </script>';
    }

    private function getPositionBadge(int $position): string
    {
        if ($position <= 3) {
            $class = 'badge badge-position-top3';
        } elseif ($position <= 10) {
            $class = 'badge badge-position-top10';
        } elseif ($position <= 20) {
            $class = 'badge badge-position-top20';
        } else {
            $class = 'badge badge-position-other';
        }

        return '<span class="' . $class . '">#' . $position . '</span>';
    }

    private function delete(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $pid = (int) ($_GET['id'] ?? 0);
        $kid = (int) ($_GET['kwid'] ?? 0);

        $db->query(
            'DELETE FROM `positions` WHERE `p_id` = ? AND `keyword_id` IN (SELECT `k_id` FROM `keywords` WHERE `user_id` = ?)',
            [$pid, $userId]
        );

        header('Location: ' . APP_URL . '/index.php?op=positions&id=' . $kid);
        exit;
    }
}
