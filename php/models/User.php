<?php
declare(strict_types=1);
require_once __DIR__ . "/../database/crud.php";
final class User
{
    public function __construct(private PDO $db) {}
    public function byEmail(string $email): ?array
    {
        return readRecord(
            $this->db,
            "users",
            [
                "id",
                "name",
                "email",
                "password_hash",
                "role",
                "is_active",
                "session_version",
                "two_factor_enabled",
            ],
            ["email" => $email],
        );
    }
    public function byId(int $id): ?array
    {
        return readRecord(
            $this->db,
            "users",
            [
                "id",
                "name",
                "email",
                "password_hash",
                "role",
                "is_active",
                "session_version",
                "two_factor_enabled",
            ],
            ["id" => $id],
        );
    }
    public static function twoFactorEnabled(array $user): bool
    {
        return in_array(
            $user["two_factor_enabled"] ?? 0,
            [true, 1, "1", "t"],
            true,
        );
    }
    public static function active(array $user): bool
    {
        return in_array($user["is_active"], [true, 1, "1", "t"], true);
    }
    public static function publicData(array $user): array
    {
        return [
            "id" => (int) $user["id"],
            "name" => $user["name"],
            "email" => $user["email"],
            "role" => $user["role"],
            "two_factor_enabled" => self::twoFactorEnabled($user),
        ];
    }
    public function create(string $name, string $email, string $password): void
    {
        createRecord($this->db, "users", [
            "name" => $name,
            "email" => $email,
            "password_hash" => password_hash($password, PASSWORD_DEFAULT),
            "created_at" => gmdate("Y-m-d H:i:s"),
            "updated_at" => gmdate("Y-m-d H:i:s"),
        ]);
    }
    public function profile(int $id, string $name): void
    {
        updateRecords(
            $this->db,
            "users",
            ["name" => $name, "updated_at" => gmdate("Y-m-d H:i:s")],
            ["id" => $id],
        );
    }
    public function changeOwnPassword(
        int $id,
        string $current,
        string $password,
        int $version,
    ): bool {
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            $user = $this->byId($id);
            if (
                !$user ||
                !self::active($user) ||
                (int) $user["session_version"] !== $version ||
                !password_verify($current, $user["password_hash"])
            ) {
                $this->db->rollBack();
                return false;
            }
            $this->changePassword($id, $password);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    public function changePassword(int $id, string $password): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash=?, session_version=session_version+1, updated_at=? WHERE id=?",
        );
        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            gmdate("Y-m-d H:i:s"),
            $id,
        ]);
        $stmt = $this->db->prepare(
            "DELETE FROM password_resets WHERE user_id=?",
        );
        $stmt->execute([$id]);
        deleteRecords($this->db, "two_factor_challenges", ["user_id" => $id]);
    }

    public function paginate(int $page): array
    {
        $total = countRecords($this->db, "users");
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $rows = readRecords(
            $this->db,
            "users",
            ["id", "name", "email", "role", "is_active", "created_at"],
            [],
            20,
            ($page - 1) * 20,
        );
        foreach ($rows as &$row) {
            $row["id"] = (int) $row["id"];
            $row["is_active"] = self::active($row);
        }
        return [
            "data" => $rows,
            "pagination" => [
                "page" => $page,
                "pages" => $pages,
                "total" => $total,
                "perPage" => 20,
            ],
        ];
    }
    public function manage(
        string $action,
        ?int $id,
        array $data,
        int $actor,
    ): void {
        $this->db->beginTransaction();
        try {
            // Serialize management changes so concurrent requests cannot remove both last admins.
            $this->db->exec("UPDATE user_management_lock SET id=id WHERE id=1");
            if ($action === "create" && $data["role"] !== "Manager") {
                throw new InvalidArgumentException(
                    "Only Manager accounts can be created from the dashboard.",
                );
            }
            $target = $id ? $this->byId($id) : null;
            if ($target && $target["role"] === "Admin" && $id !== $actor) {
                throw new InvalidArgumentException(
                    "The site owner account cannot be managed by another user.",
                );
            }
            if (
                $target &&
                $action === "edit" &&
                $data["role"] !== $target["role"]
            ) {
                throw new InvalidArgumentException(
                    "Account roles cannot be changed from the dashboard.",
                );
            }
            if (
                $target &&
                $action === "edit" &&
                $data["email"] !== $target["email"]
            ) {
                throw new InvalidArgumentException(
                    "Email addresses cannot be changed.",
                );
            }
            if ($id && !$target) {
                throw new OutOfBoundsException("User not found.");
            }
            if (
                $id === $actor &&
                ($action === "delete" ||
                    $action === "ban" ||
                    ($action === "edit" && $data["role"] !== "Admin"))
            ) {
                throw new InvalidArgumentException(
                    "You cannot delete, ban, or demote your own account.",
                );
            }
            $removesAdmin =
                $target &&
                $target["role"] === "Admin" &&
                self::active($target) &&
                ($action === "delete" ||
                    $action === "ban" ||
                    ($action === "edit" && $data["role"] !== "Admin"));
            if (
                $removesAdmin &&
                (int) $this->db
                    ->query(
                        "SELECT COUNT(*) FROM users WHERE role='Admin' AND is_active=TRUE",
                    )
                    ->fetchColumn() <= 1
            ) {
                throw new InvalidArgumentException(
                    "At least one active Admin must remain.",
                );
            }
            if ($action === "create") {
                createRecord($this->db, "users", [
                    "name" => $data["name"],
                    "email" => $data["email"],
                    "role" => $data["role"],
                    "password_hash" => password_hash(
                        $data["password"],
                        PASSWORD_DEFAULT,
                    ),
                    "created_at" => gmdate("Y-m-d H:i:s"),
                    "updated_at" => gmdate("Y-m-d H:i:s"),
                ]);
            } elseif ($action === "delete") {
                deleteRecords($this->db, "password_resets", ["user_id" => $id]);
                deleteRecords($this->db, "two_factor_challenges", [
                    "user_id" => $id,
                ]);
                deleteRecords($this->db, "users", ["id" => $id]);
            } else {
                $changes = ["updated_at" => gmdate("Y-m-d H:i:s")];
                if ($action === "edit") {
                    $changes += [
                        "name" => $data["name"],
                        "email" => $data["email"],
                        "role" => $data["role"],
                    ];
                    if ($data["password"] !== "") {
                        $changes["password_hash"] = password_hash(
                            $data["password"],
                            PASSWORD_DEFAULT,
                        );
                    }
                } else {
                    $active = $action === "unban";
                    $changes["is_active"] =
                        $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) ===
                        "pgsql"
                            ? ($active
                                ? "true"
                                : "false")
                            : (int) $active;
                }
                // Revoke sessions after role/email/password/status changes, including unban.
                $sensitive =
                    $action !== "edit" ||
                    $data["password"] !== "" ||
                    $data["email"] !== $target["email"] ||
                    $data["role"] !== $target["role"];
                if ($sensitive) {
                    $increment = $this->db->prepare(
                        "UPDATE users SET session_version=session_version+1 WHERE id=?",
                    );
                    $increment->execute([$id]);
                    deleteRecords($this->db, "password_resets", [
                        "user_id" => $id,
                    ]);
                    deleteRecords($this->db, "two_factor_challenges", [
                        "user_id" => $id,
                    ]);
                }
                updateRecords($this->db, "users", $changes, ["id" => $id]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
