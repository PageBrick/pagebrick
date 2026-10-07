<div class="card">
    <h1><?= e(__('Quase lá')) ?></h1>
    <p><?= e(__('O banco foi preparado e o administrador foi criado, mas a hospedagem não deixou gravar o arquivo de configuração.')) ?></p>
    <p><?= e(__('Crie um arquivo chamado config.php na pasta do PageBrick, cole o conteúdo abaixo e depois entre no painel.')) ?></p>
    <textarea rows="16" readonly><?= e($php) ?></textarea>
    <p><a href="<?= e(pb_url('/admin/login')) ?>"><?= e(__('Já criei o arquivo, entrar no painel')) ?></a></p>
</div>
