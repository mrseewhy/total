<?php
declare(strict_types=1);
require_once __DIR__ . "/User.php";
final class TwoFactor
{
    public const LIFETIME = 600;
    public const ATTEMPTS = 5;
    public function __construct(private PDO $db) {}
    public function find(string $id, string $purpose): ?array
    {
        $q = $this->db->prepare(
            "SELECT * FROM two_factor_challenges WHERE id=? AND purpose=?",
        );
        $q->execute([$id, $purpose]);
        return $q->fetch() ?: null;
    }
    public function remove(string $id): void
    {
        deleteRecords($this->db, "two_factor_challenges", ["id" => $id]);
    }
    public function issue(
        array $snapshot,
        string $purpose,
        ?string $password = null,
    ): array {
        if (!in_array($purpose, ["login", "enable", "disable"], true)) {
            throw new InvalidArgumentException("Invalid verification purpose.");
        }
        if ($purpose !== "login" && $password === null) {
            throw new InvalidArgumentException(
                "Current password is required to change two-factor authentication.",
            );
        }
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            $user = (new User($this->db))->byId((int) $snapshot["id"]);
            if (
                !$user ||
                !User::active($user) ||
                !in_array($user["role"], ["Admin", "Manager"], true) ||
                (int) $user["session_version"] !==
                    (int) $snapshot["session_version"]
            ) {
                throw new InvalidArgumentException(
                    "Your session has changed. Please sign in again.",
                );
            }
            if (
                $password !== null &&
                !password_verify($password, $user["password_hash"])
            ) {
                throw new ValidationException([
                    "current_password" => "Current password is incorrect.",
                ]);
            }
            $enabled = User::twoFactorEnabled($user);
            if (
                (($purpose === "login" || $purpose === "disable") &&
                    !$enabled) ||
                ($purpose === "enable" && $enabled)
            ) {
                throw new InvalidArgumentException(
                    "The two-factor setting has changed. Reload and try again.",
                );
            }
            $previous = $this->db->prepare(
                "SELECT code_hash FROM two_factor_challenges WHERE user_id=? AND purpose=?",
            );
            $previous->execute([$user["id"], $purpose]);
            $oldHash = $previous->fetchColumn();
            $id = bin2hex(random_bytes(32));
            do {
                $code = str_pad(
                    (string) random_int(0, 999999),
                    6,
                    "0",
                    STR_PAD_LEFT,
                );
            } while ($oldHash && password_verify($code, $oldHash));
            $expires = time() + self::LIFETIME;
            deleteRecords($this->db, "two_factor_challenges", [
                "user_id" => $user["id"],
                "purpose" => $purpose,
            ]);
            createRecord($this->db, "two_factor_challenges", [
                "id" => $id,
                "user_id" => $user["id"],
                "purpose" => $purpose,
                "session_version" => $user["session_version"],
                "code_hash" => password_hash($code, PASSWORD_DEFAULT),
                "expires_at" => $expires,
            ]);
            $this->db->commit();
            return [
                "id" => $id,
                "code" => $code,
                "expires_at" => $expires,
                "user" => $user,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    public function consume(
        string $id,
        string $purpose,
        string $code,
        ?int $actor = null,
    ): ?array {
        if (
            !in_array($purpose, ["login", "enable", "disable"], true) ||
            !preg_match('/^[a-f0-9]{64}$/D', $id) ||
            !preg_match('/^[0-9]{6}$/D', $code) ||
            ($purpose !== "login" && $actor === null)
        ) {
            return null;
        }
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            $challenge = $this->find($id, $purpose);
            if (!$challenge) {
                $this->db->rollBack();
                return null;
            }
            $user = (new User($this->db))->byId((int) $challenge["user_id"]);
            $enabled = $user ? User::twoFactorEnabled($user) : false;
            if (
                !$user ||
                !User::active($user) ||
                !in_array($user["role"], ["Admin", "Manager"], true) ||
                ($actor !== null && (int) $user["id"] !== $actor) ||
                (int) $user["session_version"] !==
                    (int) $challenge["session_version"] ||
                (int) $challenge["expires_at"] <= time() ||
                (int) $challenge["attempts"] >= self::ATTEMPTS ||
                (($purpose === "login" || $purpose === "disable") &&
                    !$enabled) ||
                ($purpose === "enable" && $enabled)
            ) {
                $this->remove($id);
                $this->db->commit();
                return null;
            }
            if (!password_verify($code, $challenge["code_hash"])) {
                $q = $this->db->prepare(
                    "UPDATE two_factor_challenges SET attempts=attempts+1 WHERE id=?",
                );
                $q->execute([$id]);
                if ((int) $challenge["attempts"] + 1 >= self::ATTEMPTS) {
                    $this->remove($id);
                }
                $this->db->commit();
                return null;
            }
            $this->remove($id);
            if ($purpose !== "login") {
                $q = $this->db->prepare(
                    "UPDATE users SET two_factor_enabled=?,session_version=session_version+1,updated_at=? WHERE id=?",
                );
                $q->execute([
                    $purpose === "enable" ? 1 : 0,
                    gmdate("Y-m-d H:i:s"),
                    $user["id"],
                ]);
                deleteRecords($this->db, "two_factor_challenges", [
                    "user_id" => $user["id"],
                ]);
                $user = (new User($this->db))->byId((int) $user["id"]);
            }
            $this->db->commit();
            return $user;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
