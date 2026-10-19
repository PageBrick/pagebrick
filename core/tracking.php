<?php
// Google Analytics 4 and the Meta pixel, from Settings → Tracking. The owner gives only the codes (G-… and the pixel's number); PageBrick writes the official
// snippet itself, so there is nothing to paste wrong and no theme to change: pb_head() and pb_footer(), which every theme already calls, print it. A site that
// has no code saved prints exactly what it always printed.
//
// By default the visitor is asked first (a plain "Accept / Decline" notice, remembered in the browser); the scripts of Google and Meta are loaded only after
// "Accept". Nobody signed in to the panel is ever counted, and nothing is counted unless the site is live.

const PB_TRACK_EVENTS = ['lead'];

/** The codes the owner saved: ['ga4' => 'G-…' or '', 'meta' => '123…' or '']. */
function pb_tracking_ids(): array
{
    return ['ga4' => (string) pb_option('track_ga4', ''), 'meta' => (string) pb_option('track_meta', '')];
}

/** True when visitors are asked before the tools load (the default): the switch in Settings → Tracking. */
function pb_tracking_consent_required(): bool
{
    return pb_option('track_consent', '1') !== '0';
}

/** Whether this very page view is counted: a code is saved, the site is live, nobody is signed in to the panel, and it is not a preview. */
function pb_tracking_active(): bool
{
    $ids = pb_tracking_ids();
    return ($ids['ga4'] !== '' || $ids['meta'] !== '') && pb_site_mode() === 'live' && !pb_current_user() && empty($GLOBALS['pb_current_page']['preview']);
}

/** Checks and saves the two codes and the switch; throws with a message to read. Empty means "off". */
function pb_tracking_save(string $ga4, string $meta, bool $consent): void
{
    $ga4 = strtoupper(trim($ga4));
    $meta = trim($meta);
    if ($ga4 !== '' && !preg_match('/^G-[A-Z0-9]{6,14}$/', $ga4)) {
        throw new InvalidArgumentException(match (true) {
            str_starts_with($ga4, 'UA-') => __('Esse código (UA-…) é do Google Analytics antigo, que já foi desligado. Use o código do Google Analytics 4, que começa com G-.'),
            str_starts_with($ga4, 'GTM-') => __('Esse código (GTM-…) é do Tag Manager, não do Google Analytics 4. Procure o código que começa com G-.'),
            default => __('O código do Google Analytics 4 começa com G- e tem letras e números, como G-AB12CD34EF.'),
        });
    }
    if ($meta !== '' && !preg_match('/^\d{8,20}$/', $meta)) {
        throw new InvalidArgumentException(__('O pixel da Meta é só um número, como 1234567890123456. Copie o número que aparece embaixo do nome do pixel.'));
    }
    pb_set_option('track_ga4', $ga4);
    pb_set_option('track_meta', $meta);
    pb_set_option('track_consent', $consent ? '1' : '0');
}

/**
 * Tells the page that something worth counting happened. Only 'lead' (somebody sent a message) exists; it becomes generate_lead in Google Analytics and
 * Lead in Meta. $once makes it count once per browser session for that token (a visitor who reloads the thank-you page is not counted twice). Does nothing
 * when the page is not counted, so a plugin can call it without checking.
 */
function pb_track(string $event, string $once = ''): void
{
    if (in_array($event, PB_TRACK_EVENTS, true)) {
        $GLOBALS['pb_track_events'][] = [$event, $once];
    }
}

/** The JavaScript that starts the tools: Google's loader and the Meta pixel, the way the two companies ask for it, plus pbTrack() for the events. */
function pb_tracking_script(): string
{
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $ids = pb_tracking_ids();
    return '(function(w,d){var ga=' . json_encode($ids['ga4'], $flags) . ',px=' . json_encode($ids['meta'], $flags) . ',q=[],on=false;'
        . 'function send(e){if(e==="lead"){if(w.gtag)w.gtag("event","generate_lead");if(w.fbq)w.fbq("track","Lead")}}'
        . 'w.pbTrack=function(e,once){if(once){try{if(w.sessionStorage.getItem("pb_once_"+once))return;w.sessionStorage.setItem("pb_once_"+once,"1")}catch(x){}}on?send(e):q.push(e)};'
        . 'w.pbTrackStart=function(){if(on)return;on=true;'
        . 'if(ga){w.dataLayer=w.dataLayer||[];w.gtag=function(){w.dataLayer.push(arguments)};var s=d.createElement("script");s.async=true;'
        . 's.src="https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(ga);d.head.appendChild(s);w.gtag("js",new Date());w.gtag("config",ga)}'
        . 'if(px){if(!w.fbq){var n=w.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!w._fbq)w._fbq=n;n.push=n;n.loaded=true;n.version="2.0";n.queue=[];'
        . 'var t=d.createElement("script");t.async=true;t.src="https://connect.facebook.net/en_US/fbevents.js";d.head.appendChild(t)}w.fbq("init",px);w.fbq("track","PageView")}'
        . 'q.splice(0).forEach(send)}})(window,document);';
}

