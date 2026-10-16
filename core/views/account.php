<div class="card" id="foto">
    <h2><?= e(__('Foto')) ?></h2>
    <div class="photo-row">
        <div class="photo-now">
            <?php if ($photo = pb_avatar_url($user)): ?>
                <img class="avatar big" src="<?= e($photo) ?>" alt="" width="80" height="80">
                <form method="post" action="<?= e(pb_url('/admin/account/avatar')) ?>">
                    <?= pb_csrf_field() ?>
                    <button type="submit" name="action" value="remove" class="secondary"><?= e(__('Remover foto')) ?></button>
                </form>
            <?php else: ?>
                <span class="avatar big" aria-hidden="true" data-initials="<?= e(mb_strtoupper(mb_substr(trim($user['name']), 0, 1))) ?>"></span>
            <?php endif ?>
        </div>
        <form method="post" action="<?= e(pb_url('/admin/account/avatar')) ?>" enctype="multipart/form-data" data-crop>
            <?= pb_csrf_field() ?>
            <label for="avatar"><?= e(__('Sua foto aparece no topo do painel')) ?></label>
            <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <p class="help"><?= e(__('JPG, PNG, WebP ou GIF, até 5 MB. Depois de escolher, você ajusta a foto dentro do círculo; sem isso, o PageBrick corta no centro.')) ?></p>
            <div class="crop" data-crop-box hidden>
                <div class="crop-view" data-crop-view tabindex="0" role="group" aria-label="<?= e(__('Posição da foto: arraste, ou use as setas do teclado')) ?>"><img alt="" draggable="false"></div>
                <label for="avatar-zoom"><?= e(__('Zoom')) ?></label>
                <input id="avatar-zoom" type="range" min="1" max="4" step="0.01" value="1">
                <p class="help"><?= e(__('Arraste a foto para posicioná-la no círculo e use o zoom para aproximar. O que está dentro do círculo é o que será enviado.')) ?></p>
            </div>
            <button type="submit"><?= e(__('Enviar foto')) ?></button>
        </form>
    </div>
</div>

<div class="card">
    <h1><?= e(__('Minha conta')) ?></h1>
    <?php if ($error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(pb_url('/admin/account')) ?>">
        <?= pb_csrf_field() ?>
        <label for="name"><?= e(__('Nome')) ?></label>
        <input id="name" name="name" required maxlength="100" value="<?= e($user['name']) ?>">
        <label for="email"><?= e(__('E-mail')) ?></label>
        <input id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= e($user['email']) ?>">
        <label for="locale"><?= e(__('Idioma do painel')) ?></label>
        <select id="locale" name="locale">
            <?php foreach (PB_LOCALES as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= $code === (($user['locale'] ?? '') ?: pb_locale()) ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
        </select>
        <p class="help"><?= e(__('Vale só para você e não muda o site: o idioma que os visitantes veem fica em Configurações → Endereço e idiomas.')) ?></p>
        <label for="password"><?= e(__('Nova senha')) ?></label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password">
        <p class="help"><?= e(__('Deixe em branco para manter a senha atual.')) ?></p>
        <label for="current_password"><?= e(__('Senha atual (para confirmar)')) ?></label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </form>
</div>
