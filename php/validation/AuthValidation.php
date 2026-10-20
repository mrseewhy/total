<?php
declare(strict_types=1);
require_once __DIR__ . "/Validator.php";
final class AuthValidation
{
    public static function email(array $data): string
    {
        return Validator::field("email", function () use ($data) {
            $email = strtolower(
                Validator::requiredString(
                    $data["email"] ?? null,
                    "Email is required.",
                ),
            );
            Validator::maxLength($email, 254, "Email is too long.");
            Validator::email($email);
            return $email;
        });
    }

    public static function password(mixed $value, bool $new = false): string
    {
        if (
            !is_string($value) ||
            $value === "" ||
            strlen($value) > 72 ||
            str_contains($value, "\0") ||
            ($new && strlen($value) < 12)
        ) {
            throw new InvalidArgumentException(
                $new
                    ? "Use a password between 12 and 72 bytes."
                    : "Invalid email or password.",
            );
        }
        return $value;
    }
    public static function newPassword(array $data): string
    {
        $password = Validator::field(
            "password",
            fn() => self::password($data["password"] ?? null, true),
        );
        if (($data["password_confirmation"] ?? null) !== $password) {
            throw new ValidationException([
                "password_confirmation" => "Passwords do not match.",
            ]);
        }
        return $password;
    }
}
