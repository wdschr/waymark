<?php
declare(strict_types=1);

/**
 * Parses a standard Netscape Bookmark File (the format every browser
 * exports/imports) into a flat list of links. This format is not valid
 * HTML (DT is never closed, DL nests without matching structure the way
 * a strict parser expects) so it's tokenized directly rather than parsed
 * as a DOM tree.
 *
 * Returns a list of ['url','title','description','tags'=>[],'collection'=>?string]
 */
function wf_parse_netscape_bookmarks(string $html): array
{
    $links = [];
    $folderStack = [];

    $pattern = '/<DT>\s*<H3[^>]*>(.*?)<\/H3>|<DT>\s*<A\s+([^>]*)>(.*?)<\/A>(?:\s*<DD>([^<\r\n]*))?|<\/DL>/is';

    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
        return $links;
    }

    foreach ($matches as $m) {
        $whole = $m[0];

        if (preg_match('/^<\/DL>$/i', trim($whole))) {
            if (!empty($folderStack)) {
                array_pop($folderStack);
            }
            continue;
        }

        if (stripos($whole, '<h3') !== false) {
            $folderStack[] = html_entity_decode(trim(strip_tags($m[1] ?? '')), ENT_QUOTES, 'UTF-8');
            continue;
        }

        $attrs = $m[2] ?? '';
        $title = html_entity_decode(trim(strip_tags($m[3] ?? '')), ENT_QUOTES, 'UTF-8');
        $description = isset($m[4]) ? html_entity_decode(trim($m[4]), ENT_QUOTES, 'UTF-8') : '';

        $href = '';
        if (preg_match('/HREF="([^"]*)"/i', $attrs, $hm)) {
            $href = html_entity_decode($hm[1], ENT_QUOTES, 'UTF-8');
        }
        if ($href === '' || !preg_match('#^https?://#i', $href)) {
            continue;
        }

        $tags = [];
        if (preg_match('/TAGS="([^"]*)"/i', $attrs, $tm)) {
            $tags = wf_parse_tags(html_entity_decode($tm[1], ENT_QUOTES, 'UTF-8'));
        }

        $links[] = [
            'url'         => mb_substr($href, 0, 2048),
            'title'       => mb_substr($title !== '' ? $title : $href, 0, 512),
            'description' => mb_substr($description, 0, 2000),
            'tags'        => $tags,
            'collection'  => end($folderStack) ?: null,
        ];
    }

    return $links;
}

/**
 * Builds a Netscape Bookmark File from a user's collections and links.
 * $linksByCollection maps collection_id => list of link rows.
 * $uncategorized is the list of link rows with no collection.
 */
function wf_generate_netscape_html(array $collections, array $linksByCollection, array $uncategorized): string
{
    $out = "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
    $out .= "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
    $out .= "<TITLE>Bookmarks</TITLE>\n<H1>Bookmarks</H1>\n<DL><p>\n";

    foreach ($uncategorized as $link) {
        $out .= wf_netscape_link_line($link, '    ');
    }

    foreach ($collections as $collection) {
        $out .= '    <DT><H3>' . h($collection['name']) . "</H3>\n    <DL><p>\n";
        foreach ($linksByCollection[$collection['id']] ?? [] as $link) {
            $out .= wf_netscape_link_line($link, '        ');
        }
        $out .= "    </DL><p>\n";
    }

    $out .= "</DL><p>\n";
    return $out;
}

function wf_netscape_link_line(array $link, string $indent): string
{
    $addDate = strtotime($link['created_at'] ?? '') ?: time();
    $tags = h($link['tags_cache'] ?? '');

    $line = $indent . '<DT><A HREF="' . h($link['url']) . '" ADD_DATE="' . $addDate . '"';
    if ($tags !== '') {
        $line .= ' TAGS="' . $tags . '"';
    }
    $line .= '>' . h($link['title'] !== '' ? $link['title'] : $link['url']) . "</A>\n";

    if (!empty($link['description'])) {
        $line .= $indent . '<DD>' . h($link['description']) . "\n";
    }

    return $line;
}
