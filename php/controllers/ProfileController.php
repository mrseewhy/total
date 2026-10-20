<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
final class ProfileController
{
    public function update(): void
    {
        $user = requireStaff();
        if (!$user || !verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        if (isset($data["email"]) && $data["email"] !== $user["email"]) {
            throw new InvalidArgumentException(
                "Email addresses cannot be changed.",
            );
        }
        $name = Validator::field("name", function () use ($data) {
            $name = Validator::requiredString(
                $data["name"] ?? null,
                "Name is required.",
            );
            Validator::maxLength($name, 100, "Name is too long.");
            return $name;
        });
        $db = require __DIR__ . "/../db.php";
        if (
            !checkRateLimit($db, (string) $user["id"], "profile-write", 60, 600)
        ) {
            header("Retry-After: 600");
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Too many changes. Try again later.",
                ],
                429,
            );
            return;
        }
        $model = new User($db);
        $model->profile((int) $user["id"], $name);
        recordActivity(
            $db,
            $user,
            "profile",
            "updated",
            $user["id"],
            "Updated profile name.",
        );
        jsonResponse([
            "success" => true,
            "user" => User::publicData($model->byId((int) $user["id"])),
            "message" => "Profile updated.",
        ]);
    }
    public function password(): void
    {
        $user = requireStaff();
        if (!$user || !verifyCsrf()) {
            return;
        }
        $data = jsonRequest();
        $password = AuthValidation::newPassword($data);
        $current = Validator::field(
            "current_password",
            fn() => AuthValidation::password($data["current_password"] ?? null),
        );
        $db = require __DIR__ . "/../db.php";
        if (
            !checkRateLimit(
                $db,
                (string) $user["id"],
                "change-password",
                5,
                900,
            )
        ) {
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
        $model = new User($db);
        if (
            !$model->changeOwnPassword(
                (int) $user["id"],
                $current,
                $password,
                (int) $user["session_version"],
            )
        ) {
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Current password is incorrect.",
                    "errors" => [
                        "current_password" => "Current password is incorrect.",
                    ],
                ],
                422,
            );
            return;
        }
        recordActivity(
            $db,
            $user,
            "profile",
            "password_changed",
            $user["id"],
            "Changed own password.",
        );
        establishSession($model->byId((int) $user["id"]));
        jsonResponse([
            "success" => true,
            "csrf" => csrfToken(),
            "message" =>
                "Password changed. Other sessions have been signed out.",
        ]);
    }
}
