<?php
declare(strict_types=1);
require_once __DIR__ . "/blocks.php";
function seoHead(array $seo): string
{
    $html = "<title>" . seoEscape($seo["title"]) . "</title>";
    $tags = [
        "description" => $seo["description"],
        "robots" => $seo["robots"],
        "twitter:card" => "summary_large_image",
        "twitter:title" => $seo["title"],
        "twitter:description" => $seo["description"],
        "twitter:image" => $seo["image"],
    ];
    foreach ($tags as $name => $value) {
        if ($value !== null) {
            $html .=
                '<meta name="' .
                seoEscape($name) .
                '" content="' .
                seoEscape($value) .
                '">';
        }
    }
    foreach (
        [
            "og:title" => $seo["title"],
            "og:description" => $seo["description"],
            "og:type" => $seo["ogType"],
            "og:site_name" => $seo["siteName"],
            "og:locale" => $seo["locale"],
            "og:image" => $seo["image"],
            "og:url" => $seo["canonical"],
        ]
        as $property => $value
    ) {
        if ($value !== null) {
            $html .=
                '<meta property="' .
                seoEscape($property) .
                '" content="' .
                seoEscape($value) .
                '">';
        }
    }
    if ($seo["canonical"]) {
        $html .=
            '<link rel="canonical" href="' .
            seoEscape($seo["canonical"]) .
            '">';
    }
    if ($seo["schema"]) {
        $html .=
            '<script data-seo="structured" type="application/ld+json">' .
            json_encode(
                $seo["schema"],
                JSON_THROW_ON_ERROR |
                    JSON_HEX_TAG |
                    JSON_HEX_AMP |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT |
                    JSON_UNESCAPED_SLASHES,
            ) .
            "</script>";
    }
    return "<!--seo:start-->" . $html . "<!--seo:end-->";
}
