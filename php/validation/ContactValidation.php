<?php
declare(strict_types=1);
require_once __DIR__ . "/Validator.php";
final class ContactValidation
{
    public static function create(array $data): array
    {
        Validator::emptyHoneypot(
            array_key_exists("website", $data) ? $data["website"] : "",
        );
        $validators = [];
        foreach (
            ["name" => 100, "email" => 254, "interest" => 50, "message" => 5000]
            as $key => $max
        ) {
            $validators[$key] = function () use ($data, $key, $max) {
                $value = Validator::requiredString(
                    $data[$key] ?? null,
                    ucfirst($key) . " is required",
                );
                Validator::maxLength(
                    $value,
                    $max,
                    ucfirst($key) . " must be at most {$max} bytes",
                );
                if ($key === "email") {
                    Validator::email($value);
                }
                if ($key === "message" && strlen($value) < 10) {
                    throw new InvalidArgumentException(
                        "Message must contain at least 10 characters",
                    );
                }
                if (
                    $key === "interest" &&
                    !in_array(
                        $value,
                        [
                            "Buying a home",
                            "Renting a home",
                            "Selling a property",
                            "General inquiry",
                        ],
                        true,
                    )
                ) {
                    throw new InvalidArgumentException("Invalid interest");
                }
                return $value;
            };
        }
        $validators["phone"] = function () use ($data) {
            if (isset($data["phone"]) && !is_string($data["phone"])) {
                throw new InvalidArgumentException("Invalid phone number");
            }
            $phone = trim($data["phone"] ?? "");
            Validator::maxLength($phone, 30, "Phone number is too long");
            return $phone;
        };
        $result = Validator::fields($validators);
        return $result;
    }
    public static function readStatus(array $data): bool
    {
        if (!is_bool($data["is_read"] ?? null)) {
            throw new InvalidArgumentException("is_read must be a boolean");
        }
        return $data["is_read"];
    }
}
