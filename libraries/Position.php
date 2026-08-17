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
            'SELECT * FROM `keywords` WHERE `k_id` = ? AND `user_id` = ?',
            [$kid, $userId]
        );

        if (!$keyword) {
            header('Location: ' . APP_URL . '/index.php?op=dashboard');
            exit;
        }

        // Count distinct users tracking this exact keyword phrase
        $adoptionRow = $db->fetchOne(
            'SELECT COUNT(DISTINCT `user_id`) AS `count` FROM `keywords` WHERE LOWER(`name`) = LOWER(?)',
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
        <div class="max-w-6xl mx-auto p-6">
            <div class="mb-6">
                <a href="' . $appUrl . '/index.php?op=dashboard"
                    class="text-blue-500 hover:underline">&larr; Back to Dashboard</a>
            </div>

            <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold">' . $keywordName . '</h1>
                    <p class="text-gray-500 mt-1">Current Rank: ' . $positionBadge . '</p>
                </div>
                <div class="bg-white rounded-lg shadow px-6 py-4 text-center">
                    <p class="text-sm text-gray-500">Users Tracking</p>
                    <p class="text-2xl font-bold text-blue-600">' . $adoptionCount . '</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Ranking History</h2>
                <div id="chart-wrapper" class="relative" style="height: 350px;">
                    <canvas id="ranking-chart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-lg font-semibold">Position History</h2>
                </div>
                <div id="history-empty" class="hidden px-6 py-12 text-center text-gray-500">
                    No position records yet. Use Refresh Positions on the dashboard to generate data.
                </div>
                <div id="history-content">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-3 px-4 text-left w-16">#</th>
                                <th class="py-3 px-4 text-left">Date</th>
                                <th class="py-3 px-4 text-left">Position</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body"></tbody>
                    </table>
                </div>
                <div id="pagination-controls" class="flex justify-between items-center px-6 py-4 border-t"></div>
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
                        wrapper.innerHTML = \'<div class="flex items-center justify-center h-full text-gray-400">No data available for chart</div>\';
                    }
                    return;
                }
                if (typeof Chart === "undefined") {
                    var wrapper = document.getElementById("chart-wrapper");
                    if (wrapper) {
                        wrapper.innerHTML = \'<div class="flex items-center justify-center h-full text-gray-400">Chart library failed to load</div>\';
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
                            borderColor: "rgb(59, 130, 246)",
                            backgroundColor: "rgba(59, 130, 246, 0.1)",
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
                var color;
                if (pos <= 3) { color = "bg-green-100 text-green-800"; }
                else if (pos <= 10) { color = "bg-blue-100 text-blue-800"; }
                else if (pos <= 20) { color = "bg-yellow-100 text-yellow-800"; }
                else { color = "bg-gray-100 text-gray-800"; }
                return \'<span class="px-2 py-1 rounded-full text-xs font-medium \' + color + \'">#\' + pos + \'</span>\';
            }

            // --- Pagination via TablePaginator ---
            var tbody = document.getElementById("history-table-body");
            var paginationControls = document.getElementById("pagination-controls");
            var historyEmpty = document.getElementById("history-empty");
            var historyContent = document.getElementById("history-content");

            function renderRow(row, globalIndex) {
                var html = \'<tr class="border-b hover:bg-gray-50">\';
                html += \'<td class="py-3 px-4 text-gray-500">\' + (globalIndex + 1) + \'</td>\';
                html += \'<td class="py-3 px-4">\' + formatDate(row.date) + \'</td>\';
                html += \'<td class="py-3 px-4">\' + getPositionBadgeHtml(row.position) + \'</td>\';
                html += \'</tr>\';
                return html;
            }

            function initPagination() {
                if (allRows.length === 0) {
                    historyEmpty.classList.remove("hidden");
                    historyContent.classList.add("hidden");
                    paginationControls.classList.add("hidden");
                    return;
                }
                historyEmpty.classList.add("hidden");
                historyContent.classList.remove("hidden");
                paginationControls.classList.remove("hidden");

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
            $color = 'bg-green-100 text-green-800';
        } elseif ($position <= 10) {
            $color = 'bg-blue-100 text-blue-800';
        } elseif ($position <= 20) {
            $color = 'bg-yellow-100 text-yellow-800';
        } else {
            $color = 'bg-gray-100 text-gray-800';
        }

        return '<span class="px-2 py-1 rounded-full text-xs font-medium ' . $color . '">#' . $position . '</span>';
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
