<?php

class Keyword
{
    public function displayPage(): string
    {
        $op = $_GET['op'] ?? 'keywords';

        switch ($op) {
            case 'add_keyword':
                return $this->add();
            case 'edit_keyword':
                return $this->edit();
            case 'delete_keyword':
                return $this->delete();
            default:
                return $this->list();
        }
    }

    private function list(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];

        $keywords = $db->fetchAll(
            'SELECT * FROM `keywords` WHERE `user_id` = ? ORDER BY `created_at` DESC',
            [$userId]
        );

        $rows = '';
        foreach ($keywords as $keyword) {
            $rows .= '
                <tr class="border-b">
                    <td class="py-2 px-4">' . sanitize($keyword['name']) . '</td>
                    <td class="py-2 px-4 text-sm text-gray-500">' . sanitize(formatDate($keyword['created_at'])) . '</td>
                    <td class="py-2 px-4">
                        <a href="' . sanitize(APP_URL . '/index.php?op=edit_keyword&id=' . $keyword['k_id']) . '"
                            class="text-blue-500 hover:underline mr-2">Edit</a>
                        <a href="' . sanitize(APP_URL . '/index.php?op=delete_keyword&id=' . $keyword['k_id']) . '"
                            class="text-red-500 hover:underline">Delete</a>
                    </td>
                </tr>';
        }

        return '
        <div class="max-w-4xl mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Keywords</h1>
                <a href="' . sanitize(APP_URL . '/index.php?op=add_keyword') . '"
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">+ Add Keyword</a>
            </div>
            <table class="w-full bg-white rounded-lg shadow">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-4 text-left">Keyword</th>
                        <th class="py-2 px-4 text-left">Created</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </div>';
    }

    private function add(): string
    {
        global $db;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
                header('Location: ' . APP_URL . '/index.php?op=add_keyword');
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                $db->insert('keywords', [
                    'user_id' => $_SESSION['user_id'],
                    'name' => $name,
                ]);
                header('Location: ' . APP_URL . '/index.php?op=keywords');
                exit;
            }
        }

        return '
        <div class="max-w-lg mx-auto p-6">
            <h1 class="text-2xl font-bold mb-4">Add Keyword</h1>
            <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=add_keyword') . '">
                <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1" for="name">Keyword</label>
                    <input type="text" id="name" name="name" required
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save</button>
                <a href="' . sanitize(APP_URL . '/index.php?op=keywords') . '"
                    class="ml-2 text-gray-500 hover:underline">Cancel</a>
            </form>
        </div>';
    }

    private function edit(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $kid = (int) ($_GET['id'] ?? 0);

        $keyword = $db->fetchOne(
            'SELECT * FROM `keywords` WHERE `k_id` = ? AND `user_id` = ?',
            [$kid, $userId]
        );

        if (!$keyword) {
            header('Location: ' . APP_URL . '/index.php?op=keywords');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
                header('Location: ' . APP_URL . '/index.php?op=edit_keyword&id=' . $kid);
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            if ($name !== '') {
                $db->update('keywords', ['name' => $name], '`k_id` = ? AND `user_id` = ?', [$kid, $userId]);
                header('Location: ' . APP_URL . '/index.php?op=keywords');
                exit;
            }
        }

        return '
        <div class="max-w-lg mx-auto p-6">
            <h1 class="text-2xl font-bold mb-4">Edit Keyword</h1>
            <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=edit_keyword&id=' . $kid) . '">
                <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1" for="name">Keyword</label>
                    <input type="text" id="name" name="name" required value="' . sanitize($keyword['name']) . '"
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Update</button>
                <a href="' . sanitize(APP_URL . '/index.php?op=keywords') . '"
                    class="ml-2 text-gray-500 hover:underline">Cancel</a>
            </form>
        </div>';
    }

    private function delete(): string
    {
        global $db;
        $userId = $_SESSION['user_id'];
        $kid = (int) ($_GET['id'] ?? 0);

        $db->delete('keywords', '`k_id` = ? AND `user_id` = ?', [$kid, $userId]);
        header('Location: ' . APP_URL . '/index.php?op=keywords');
        exit;
    }
}
