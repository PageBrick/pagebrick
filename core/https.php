<?php
// Making a site open only over https://. On Apache (cPanel and most shared hosting) that is a few lines in .htaccess; the button in
// Settings → Address and languages puts them there and takes them out, so nobody has to edit the file by hand after getting the SSL certificate.

const PB_HTTPS_BEGIN = '# BEGIN PageBrick HTTPS';
const PB_HTTPS_END = '# END PageBrick HTTPS';

/** The .htaccess of the site (tests point it at a scratch copy). */
function pb_htaccess_file(): string
{
    return $GLOBALS['pb_config']['htaccess_file'] ?? PB_ROOT . '/.htaccess';
}

/**
 * The rules, between two marker lines so they can be found and taken out again. Nothing is redirected when the visit already came over https, nor when a
 * proxy or CDN in front of the site says it did (X-Forwarded-Proto: with a "flexible" SSL of such a service that would be a loop), nor the certificate
 * authority's checks (.well-known), which the renewal of the certificate needs on plain http.
 */
function pb_https_block(): string
{
    return PB_HTTPS_BEGIN . " (made by Settings, Address and languages: take it out there)\n"
        . "<IfModule mod_rewrite.c>\n"
        . "    RewriteEngine On\n"
        . "    RewriteCond %{HTTPS} !=on\n"
        . "    RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]\n"
        . "    RewriteCond %{REQUEST_URI} !^/\\.well-known/\n"
        . "    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n"
        . "</IfModule>\n"
        . PB_HTTPS_END . "\n";
}

/** True when the .htaccess has the block above. */
function pb_https_forced(): bool
{
    $file = @file_get_contents(pb_htaccess_file());
    return is_string($file) && str_contains($file, PB_HTTPS_BEGIN);
}

/** Whether this process can change the .htaccess (or create it). */
function pb_https_writable(): bool
{
    $file = pb_htaccess_file();
    return is_file($file) ? is_writable($file) : is_writable(dirname($file));
}

/** True when the .htaccess has the block that starts with $begin. */
function pb_htaccess_has(string $begin): bool
{
    $file = @file_get_contents(pb_htaccess_file());
    return is_string($file) && str_contains($file, $begin);
}

/**
 * Puts a block (its first line is $begin, its last is $end) in the .htaccess, at the top or at the end ($on), or takes it out. The file as it was is kept in the
 * backups folder (closed to the web) first. Throws $unwritable (a message to read) when the file can't be written.
 */
function pb_htaccess_block(string $begin, string $end, string $block, bool $on, bool $top, string $unwritable): void
{
    $file = pb_htaccess_file();
    $current = is_file($file) ? (string) file_get_contents($file) : '';
    // a block at the end took a blank line with it when it went in: the same line goes out
    $without = (string) preg_replace('~' . ($top ? '' : "(?:\r?\n)?") . preg_quote($begin, '~') . '.*?' . preg_quote($end, '~') . "[ \t]*\r?\n?~s", '', $current);
    $next = !$on ? $without : ($top ? $block . $without : rtrim($without, "\r\n") . ($without === '' ? '' : "\n\n") . $block);
    if ($next === $current) {
        return;
    }
    if (!pb_https_writable()) {
        throw new InvalidArgumentException($unwritable);
    }
    if ($current !== '') {
        $dir = pb_backups_dir();
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents("$dir/htaccess_" . date('YmdHis') . '.txt', $current);
        foreach (array_slice(array_reverse(glob("$dir/htaccess_*.txt") ?: []), 5) as $old) {
            @unlink($old);
        }
    }
    if (file_put_contents($file, $next, LOCK_EX) === false) {
        throw new InvalidArgumentException($unwritable);
    }
}

/** Puts the https rules at the top of the .htaccess ($on) or takes them out. */
function pb_https_set(bool $on): void
{
    pb_htaccess_block(PB_HTTPS_BEGIN, PB_HTTPS_END, pb_https_block(), $on, true,
        __('O PageBrick não conseguiu escrever no arquivo .htaccess. Peça à hospedagem para liberar a escrita nele, ou cole as linhas abaixo no começo do arquivo, no gerenciador de arquivos.'));
}

/**
 * The button. $httpsWorks: the browser of the person opened the https:// address of the site without complaint (a certificate that is not there would lock
 * everybody out, so a visit over plain http must prove it first). Returns the sentence to show.
 */
function pb_https_apply(bool $on, bool $httpsWorks): string
{
    if ($on && !pb_is_https() && !$httpsWorks) {
        throw new InvalidArgumentException(__('Não foi possível confirmar que o https:// funciona neste endereço. Instale o certificado SSL do domínio (no cPanel: SSL/TLS Status, Run AutoSSL), espere o cadeado aparecer e tente de novo.'));
    }
    pb_https_set($on);
    $siteUrl = (string) pb_option('site_url', '');
    if ($on && str_starts_with($siteUrl, 'http://')) {
        pb_set_option('site_url', 'https://' . substr($siteUrl, 7)); // the official address says https:// too: the sitemap, the e-mails and Google follow it
    }
    return $on ? __('Pronto: o site agora abre sempre por https://.') : __('Desligado: o site volta a abrir também por http://.');
}
