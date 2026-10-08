<?php
/**
 * @var PbGroup $page
 * @var PbGroup $site
 * @var PbValue $title
 */
$contact = $site->contact;
$whatsapp = pb_whatsapp_url($contact->whatsapp->raw(), $contact->whatsapp_message->raw());
$hasContact = $whatsapp || !$contact->phone->isEmpty() || !$contact->email->isEmpty() || !$contact->address->isEmpty();
$form = pb_slot('contact'); // the contact form plugin puts its form here
$hint = !$hasContact && pb_is_logged_in();
// Two columns only when there is something to put beside the form.
$twoColumns = $form !== '' && ($hasContact || $hint || !$page->body->isEmpty());
?>
<header class="page-head">
    <div class="wrap<?= $twoColumns ? '' : ' narrow' ?>">
        <h1><?= $title ?></h1>
        <?php if (!$page->intro->isEmpty()): ?><p class="lead"><?= $page->intro ?></p><?php endif ?>
    </div>
</header>

<section class="section-tight">
    <div class="wrap<?= $twoColumns ? ' contact-grid' : ' narrow' ?>">
        <?php if ($hasContact || $hint || !$page->body->isEmpty()): ?>
        <div>
            <?php if ($hasContact): ?>
                <dl class="contact-list">
                    <?php if ($whatsapp): ?>
                        <div><dt><?= e(__('WhatsApp')) ?></dt><dd><a class="button button--large" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><?= e(__('Conversar no WhatsApp')) ?></a></dd></div>
                    <?php endif ?>
                    <?php if (!$contact->phone->isEmpty()): ?>
                        <div><dt><?= e(__('Telefone')) ?></dt><dd><a href="<?= $contact->phone ?>"><?= e($contact->phone->raw()) ?></a></dd></div>
                    <?php endif ?>
                    <?php if (!$contact->email->isEmpty()): ?>
                        <div><dt><?= e(__('E-mail')) ?></dt><dd><a href="<?= $contact->email ?>"><?= e($contact->email->raw()) ?></a></dd></div>
                    <?php endif ?>
                    <?php if (!$contact->address->isEmpty()): ?>
                        <div><dt><?= e(__('Endereço')) ?></dt><dd><?= $contact->address ?><br><a href="<?= e(pb_map_url($contact->address->raw())) ?>" target="_blank" rel="noopener"><?= e(__('Ver no mapa')) ?></a></dd></div>
                    <?php endif ?>
                    <?php if (!$contact->hours->isEmpty()): ?>
                        <div><dt><?= e(__('Horário')) ?></dt><dd><?= $contact->hours ?></dd></div>
                    <?php endif ?>
                </dl>
            <?php elseif ($hint): ?>
                <p class="owner-hint">
                    <?= e(__('Só você vê este aviso: os dados de contato ainda estão em branco.')) ?>
                    <a href="<?= e(pb_url('/admin/settings')) ?>"><?= e(__('Preencher em Aparência e contato')) ?></a>
                </p>
            <?php endif ?>
            <?php if (!$page->body->isEmpty()): ?>
                <div class="prose"><?= $page->body ?></div>
            <?php endif ?>
        </div>
        <?php endif ?>
        <?php if ($form !== ''): ?>
            <div><?= $form ?></div>
        <?php endif ?>
    </div>
</section>
