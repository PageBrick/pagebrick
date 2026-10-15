<?php
/**
 * @var string|null $error
 * @var array       $old     what was typed, when saving failed
 */
$siteUrl = $old['site_url'] ?? rtrim(pb_absolute_url(), '/');
$main = $old['locale'] ?? pb_site_locale();
$extra = $old['locales'] ?? array_slice(pb_site_locales(), 1);
?>
<form method="post" action="<?= e(pb_url('/admin/general')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('Endereço e idiomas')) ?></h1>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <label for="site_url"><?= e(__('Endereço do site')) ?></label>
        <input id="site_url" name="site_url" type="url" required maxlength="190" value="<?= e($siteUrl) ?>">
        <p class="help"><?= e(__('O endereço oficial, usado pelo Google, no mapa do site e nos e-mails. Com o cadeado de segurança no navegador, use https://.')) ?></p>
        <?php if (pb_is_https() && str_starts_with(pb_absolute_url(), 'http://')): ?>
            <p class="flash error"><?= e(__('O site já abre com https://, mas o endereço oficial ainda começa com http://. Troque para https:// e salve.')) ?></p>
        <?php endif ?>
        <label for="locale"><?= e(__('Idioma do site')) ?></label>
        <select id="locale" name="locale">
            <?php foreach (PB_LOCALES as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= $code === $main ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
        </select>
        <p class="help"><?= e(__('Muda os textos fixos do site (menus, botões, formulário) e o idioma informado ao Google. O texto das suas páginas não é traduzido automaticamente.')) ?></p>
        <fieldset class="categories">
            <legend><?= e(__('Outros idiomas do site')) ?></legend>
            <?php foreach (PB_LOCALES as $code => $name): if ($code === pb_site_locale()) continue; ?>
                <label class="switch"><input type="checkbox" name="locales[]" value="<?= e($code) ?>"<?= in_array($code, $extra, true) ? ' checked' : '' ?>>
                    <?= e($name) ?> <span class="muted"><?= e(pb_locale_path($code, '/') === '/' ? '' : '(' . pb_absolute_url(pb_locale_path($code, '/')) . ')') ?></span></label>
            <?php endforeach ?>
            <p class="help"><?= e(__('Para oferecer o site em mais idiomas. Depois, em Páginas, traduza cada página; o tema mostra um seletor de idioma se tiver um.')) ?></p>
        </fieldset>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>

<?php
// Forcing https://: see core/https.php. Over plain http the browser itself first tries the https:// address (a certificate that is not there would lock everybody out).
$forced = pb_https_forced();
$secure = pb_is_https();
$check = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . pb_url('/admin/login');
?>
<form method="post" action="<?= e(pb_url('/admin/general/https')) ?>" id="https"<?= !$forced && !$secure ? ' data-https-check="' . e($check) . '" data-checking="' . e(__('Conferindo se o seu navegador abre o https:// deste endereço…')) . '" data-failed="' . e(__('O seu navegador não conseguiu abrir o https:// deste endereço. Provavelmente o certificado SSL ainda não foi instalado, ou vale para outro domínio. Instale o certificado (no cPanel: SSL/TLS Status, Run AutoSSL), espere o cadeado aparecer e tente de novo.')) . '"' : '' ?>>
    <?= pb_csrf_field() ?>
    <input type="hidden" name="action" value="<?= $forced ? 'off' : 'on' ?>">
    <input type="hidden" name="https_ok" value="">
    <div class="card">
        <h2><?= e(__('Conexão segura (https)')) ?></h2>
        <?php if ($forced): ?>
            <p class="flash ok"><?= e(__('Ligado: quem abrir o site com http:// é levado, sozinho, para o https://.')) ?></p>
            <button type="submit" class="secondary"><?= e(__('Desligar')) ?></button>
        <?php else: ?>
            <p><?= e($secure
                ? __('Você está acessando por https://, com o cadeado. Falta obrigar: hoje, quem digitar http:// ainda abre a versão sem cadeado.')
                : __('Você está acessando por http://, sem o cadeado. Se o seu domínio já tem certificado SSL, ligue abaixo.')) ?></p>
            <p class="help"><?= e(__('Use depois de instalar o certificado SSL do domínio (no cPanel: SSL/TLS Status, Run AutoSSL). O botão acrescenta algumas linhas no começo do arquivo .htaccess; o PageBrick guarda uma cópia do arquivo antes, e você pode desligar aqui quando quiser.')) ?></p>
            <?php if (!pb_https_writable()): ?>
                <p class="error" role="alert"><?= e(__('O PageBrick não conseguiu escrever no arquivo .htaccess. Peça à hospedagem para liberar a escrita nele, ou cole as linhas abaixo no começo do arquivo, no gerenciador de arquivos.')) ?></p>
                <textarea readonly rows="9" aria-label="<?= e(__('Linhas para o arquivo .htaccess')) ?>"><?= e(pb_https_block()) ?></textarea>
            <?php else: ?>
                <p class="help" data-https-note role="status" hidden></p>
                <button type="submit"><?= e(__('Obrigar https://')) ?></button>
            <?php endif ?>
        <?php endif ?>
    </div>
</form>
