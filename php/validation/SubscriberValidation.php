<?php
declare(strict_types=1);
require_once __DIR__ . "/Validator.php";

final class SubscriberValidation
{
    public static function create(array $data): array
    {
        Validator::emptyHoneypot(
            array_key_exists("website", $data) ? $data["website"] : "",
        );
        $email = Validator::field("email", function () use ($data) {
            $email = Validator::requiredString(
                $data["email"] ?? null,
                "A valid email address is required",
            );
            Validator::maxLength($email, 254, "Invalid email address");
            Validator::email($email);
            return $email;
        });
        return ["email" => $email];
    }
}
