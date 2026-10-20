<?php
declare(strict_types=1);

require_once __DIR__ . "/ValidationException.php";

final class Validator
{
    public static function field(string $name, callable $validate): mixed
    {
        try {
            return $validate();
        } catch (InvalidArgumentException $e) {
            throw new ValidationException([$name => $e->getMessage()]);
        }
    }
    public static function fields(array $validators): array
    {
        $values = [];
        $errors = [];
        foreach ($validators as $name => $validate) {
            try {
                $values[$name] = $validate();
            } catch (InvalidArgumentException $e) {
                $errors[$name] = $e->getMessage();
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $values;
    }
    public static function requiredString(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === "") {
            throw new InvalidArgumentException($message);
        }
        return trim($value);
    }

    public static function maxLength(
        string $value,
        int $length,
        string $message,
    ): void {
        if (strlen($value) > $length) {
            throw new InvalidArgumentException($message);
        }
    }

    public static function email(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address");
        }
    }

    public static function emptyHoneypot(mixed $value): void
    {
        if (!is_string($value) || $value !== "") {
            throw new InvalidArgumentException(
                "Could not process subscription",
            );
        }
    }
}
