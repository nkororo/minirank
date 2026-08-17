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
        $projectId = $_SESSION['project_id'] ?? 0;

        $keywords = $db->fetchAll(
            'SELECT * FROM `keywords` WHERE `project_id` = ? ORDER BY `created_at` DESC',
            [$projectId]
        );

        $rows = '';
        foreach ($keywords as $keyword) {
            $rows .= '
                <tr>
                    <td>' . sanitize($keyword['name']) . '</td>
                    <td class="text-sm text-muted">' . sanitize(formatDate($keyword['created_at'])) . '</td>
                    <td>
                        <a href="' . sanitize(APP_URL . '/index.php?op=edit_keyword&id=' . $keyword['k_id']) . '"
                            class="link mr-2">Edit</a>
                        <a href="' . sanitize(APP_URL . '/index.php?op=delete_keyword&id=' . $keyword['k_id']) . '"
                            class="link link--danger">Delete</a>
                    </td>
                </tr>';
        }

        return '
        <div class="container-md">
            <div class="page-header">
                <h1 class="page-title">Keywords</h1>
                <a href="' . sanitize(APP_URL . '/index.php?op=add_keyword') . '"
                    class="btn btn-success">+ Add Keyword</a>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Keyword</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
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
                    'project_id' => $_SESSION['project_id'] ?? 0,
                    'name' => $name,
                ]);
                header('Location: ' . APP_URL . '/index.php?op=keywords');
                exit;
            }
        }

        return '
        <div class="container-sm">
            <h1 class="page-title mb-4">Add Keyword</h1>
            <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=add_keyword') . '">
                <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                <div class="form-group">
                    <label class="form-label" for="name">Keyword</label>
                    <input type="text" id="name" name="name" required
                        class="form-input">
                </div>
                <button type="submit"
                    class="btn btn-primary">Save</button>
                <a href="' . sanitize(APP_URL . '/index.php?op=keywords') . '"
                    class="btn btn-ghost ml-2">Cancel</a>
            </form>
        </div>';
    }

    private function edit(): string
    {
        global $db;
        $projectId = $_SESSION['project_id'] ?? 0;
        $kid = (int) ($_GET['id'] ?? 0);

        $keyword = $db->fetchOne(
            'SELECT * FROM `keywords` WHERE `k_id` = ? AND `project_id` = ?',
            [$kid, $projectId]
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
                $db->update('keywords', ['name' => $name], '`k_id` = ? AND `project_id` = ?', [$kid, $projectId]);
                header('Location: ' . APP_URL . '/index.php?op=keywords');
                exit;
            }
        }

        return '
        <div class="container-sm">
            <h1 class="page-title mb-4">Edit Keyword</h1>
            <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=edit_keyword&id=' . $kid) . '">
                <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                <div class="form-group">
                    <label class="form-label" for="name">Keyword</label>
                    <input type="text" id="name" name="name" required value="' . sanitize($keyword['name']) . '"
                        class="form-input">
                </div>
                <button type="submit"
                    class="btn btn-primary">Update</button>
                <a href="' . sanitize(APP_URL . '/index.php?op=keywords') . '"
                    class="btn btn-ghost ml-2">Cancel</a>
            </form>
        </div>';
    }

    private function delete(): string
    {
        global $db;
        $projectId = $_SESSION['project_id'] ?? 0;
        $kid = (int) ($_GET['id'] ?? 0);

        $db->delete('keywords', '`k_id` = ? AND `project_id` = ?', [$kid, $projectId]);
        header('Location: ' . APP_URL . '/index.php?op=keywords');
        exit;
    }
}
