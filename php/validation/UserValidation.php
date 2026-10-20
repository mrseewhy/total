<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthValidation.php";
final class UserValidation
{
    public static function details(array $data, bool $creating): array
    {
        $name = Validator::field("name", function () use ($data) {
            $name = Validator::requiredString(
                $data["name"] ?? null,
                "Name is required.",
            );
            Validator::maxLength($name, 100, "Name is too long.");
            return $name;
        });
        $email = AuthValidation::email($data);
        $role = $data["role"] ?? null;
        if (!in_array($role, ["Admin", "Manager"], true)) {
            throw new ValidationException([
                "role" => "Choose Admin or Manager.",
            ]);
        }
        $password = $data["password"] ?? "";
        if ($creating || $password !== "") {
            $password = AuthValidation::newPassword($data);
        }
        if (
            !$creating &&
            $password === "" &&
            ($data["password_confirmation"] ?? "") !== ""
        ) {
            throw new ValidationException([
                "password_confirmation" => "Passwords do not match.",
            ]);
        }
        if (!is_string($password)) {
            throw new ValidationException(["password" => "Invalid password."]);
        }
        return [
            "name" => $name,
            "email" => $email,
            "role" => $role,
            "password" => $password,
        ];
    }
}
