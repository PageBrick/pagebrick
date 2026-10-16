<?php
// Before the site is shown to everybody. While it is live, the panel tells an administrator about two things that are easy to forget: pages that still say
// "write here what your company does" (the example site the installer makes), and an address that still opens without the padlock (https). A card on the
// dashboard and a line in the bell; for each thing the owner can fix it or say that it is fine as it is (then it is not mentioned again).

/**
 * The sentences of the example site, in every language the core speaks, long enough to be recognised (a title like "Sobre" proves nothing).
 * Built once per version and kept in an option: building it means loading the three dictionaries.
 */
function pb_sample_texts(): array
{
    $kept = json_decode(pb_option('sample_texts', ''), true);
    if (is_array($kept) && ($kept['version'] ?? '') === PB_VERSION && is_array($kept['texts'] ?? null)) {
        return $kept['texts'];
    }
    $texts = [];
    $panel = pb_locale();
    try {
        foreach (array_keys(PB_LOCALES) as $locale) {
            pb_set_locale($locale);
            $leaves = [];
            $demoPages = pb_standard_demo()['pages'];
            array_walk_recursive($demoPages, function ($value, $key) use (&$leaves) {
                if (is_string($value) && !in_array($key, ['slug', 'template', 'image', 'button_link', 'link', 'value', '_visible'], true)) {
                    $leaves[] = $value;
                }
            });
            foreach ($leaves as $leaf) {
                foreach (pb_sample_sentences($leaf) as $sentence) {
                    $texts[$sentence] = true;
                }
            }
        }
    } finally {
        pb_set_locale($panel);
    }
    $texts = array_keys($texts);
    pb_set_option('sample_texts', json_encode(['version' => PB_VERSION, 'texts' => $texts], JSON_UNESCAPED_UNICODE));
    return $texts;
}

/** A field's text cut at the tags (a paragraph, a heading) into plain pieces of at least 30 characters. */
function pb_sample_sentences(string $html): array
{
    $pieces = preg_split('~<[^>]*>~', $html) ?: [];
    $pieces = array_map(fn($p) => trim((string) preg_replace('~\s+~u', ' ', html_entity_decode($p, ENT_QUOTES | ENT_HTML5))), $pieces);
    return array_values(array_filter($pieces, fn($p) => mb_strlen($p) >= 30));
}

/** True when the page's published content still has a sentence of the example site. */
function pb_page_has_sample_text(array $page): bool
{
    $found = false;
    $texts = pb_sample_texts();
    $look = function ($value) use (&$found, $texts, &$look): void {
        if ($found) {
            return;
        }
        if (is_array($value)) {
            array_walk($value, fn($v) => $look($v));
        } elseif (is_string($value)) {
            foreach (pb_sample_sentences($value) as $sentence) {
                if (in_array($sentence, $texts, true)) {
                    $found = true;
                    return;
                }
            }
        }
    };
    $look(json_decode((string) $page['data'], true) ?: []);
    $look((string) $page['seo_description']);
    return $found;
}

/** What the owner has said is fine as it is: ['pages' => [ids], 'https' => bool]. */
function pb_launch_acknowledged(): array
{
    $kept = json_decode(pb_option('launch_ack', ''), true);
    return ['pages' => array_map('intval', (array) ($kept['pages'] ?? [])), 'https' => !empty($kept['https'])];
}

/** $what is 'page' (with its id) or 'https'. */
function pb_launch_acknowledge(string $what, int $pageId = 0): void
{
    $ack = pb_launch_acknowledged();
    if ($what === 'https') {
        $ack['https'] = true;
    } elseif ($what === 'page' && $pageId > 0) {
        $ack['pages'] = array_values(array_unique([...$ack['pages'], $pageId]));
    } else {
        return;
    }
    pb_set_option('launch_ack', json_encode($ack));
}

/**
 * What the panel should say before (or while) the site is open to everybody: ['pages' => [id => title] with the published pages that still have text of the example
 * site, 'https' => true when nothing makes the site open only over https]. Empty unless the site is live: while it is under construction there is nothing to warn about yet.
 */
function pb_launch_notices(): array
{
    $none = ['pages' => [], 'https' => false];
    if (pb_site_mode() !== 'live') {
        return $none;
    }
    $ack = pb_launch_acknowledged();
    $pages = [];
    foreach (pb_db()->query('SELECT id, title, data, seo_description FROM ' . pb_table('pages') . " WHERE status = 'published' ORDER BY id")->fetchAll() as $page) {
        if (!in_array((int) $page['id'], $ack['pages'], true) && pb_page_has_sample_text($page)) {
            $pages[(int) $page['id']] = $page['title'];
        }
    }
    return ['pages' => $pages, 'https' => !$ack['https'] && !pb_https_forced()];
}
