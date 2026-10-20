<?php
declare(strict_types=1);
require_once __DIR__ . "/../http/request.php";
require_once __DIR__ . "/../http/response.php";
require_once __DIR__ . "/../validation/SubscriberValidation.php";
require_once __DIR__ . "/../models/Subscriber.php";
require_once __DIR__ . "/../middleware/rateLimit.php";

require_once __DIR__ . "/../functions/csv.php";
final class SubscriberController
{
    public function create(): void
    {
        try {
            $data = SubscriberValidation::create(jsonRequest());
        } catch (InvalidArgumentException $e) {
            validationResponse($e);
            return;
        }
        $db = null;
        try {
            $db = require __DIR__ . "/../db.php";
            if (
                !checkRateLimit($db, rateLimitClientIp(), "newsletter", 3, 600)
            ) {
                header("Retry-After: 600");
                jsonResponse(
                    [
                        "success" => false,
                        "message" =>
                            "Too many attempts. Please try again later.",
                    ],
                    429,
                );
                return;
            }
            (new Subscriber($db))->create($data);
            jsonResponse(
                ["success" => true, "message" => "Subscribed successfully"],
                201,
            );
        } catch (Throwable $e) {
            if (
                $e instanceof PDOException &&
                $db instanceof PDO &&
                isUniqueViolation($db, $e)
            ) {
                jsonResponse(
                    [
                        "success" => false,
                        "message" => "Email already subscribed",
                        "errors" => ["email" => "Email already subscribed"],
                    ],
                    409,
                );
                return;
            }
            error_log($e->getMessage());
            jsonResponse(
                ["success" => false, "message" => "Could not subscribe"],
                500,
            );
        }
    }

    public function index(): void
    {
        try {
            $db = require __DIR__ . "/../db.php";
            $model = new Subscriber($db);
            if (($_GET["export"] ?? "") === "csv") {
                header("Content-Type: text/csv; charset=utf-8");
                header(
                    'Content-Disposition: attachment; filename="subscribers.csv"',
                );
                $output = fopen("php://output", "w");
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv(
                    $output,
                    ["Email", "Date submitted (UTC)"],
                    ",",
                    '"',
                    "",
                );
                foreach ($model->exportRows() as $row) {
                    fputcsv(
                        $output,
                        array_map("csvCell", [
                            $row["email"],
                            $row["created_at"] ?? "",
                        ]),
                        ",",
                        '"',
                        "",
                    );
                }
                fclose($output);
                return;
            }
            $page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
            if ($page === false || $page < 1) {
                jsonResponse(
                    ["success" => false, "message" => "Invalid page"],
                    422,
                );
                return;
            }
            jsonResponse(["success" => true, ...$model->paginate($page, 20)]);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            jsonResponse(
                ["success" => false, "message" => "Could not load subscribers"],
                500,
            );
        }
    }
}
