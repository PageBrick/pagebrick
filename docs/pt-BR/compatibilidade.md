# A promessa de compatibilidade

Agências criam sites reais para clientes reais no PageBrick. Clicar em **Atualizar** no painel nunca pode quebrar nenhum deles. Esta página explica o que é prometido, como isso é garantido e o que acontece em uma atualização.

[English](../en/compatibility.md) · **Português** · [Español](../es/compatibilidad.md)

## Com o que quem desenvolve temas e plugins pode contar

Dentro de uma mesma versão da API (`"api": 1` no `theme.json` / `plugin.json`):

1. **Nada na API pública é removido ou alterado.** A API pública é a lista em [core/api.php](../../core/api.php): funções, as classes de campo (`PbGroup`, `PbList`, `PbValue`), constantes e ganchos (hooks). Nenhuma função some, nenhum parâmetro é renomeado, reordenado ou passa a ser obrigatório, nenhum tipo de retorno muda, nenhum gancho deixa de ser disparado. Coisas novas podem ser adicionadas.
2. **O conteúdo padrão continua.** Os tipos de página, campos, configurações e menus de [core/standard.php](../../core/standard.php) fazem parte da API: nenhum é removido nem muda de tipo.
3. **O HTML que o seu site entrega não muda.** O que o núcleo imprime para você (`pb_head()`, menus, imagens, texto rico, escape) continua igual, byte por byte.
4. **Os seus arquivos nunca são tocados.** Uma atualização substitui só `core/`, `vendor/` e `index.php`. Tudo dentro de `content/` (temas, plugins, uploads), o `config.php` e o `.htaccess` ficam como estão.
5. **O banco de dados só cresce.** As migrações adicionam tabelas e colunas; nunca apagam nem renomeiam o que uma versão anterior usava.

Tudo o que não está listado em `core/api.php`, mesmo uma função que comece com `pb_`, é interno e pode mudar. A aparência e os textos do painel também podem mudar.

## Como ela é garantida

Toda versão precisa passar nestes testes automáticos ([tests/CompatibilityTest.php](../../tests/CompatibilityTest.php)):

| Garantia | Teste |
|---|---|
| A API cumpre a promessa | `core/api.php` está congelado em `tests/fixtures/api-v1.json`; o teste falha se algo congelado sumir ou mudar |
| Os sites feitos na 0.1 continuam funcionando | `tests/fixtures/sites/v0.1` é um site de agência feito na 0.1 (tema próprio com campos e tipos de página extras, plugin próprio com tabelas, rotas, ganchos e uma tela no painel, além de um plugin oficial). Ele nunca é editado, e toda versão precisa rodá-lo |
| O HTML e as respostas da API deles não mudam | `tests/fixtures/sites/v0.1-output` guarda as páginas e as respostas da API de conteúdo exatas desse site; um caractere alterado faz o teste falhar |
| Atualizações para as quais o site não está pronto são recusadas | veja abaixo |
| Atualizações que quebram o site são desfeitas | um plugin quebrado, um tema quebrado, um erro fatal durante a conferência e uma versão que nem consegue iniciar são todos simulados |

## O que acontece quando alguém clica em Atualizar

1. **Antes:** o painel confere a versão nova com o site. Se o PHP do servidor for antigo demais, ou se um plugin ativo ou o tema tiver sido feito para uma versão da API que a nova versão não suporta, a atualização é recusada e o painel diz qual é e o que fazer.
2. **O pacote:** baixado do catálogo oficial e conferido com a assinatura Ed25519 do projeto, então um arquivo adulterado ou trocado é recusado.
3. **Backup:** os `core/`, `vendor/` e `index.php` atuais são compactados em `content/backups/` (inacessível pela web).
4. **Troca:** cada pasta é trocada em um único passo, então os visitantes nunca veem meia atualização.
5. **Conferência:** a primeira requisição na versão nova renderiza, nos bastidores, todas as páginas publicadas, a página 404 e o sitemap. Se algo falhar, ou se um plugin tiver sido desligado, a versão anterior volta com os plugins e o tema exatamente como estavam, e o painel explica o que quebrou.
6. **Rede de segurança:** se a versão nova nem consegue iniciar (um erro fatal de PHP no núcleo), o `index.php` restaura o backup sozinho.
7. **Depois:** **Voltar para a versão x.y** continua disponível em **Sistema → Atualizações**.

## Se um dia vier a versão 2 da API

Ela só existiria para uma mudança que não pode ser feita adicionando. Uma versão que suporta as duas declara `"api": [1, 2]` no catálogo, e os sites atualizam normalmente. Uma versão que abandona a versão 1 é recusada por todo site que ainda tenha um tema ou plugin ativo da versão 1, com uma mensagem dizendo qual atualizar primeiro.

## Para quem contribui com o núcleo

- Para adicionar algo à API pública: adicione em `core/api.php` e depois rode `php tools/pagebrick.php api-snapshot`. O comando se recusa a congelar uma versão que quebre a promessa atual.
- Nunca edite `tests/fixtures/sites/v0.1` nem `v0.1-output`. Se uma mudança faz esses testes falharem, é a mudança que precisa ser corrigida. Para cobrir recursos de uma versão posterior, adicione um novo site congelado (`sites/v0.2/`) ao lado.
- As migrações só adicionam. Nunca use `DROP` nem `RENAME` em algo que uma versão anterior usava.
- Se uma mudança no HTML que o núcleo imprime for mesmo necessária (uma correção de segurança, por exemplo), ela é uma mudança visível para todos os sites: registre no [CHANGELOG.md](../../CHANGELOG.md) em "HTML changes" e depois gere o snapshot de novo, apagando o arquivo afetado em `v0.1-output` e rodando os testes.
