<?php
declare(strict_types=1);
function seoEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}
function seoShort(string $text, int $limit): string
{
    $text = preg_replace("/\s+/u", " ", trim(strip_tags($text))) ?? "";
    preg_match("/^.{0," . $limit . "}/us", $text, $match);
    return $match[0] ?? "";
}
function seoInline(mixed $content): string
{
    if (is_string($content)) {
        return seoEscape($content);
    }
    if (!is_array($content)) {
        return "";
    }
    $html = "";
    foreach ($content as $part) {
        if (!is_array($part)) {
            continue;
        }
        if (($part["type"] ?? "") === "link") {
            $href = $part["href"] ?? "";
            $safe =
                is_string($href) &&
                preg_match("#^(https?://|mailto:|tel:|/(?!/)|\#)#i", $href);
            $inside = seoInline($part["content"] ?? []);
            $html .= $safe
                ? '<a href="' . seoEscape($href) . '">' . $inside . "</a>"
                : $inside;
            continue;
        }
        $text = seoEscape(
            is_string($part["text"] ?? null) ? $part["text"] : "",
        );
        $styles = $part["styles"] ?? [];
        foreach (
            [
                "bold" => "strong",
                "italic" => "em",
                "underline" => "u",
                "strike" => "s",
                "code" => "code",
            ]
            as $key => $tag
        ) {
            if (($styles[$key] ?? false) === true) {
                $text = "<" . $tag . ">" . $text . "</" . $tag . ">";
            }
        }
        $html .= str_replace("\n", "<br>", $text);
    }
    return $html;
}
function seoBlocks(array $blocks, int $depth = 0): string
{
    if ($depth > 20) {
        return "";
    }
    $html = "";
    $list = null;
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = $block["type"] ?? "paragraph";
        $content = seoInline($block["content"] ?? []);
        $props = $block["props"] ?? [];
        $next = in_array($type, ["bulletListItem", "checkListItem"], true)
            ? "ul"
            : ($type === "numberedListItem"
                ? "ol"
                : null);
        if ($list !== $next) {
            if ($list) {
                $html .= "</" . $list . ">";
            }
            if ($next) {
                $html .= "<" . $next . ">";
            }
            $list = $next;
        }
        $children = seoBlocks(
            is_array($block["children"] ?? null) ? $block["children"] : [],
            $depth + 1,
        );
        if ($next) {
            $html .= "<li>" . $content . $children . "</li>";
            continue;
        }
        if ($type === "heading") {
            $level = max(2, min(6, (int) ($props["level"] ?? 2)));
            $html .= "<h" . $level . ">" . $content . "</h" . $level . ">";
        } elseif ($type === "image") {
            $url = $props["url"] ?? "";
            if (
                is_string($url) &&
                preg_match(
                    '#^/uploads/[a-f0-9]{32}\.(jpg|png|webp|gif)$#D',
                    $url,
                )
            ) {
                $html .=
                    '<figure><img loading="lazy" src="' .
                    seoEscape($url) .
                    '" alt="' .
                    seoEscape(
                        (string) ($props["caption"] ?? ($props["name"] ?? "")),
                    ) .
                    '">' .
                    (!empty($props["caption"])
                        ? "<figcaption>" .
                            seoEscape((string) $props["caption"]) .
                            "</figcaption>"
                        : "") .
                    "</figure>";
            }
        } elseif ($type === "codeBlock") {
            $html .= "<pre><code>" . $content . "</code></pre>";
        } elseif ($type === "quote") {
            $html .= "<blockquote>" . $content . "</blockquote>";
        } elseif ($type === "divider") {
            $html .= "<hr>";
        } elseif ($type === "table") {
            $html .= '<div class="table-scroll"><table><tbody>';
            foreach ($block["content"]["rows"] ?? [] as $row) {
                $html .= "<tr>";
                foreach ($row["cells"] ?? [] as $cell) {
                    $html .=
                        "<td>" .
                        seoInline(
                            isset($cell["type"])
                                ? $cell["content"] ?? []
                                : $cell,
                        ) .
                        "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</tbody></table></div>";
        } else {
            $html .= "<p>" . $content . "</p>";
        }
        $html .= $children;
    }
    if ($list) {
        $html .= "</" . $list . ">";
    }
    return $html;
}
