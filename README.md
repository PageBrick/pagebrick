# PageBrick

CMS simples para sites institucionais, feito para agências: o desenvolvedor constrói o site em cima de uma estrutura pronta e o cliente só preenche o conteúdo.

> **Em construção:** etapa 1 de 5 (instalador, login e usuários). Planta do projeto em [docs/decisoes.md](docs/decisoes.md).

## Requisitos

PHP 8.2+ com `pdo_mysql`, MySQL 5.7+ ou MariaDB 10.4+ e Apache com `mod_rewrite` — ou seja, qualquer hospedagem com cPanel.

## Instalação

1. Envie os arquivos para a hospedagem.
2. Crie um banco de dados MySQL (no cPanel: "Bancos de dados MySQL").
3. Abra o endereço do site e preencha o instalador.

## Desenvolvimento local

```bash
docker compose up -d --build
```

Abra http://localhost:8080. No instalador, use servidor `db`, banco `pagebrick`, usuário `pagebrick` e senha `pagebrick`.

Contas de teste usadas no desenvolvimento local: `admin@pagebrick.test` (administrador) e `editor@pagebrick.test` (editor), ambas com a senha `pagebrick-local`.

Testes automáticos:

```bash
docker compose exec app composer install
docker compose exec app vendor/bin/phpunit
```

Para recomeçar do zero: apague `config.php` e rode `docker compose down -v`.

## Licença

[GPL-3.0-or-later](LICENSE). Criado pela [Alcateia Digital](https://alcateia.digital).
