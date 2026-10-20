<?php
declare(strict_types=1);
require_once __DIR__ . "/User.php";
require_once __DIR__ . "/../functions/activity.php";
final class PasswordReset
{
    public function __construct(private PDO $db) {}
    public function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            $user = (new User($this->db))->byId($userId);
            if (!$user || !User::active($user)) {
                throw new RuntimeException("Account unavailable.");
            }
            $stmt = $this->db->prepare(
                "DELETE FROM password_resets WHERE user_id=?",
            );
            $stmt->execute([$userId]);
            createRecord($this->db, "password_resets", [
                "user_id" => $userId,
                "token_hash" => hash("sha256", $token),
                "expires_at" => time() + 1800,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $token;
    }
    public function consume(string $token, string $password): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            $hash = hash("sha256", $token);
            $stmt = $this->db->prepare(
                "SELECT user_id FROM password_resets WHERE token_hash=? AND expires_at>?",
            );
            $stmt->execute([$hash, time()]);
            $id = $stmt->fetchColumn();
            if (!$id) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare(
                "DELETE FROM password_resets WHERE token_hash=? AND expires_at>?",
            );
            $stmt->execute([$hash, time()]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            $user = (new User($this->db))->byId((int) $id);
            if (!$user || !User::active($user)) {
                $this->db->rollBack();
                return false;
            }
            (new User($this->db))->changePassword((int) $id, $password);
            $this->db->commit();
            recordActivity(
                $this->db,
                $user,
                "auth",
                "password_reset",
                $user["id"],
                "Completed password reset.",
            );
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
