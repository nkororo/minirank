<?php

class Dashboard
{
    public function displayPage(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];

        $keywordCount = $db->fetchOne(
            'SELECT COUNT(*) AS `cnt` FROM `keywords` WHERE `user_id` = ?',
            [$userId]
        );

        $positionCount = $db->fetchOne(
            'SELECT COUNT(*) AS `cnt` FROM `positions` WHERE `keyword_id` IN (SELECT `k_id` FROM `keywords` WHERE `user_id` = ?)',
            [$userId]
        );

        $recentPositions = $db->fetchAll(
            'SELECT `p`.`position`, `p`.`created_at`, `k`.`name`
             FROM `positions` `p`
             JOIN `keywords` `k` ON `p`.`keyword_id` = `k`.`k_id`
             WHERE `k`.`user_id` = ?
             ORDER BY `p`.`created_at` DESC
             LIMIT 10',
            [$userId]
        );

        $rows = '';
        foreach ($recentPositions as $pos) {
            $rows .= '
                <tr class="border-b">
                    <td class="py-2 px-4">' . sanitize($pos['name']) . '</td>
                    <td class="py-2 px-4">' . $pos['position'] . '</td>
                    <td class="py-2 px-4 text-sm text-gray-500">' . sanitize($pos['created_at']) . '</td>
                </tr>';
        }

        return '
        <div class="max-w-4xl mx-auto p-6">
            <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <div class="text-3xl font-bold text-blue-600">' . ($keywordCount['cnt'] ?? 0) . '</div>
                    <div class="text-gray-500">Keywords</div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow text-center">
                    <div class="text-3xl font-bold text-green-600">' . ($positionCount['cnt'] ?? 0) . '</div>
                    <div class="text-gray-500">Positions Recorded</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <h2 class="text-lg font-semibold p-4 border-b">Recent Positions</h2>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 text-left">Keyword</th>
                            <th class="py-2 px-4 text-left">Position</th>
                            <th class="py-2 px-4 text-left">Recorded</th>
                        </tr>
                    </thead>
                    <tbody>' . ($rows ?: '<tr><td class="py-4 px-4 text-center text-gray-500" colspan="3">No positions recorded yet.</td></tr>') . '</tbody>
                </table>
            </div>
        </div>';
    }
}
