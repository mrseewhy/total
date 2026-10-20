<?php
declare(strict_types=1);
function csvCell(string $value): string
{
    return preg_match('/^(?:\s*[=+@\-＝＋＠－]|[\t\r\n])/u', $value)
        ? "'" . $value
        : $value;
}
