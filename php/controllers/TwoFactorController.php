<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../models/TwoFactor.php";
final class TwoFactorController
{
    public static function pending(string $key): ?array
    {
        startAuthSession();
        $pending = $_SESSION[$key] ?? null;
        if (
            !is_array($pending) ||
            !is_string($pending["id"] ?? null) ||
            !preg_match('/^[a-f0-9]{64}$/D', $pending["id"]) ||
            (int) ($pending["expires_at"] ?? 0) <= time()
        ) {
            unset($_SESSION[$key]);
            return null;
        }
        return $pending;
    }
    private static function limited(
        PDO $db,
        string $user,
        string $action,
        int $account,
        int $ip,
    ): bool {
        if (
            !checkRateLimit(
                $db,
                rateLimitClientIp(),
                $action . "-ip",
                $ip,
                900,
            ) ||
            !checkRateLimit($db, $user, $action . "-user", $account, 900)
        ) {
            header("Retry-After: 900");
            jsonResponse(
                [
                    "success" => false,
                    "message" =>
                        "Too many verification attempts. Please try again later.",
                ],
                429,
            );
            return true;
        }
        return false;
    }
    public static function send(
        PDO $db,
        array $user,
        string $purpose,
        ?string $password = null,
    ): ?array {
        if (
            self::limited($db, (string) $user["id"], "two-factor-send", 3, 20)
        ) {
            return null;
        }
        $model = new TwoFactor($db);
        $challenge = $model->issue($user, $purpose, $password);
        try {
            sendTwoFactorEmail(
                $challenge["user"]["email"],
                $challenge["code"],
                $purpose,
            );
        } catch (Throwable $e) {
            $model->remove($challenge["id"]);
            error_log(
                "Two-factor email could not be sent. Check private mail configuration and host delivery.",
            );
            jsonResponse(
                [
                    "success" => false,
                    "message" =>
                        "The verification email could not be sent. Please try again later.",
                    "code" => "mail_unavailable",
                ],
                503,
            );
            return null;
        }
        return [
            "id" => $challenge["id"],
            "purpose" => $purpose,
            "expires_at" => $challenge["expires_at"],
        ];
    }
    public function requestSetting(): void
    {
        $user = requireStaff();
        if (!$user || !verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        if (!is_bool($data["enabled"] ?? null)) {
            throw new ValidationException([
                "enabled" =>
                    "Choose whether to enable two-factor authentication.",
            ]);
        }
        $password = Validator::field(
            "current_password",
            fn() => AuthValidation::password($data["current_password"] ?? null),
        );
        $db = require __DIR__ . "/../db.php";
        $purpose = $data["enabled"] ? "enable" : "disable";
        $challenge = self::send($db, $user, $purpose, $password);
        if (!$challenge) {
            return;
        }
        $_SESSION["two_factor_setting"] = $challenge;
        jsonResponse([
            "success" => true,
            "message" =>
                "A verification code has been sent to your account email.",
            "expires_in" => TwoFactor::LIFETIME,
        ]);
    }
    private static function code(array $data): string
    {
        $code = $data["code"] ?? null;
        if (!is_string($code) || !preg_match('/^[0-9]{6}$/D', $code)) {
            throw new ValidationException([
                "code" => "Enter the six-digit code from your email.",
            ]);
        }
        return $code;
    }
    public function verifySetting(): void
    {
        $actor = requireStaff();
        if (!$actor || !verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        $code = self::code($data);
        $pending = self::pending("two_factor_setting");
        if (!$pending) {
            self::invalid();
            return;
        }
        $db = require __DIR__ . "/../db.php";
        if (
            self::limited(
                $db,
                (string) $actor["id"],
                "two-factor-verify",
                10,
                60,
            )
        ) {
            return;
        }
        $user = (new TwoFactor($db))->consume(
            $pending["id"],
            $pending["purpose"],
            $code,
            (int) $actor["id"],
        );
        if (!$user) {
            self::invalid();
            return;
        }
        establishSession($user);
        try {
            $mail = mailConfiguration(true);
            sendSiteMail(
                $user["email"],
                $mail["from_name"] . " security setting changed",
                "Email two-factor authentication was " .
                    (User::twoFactorEnabled($user) ? "enabled" : "disabled") .
                    " for your account.\n\nIf you did not make this change, contact the site owner.",
            );
        } catch (Throwable) {
            error_log("Two-factor setting notification could not be sent.");
        }
        recordActivity(
            $db,
            $user,
            "profile",
            "two_factor_" .
                (User::twoFactorEnabled($user) ? "enabled" : "disabled"),
            $user["id"],
            "Updated optional email two-factor authentication.",
        );
        jsonResponse([
            "success" => true,
            "user" => User::publicData($user),
            "csrf" => csrfToken(),
            "message" => User::twoFactorEnabled($user)
                ? "Email two-factor authentication enabled."
                : "Email two-factor authentication disabled.",
        ]);
    }
    public function verifyLogin(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        $code = self::code($data);
        $pending = self::pending("two_factor_login");
        if (!$pending) {
            self::invalid();
            return;
        }
        $db = require __DIR__ . "/../db.php";
        $model = new TwoFactor($db);
        $row = $model->find($pending["id"], "login");
        if (!$row) {
            self::invalid();
            return;
        }
        if (
            self::limited(
                $db,
                (string) $row["user_id"],
                "two-factor-verify",
                10,
                60,
            )
        ) {
            return;
        }
        $user = $model->consume($pending["id"], "login", $code);
        if (!$user) {
            self::invalid();
            return;
        }
        establishSession($user);
        recordActivity(
            $db,
            $user,
            "auth",
            "signed_in",
            $user["id"],
            "Signed in with email verification.",
        );
        jsonResponse([
            "success" => true,
            "user" => User::publicData($user),
            "csrf" => csrfToken(),
        ]);
    }
    public function resend(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        jsonRequest();
        $pending = self::pending("two_factor_login");
        if (!$pending) {
            self::invalid();
            return;
        }
        $db = require __DIR__ . "/../db.php";
        $model = new TwoFactor($db);
        $row = $model->find($pending["id"], "login");
        if (
            !$row ||
            (int) $row["expires_at"] <= time() ||
            (int) $row["attempts"] >= TwoFactor::ATTEMPTS
        ) {
            self::invalid();
            return;
        }
        $user = (new User($db))->byId((int) $row["user_id"]);
        if (
            !$user ||
            (int) $row["session_version"] !== (int) $user["session_version"]
        ) {
            self::invalid();
            return;
        }
        $new = self::send($db, $user, "login");
        if (!$new) {
            return;
        }
        // Resending never extends the original password-verified session lifetime.
        $new["expires_at"] = min($new["expires_at"], $pending["expires_at"]);
        $_SESSION["two_factor_login"] = $new;
        jsonResponse([
            "success" => true,
            "message" => "A new verification code has been sent.",
        ]);
    }
    public function cancel(): void
    {
        if (!verifyCsrf()) {
            return;
        }
        jsonRequest();
        $pending = self::pending("two_factor_login");
        if ($pending) {
            $db = require __DIR__ . "/../db.php";
            (new TwoFactor($db))->remove($pending["id"]);
        }
        unset($_SESSION["two_factor_login"]);
        jsonResponse(["success" => true, "csrf" => csrfToken()]);
    }
    private static function invalid(): void
    {
        jsonResponse(
            [
                "success" => false,
                "message" =>
                    "The code is invalid or expired. Request a new code or start again.",
                "errors" => ["code" => "The code is invalid or expired."],
            ],
            422,
        );
    }
}
