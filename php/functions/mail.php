<?php
declare(strict_types=1);
function mailConfiguration(bool $requireEnabled = false): array
{
    $config = require __DIR__ . "/../mail.config.php";
    if (
        !is_bool($config["enabled"] ?? null) ||
        ($config["transport"] ?? "") !== "mail"
    ) {
        throw new RuntimeException(
            "Mail configuration must use a boolean enabled setting and the mail transport.",
        );
    }
    foreach (["from_address", "from_name", "reply_to"] as $key) {
        if (
            !is_string($config[$key] ?? null) ||
            preg_match('/[\x00-\x1f\x7f]/', $config[$key])
        ) {
            throw new RuntimeException(
                "Invalid mail configuration: " . $key . ".",
            );
        }
    }
    if ($config["from_name"] === "" || strlen($config["from_name"]) > 100) {
        throw new RuntimeException(
            "Mail sender name must contain 1–100 bytes.",
        );
    }
    foreach (["from_address", "reply_to"] as $key) {
        if (
            $config[$key] !== "" &&
            (strlen($config[$key]) > 254 ||
                !filter_var($config[$key], FILTER_VALIDATE_EMAIL))
        ) {
            throw new RuntimeException("Configure a valid mail " . $key . ".");
        }
    }
    if ($requireEnabled) {
        if (!$config["enabled"]) {
            throw new RuntimeException(
                "Outgoing mail is disabled. Enable it in php/mail.config.local.php on the host.",
            );
        }
        if ($config["from_address"] === "") {
            throw new RuntimeException(
                "Configure a host-approved from_address in php/mail.config.local.php.",
            );
        }
        if (!function_exists("mail")) {
            throw new RuntimeException(
                "PHP mail() is unavailable. Ask the hosting provider to enable it.",
            );
        }
        if (!function_exists("mb_encode_mimeheader")) {
            throw new RuntimeException(
                "Enable PHP mbstring for outgoing mail.",
            );
        }
    }
    return $config;
}

/** Send one transactional plain-text message through the hosting provider's PHP transport. */
function sendSiteMail(string $recipient, string $subject, string $body): void
{
    $config = mailConfiguration(true);
    if (
        strlen($recipient) > 254 ||
        preg_match('/[\x00-\x1f\x7f]/', $recipient) ||
        !filter_var($recipient, FILTER_VALIDATE_EMAIL)
    ) {
        throw new InvalidArgumentException("Invalid email recipient.");
    }
    if (
        trim($subject) === "" ||
        strlen($subject) > 200 ||
        preg_match('/[\x00-\x1f\x7f]/', $subject)
    ) {
        throw new InvalidArgumentException("Invalid email subject.");
    }
    if (strlen($body) > 1000000 || str_contains($body, "\0")) {
        throw new InvalidArgumentException("Invalid or oversized email body.");
    }
    $name = preg_match('/[^\x20-\x7e]/', $config["from_name"])
        ? mb_encode_mimeheader($config["from_name"], "UTF-8", "B", "\r\n", 6)
        : '"' . addcslashes($config["from_name"], '"\\') . '"';
    $headers = [
        "From" => $name . " <" . $config["from_address"] . ">",
        "MIME-Version" => "1.0",
        "Content-Type" => "text/plain; charset=UTF-8",
        "Content-Transfer-Encoding" => "quoted-printable",
    ];
    if ($config["reply_to"] !== "") {
        $headers["Reply-To"] = $config["reply_to"];
    }
    // No user-controlled extra headers or sendmail command arguments are accepted.
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = quoted_printable_encode(str_replace("\n", "\r\n", $body));
    try {
        $accepted = @mail(
            $recipient,
            mb_encode_mimeheader($subject, "UTF-8", "B", "\r\n"),
            $body,
            $headers,
        );
    } catch (Throwable) {
        throw new RuntimeException(
            "The host could not accept outgoing mail. Check PHP mail configuration with the hosting provider.",
        );
    }
    if (!$accepted) {
        throw new RuntimeException(
            "The host could not accept outgoing mail. Check PHP mail configuration with the hosting provider.",
        );
    }
}

function sendResetEmail(string $email, string $token): void
{
    $auth = require __DIR__ . "/../auth.config.php";
    $url = rtrim($auth["app_url"], "/");
    $parts = parse_url($url);
    $local = in_array(
        strtolower($parts["host"] ?? ""),
        ["localhost", "127.0.0.1", "[::1]", "::1"],
        true,
    );
    if (
        !filter_var($url, FILTER_VALIDATE_URL) ||
        !$parts ||
        !in_array($parts["scheme"] ?? "", ["http", "https"], true) ||
        (!$local && $parts["scheme"] !== "https") ||
        isset($parts["user"]) ||
        isset($parts["pass"]) ||
        isset($parts["query"]) ||
        isset($parts["fragment"]) ||
        !in_array($parts["path"] ?? "", ["", "/"], true)
    ) {
        throw new RuntimeException(
            "Configure the trusted website root app_url in php/auth.config.local.php. Use HTTPS on the host.",
        );
    }
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        throw new InvalidArgumentException("Invalid password-reset token.");
    }
    $mail = mailConfiguration(true);
    // Never build this link from the request Host header or log its contents.
    $link = $url . "/site-admin/reset-password?token=" . rawurlencode($token);
    sendSiteMail(
        $email,
        "Reset your " . $mail["from_name"] . " password",
        "Use this link to reset your password within 30 minutes:\n\n" .
            $link .
            "\n\nIf you did not request this, ignore this email.",
    );
}

function sendTwoFactorEmail(string $email, string $code, string $purpose): void
{
    if (
        !preg_match('/^[0-9]{6}$/D', $code) ||
        !in_array($purpose, ["login", "enable", "disable"], true)
    ) {
        throw new InvalidArgumentException("Invalid verification message.");
    }
    $config = mailConfiguration(true);
    $action = match ($purpose) {
        "enable" => "enable email two-factor authentication",
        "disable" => "disable email two-factor authentication",
        default => "complete your sign-in",
    };
    sendSiteMail(
        $email,
        $config["from_name"] . " verification code",
        "Your verification code is: " .
            $code .
            "\n\nUse this code to " .
            $action .
            ". It expires in 10 minutes and can be used once.\n\nIf you did not request this, do not share the code.",
    );
}
