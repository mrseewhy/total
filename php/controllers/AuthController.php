<?php
declare(strict_types=1);
require_once __DIR__ . "/../middleware/auth.php";
require_once __DIR__ . "/../middleware/csrf.php";
require_once __DIR__ . "/../middleware/rateLimit.php";
require_once __DIR__ . "/../validation/AuthValidation.php";
require_once __DIR__ . "/../http/request.php";
require_once __DIR__ . "/../models/PasswordReset.php";
require_once __DIR__ . "/../functions/mail.php";
require_once __DIR__ . "/../functions/activity.php";
final class AuthController
{
    public function me(): void
    {
        $user = currentUser();
        jsonResponse([
            "success" => true,
            "user" => $user ? User::publicData($user) : null,
            "csrf" => csrfToken(),
            "two_factor_pending" =>
                TwoFactorController::pending("two_factor_login") !== null,
        ]);
    }
    private function limited(
        PDO $db,
        string $email,
        string $action,
        int $emailLimit,
        int $ipLimit,
        int $window,
    ): bool {
        $ip = checkRateLimit(
            $db,
            rateLimitClientIp(),
            $action . "-ip",
            $ipLimit,
            $window,
        );
        $account =
            $ip &&
            checkRateLimit(
                $db,
                $email,
                $action . "-email",
                $emailLimit,
                $window,
            );
        if (!$ip || !$account) {
            header("Retry-After: " . $window);
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Too many attempts. Please try again later.",
                ],
                429,
            );
            return true;
        }
        return false;
    }
    public function login(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        $email = AuthValidation::email($data);
        $password = AuthValidation::password($data["password"] ?? null);
        $db = require __DIR__ . "/../db.php";
        if ($this->limited($db, $email, "login", 5, 30, 900)) {
            return;
        }
        $model = new User($db);
        $user = $model->byEmail($email);
        // A fixed dummy hash keeps unknown accounts on the password verification path.
        $valid = password_verify(
            $password,
            $user["password_hash"] ??
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi',
        );
        if (
            !$valid ||
            !$user ||
            !User::active($user) ||
            !in_array($user["role"], ["Admin", "Manager"], true)
        ) {
            jsonResponse(
                ["success" => false, "message" => "Invalid email or password."],
                401,
            );
            return;
        }
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier=?");
        $stmt->execute([hash("sha256", "login-email:" . $email)]);
        if (password_needs_rehash($user["password_hash"], PASSWORD_DEFAULT)) {
            // Never overwrite a password reset that completed after verification.
            $update = $db->prepare(
                "UPDATE users SET password_hash=? WHERE id=? AND password_hash=? AND session_version=?",
            );
            $update->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $user["id"],
                $user["password_hash"],
                $user["session_version"],
            ]);
            if ($update->rowCount() !== 1) {
                jsonResponse(
                    [
                        "success" => false,
                        "message" => "Invalid email or password.",
                    ],
                    401,
                );
                return;
            }
        }
        $fresh = $model->byId((int) $user["id"]);
        if (
            !$fresh ||
            !User::active($fresh) ||
            (int) $fresh["session_version"] !== (int) $user["session_version"]
        ) {
            jsonResponse(
                ["success" => false, "message" => "Invalid email or password."],
                401,
            );
            return;
        }
        $user = $fresh;
        if (User::twoFactorEnabled($user)) {
            $pending = TwoFactorController::send($db, $user, "login");
            if (!$pending) {
                return;
            }
            startAuthSession();
            session_regenerate_id(true);
            $_SESSION = [
                "csrf" => bin2hex(random_bytes(32)),
                "two_factor_login" => $pending,
            ];
            jsonResponse([
                "success" => true,
                "requires_two_factor" => true,
                "csrf" => csrfToken(),
                "message" =>
                    "A verification code has been sent to your account email.",
            ]);
            return;
        }
        establishSession($user);
        recordActivity(
            $db,
            $user,
            "auth",
            "signed_in",
            $user["id"],
            "Signed in.",
        );
        jsonResponse([
            "success" => true,
            "user" => User::publicData($user),
            "csrf" => csrfToken(),
        ]);
    }
    public function logout(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        $actor = currentUser();
        if ($actor) {
            $db = require __DIR__ . "/../db.php";
            recordActivity(
                $db,
                $actor,
                "auth",
                "signed_out",
                $actor["id"],
                "Signed out.",
            );
        }
        endAuthSession();
        jsonResponse(["success" => true]);
    }
    public function forgot(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        $email = AuthValidation::email(jsonRequest());
        $db = require __DIR__ . "/../db.php";
        if ($this->limited($db, $email, "forgot", 3, 20, 3600)) {
            return;
        }
        $user = (new User($db))->byEmail($email);
        if ($user && User::active($user)) {
            try {
                $token = (new PasswordReset($db))->issue((int) $user["id"]);
                sendResetEmail($email, $token);
            } catch (Throwable $e) {
                error_log($e->getMessage());
            }
        }
        jsonResponse([
            "success" => true,
            "message" =>
                "If an account exists, a password reset link will be emailed to you.",
        ]);
    }
    public function reset(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        $password = AuthValidation::newPassword($data);
        $token = $data["token"] ?? "";
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new InvalidArgumentException(
                "Invalid or expired reset link.",
            );
        }
        $db = require __DIR__ . "/../db.php";
        if (!checkRateLimit($db, rateLimitClientIp(), "reset-ip", 10, 900)) {
            header("Retry-After: 900");
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Too many attempts. Please try again later.",
                ],
                429,
            );
            return;
        }
        if (!(new PasswordReset($db))->consume($token, $password)) {
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Invalid or expired reset link.",
                ],
                422,
            );
            return;
        }
        endAuthSession();
        jsonResponse([
            "success" => true,
            "message" => "Your password has been reset. Please sign in.",
        ]);
    }
}
