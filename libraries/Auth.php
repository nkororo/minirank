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
        <div class="auth-container">
            <div class="auth-card">
                <h1 class="auth-title">' . sanitize(APP_NAME) . '</h1>
                <h2 class="auth-subtitle">Login</h2>
                ' . ($error ? '<div class="alert alert-error">' . sanitize($error) . '</div>' : '') . '
                <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=login') . '">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" required
                            class="form-input">
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" required
                            class="form-input">
                    </div>
                    <button type="submit"
                        class="btn btn-primary btn-block">Login</button>
                </form>
                <p class="auth-link">
                    <a href="' . sanitize(APP_URL . '/index.php?op=register') . '" class="link">Create an account</a>
                </p>
            </div>
        </div>';
    }

    private function registerForm(): string
    {
        $error = $_SESSION['auth_error'] ?? '';
        unset($_SESSION['auth_error']);

        return '
        <div class="auth-container">
            <div class="auth-card">
                <h1 class="auth-title">' . sanitize(APP_NAME) . '</h1>
                <h2 class="auth-subtitle">Register</h2>
                ' . ($error ? '<div class="alert alert-error">' . sanitize($error) . '</div>' : '') . '
                <form method="POST" action="' . sanitize(APP_URL . '/index.php?op=register') . '">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" required
                            class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" required
                            class="form-input">
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            class="form-input">
                    </div>
                    <button type="submit"
                        class="btn btn-primary btn-block">Register</button>
                </form>
                <p class="auth-link">
                    <a href="' . sanitize(APP_URL . '/index.php?op=login') . '" class="link">Already have an account? Login</a>
                </p>
            </div>
        </div>';
    }

    private function login(): string
    {
        global $db;

        if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
            $_SESSION['auth_error'] = 'Invalid form submission.';
            header('Location: ' . APP_URL . '/index.php?op=login');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $_SESSION['auth_error'] = 'Email and password are required.';
            header('Location: ' . APP_URL . '/index.php?op=login');
            exit;
        }

        $user = $db->fetchOne('SELECT * FROM `users` WHERE `email` = ?', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['auth_error'] = 'Invalid email or password.';
            header('Location: ' . APP_URL . '/index.php?op=login');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['u_id'];
        unset($_SESSION['csrf_token']);

        header('Location: ' . APP_URL . '/index.php?op=dashboard');
        exit;
    }

    private function register(): string
    {
        global $db;

        if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
            $_SESSION['auth_error'] = 'Invalid form submission.';
            header('Location: ' . APP_URL . '/index.php?op=register');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($email === '' || $password === '') {
            $_SESSION['auth_error'] = 'Email and password are required.';
            header('Location: ' . APP_URL . '/index.php?op=register');
            exit;
        }

        if ($password !== $confirmPassword) {
            $_SESSION['auth_error'] = 'Passwords do not match.';
            header('Location: ' . APP_URL . '/index.php?op=register');
            exit;
        }

        if (strlen($password) < 8) {
            $_SESSION['auth_error'] = 'Password must be at least 8 characters.';
            header('Location: ' . APP_URL . '/index.php?op=register');
            exit;
        }

        $existing = $db->fetchOne('SELECT `u_id` FROM `users` WHERE `email` = ?', [$email]);
        if ($existing) {
            $_SESSION['auth_error'] = 'An account with this email already exists.';
            header('Location: ' . APP_URL . '/index.php?op=register');
            exit;
        }

        $db->insert('users', [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $_SESSION['auth_error'] = 'Account created. Please login.';
        header('Location: ' . APP_URL . '/index.php?op=login');
        exit;
    }

    private function logout(): string
    {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?op=login');
        exit;
    }
}
