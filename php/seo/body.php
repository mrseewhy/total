<?php
declare(strict_types=1);
require_once __DIR__ . "/blocks.php";
function seoBody(array $page): string
{
    if ($page["type"] === "private") {
        return '<main><h1>Site administration</h1><p>Sign in to access your workspace.</p><a href="/site-admin/login">Sign in</a></main>';
    }
    $links = ["/" => "Home", "/about" => "About us"];
    foreach (ContentModules::all() as $module) {
        if ($module["navigation"]) {
            $links["/" . $module["path"]] = $module["label"];
        }
    }
    $links["/contact"] = "Contact";
    $profile = isset($page["kind"])
        ? ContentModules::get($page["kind"])["profile"]
        : "content";
    $html =
        '<div class="min-h-screen bg-stone-50 text-gray-800"><header class="mx-auto max-w-6xl px-6 py-6"><a href="/" class="text-2xl font-semibold">haven.</a><nav aria-label="Main navigation" class="mt-4 flex flex-wrap gap-5">';
    foreach ($links as $url => $label) {
        $html .= '<a href="' . $url . '">' . $label . "</a>";
    }
    $html .=
        '</nav></header><main class="mx-auto max-w-6xl px-6 py-12"><h1 class="text-4xl font-semibold">' .
        seoEscape($page["heading"]) .
        "</h1>";
    if ($page["intro"]) {
        $html .=
            '<p class="mt-6 text-lg">' . seoEscape($page["intro"]) . "</p>";
    }
    if ($page["type"] === "listing") {
        $html .= '<div class="mt-8 grid gap-6 md:grid-cols-3">';
        foreach ($page["items"] as $item) {
            $html .= '<article><a href="' . seoEscape($item["url"]) . '">';
            if ($item["image"]) {
                $html .=
                    '<img loading="lazy" src="' .
                    seoEscape($item["image"]["url"]) .
                    '" alt="' .
                    seoEscape($item["image"]["alt"]) .
                    '" width="' .
                    $item["image"]["width"] .
                    '" height="' .
                    $item["image"]["height"] .
                    '">';
            }
            $html .=
                "<h2>" .
                seoEscape($item["title"]) .
                "</h2></a><p>" .
                seoEscape($item["excerpt"]) .
                "</p></article>";
        }
        $html .= "</div>";
        if (!$page["items"]) {
            $html .= '<p class="mt-8">No published items yet.</p>';
        }
        $paging = $page["pagination"];
        $html .= '<nav aria-label="Pagination" class="mt-8 flex gap-6">';
        if ($paging["page"] > 1) {
            $html .=
                '<a href="' .
                $page["path"] .
                ($paging["page"] === 2
                    ? ""
                    : "?page=" . ($paging["page"] - 1)) .
                '">Previous</a>';
        }
        if ($paging["page"] < $paging["pages"]) {
            $html .=
                '<a href="' .
                $page["path"] .
                "?page=" .
                ($paging["page"] + 1) .
                '">Next</a>';
        }
        $html .= "</nav>";
    }
    if ($page["type"] === "detail") {
        if ($page["image"]) {
            $html .=
                '<img class="mt-8 w-full rounded-xl" src="' .
                seoEscape($page["image"]["url"]) .
                '" alt="' .
                seoEscape($page["image"]["alt"]) .
                '" width="' .
                $page["image"]["width"] .
                '" height="' .
                $page["image"]["height"] .
                '">';
        }
        $item = $page["item"];
        $details = $item["details"];
        if ($profile === "event") {
            $html .=
                '<p class="mt-6">' . seoEscape($details["location"]) . "</p>";
            if ($details["starts_at"]) {
                $html .=
                    '<time datetime="' .
                    str_replace(" ", "T", $details["starts_at"]) .
                    'Z">' .
                    seoEscape($details["starts_at"]) .
                    " UTC</time>";
            }
        }
        if ($profile === "property") {
            $html .=
                '<p class="mt-6">' .
                seoEscape(
                    $details["currency"] .
                        " " .
                        $details["price"] .
                        " · For " .
                        $details["listing_type"] .
                        " · " .
                        $details["location"] .
                        " · " .
                        $details["bedrooms"] .
                        " bedrooms · " .
                        $details["bathrooms"] .
                        " bathrooms · " .
                        $details["availability"],
                ) .
                "</p>";
        }
        $html .=
            '<article class="site-prose mt-8">' . $page["html"] . "</article>";
        foreach ($page["gallery"] as $image) {
            $html .=
                '<img loading="lazy" src="' .
                seoEscape($image["url"]) .
                '" alt="' .
                seoEscape($image["alt"]) .
                '">';
        }
    }
    if ($page["path"] === "/about") {
        $html .=
            '<section class="mt-8"><h2>A more thoughtful way home.</h2><p>Haven is based in Lagos. We connect people with spaces to buy, rent, and call their own, with straightforward conversations and local understanding.</p></section>';
    }
    if ($page["path"] === "/contact") {
        $html .=
            '<section class="mt-8"><h2>Your next chapter starts with a hello.</h2><p>Share your preferred location, budget, and timeline. Enable JavaScript to use our contact form.</p></section>';
    }
    if ($page["path"] === "/") {
        $html .=
            '<section class="mt-8"><h2>Finding a home is personal. We keep it that way.</h2><p>We listen first, share honest advice, and help you make a decision that feels right for you.</p><a href="/properties">Explore properties</a></section>';
    }
    return $html .
        '</main><footer class="mx-auto max-w-6xl px-6 py-8">Haven Real Estate · Lagos, Nigeria</footer></div>';
}
