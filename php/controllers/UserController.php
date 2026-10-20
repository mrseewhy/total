<?php
declare(strict_types=1);
require_once __DIR__ . "/AuthController.php";
require_once __DIR__ . "/../validation/UserValidation.php";
final class UserController
{
    public function index(): void
    {
        if (!requireAdmin()) {
            return;
        }
        $page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            throw new InvalidArgumentException("Invalid page.");
        }
        $db = require __DIR__ . "/../db.php";
        jsonResponse(["success" => true, ...(new User($db))->paginate($page)]);
    }
    public function create(): void
    {
        $this->mutate("create");
    }
    public function edit(string $id): void
    {
        $this->mutate("edit", $id);
    }
    public function status(string $id): void
    {
        $this->mutate("status", $id);
    }
    public function delete(string $id): void
    {
        $this->mutate("delete", $id);
    }
    private function mutate(string $action, ?string $id = null): void
    {
        $actor = requireAdmin();
        if (!$actor || !verifyCsrf()) {
            return;
        }
        $number = $id === null ? null : filter_var($id, FILTER_VALIDATE_INT);
        if ($id !== null && ($number === false || $number < 1)) {
            throw new InvalidArgumentException("Invalid user ID.");
        }
        $data = [];
        if (in_array($action, ["create", "edit"], true)) {
            $data = UserValidation::details(
                jsonRequest(),
                $action === "create",
            );
        }
        if ($action === "status") {
            $input = jsonRequest();
            if (!is_bool($input["is_active"] ?? null)) {
                throw new InvalidArgumentException(
                    "is_active must be a boolean.",
                );
            }
            $action = $input["is_active"] ? "unban" : "ban";
        }
        $db = require __DIR__ . "/../db.php";
        if (
            !checkRateLimit(
                $db,
                (string) $actor["id"],
                "user-management",
                60,
                600,
            )
        ) {
            header("Retry-After: 600");
            jsonResponse(
                [
                    "success" => false,
                    "message" => "Too many changes. Try again later.",
                ],
                429,
            );
            return;
        }
        $subject = $number ? (new User($db))->byId($number) : null;
        try {
            (new User($db))->manage(
                $action,
                $number,
                $data,
                (int) $actor["id"],
            );
        } catch (OutOfBoundsException $e) {
            jsonResponse(
                ["success" => false, "message" => $e->getMessage()],
                404,
            );
            return;
        } catch (PDOException $e) {
            if (isUniqueViolation($db, $e)) {
                jsonResponse(
                    [
                        "success" => false,
                        "message" => "That email address is already in use.",
                        "errors" => [
                            "email" => "That email address is already in use.",
                        ],
                    ],
                    409,
                );
                return;
            }
            throw $e;
        }
        $subject = $number
            ? $subject
            : (new User($db))->byEmail($data["email"]);
        recordActivity(
            $db,
            $actor,
            "users",
            $action,
            $subject["id"] ?? ($number ?? "new"),
            "User account " .
                $action .
                ": " .
                ($subject["name"] ?? "User") .
                ".",
        );
        // Keep the acting admin signed in after changing their own password/email.
        if ($number === (int) $actor["id"]) {
            establishSession((new User($db))->byId($number));
        }
        jsonResponse(
            [
                "success" => true,
                "message" =>
                    "User " .
                    match ($action) {
                        "create" => "created",
                        "edit" => "updated",
                        "ban" => "banned",
                        "unban" => "unbanned",
                        "delete" => "deleted",
                    } .
                    ".",
                "csrf" => csrfToken(),
            ],
            $action === "create" ? 201 : 200,
        );
    }
}
