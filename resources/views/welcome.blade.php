@php
    $stack = [
        ['label' => 'Framework', 'value' => 'Laravel 11.x'],
        ['label' => 'Banco', 'value' => 'SQLite (padrão)'],
        ['label' => 'Filas', 'value' => 'Database queue + jobs'],
        ['label' => 'PHP', 'value' => '^8.2'],
    ];

    $implementation = [
        [
            'title' => 'Arquitetura contas + movimentacoes',
            'details' => 'A entidade Account amarra usuario e subadquirente. Movements registram qualquer transacao (PIX/saque) e servem de pivot para os registros especificos.'
        ],
        [
            'title' => 'Gerenciador de subadquirentes',
            'details' => 'SubadqAService e SubadqBService implementam SubadquirenteInterface e sao resolvidos dinamicamente via SubadquirenteManager conforme provider definido na conta.'
        ],
        [
            'title' => 'Webhooks configuraveis',
            'details' => 'Config/subadquirentes.php expõe SUBADQ_WEBHOOK_MODE. Em modo simulation, SimulateWebhookJob produz payloads reais e reusa o parser dos webhooks.'
        ],
        [
            'title' => 'Testes de endpoints',
            'details' => 'tests/Feature/PaymentEndpointsTest.php cobre criacao de movimento, registro especifico e despacho do job para /api/pix e /api/withdraw.'
        ],
    ];

    $endpoints = [
        [
            'method' => 'POST',
            'path' => '/api/pix',
            'request' => '{"account_id": 1, "amount": 150.75}',
            'response' => '{"id":1,"movement_id":10,"account_id":1,"pix_id":"PIX...","status":"PENDING","amount":"150.75"}'
        ],
        [
            'method' => 'POST',
            'path' => '/api/withdraw',
            'request' => '{"account_id": 1, "amount": 320.10}',
            'response' => '{"id":1,"movement_id":11,"account_id":1,"withdraw_id":"WD...","status":"PENDING"}'
        ],
    ];

    $webhookSteps = [
        'Controller valida account_id, cria Movement e define o provider responsavel.',
        'O service gera IDs externos simulados; PixPayment/Withdrawal recebem FK movement_id.',
        'SimulateWebhookJob (modo simulation) ou o endpoint /api/webhooks/{provider} acionam SubadquirenteInterface::process*.',
        'O parser normaliza status, atualiza o modelo especifico e o Movement, armazenando o payload em meta.',
    ];

    $models = [
        [
            'name' => 'accounts',
            'fields' => [
                'provider' => 'Identificador (subadq_a ou subadq_b).',
                'webhook_url' => 'URL configurada para ambiente real.',
                'webhook_secret' => 'Token para validar assinaturas.',
                'settings' => 'JSON com credenciais e preferencias.'
            ],
        ],
        [
            'name' => 'movements',
            'fields' => [
                'account_id' => 'FK para a conta responsavel.',
                'type' => 'PIX ou WITHDRAW.',
                'status' => 'CREATED/PENDING/CONFIRMED...',
                'payload' => 'Snapshot da requisicao.',
                'processed_at' => 'Marcado apos webhook.'
            ],
        ],
        [
            'name' => 'pix_payments',
            'fields' => [
                'movement_id' => 'Referencia direta ao movimento.',
                'account_id' => 'Reforco para consultas.',
                'pix_id' => 'Identificador externo.',
                'transaction_id' => 'Transaction reference do provider.',
                'meta' => 'Dados retornados pelo service.'
            ],
        ],
        [
            'name' => 'withdrawals',
            'fields' => [
                'movement_id' => 'FK para movement.',
                'account_id' => 'Conta responsavel.',
                'withdraw_id' => 'ID enviado ao provider.',
                'transaction_id' => 'Referencia complementar.',
                'meta' => 'Resposta e payloads de webhook.'
            ],
        ],
    ];

    $commands = [
        'composer install',
        'cp .env.example .env',
        'php artisan key:generate',
        'php artisan migrate --seed',
        'php artisan serve',
        'php artisan queue:listen --tries=1',
    ];

    $faq = [
        [
            'q' => 'Como alterno entre webhook simulado e real?',
            'a' => 'Defina SUBADQ_WEBHOOK_MODE=simulation (padrão) ou real. Em modo real nao dispararemos o job; use /api/webhooks/{provider}/pix|withdraw para receber notificacoes.'
        ],
        [
            'q' => 'Posso adicionar novas subadquirentes?',
            'a' => 'Sim. Registre a classe em config/subadquirentes.php e implemente os seis metodos do contrato. Movements continuarao funcionando sem ajuste.'
        ],
        [
            'q' => 'Onde ficam os testes?',
            'a' => 'tests/Feature/PaymentEndpointsTest.php cobre os fluxos principais e pode ser expandido para validacoes e webhooks.'
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Pagamentos - Documentacao do Modulo</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary: #0641FC;
            --secondary: #1B1C29;
            --tertiary: #000000;
            --quaternary: #FFFFFF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Figtree", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--secondary);
            color: var(--quaternary);
            line-height: 1.6;
        }

        a {
            color: var(--quaternary);
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: radial-gradient(circle at top, rgba(6,65,252,0.35), transparent), var(--secondary);
            padding: 4rem 1.5rem 2rem;
        }

        .hero {
            max-width: 1100px;
            margin: 0 auto;
            text-align: center;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 3.5rem);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.05rem;
            max-width: 820px;
            margin: 0 auto 1.5rem;
            color: rgba(255,255,255,0.85);
        }

        .badges {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.5rem;
        }

        .badge {
            border: 1px solid rgba(255,255,255,0.2);
            padding: .35rem .9rem;
            border-radius: 999px;
            font-size: .85rem;
            letter-spacing: .02em;
        }

        main {
            flex: 1;
            padding: 3rem 1.5rem 4rem;
            background: linear-gradient(180deg, rgba(6,65,252,0.08), transparent 40%),
                        radial-gradient(circle at 10% 20%, rgba(6,65,252,0.08), transparent 25%),
                        var(--secondary);
        }

        .section {
            max-width: 1200px;
            margin: 0 auto 2.75rem;
        }

        .section h2 {
            font-size: 1.5rem;
            letter-spacing: .02em;
            margin-bottom: 1rem;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }

        .card {
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 0.4rem;
            font-size: 1.15rem;
        }

        .card p {
            margin: 0;
            color: rgba(255,255,255,0.85);
        }

        .code-block {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            background: rgba(0,0,0,0.65);
            border-radius: 0.75rem;
            padding: 1rem;
            font-size: 0.9rem;
            overflow: auto;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .timeline {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.85rem;
        }

        .timeline li {
            position: relative;
            padding-left: 1.75rem;
        }

        .timeline li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.35rem;
            width: 0.8rem;
            height: 0.8rem;
            border-radius: 50%;
            border: 2px solid var(--primary);
        }

        .model-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .model-card {
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem;
            padding: 1.25rem;
        }

        .model-card ul {
            padding-left: 1rem;
            margin: 0;
        }

        .faq {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .faq article {
            background: rgba(0,0,0,0.35);
            border-radius: 0.9rem;
            padding: 1.25rem;
            border: 1px solid rgba(255,255,255,0.08);
        }

        footer {
            text-align: center;
            padding: 2rem 1.5rem;
            background: #000000;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }

        @media (max-width: 640px) {
            header {
                padding-top: 3rem;
            }
            .hero h1 {
                font-size: 2.25rem;
            }
            main {
                padding: 2rem 1rem 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <div class="hero">
                <h1>Documentacao do Modulo Super Pagamentos</h1>
                <p>
                    Esta pagina descreve a implementacao entregue para o desafio. Aqui voce encontra como
                    as rotas foram definidas, de que forma os webhooks sao simulados, quais modelos recebem os dados
                    e quais comandos permitem testar tudo rapidamente.
                </p>
            </div>
            <div class="badges">
                @foreach ($stack as $item)
                    <span class="badge">{{ $item['label'] }}: {{ $item['value'] }}</span>
                @endforeach
            </div>
        </header>
        <main>
            <section class="section">
                <h2>Visao geral da implementacao</h2>
                <div class="card-grid">
                    @foreach ($implementation as $item)
                        <article class="card">
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['details'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="section">
                <h2>Endpoints expostos</h2>
                <div class="card-grid">
                    @foreach ($endpoints as $endpoint)
                        <article class="card">
                            <h3>{{ $endpoint['method'] }} {{ $endpoint['path'] }}</h3>
                            <p>Request</p>
                            <div class="code-block">{{ $endpoint['request'] }}</div>
                            <p>Response (exemplo)</p>
                            <div class="code-block">{{ $endpoint['response'] }}</div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="section">
                <h2>Fluxo do webhook simulado</h2>
                <div class="card">
                    <ul class="timeline">
                        @foreach ($webhookSteps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="section">
                <h2>Modelos persistidos</h2>
                <div class="model-grid">
                    @foreach ($models as $model)
                        <article class="model-card">
                            <h3>{{ $model['name'] }}</h3>
                            <ul>
                                @foreach ($model['fields'] as $field => $desc)
                                    <li><strong>{{ $field }}</strong>: {{ $desc }}</li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="section">
                <h2>Execucao local</h2>
                <div class="card-grid">
                    <article class="card">
                        <h3>Setup e servidor</h3>
                        <div class="code-block">
                            @foreach ($commands as $command)
                                {{ $command }}<br>
                            @endforeach
                        </div>
                        <p>Execute queue:listen em um terminal separado para processar SimulateWebhookJob.</p>
                    </article>
                    <article class="card">
                        <h3>Testes automatizados</h3>
                        <div class="code-block">
                            php artisan test
                        </div>
                        <p>
                            PaymentEndpointsTest verifica criacao dos registros, status inicial e despacho do job.
                            Use como ponto de partida para cobrir validacoes adicionais.
                        </p>
                    </article>
                </div>
            </section>

            <section class="section">
                <h2>FAQ rapido</h2>
                <div class="faq">
                    @foreach ($faq as $item)
                        <article>
                            <h3>{{ $item['q'] }}</h3>
                            <p>{{ $item['a'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </main>
        <footer>
            Super Pagamentos - {{ now()->year }} - Documentacao do desafio backend
        </footer>
    </div>
</body>
</html>