/** What goes in <head> (called by pb_head()): the tools start right away, when visitors are not asked first. */
function pb_tracking_head(): string
{
    if (!pb_tracking_active() || pb_tracking_consent_required()) {
        return '';
    }
    return '<script>' . pb_tracking_script() . 'pbTrackStart();</script>' . "\n";
}

/** The tools' names for the notice: ['Google Analytics', 'Meta']. */
function pb_tracking_tools(): array
{
    $ids = pb_tracking_ids();
    return array_values(array_filter([$ids['ga4'] !== '' ? 'Google Analytics' : '', $ids['meta'] !== '' ? 'Meta' : '']));
}

/**
 * The notice that asks the visitor. A theme or plugin can replace it with the 'consent_html' filter (it receives the notice and the tools' names): the buttons
 * are any element with data-pb-consent="yes" or "no", and data-pb-consent="ask" anywhere on the page (a footer link) shows the notice again.
 */
function pb_consent_banner(): string
{
    $tools = pb_tracking_tools();
    $privacy = pb_page_by_slug(__('politica-de-privacidade'), pb_site_locale());
    $link = $privacy && $privacy['status'] === 'published' ? ' <a href="' . e(pb_page_url($privacy)) . '" style="color:#fff">' . e(__('Política de privacidade')) . '</a>' : '';
    $button = fn(string $value, string $label, string $style) => '<button type="button" data-pb-consent="' . $value . '" style="padding:.45rem 1rem;border:1px solid #fff;border-radius:4px;font:inherit;cursor:pointer;' . $style . '">' . e($label) . '</button>';
    $html = '<div id="pb-consent" role="region" aria-label="' . e(__('Cookies')) . '" style="display:none;position:fixed;inset:auto 0 0 0;z-index:9997;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5rem 1rem;'
        . 'padding:.9rem 1rem;background:#171923;color:#fff;font:15px/1.4 system-ui,sans-serif;text-align:center"><span>'
        . e(sprintf(__('Este site usa cookies (%s) para medir as visitas e os anúncios. Você aceita?'), implode(' + ', $tools))) . $link . '</span>'
        . $button('yes', __('Aceitar'), 'background:#fff;color:#171923') . $button('no', __('Recusar'), 'background:transparent;color:#fff') . '</div>';
    return pb_apply_filters('consent_html', $html, $tools);
}

/** What goes before </body> (called by pb_footer()): the notice and the tools, when visitors are asked first, and the events that happened on this page. */
function pb_tracking_footer(): string
{
    if (!pb_tracking_active()) {
        return '';
    }
    $out = '';
    if (pb_tracking_consent_required()) {
        $out .= pb_consent_banner() . '<script>' . pb_tracking_script()
            . '(function(w,d){var K="pb_consent",box=d.getElementById("pb-consent"),v=null;try{v=w.localStorage.getItem(K)}catch(x){}'
            . 'function show(){if(box)box.style.display="flex"}'
            . 'd.addEventListener("click",function(ev){var b=ev.target.closest?ev.target.closest("[data-pb-consent]"):null;if(!b)return;var c=b.getAttribute("data-pb-consent");'
            . 'if(c==="ask"){show();return}if(c!=="yes"&&c!=="no")return;try{w.localStorage.setItem(K,c)}catch(x){}if(box)box.style.display="none";if(c==="yes")w.pbTrackStart()});'
            . 'if(v==="yes")w.pbTrackStart();else if(v!=="no")show()})(window,document);</script>' . "\n";
    }
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    foreach ($GLOBALS['pb_track_events'] ?? [] as [$event, $once]) {
        $out .= '<script>window.pbTrack&&pbTrack(' . json_encode($event, $flags) . ',' . json_encode($once, $flags) . ');</script>' . "\n";
    }
    return $out;
}
