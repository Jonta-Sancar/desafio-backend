@php
    $objectives = [
        'Simular a geracao de PIX via subadquirentes configuradas.',
        'Processar notificacoes internas (webhooks) para confirmar pagamentos.',
        'Registrar e acompanhar pedidos de saque.',
        'Permitir que novas subadquirentes sejam adicionadas rapidamente.'
    ];

    $routes = [
        [
            'title' => 'Gerar PIX',
            'method' => 'POST',
            'endpoint' => '/api/pix',
            'details' => 'Envia o payload para a subadquirente do usuario e agenda a confirmacao via webhook simulado.'
        ],
        [
            'title' => 'Realizar Saque',
            'method' => 'POST',
            'endpoint' => '/api/withdraw',
            'details' => 'Registra o saque e executa fluxo semelhante ao do PIX, garantindo atualizacao posterior via evento.'
        ],
    ];

    $pixStatuses = [
        'PENDING' => 'Pix criado aguardando pagamento.',
        'PROCESSING' => 'Pix aguardando confirmacao.',
        'CONFIRMED' => 'Pagamento confirmado pela subadquirente.',
        'PAID' => 'Pagamento concluido com sucesso.',
        'CANCELLED' => 'Cancelado pela subadquirente.',
        'FAILED' => 'Falha na autorizacao.'
    ];

    $withdrawStatuses = [
        'PENDING' => 'Saque criado aguardando processamento.',
        'PROCESSING' => 'Saque em processamento.',
        'SUCCESS' => 'Saque realizado com sucesso.',
        'DONE' => 'Equivalente a sucesso para SubadqB.',
        'CANCELLED' => 'Cancelado pela subadquirente.',
        'FAILED' => 'Falha na liquidacao.'
    ];

    $subadquirentes = [
        [
            'name' => 'SubadqA',
            'doc' => 'https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p',
            'base' => 'https://0acdeaee-1729-4d55-80eb-d54a125e5e18.mock.pstmn.io'
        ],
        [
            'name' => 'SubadqB',
            'doc' => 'https://documenter.getpostman.com/view/49994027/2sB3WvMJD7',
            'base' => 'https://ef8513c8-fd99-4081-8963-573cd135e133.mock.pstmn.io'
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Pagamentos - Desafio Backend</title>
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
            font-size: 1.15rem;
            max-width: 800px;
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
            margin-bottom: 0.5rem;
            font-size: 1.15rem;
        }

        .card p {
            margin: 0;
            color: rgba(255,255,255,0.85);
        }

        .objective-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.75rem;
        }

        .objective-list li {
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
            background: rgba(0,0,0,0.3);
            border-radius: 0.5rem;
            padding-block: 0.75rem;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .status-grid article {
            border-radius: 0.75rem;
            background: rgba(0,0,0,0.3);
            padding: 1rem;
        }

        .status-grid h4 {
            margin: 0 0 0.75rem;
            font-size: 1rem;
            color: rgba(255,255,255,0.85);
        }

        .status-grid ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .status-grid li {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .status-grid span {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            color: var(--primary);
        }

        .sub-card {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
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
                <h1>Desafio Super Pagamentos - Backend</h1>
                <p>
                    Mantenha o ecossistema preparado para integrar diversas subadquirentes, simulando fluxos
                    de PIX e saques com webhooks internos. Esta pagina resume o desafio descrito no README,
                    agora com uma experiencia visual alinhada ao estilo Laravel e a paleta Super.
                </p>
            </div>
            <div class="badges">
                <span class="badge">Laravel 11</span>
                <span class="badge">Multi subadquirente</span>
                <span class="badge">Webhooks simulados</span>
                <span class="badge">Fila + Jobs</span>
            </div>
        </header>
        <main>
            <section class="section">
                <h2>Objetivos principais</h2>
                <ul class="objective-list">
                    @foreach ($objectives as $objective)
                        <li>{{ $objective }}</li>
                    @endforeach
                </ul>
            </section>

            <section class="section">
                <h2>Fluxo da API</h2>
                <div class="card-grid">
                    @foreach ($routes as $route)
                        <article class="card">
                            <h3>{{ $route['title'] }}</h3>
                            <p><strong>{{ $route['method'] }}</strong> {{ $route['endpoint'] }}</p>
                            <p>{{ $route['details'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="section">
                <h2>Webhooks e status possiveis</h2>
                <div class="status-grid">
                    <article>
                        <h4>PIX</h4>
                        <ul>
                            @foreach ($pixStatuses as $status => $desc)
                                <li><span>{{ $status }}</span> - {{ $desc }}</li>
                            @endforeach
                        </ul>
                    </article>
                    <article>
                        <h4>Saques</h4>
                        <ul>
                            @foreach ($withdrawStatuses as $status => $desc)
                                <li><span>{{ $status }}</span> - {{ $desc }}</li>
                            @endforeach
                        </ul>
                    </article>
                </div>
            </section>

            <section class="section">
                <h2>Subadquirentes disponiveis</h2>
                <div class="card-grid">
                    @foreach ($subadquirentes as $sub)
                        <article class="card sub-card">
                            <h3>{{ $sub['name'] }}</h3>
                            <p>Base URL<br><strong>{{ $sub['base'] }}</strong></p>
                            <p><a href="{{ $sub['doc'] }}" target="_blank" rel="noreferrer">Documentacao Postman -></a></p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="section">
                <h2>Como experimentar rapidamente</h2>
                <div class="card-grid">
                    <article class="card">
                        <h3>Comandos principais</h3>
                        <div class="code-block">
                            composer install<br>
                            cp .env.example .env<br>
                            php artisan key:generate<br>
                            php artisan migrate --seed<br>
                            php artisan serve
                        </div>
                    </article>
                    <article class="card">
                        <h3>Requisicao de PIX</h3>
                        <div class="code-block">
                            curl -X POST http://localhost:8000/api/pix \<br>
                            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                            &nbsp;&nbsp;-d '{"amount":125.50,"user_id":1,"subadq":"subadqA"}'
                        </div>
                        <p>O webhook simulado confirma o pagamento em poucos segundos.</p>
                    </article>
                </div>
            </section>
        </main>
        <footer>
            Super Pagamentos - {{ now()->year }} - Construido com Laravel
        </footer>
    </div>
</body>
</html>
