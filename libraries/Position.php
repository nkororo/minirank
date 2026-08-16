<?php

class Position
{
    public function displayPage(): string
    {
        $op = $_GET['op'] ?? '';

        if ($op === 'delete_position') {
            return $this->delete();
        }

        return $this->list();
    }

    private function list(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $kid = (int) ($_GET['id'] ?? 0);

        $keyword = $db->fetchOne(
            'SELECT * FROM `keywords` WHERE `k_id` = ? AND `user_id` = ?',
            [$kid, $userId]
        );

        if (!$keyword) {
            redirect(APP_URL . '/index.php?op=keywords');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                redirect(APP_URL . '/index.php?op=positions&id=' . $kid);
            }

            $position = (int) ($_POST['position'] ?? 0);
            if ($position >= POSITION_MIN && $position <= POSITION_MAX) {
                $db->insert('positions', [
                    'keyword_id' => $kid,
                    'position' => $position,
                ]);
                redirect(APP_URL . '/index.php?op=positions&id=' . $kid);
            }
        }

        $positions = $db->fetchAll(
            'SELECT * FROM `positions` WHERE `keyword_id` = ? ORDER BY `created_at` DESC',
            [$kid]
        );

        $rows = '';
        foreach ($positions as $pos) {
            $rows .= '
                <tr class="border-b">
                    <td class="py-2 px-4">' . $pos['position'] . '</td>
                    <td class="py-2 px-4 text-sm text-gray-500">' . sanitize($pos['created_at']) . '</td>
                    <td class="py-2 px-4">
                        <a href="' . sanitize(APP_URL . '/index.php?op=delete_position&id=' . $pos['p_id'] . '&kwid=' . $kid) . '"
                            class="text-red-500 hover:underline">Delete</a>
                    </td>
                </tr>';
        }

        return '
        <div class="max-w-4xl mx-auto p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">' . sanitize($keyword['name']) . '</h1>
                <a href="' . sanitize(APP_URL . '/index.php?op=keywords') . '" class="text-blue-500 hover:underline">&larr; Back to Keywords</a>
            </div>

            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=positions&id=' . $kid) . '" class="flex gap-2 items-end">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    <div>
                        <label class="block text-sm font-medium mb-1" for="position">Add Position (' . POSITION_MIN . '-' . POSITION_MAX . ')</label>
                        <select id="position" name="position"
                            class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            ' . $this->getPositionOptions() . '
                        </select>
                    </div>
                    <button type="submit"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add</button>
                </form>
            </div>

            <table class="w-full bg-white rounded-lg shadow">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-4 text-left">Position</th>
                        <th class="py-2 px-4 text-left">Recorded</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </div>';
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

        redirect(APP_URL . '/index.php?op=positions&id=' . $kid);
    }

    private function getPositionOptions(): string
    {
        $options = '';
        for ($i = POSITION_MIN; $i <= POSITION_MAX; $i++) {
            $options .= '<option value="' . $i . '">' . $i . '</option>';
        }
        return $options;
    }
}
