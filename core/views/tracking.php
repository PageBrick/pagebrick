<?php
/**
 * @var string|null $error
 * @var string      $ga4      the Google Analytics 4 code in the field
 * @var string      $meta     the Meta pixel number in the field
 * @var bool        $consent  the switch "ask the visitor first"
 */
$saved = pb_tracking_ids();
$any = $saved['ga4'] !== '' || $saved['meta'] !== '';
$privacy = $any ? pb_page_by_slug(__('politica-de-privacidade'), pb_site_locale()) : null;
?>
<form method="post" action="<?= e(pb_url('/admin/tracking')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('Rastreamento')) ?></h1>
        <p class="muted"><?= e(__('Coloque aqui os códigos do Google Analytics e da Meta para medir as visitas e os anúncios. É só o código: o PageBrick escreve o resto, e você não precisa mexer no tema.')) ?></p>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>

        <label for="ga4"><?= e(__('Google Analytics 4 (código de medição)')) ?></label>
        <input id="ga4" name="ga4" maxlength="20" autocomplete="off" spellcheck="false" placeholder="G-XXXXXXXXXX" value="<?= e($ga4) ?>">
        <p class="help"><?= e(__('No Google Analytics: Administrador → Fluxos de dados → escolha o site → "ID da métrica". Começa com G-. Deixe em branco para não usar.')) ?></p>

        <label for="meta"><?= e(__('Pixel da Meta (Facebook e Instagram)')) ?></label>
        <input id="meta" name="meta" maxlength="20" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="1234567890123456" value="<?= e($meta) ?>">
        <p class="help"><?= e(__('No Gerenciador de Eventos da Meta: Fontes de dados → escolha o pixel → o número que aparece embaixo do nome (só números). Deixe em branco para não usar.')) ?></p>

        <label class="switch"><input type="checkbox" name="consent" value="1"<?= $consent ? ' checked' : '' ?>>
            <?= e(__('Pedir o consentimento do visitante (recomendado)')) ?></label>
        <p class="help"><?= e(__('Mostra um aviso "Aceitar / Recusar". O Google e a Meta só começam a medir depois do "Aceitar", e a escolha fica guardada no navegador. Desligue só se o site já tem outra ferramenta de aviso de cookies.')) ?></p>
        <?php if ($any && !$consent): ?>
            <p class="flash warn"><?= e(__('Sem o aviso, o Google e a Meta gravam cookies nos visitantes sem perguntar, e leis como a LGPD costumam exigir o consentimento.')) ?></p>
        <?php endif ?>

        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>

<div class="card">
    <h2><?= e(__('Como funciona')) ?></h2>
    <ul>
        <li><?= e(__('Quem está logado no painel nunca é contado, para você não inflar os seus próprios números.')) ?></li>
        <li><?= e(__('Só conta com o site "No ar". Em construção ou em manutenção, nada é medido.')) ?></li>
        <li><?= e(__('Cada envio do formulário de contato conta como "lead" (generate_lead no Google, Lead na Meta). Isso precisa do plugin Formulário de contato 1.3.0 ou mais novo.')) ?></li>
    </ul>
    <?php if ($any): ?>
        <p class="flash warn"><?= e(__('Atualize a sua política de privacidade: o modelo que o PageBrick instala diz que o site não usa cookies, e isso deixa de ser verdade com esses códigos.')) ?>
            <?php if ($privacy): ?><a href="<?= e(pb_url('/admin/pages/edit?id=' . (int) $privacy['id'])) ?>"><?= e(__('Editar a política de privacidade')) ?></a><?php endif ?></p>
    <?php endif ?>
    <?php if ($any && pb_site_mode() !== 'live'): ?>
        <p class="flash warn"><?= e(__('O site não está "No ar" agora, então nada está sendo medido.')) ?></p>
    <?php endif ?>
</div>
