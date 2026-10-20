<?php
declare(strict_types=1);
final class ValidationException extends InvalidArgumentException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(
            (string) (array_values($errors)[0] ??
                "Please check the highlighted fields."),
        );
    }
}
