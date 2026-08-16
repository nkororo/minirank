<?php

class Auth
{
    public function displayPage(): string
    {
        global $db;

        $op = $_GET['op'] ?? 'login';

        if ($op === 'logout') {
            return $this->logout();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $op === 'register' ? $this->register() : $this->login();
        }

        return $op === 'register' ? $this->registerForm() : $this->loginForm();
    }

    private function loginForm(): string
    {
        $error = $_SESSION['auth_error'] ?? '';
        unset($_SESSION['auth_error']);

        return '
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <h1 class="text-2xl font-bold mb-6 text-center">' . sanitize(APP_NAME) . '</h1>
                <h2 class="text-xl mb-4 text-center">Login</h2>
                ' . ($error ? '<p class="text-red-500 text-sm mb-4">' . sanitize($error) . '</p>' : '') . '
                <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=login') . '">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1" for="email">Email</label>
                        <input type="email" id="email" name="email" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-1" for="password">Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">Login</button>
                </form>
                <p class="text-center mt-4">
                    <a href="' . sanitize(APP_URL . '/index.php?op=register') . '" class="text-blue-500 hover:underline">Create an account</a>
                </p>
            </div>
        </div>';
    }

    private function registerForm(): string
    {
        $error = $_SESSION['auth_error'] ?? '';
        unset($_SESSION['auth_error']);

        return '
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <h1 class="text-2xl font-bold mb-6 text-center">' . sanitize(APP_NAME) . '</h1>
                <h2 class="text-xl mb-4 text-center">Register</h2>
                ' . ($error ? '<p class="text-red-500 text-sm mb-4">' . sanitize($error) . '</p>' : '') . '
                <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=register') . '">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1" for="email">Email</label>
                        <input type="email" id="email" name="email" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1" for="password">Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-1" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">Register</button>
                </form>
                <p class="text-center mt-4">
                    <a href="' . sanitize(APP_URL . '/index.php?op=login') . '" class="text-blue-500 hover:underline">Already have an account? Login</a>
                </p>
            </div>
        </div>';
    }

    private function login(): string
    {
        global $db;

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['auth_error'] = 'Invalid form submission.';
            redirect(APP_URL . '/index.php?op=login');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $_SESSION['auth_error'] = 'Email and password are required.';
            redirect(APP_URL . '/index.php?op=login');
        }

        $user = $db->fetchOne('SELECT * FROM `users` WHERE `email` = ?', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['auth_error'] = 'Invalid email or password.';
            redirect(APP_URL . '/index.php?op=login');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['u_id'];
        unset($_SESSION['csrf_token']);

        redirect(APP_URL . '/index.php?op=dashboard');
    }

    private function register(): string
    {
        global $db;

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['auth_error'] = 'Invalid form submission.';
            redirect(APP_URL . '/index.php?op=register');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($email === '' || $password === '') {
            $_SESSION['auth_error'] = 'Email and password are required.';
            redirect(APP_URL . '/index.php?op=register');
        }

        if ($password !== $confirmPassword) {
            $_SESSION['auth_error'] = 'Passwords do not match.';
            redirect(APP_URL . '/index.php?op=register');
        }

        if (strlen($password) < 8) {
            $_SESSION['auth_error'] = 'Password must be at least 8 characters.';
            redirect(APP_URL . '/index.php?op=register');
        }

        $existing = $db->fetchOne('SELECT `u_id` FROM `users` WHERE `email` = ?', [$email]);
        if ($existing) {
            $_SESSION['auth_error'] = 'An account with this email already exists.';
            redirect(APP_URL . '/index.php?op=register');
        }

        $db->insert('users', [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $_SESSION['auth_error'] = 'Account created. Please login.';
        redirect(APP_URL . '/index.php?op=login');
    }

    private function logout(): string
    {
        session_unset();
        session_destroy();
        redirect(APP_URL . '/index.php?op=login');
    }
}
