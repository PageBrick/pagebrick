<?php
// The standard content contract: page types, site settings and menus that every theme displays.
// Because content follows these definitions (and not the theme's), switching themes keeps everything.
// A theme may ADD fields to these templates, or whole new page types, in its theme.php; it can't remove or change these.
//
// Templates every theme must have files for: templates/home.php, page.php, services.php, contact.php.

const PB_STANDARD_TEMPLATES = ['home', 'page', 'services', 'contact'];

function pb_standard_templates(): array
{
    $cta = [
        'type' => 'group', 'label' => __('Chamada final'), 'toggle' => true,
        'help' => __('Uma faixa no fim da página convidando o visitante a entrar em contato.'),
        'fields' => [
            'title' => ['type' => 'text', 'label' => __('Título')],
            'text' => ['type' => 'textarea', 'label' => __('Texto')],
            'button_label' => ['type' => 'text', 'label' => __('Texto do botão')],
            'button_link' => ['type' => 'link', 'label' => __('O botão leva para')],
        ],
    ];
    $services = [
        'type' => 'list', 'label' => __('Serviços'), 'item_label' => __('Serviço'), 'add_label' => __('Adicionar serviço'),
        'fields' => [
            'title' => ['type' => 'text', 'label' => __('Nome do serviço')],
            'text' => ['type' => 'textarea', 'label' => __('Explicação curta')],
            'image' => ['type' => 'image', 'label' => __('Foto (opcional)')],
        ],
    ];
    return [
        'home' => ['label' => __('Página inicial'), 'fields' => [
            'hero' => ['type' => 'group', 'label' => __('Destaque (topo da página)'), 'toggle' => true, 'fields' => [
                'title' => ['type' => 'text', 'label' => __('Frase principal'), 'help' => __('O que sua empresa faz, em uma frase.')],
                'text' => ['type' => 'textarea', 'label' => __('Texto de apoio')],
                'button_label' => ['type' => 'text', 'label' => __('Texto do botão')],
                'button_link' => ['type' => 'link', 'label' => __('O botão leva para')],
                'image' => ['type' => 'image', 'label' => __('Foto'), 'help' => __('Sem foto, o destaque ocupa a largura toda.')],
            ]],
            'about' => ['type' => 'group', 'label' => __('Quem somos'), 'toggle' => true, 'fields' => [
                'title' => ['type' => 'text', 'label' => __('Título')],
                'text' => ['type' => 'richtext', 'label' => __('Texto')],
                'image' => ['type' => 'image', 'label' => __('Foto')],
            ]],
            'services' => ['type' => 'group', 'label' => __('Serviços'), 'toggle' => true, 'fields' => [
                'title' => ['type' => 'text', 'label' => __('Título')],
                'intro' => ['type' => 'textarea', 'label' => __('Introdução')],
                'items' => $services,
                'link_label' => ['type' => 'text', 'label' => __('Texto do link no fim da seção')],
                'link' => ['type' => 'link', 'label' => __('O link leva para')],
            ]],
            'numbers' => ['type' => 'group', 'label' => __('Números'), 'toggle' => true,
                'help' => __('Use só números verdadeiros, como anos de mercado ou clientes atendidos.'), 'fields' => [
                    'title' => ['type' => 'text', 'label' => __('Título')],
                    'items' => ['type' => 'list', 'label' => __('Números'), 'item_label' => __('Número'), 'add_label' => __('Adicionar número'), 'fields' => [
                        'value' => ['type' => 'text', 'label' => __('Número'), 'help' => __('Por exemplo: 15, 300+ ou 4,9')],
                        'label' => ['type' => 'text', 'label' => __('O que ele significa')],
                    ]],
                ]],
            'testimonials' => ['type' => 'group', 'label' => __('Depoimentos'), 'toggle' => true,
                'help' => __('Use só depoimentos reais, com autorização de quem escreveu.'), 'fields' => [
                    'title' => ['type' => 'text', 'label' => __('Título')],
                    'items' => ['type' => 'list', 'label' => __('Depoimentos'), 'item_label' => __('Depoimento'), 'add_label' => __('Adicionar depoimento'), 'fields' => [
                        'quote' => ['type' => 'textarea', 'label' => __('O que a pessoa disse')],
                        'name' => ['type' => 'text', 'label' => __('Nome')],
                        'role' => ['type' => 'text', 'label' => __('Empresa, cargo ou cidade')],
                    ]],
                ]],
            'cta' => $cta,
        ]],
        'page' => ['label' => __('Página simples'), 'fields' => [
            'intro' => ['type' => 'textarea', 'label' => __('Introdução'), 'help' => __('Aparece em destaque logo abaixo do título.')],
            'image' => ['type' => 'image', 'label' => __('Foto')],
            'body' => ['type' => 'richtext', 'label' => __('Texto')],
        ]],
        'services' => ['label' => __('Serviços'), 'fields' => [
            'intro' => ['type' => 'textarea', 'label' => __('Introdução')],
            'items' => $services,
            'cta' => $cta,
        ]],
        'contact' => ['label' => __('Contato'), 'fields' => [
            'intro' => ['type' => 'textarea', 'label' => __('Introdução'), 'help' => __('Os dados de contato vêm de "Aparência e contato".')],
            'body' => ['type' => 'richtext', 'label' => __('Texto adicional')],
        ]],
    ];
}

