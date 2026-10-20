<?php
declare(strict_types=1);
require_once __DIR__ . "/controllers/SubscriberController.php";

require_once __DIR__ . "/controllers/ContactController.php";

require_once __DIR__ . "/controllers/AuthController.php";
require_once __DIR__ . "/controllers/ProfileController.php";

require_once __DIR__ . "/controllers/UserController.php";

require_once __DIR__ . "/controllers/MediaController.php";

require_once __DIR__ . "/controllers/SeoController.php";

require_once __DIR__ . "/controllers/MetadataController.php";
require_once __DIR__ . "/controllers/ActivityController.php";
require_once __DIR__ . "/config/ContentModules.php";
require_once __DIR__ . "/controllers/ContentController.php";
require_once __DIR__ . "/controllers/DashboardController.php";
require_once __DIR__ . "/controllers/TwoFactorController.php";
return function (Router $router): void {
    $twoFactor = new TwoFactorController();
    $router->post("/auth/two-factor/verify", [$twoFactor, "verifyLogin"]);
    $router->post("/auth/two-factor/resend", [$twoFactor, "resend"]);
    $router->post("/auth/two-factor/cancel", [$twoFactor, "cancel"]);
    $router->post("/auth/two-factor/request-setting", [
        $twoFactor,
        "requestSetting",
    ]);
    $router->post("/auth/two-factor/verify-setting", [
        $twoFactor,
        "verifySetting",
    ]);
    $router->get("/dashboard", [new DashboardController(), "index"]);
    $metadata = new MetadataController();
    $router->get("/metadata", [$metadata, "index"]);
    $router->put("/metadata", [$metadata, "save"]);
    $router->get("/activity", [new ActivityController(), "index"]);
    $router->get("/seo", [new SeoController(), "page"]);
    $router->get("/seo/robots", [new SeoController(), "robots"]);
    $router->get("/seo/sitemap", [new SeoController(), "sitemap"]);
    $media = new MediaController();
    $router->get("/media", [$media, "index"]);
    $router->post("/media", [$media, "upload"]);
    $router->post("/media/bulk-delete", fn() => $media->delete());
    $router->get("/media/{id}", [$media, "show"]);
    $router->patch("/media/{id}", [$media, "update"]);
    $router->delete("/media/{id}", [$media, "delete"]);
    foreach (ContentModules::all() as $path => $definition) {
        $controller = new ContentController($path);
        $router->get("/" . $path, [$controller, "index"]);
        $router->post("/" . $path, fn() => $controller->save());
        $router->post(
            "/" . $path . "/bulk-delete",
            fn() => $controller->delete(),
        );
        $router->get("/" . $path . "/{id}", [$controller, "show"]);
        $router->patch("/" . $path . "/{id}", [$controller, "save"]);
        $router->delete("/" . $path . "/{id}", [$controller, "delete"]);
        $router->get("/public/" . $path . "/{slug}", [
            $controller,
            "publicShow",
        ]);
    }
    $users = new UserController();
    $router->get("/users", [$users, "index"]);
    $router->post("/users", [$users, "create"]);
    $router->patch("/users/{id}", [$users, "edit"]);
    $router->patch("/users/{id}/status", [$users, "status"]);
    $router->delete("/users/{id}", [$users, "delete"]);
    $auth = new AuthController();
    $profile = new ProfileController();
    $router->get("/auth/me", [$auth, "me"]);
    $router->post("/auth/login", [$auth, "login"]);
    $router->post("/auth/logout", [$auth, "logout"]);
    $router->post("/auth/forgot-password", [$auth, "forgot"]);
    $router->post("/auth/reset-password", [$auth, "reset"]);
    $router->patch("/auth/profile", [$profile, "update"]);
    $router->post("/auth/password", [$profile, "password"]);
    $contacts = new ContactController();
    $router->post("/contacts", [$contacts, "create"]);
    $router->get("/contacts", function () use ($contacts) {
        if (requireStaff()) {
            $contacts->index();
        }
    });
    $router->patch("/contacts/{id}/read", function (string $id) use (
        $contacts,
    ) {
        if (requireStaff() && verifyCsrf()) {
            $contacts->updateRead($id);
        }
    });
    $subscribers = new SubscriberController();
    $router->post("/subscribers", [$subscribers, "create"]);
    $router->get("/subscribers", function () use ($subscribers) {
        if (requireStaff()) {
            $subscribers->index();
        }
    });
};
