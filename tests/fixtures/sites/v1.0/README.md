# Site feito com o PageBrick 1.0 — CONGELADO

Este tema (`themes/agencia`) e este plugin (`plugins/agencia-extras`) representam um site que uma agência
construiu em cima do PageBrick 1.0, usando boa parte das funções públicas (`core/api.php`).

`tests/CompatibilityTest.php` instala os dois na versão atual do PageBrick e confere se o site continua funcionando.
É a garantia de que uma atualização pelo painel não quebra sites já desenvolvidos.

**Nunca edite estes arquivos.** Se uma versão nova do PageBrick faz este site quebrar, o erro está no PageBrick, não aqui.
Para cobrir recursos de versões futuras, crie uma nova pasta (por exemplo `sites/v1.1/`) — e mantenha esta.
O teste confere a impressão digital (SHA-256) destes arquivos para evitar edições por engano.