function pb_standard_settings(): array
{
    return [
        'identity' => ['type' => 'group', 'label' => __('Identidade visual'), 'fields' => [
            'logo' => ['type' => 'image', 'label' => __('Logo'), 'help' => __('De preferência PNG com fundo transparente. Sem logo, o nome do site aparece no lugar.')],
            'icon' => ['type' => 'image', 'label' => __('Ícone da aba do navegador'), 'help' => __('Uma imagem quadrada, como o símbolo do seu logo.')],
            'color' => ['type' => 'color', 'label' => __('Cor principal'), 'default' => '#d24e2b', 'help' => __('Usada nos botões, links e destaques. O texto em cima dela se ajusta sozinho para continuar legível.')],
            'fonts' => ['type' => 'select', 'label' => __('Estilo das letras'), 'default' => 'sobria', 'options' => [
                'sobria' => __('Sóbria: direta e profissional'),
                'elegante' => __('Elegante: títulos com serifa'),
                'acolhedora' => __('Acolhedora: arredondada e simpática'),
            ]],
            'share_image' => ['type' => 'image', 'label' => __('Imagem para compartilhamento'), 'help' => __('Aparece quando alguém envia o link do site no WhatsApp ou nas redes. Tamanho ideal: 1200 × 630.')],
        ]],
        'contact' => ['type' => 'group', 'label' => __('Contato'), 'help' => __('Aparece no topo, no rodapé e na página de contato. O que ficar em branco não aparece.'), 'fields' => [
            'whatsapp' => ['type' => 'tel', 'label' => __('WhatsApp'), 'help' => __('Com DDD, por exemplo (19) 99999-9999. Cria o botão "WhatsApp".')],
            'whatsapp_message' => ['type' => 'text', 'label' => __('Mensagem que já vem escrita no WhatsApp')],
            'phone' => ['type' => 'tel', 'label' => __('Telefone')],
            'email' => ['type' => 'email', 'label' => __('E-mail')],
            'address' => ['type' => 'textarea', 'label' => __('Endereço'), 'help' => __('Ganha um link "Ver no mapa" automaticamente.')],
            'hours' => ['type' => 'textarea', 'label' => __('Horário de atendimento')],
        ]],
        'social' => ['type' => 'group', 'label' => __('Redes sociais'), 'help' => __('Cole o endereço completo de cada perfil.'), 'fields' => [
            'instagram' => ['type' => 'url', 'label' => 'Instagram'],
            'facebook' => ['type' => 'url', 'label' => 'Facebook'],
            'linkedin' => ['type' => 'url', 'label' => 'LinkedIn'],
            'youtube' => ['type' => 'url', 'label' => 'YouTube'],
            'tiktok' => ['type' => 'url', 'label' => 'TikTok'],
        ]],
        'footer' => ['type' => 'group', 'label' => __('Rodapé'), 'fields' => [
            'text' => ['type' => 'textarea', 'label' => __('Texto do rodapé'), 'help' => __('Por exemplo: razão social e CNPJ.')],
            'credit' => ['type' => 'select', 'label' => __('Crédito "Feito com PageBrick"'), 'default' => 'show', 'options' => ['show' => __('Mostrar'), 'hide' => __('Ocultar')]],
        ]],
    ];
}

function pb_standard_menus(): array
{
    return ['main' => __('Menu principal (topo do site)'), 'footer' => __('Menu do rodapé')];
}

/**
 * Adds a theme's own definitions to the standard ones: extra fields on standard templates, extra templates,
 * settings and menus. Standard definitions always win, so a theme can't break the contract.
 */
function pb_merge_theme_definitions(array $own): array
{
    $templates = pb_standard_templates();
    foreach ($own['templates'] ?? [] as $name => $def) {
        $templates[$name] = isset($templates[$name])
            ? ['label' => $templates[$name]['label'], 'fields' => $templates[$name]['fields'] + ($def['fields'] ?? [])]
            : $def + ['label' => $name, 'fields' => []];
    }
    return [
        'templates' => $templates,
        'settings' => pb_standard_settings() + ($own['settings'] ?? []),
        'menus' => pb_standard_menus() + ($own['menus'] ?? []),
    ];
}

