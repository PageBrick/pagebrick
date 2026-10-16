<?php
// Browser cache for the files of the site. Pictures, fonts, style sheets and scripts are the same every time somebody comes back, but a host that sends no
// Cache-Control makes the browser ask for them (or download them) again at every visit. A few lines in .htaccess fix that; a new installation already has them,
// and the card in Settings → Address and languages puts them in a site that was installed before (and takes them out again), like the https button does.

const PB_CACHE_BEGIN = '# BEGIN PageBrick cache';
const PB_CACHE_END = '# END PageBrick cache';

/**
 * The lines. A week for pictures, style sheets and scripts. A year for what has another address when it changes: files asked for with ?v=… (the panel's, the
 * theme's and the plugins' do, the number follows the file), the uploaded files (their names are random: a new picture is a new address) and the fonts.
 * Marked with environment variables by mod_rewrite (it can tell the ?v= from the kind of file), and sent by mod_headers: a host without either ignores them.
 */
function pb_cache_block(): string
{
    return <<<'HT'
# BEGIN PageBrick cache (made by Settings, Address and languages: take it out there)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule \.(avif|webp|jpe?g|png|gif|svg|ico|css|m?js)$ - [E=PB_CACHE_WEEK:1]
    RewriteCond %{QUERY_STRING} (^|&)v=[0-9]+(&|$)
    RewriteRule \.(avif|webp|jpe?g|png|gif|svg|ico|css|m?js)$ - [E=PB_CACHE_YEAR:1]
    RewriteRule \.woff2?$ - [E=PB_CACHE_YEAR:1]
    RewriteRule ^content/uploads/ - [E=PB_CACHE_YEAR:1]
</IfModule>
<IfModule mod_headers.c>
    Header set Cache-Control "public, max-age=604800" env=PB_CACHE_WEEK
    Header set Cache-Control "public, max-age=31536000, immutable" env=PB_CACHE_YEAR
</IfModule>
# END PageBrick cache

HT;
}

/** True when the .htaccess has the cache lines. */
function pb_cache_on(): bool
{
    return pb_htaccess_has(PB_CACHE_BEGIN);
}

/**
 * The status the site answers for one of its own static files, or null when it can't be asked (a site that is not on https, a host that does not let the site
 * call itself). A 5xx after the lines went in means the host does not accept them.
 */
function pb_cache_probe(): ?int
{
    try {
        return (int) pb_http(pb_absolute_url('core/assets/favicon.svg'), [], 6)['status'];
    } catch (Throwable) {
        return null;
    }
}

/**
 * The button: puts the lines at the end of the .htaccess ($on) or takes them out. A host that does not accept the lines would answer every page with an error,
 * so the site is asked once afterwards, and if it answers with one the lines go out again. Returns the sentence to show; throws with one to read.
 */
function pb_cache_apply(bool $on): string
{
    $unwritable = __('O PageBrick não conseguiu escrever no arquivo .htaccess. Peça à hospedagem para liberar a escrita nele, ou cole as linhas abaixo no fim do arquivo, no gerenciador de arquivos.');
    pb_htaccess_block(PB_CACHE_BEGIN, PB_CACHE_END, pb_cache_block(), $on, false, $unwritable);
    if ($on && ($status = pb_cache_probe()) !== null && $status >= 500) {
        pb_htaccess_block(PB_CACHE_BEGIN, PB_CACHE_END, pb_cache_block(), false, false, $unwritable);
        throw new InvalidArgumentException(__('A hospedagem não aceitou estas linhas (o site respondeu com erro) e o PageBrick as tirou: o arquivo ficou como estava.'));
    }
    return $on ? __('Pronto: as fotos, as fontes e os arquivos do site ficam guardados no navegador de quem volta.') : __('Desligado: o navegador volta a pedir os arquivos do site a cada visita.');
}
