<?php
declare(strict_types=1);
require_once __DIR__ . "/../console/cli.php";
cliOnly();
require_once __DIR__ . "/../maintenance/Mode.php";
require_once __DIR__ . "/../models/User.php";
$locks = [];
if (in_array("--help", $argv, true)) {
    echo "Usage: php php/commands/two-factor-recovery.php <account-email> --confirm=DISABLE-2FA\nPrivate terminal recovery only: disables this account's email 2FA, revokes sessions and clears challenges.\n";
    exit();
}
try {
    if (
        count($argv) !== 3 ||
        $argv[2] !== "--confirm=DISABLE-2FA" ||
        !filter_var($argv[1], FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException(
            "Use --help for the recovery command and explicit confirmation.",
        );
    }
    $locks = MaintenanceMode::commandLock(false);
    $db = require __DIR__ . "/../db.php";
    $model = new User($db);
    $db->beginTransaction();
    try {
        $db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
        $user = $model->byEmail(strtolower($argv[1]));
        if (!$user) {
            throw new RuntimeException("Account not found.");
        }
        $q = $db->prepare(
            "UPDATE users SET two_factor_enabled=0,session_version=session_version+1,updated_at=? WHERE id=?",
        );
        $q->execute([gmdate("Y-m-d H:i:s"), $user["id"]]);
        deleteRecords($db, "two_factor_challenges", ["user_id" => $user["id"]]);
        deleteRecords($db, "password_resets", ["user_id" => $user["id"]]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    require_once __DIR__ . "/../functions/activity.php";
    recordActivity(
        $db,
        $user,
        "profile",
        "two_factor_recovery",
        $user["id"],
        "Email two-factor authentication disabled through private terminal recovery.",
    );
    echo "Email two-factor authentication disabled. Existing sessions were revoked.\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        ($e instanceof PDOException
            ? "Database recovery operation failed."
            : $e->getMessage()) . PHP_EOL,
    );
    exit(1);
} finally {
    MaintenanceMode::release($locks);
}