/**
 * The example site created by the installer, for any theme. Texts tell the owner what to write;
 * nothing pretends to be real. Photos are public domain (core/demo/CREDITS.md) and go to the media library.
 * Links point to pages by slug ('page:contato') and images to demo photos ('media:equipe').
 */
function pb_standard_demo(): array
{
    $instruction = __('Troque este texto em Páginas, no painel.');
    return [
        'media' => [
            'destaque' => ['file' => 'destaque.webp', 'alt' => __('Foto de exemplo: atendente trabalhando no balcão de uma loja')],
            'equipe' => ['file' => 'equipe.webp', 'alt' => __('Foto de exemplo: equipe reunida em uma mesa de trabalho')],
            'espaco' => ['file' => 'espaco.webp', 'alt' => __('Foto de exemplo: interior claro de um estabelecimento')],
            'servico-1' => ['file' => 'servico-planejamento.webp', 'alt' => __('Foto de exemplo: pessoa planejando um projeto no computador')],
            'servico-2' => ['file' => 'servico-execucao.webp', 'alt' => __('Foto de exemplo: mãos medindo uma peça de madeira')],
            'servico-3' => ['file' => 'servico-acompanhamento.webp', 'alt' => __('Foto de exemplo: reunião com anotações coloridas sobre a mesa')],
        ],
        'pages' => [
            ['title' => __('Início'), 'slug' => 'inicio', 'template' => 'home', 'home' => true,
                'seo_description' => __('Descreva sua empresa em uma ou duas frases. É o texto que aparece no Google.'),
                'data' => [
                    'hero' => [
                        'title' => __('Diga aqui, em uma frase, o que sua empresa faz'),
                        'text' => __('É o primeiro texto que o visitante lê. Conte para quem você trabalha e por que vale a pena falar com você.') . " $instruction",
                        'button_label' => __('Conheça nossos serviços'),
                        'button_link' => 'page:servicos',
                        'image' => 'media:destaque',
                    ],
                    'about' => [
                        'title' => __('Quem somos'),
                        'text' => '<p>' . __('Conte como a empresa começou, há quanto tempo está no mercado e o que ela valoriza. Duas ou três frases bastam.') . '</p><p>'
                            . __('As fotos desta página são exemplos. Uma foto real da equipe, da fachada ou do espaço de trabalho deixa o site mais confiável: para trocar, use o botão Escolher imagem.') . '</p>',
                        'image' => 'media:equipe',
                    ],
                    'services' => [
                        'title' => __('O que fazemos'),
                        'intro' => __('Liste os principais serviços, cada um com um nome curto e uma explicação de duas linhas.'),
                        'items' => [
                            ['title' => __('Primeiro serviço'), 'text' => __('Explique o que está incluído e para quem ele serve.'), 'image' => 'media:servico-1'],
                            ['title' => __('Segundo serviço'), 'text' => __('Use as palavras que o seu cliente usaria, sem termos técnicos.'), 'image' => 'media:servico-2'],
                            ['title' => __('Terceiro serviço'), 'text' => __('Tem mais serviços? Use "Adicionar serviço". Tem menos? Use "Remover".'), 'image' => 'media:servico-3'],
                        ],
                        'link_label' => __('Ver todos os serviços'),
                        'link' => 'page:servicos',
                    ],
                    'numbers' => [
                        '_visible' => '0',
                        'title' => __('Em números'),
                        'items' => [
                            ['value' => '00', 'label' => __('Troque por um número real, como anos de mercado')],
                            ['value' => '00', 'label' => __('Ou clientes atendidos, projetos entregues…')],
                        ],
                    ],
                    'testimonials' => [
                        '_visible' => '0',
                        'title' => __('O que dizem nossos clientes'),
                        'items' => [
                            ['quote' => __('Cole aqui um depoimento real, com autorização de quem escreveu. Depoimentos inventados afastam clientes.'), 'name' => __('Nome do cliente'), 'role' => __('Empresa ou cidade')],
                        ],
                    ],
                    'cta' => [
                        'title' => __('Vamos conversar?'),
                        'text' => __('Diga ao visitante qual é o próximo passo: pedir um orçamento, agendar uma visita ou tirar uma dúvida.'),
                        'button_label' => __('Fale com a gente'),
                        'button_link' => 'page:contato',
                    ],
                ]],
            ['title' => __('Sobre'), 'slug' => 'sobre', 'template' => 'page', 'data' => [
                'intro' => __('Um parágrafo de apresentação: quem é a empresa, onde está e quem ela atende.'),
                'image' => 'media:espaco',
                'body' => '<h2>' . __('Nossa história') . '</h2><p>' . __('Como e por que a empresa começou. Fatos simples convencem mais que adjetivos.') . '</p>'
                    . '<h2>' . __('Como trabalhamos') . '</h2><p>' . __('O que o cliente pode esperar: prazos, atendimento, cuidados. Se tiver certificações ou registros profissionais, cite aqui.') . '</p>'
                    . '<h2>' . __('Equipe') . '</h2><p>' . __('Quem são as pessoas por trás do trabalho. Nome, função e uma linha sobre cada uma já ajudam.') . '</p>',
            ]],
            ['title' => __('Serviços'), 'slug' => 'servicos', 'template' => 'services', 'data' => [
                'intro' => __('Apresente seus serviços com mais detalhes que na página inicial.'),
                'items' => [
                    ['title' => __('Primeiro serviço'), 'text' => __('O que está incluído, para quem é indicado e como funciona na prática.'), 'image' => 'media:servico-1'],
                    ['title' => __('Segundo serviço'), 'text' => __('Se houver etapas, prazos ou formas de contratação, explique aqui.'), 'image' => 'media:servico-2'],
                    ['title' => __('Terceiro serviço'), 'text' => __('Uma foto do serviço sendo feito ajuda o cliente a imaginar o resultado.'), 'image' => 'media:servico-3'],
                ],
                'cta' => [
                    'title' => __('Ficou com alguma dúvida?'),
                    'text' => __('Conte o que você precisa e respondemos com um orçamento.'),
                    'button_label' => __('Pedir orçamento'),
                    'button_link' => 'page:contato',
                ],
            ]],
            ['title' => __('Contato'), 'slug' => 'contato', 'template' => 'contact', 'data' => [
                'intro' => __('Diga como prefere ser procurado e em quanto tempo costuma responder.'),
            ]],
            ['title' => __('Política de privacidade'), 'slug' => 'politica-de-privacidade', 'template' => 'page', 'data' => [
                'intro' => __('Modelo para adaptar à sua empresa. Troque o que está entre colchetes e, se puder, peça a um advogado para revisar.'),
                'body' => '<h2>' . __('Quem cuida dos seus dados') . '</h2><p>'
                    . __('[Nome da empresa], inscrita no CNPJ [número], com sede em [endereço], é a responsável pelos dados pessoais coletados neste site, nos termos da Lei Geral de Proteção de Dados (Lei 13.709/2018).') . '</p>'
                    . '<h2>' . __('Quais dados coletamos') . '</h2><p>'
                    . __('Quando você usa o formulário de contato, recebemos o nome, o e-mail, o telefone (se informado) e a mensagem que você escreveu. Para proteger o formulário contra abusos, também registramos o endereço IP de quem envia.') . '</p>'
                    . '<h2>' . __('Para que usamos') . '</h2><p>'
                    . __('Usamos esses dados só para responder ao seu contato e, se você pedir, preparar um orçamento. Não vendemos nem alugamos seus dados.') . '</p>'
                    . '<h2>' . __('Com quem compartilhamos') . '</h2><p>'
                    . __('Os dados ficam armazenados na nossa hospedagem e passam pelo nosso serviço de e-mail. Não compartilhamos com mais ninguém, exceto quando a lei exigir.') . '</p>'
                    . '<h2>' . __('Por quanto tempo guardamos') . '</h2><p>'
                    . __('Guardamos as mensagens pelo tempo necessário para atender ao seu pedido, por no máximo [prazo, por exemplo 12 meses]. Depois disso, elas são apagadas.') . '</p>'
                    . '<h2>' . __('Cookies') . '</h2><p>'
                    . __('Este site não coloca cookies no navegador de quem o visita e não usa ferramentas de publicidade nem de rastreamento. [Se você instalar algo como o Google Analytics, atualize esta parte.]') . '</p>'
                    . '<h2>' . __('Seus direitos') . '</h2><p>'
                    . __('Você pode pedir a qualquer momento para ver, corrigir ou apagar seus dados, ou saber com quem eles foram compartilhados. Escreva para [e-mail de contato] e respondemos em até 15 dias.') . '</p>',
            ]],
        ],
        'menus' => [
            'main' => [
                ['label' => '', 'link' => 'page:inicio'],
                ['label' => '', 'link' => 'page:sobre'],
                ['label' => '', 'link' => 'page:servicos'],
                ['label' => '', 'link' => 'page:contato'],
            ],
            'footer' => [
                ['label' => '', 'link' => 'page:sobre'],
                ['label' => '', 'link' => 'page:contato'],
                ['label' => '', 'link' => 'page:politica-de-privacidade'],
            ],
        ],
        'settings' => [
            'identity' => ['color' => '#d24e2b', 'fonts' => 'sobria'],
            'contact' => ['whatsapp_message' => __('Olá! Vim pelo site e gostaria de mais informações.')],
            'footer' => ['credit' => 'show'],
        ],
    ];
}
