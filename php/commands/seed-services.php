<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit();
}
require_once __DIR__ . "/../models/Content.php";
require_once __DIR__ . "/../validation/ContentValidation.php";
require_once __DIR__ . "/../functions/activity.php";
try {
    $db = require __DIR__ . "/../db.php";
    $actor = $db
        ->query(
            "SELECT id,name FROM users WHERE role='Admin' AND is_active=1 ORDER BY id LIMIT 1",
        )
        ->fetch(PDO::FETCH_ASSOC);
    if (!$actor) {
        throw new RuntimeException(
            "Create an active administrator before adding the initial services.",
        );
    }
    $services = [
        [
            "Property Sales",
            "property-sales",
            "From preparing your listing to arranging viewings, get practical support for presenting your property and finding the right buyer.",
            [
                [
                    "A clear plan for your property",
                    "We begin with a conversation about your property, preferred timeline, and priorities. Together we identify the features that matter to potential buyers and prepare a focused plan for presenting the home.",
                ],
                [
                    "Thoughtful presentation and viewings",
                    'Our team helps organize the listing information, highlights the property\'s strengths, and coordinates viewings. You receive clear feedback so you can understand interest and decide on your next steps.',
                ],
                [
                    "Support through the next steps",
                    "When a buyer expresses interest, we help keep communication organized and explain the practical steps ahead. Contact Haven to discuss your property and agree the scope of support before getting started.",
                ],
            ],
        ],
        [
            "Residential Rentals",
            "residential-rentals",
            "Find a rental that suits your routine, or connect your available home with prospective tenants through an organized letting process.",
            [
                [
                    "For people looking for a home",
                    "Tell us your preferred areas, space requirements, move-in date, and budget. We help narrow the search and arrange suitable viewings so you can compare homes with a clear set of priorities.",
                ],
                [
                    "For landlords preparing to let",
                    "We help present available homes with useful listing information and coordinated viewings. Our approach keeps communication clear between the property owner and prospective tenants.",
                ],
                [
                    "Understand the details before moving",
                    "Before proceeding, discuss the advertised rent, service arrangements, included fittings, and any additional costs. Contact our team to share your requirements or tell us about a home you would like to let.",
                ],
            ],
        ],
        [
            "Property Management",
            "property-management",
            "Keep everyday property matters organized with support for tenant communication, maintenance coordination, and routine owner updates.",
            [
                [
                    "An agreed approach to management",
                    "Every property has different needs. We start by agreeing the responsibilities, reporting arrangements, and approval process that fit the owner and the property.",
                ],
                [
                    "Coordination for everyday issues",
                    "Our team can help organize tenant communication and coordinate maintenance requests within the agreed scope. Clear records and timely updates help owners follow the practical work being carried out.",
                ],
                [
                    "Stay informed about your property",
                    "Regular communication helps you understand outstanding tasks and plan ahead. Contact Haven with the property location and your management needs so we can discuss an appropriate arrangement.",
                ],
            ],
        ],
        [
            "Property Marketing",
            "property-marketing",
            "Give your property a clear, consistent presentation with compelling listing copy, organized photographs, and useful information for enquiries.",
            [
                [
                    "Bring the important details together",
                    "We help organize the information a prospective buyer or tenant needs, from room layouts and outdoor space to location and available facilities. Accurate details make a listing easier to understand.",
                ],
                [
                    "Present the space with purpose",
                    'A considered selection of photographs and concise descriptions helps people recognize the property\'s strongest features. We work with the material available and agree any additional preparation before publication.',
                ],
                [
                    "Keep enquiries focused",
                    "A consistent presentation helps the right people ask useful questions. Speak with Haven about a marketing brief, the materials you already have, and how you would like your property introduced.",
                ],
            ],
        ],
        [
            "Relocation Support",
            "relocation-support",
            "Make a move to Lagos more manageable with neighbourhood introductions, a focused home shortlist, and coordinated viewing plans.",
            [
                [
                    "Start with your daily life",
                    "A useful relocation plan begins with where you will work, how you will travel, and what your household needs nearby. We use those priorities to guide the areas and homes you consider.",
                ],
                [
                    "Make the most of your search time",
                    "We help organize a realistic shortlist and coordinate viewings around your availability. Clear notes on each option make it easier to compare homes when you are planning from a distance.",
                ],
                [
                    "Prepare for the move",
                    "Once you identify a suitable home, we help keep the practical property questions organized. Contact our team with your target move date and preferred locations to discuss how we can support your search.",
                ],
            ],
        ],
        [
            "Property Search and Viewings",
            "property-search-and-viewings",
            "Turn a broad property search into a practical shortlist, with viewing coordination and space to compare the details that matter to you.",
            [
                [
                    "Define your priorities",
                    "We begin by separating essential requirements from preferences. Your preferred location, budget, household size, and everyday routines shape a search that is useful rather than overwhelming.",
                ],
                [
                    "View homes with a clear checklist",
                    "A good viewing leaves time to understand the layout and ask practical questions. We coordinate appointments and help you keep track of facilities, access, parking, and other details across different options.",
                ],
                [
                    "Compare and choose your next step",
                    "After each viewing, review what works and what does not. We help refine the shortlist as your priorities become clearer. Share your requirements through our contact page to begin a focused conversation.",
                ],
            ],
        ],
    ];
    $prepared = [];
    foreach ($services as [$title, $slug, $excerpt, $sections]) {
        $blocks = [];
        foreach ($sections as [$heading, $body]) {
            $blocks[] = [
                "type" => "heading",
                "props" => ["level" => 2],
                "content" => [
                    ["type" => "text", "text" => $heading, "styles" => []],
                ],
            ];
            $blocks[] = [
                "type" => "paragraph",
                "content" => [
                    ["type" => "text", "text" => $body, "styles" => []],
                ],
            ];
        }
        $prepared[] = ContentValidation::validate(
            [
                "title" => $title,
                "slug" => $slug,
                "excerpt" => $excerpt,
                "content" => $blocks,
                "details" => [],
                "status" => "published",
                "featured_media_id" => null,
                "seo_title" => $title . " | Haven Real Estate",
                "seo_description" => $excerpt,
                "seo_noindex" => false,
            ],
            "services",
        );
    }
    $model = new Content($db, "services");
    foreach ($prepared as $data) {
        $query = $db->prepare("SELECT id FROM services WHERE slug=?");
        $query->execute([$data["slug"]]);
        if ($query->fetchColumn()) {
            echo "Skipped existing service: " . $data["title"] . PHP_EOL;
            continue;
        }
        $service = $model->save($data, (int) $actor["id"], null);
        recordActivity(
            $db,
            $actor,
            "services",
            "created",
            $service["id"],
            "Created " . $service["title"] . " (published).",
        );
        echo "Created: " . $service["title"] . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Could not seed services: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
