<?php
declare(strict_types=1);
require_once __DIR__ . "/../http/request.php";
require_once __DIR__ . "/../http/response.php";
require_once __DIR__ . "/../validation/ContactValidation.php";
require_once __DIR__ . "/../models/Contact.php";
require_once __DIR__ . "/../middleware/rateLimit.php";
final class ContactController
{
    public function create(): void
    {
        try {
            $data = ContactValidation::create(jsonRequest());
        } catch (InvalidArgumentException $e) {
            validationResponse($e);
            return;
        }
        try {
            $db = require __DIR__ . "/../db.php";
            if (!checkRateLimit($db, rateLimitClientIp(), "contact", 3, 600)) {
                header("Retry-After: 600");
                jsonResponse(
                    [
                        "success" => false,
                        "message" =>
                            "Too many inquiries. Please try again later.",
                    ],
                    429,
                );
                return;
            }
            (new Contact($db))->create($data);
            jsonResponse(
                [
                    "success" => true,
                    "message" => "Thank you! Your inquiry has been received.",
                ],
                201,
            );
        } catch (Throwable $e) {
            $this->failure($e);
        }
    }
    public function index(): void
    {
        $page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            jsonResponse(
                ["success" => false, "message" => "Invalid page"],
                422,
            );
            return;
        }
        try {
            $db = require __DIR__ . "/../db.php";
            jsonResponse([
                "success" => true,
                ...(new Contact($db))->paginate($page),
            ]);
        } catch (Throwable $e) {
            $this->failure($e);
        }
    }
    public function updateRead(string $id): void
    {
        try {
            $number = filter_var($id, FILTER_VALIDATE_INT);
            if ($number === false || $number < 1) {
                throw new InvalidArgumentException("Invalid inquiry ID");
            }
            $read = ContactValidation::readStatus(jsonRequest());
        } catch (InvalidArgumentException $e) {
            validationResponse($e);
            return;
        }
        try {
            $db = require __DIR__ . "/../db.php";
            $row = (new Contact($db))->setRead($number, $read);
            if ($row && ($actor = currentUser())) {
                recordActivity(
                    $db,
                    $actor,
                    "contacts",
                    $read ? "marked_read" : "marked_unread",
                    $number,
                    "Marked inquiry " . ($read ? "read." : "unread."),
                );
            }
            jsonResponse(
                $row
                    ? ["success" => true, "data" => $row]
                    : ["success" => false, "message" => "Inquiry not found"],
                $row ? 200 : 404,
            );
        } catch (Throwable $e) {
            $this->failure($e);
        }
    }
    private function failure(Throwable $e): void
    {
        error_log($e->getMessage());
        jsonResponse(
            [
                "success" => false,
                "message" => "Could not process inquiry. Please try again.",
            ],
            500,
        );
    }
}
