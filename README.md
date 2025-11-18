# Desafio Super Pagamentos – Backend

Este repositório contém a implementação proposta para o desafio de integração com múltiplas subadquirentes (PIX e saques). A aplicação foi construída em Laravel 11 e prioriza **padronização de contratos**, **extensibilidade** e **processamento assíncrono configurável**.

## Visão Geral

- **Contas e Movimentações** – cada usuário possui uma ou mais contas (`accounts`) vinculadas a um provedor (`subadq_a`, `subadq_b`). Toda transação gera um registro de movimentação (`movements`) que funciona como pivot para `pix_payments` ou `withdrawals`.
- **MovementService** – camada de orquestração que recebe um payload consistente da API, enriquece com dados da conta (merchant/seller, dados bancários, currency, expirations), invoca o serviço da subadquirente, persiste os registros específicos e normaliza a resposta antes de devolvê-la ao cliente.
- **Subadquirente Manager** – resolve dinamicamente a implementação correta com base no provider da conta. Cada serviço (`SubadqAService`, `SubadqBService`) conhece apenas seu formato específico, enquanto o resto da aplicação trabalha com o contrato genérico.
- **Webhooks simulados / reais** – o job `SimulateWebhookJob` dispara payloads semelhantes aos webhooks reais. Quando `SUBADQ_WEBHOOK_MODE=real`, basta configurar as URLs de `routes/api.php` para receber chamadas externas, pois o parser já está desacoplado.

## Arquitetura e Fluxos

```
Request API → MovementService → SubadquirenteManager → Subadq[A|B]Service
      ↓                                                ↑
  Normalização  ← Persistência em movements/pix/withdraws
```

1. **API recebe request padronizado** usando apenas `account_id`, `amount` e os dados específicos do tipo (payer para PIX, conta bancária para saque).
2. **MovementService**:
   - Valida e monta o payload “oficial” exigido pela subadquirente (merchant_id/seller_id vêm da conta; `expires_in` é definido no servidor via `SUBADQ_PIX_EXPIRES_IN`).
   - Cria o `Movement` e registra o modelo específico (`PixPayment` ou `Withdrawal`).
   - Salva metadados com o request normalizado e a resposta bruta do provedor.
   - Retorna à API um objeto padronizado contendo IDs, status, qrcode/location (PIX) ou dados bancários (saque).
3. **Webhooks**:
   - Em **modo simulation** (padrão), `SimulateWebhookJob` gera um payload mockado, reaproveita o mesmo parser real e atualiza os status dos modelos.
   - Em **modo real**, basta apontar as URLs `/api/webhooks/{provider}/pix|withdraw` nas configurações da subadquirente.

Essa abordagem garante que **novas subadquirentes** sejam adicionadas implementando apenas `SubadquirenteInterface` e registrando a classe em `config/subadquirentes.php`. O restante da aplicação permanece intocado.

## Modelo de Dados

- `accounts`: vincula usuário ao provider + credenciais (merchant/seller, dados bancários, etc.).
- `movements`: registro genérico de toda transação (tipo, status, payload original).
- `pix_payments`: detalhes específicos, inclusive pix_id e qrcode.
- `withdrawals`: detalhes de saque, transaction_id e metadados bancários.

## Contratos de API

### `POST /api/pix`
```jsonc
{
  "account_id": 1,
  "amount": 125.50,
  "order": "order_20251118_001", // opcional, gerado automaticamente se omitido
  "payer": {
    "name": "Fulano",
    "cpf_cnpj": "00000000000"
  }
}
```
> `merchant_id/seller_id`, `currency` e `expires_in` são preenchidos internamente.

Resposta:
```jsonc
{
  "movement_id": 10,
  "provider": "subadq_a",
  "pix_id": "PIX7F3C...",
  "transaction_id": "SP_SUBADQA_30a126bf-154f-4fd7-a328-42d03c23ed12",
  "amount": 125.5,
  "currency": "BRL",
  "order": "order_20251118_001",
  "payer": { "name": "Fulano", "cpf_cnpj": "00000000000" },
  "expires_in": 3600,
  "location": "https://subadqA.com/pix/loc/325",
  "qrcode": "...",
  "expires_at": "1763445181",
  "status": "PENDING"
}
```

### `POST /api/withdraw`
```jsonc
{
  "account_id": 1,
  "amount": 5000,
  "transaction_id": "SP54127d18-e44c-4929-98fd-cf7dce2cdff2", // opcional
  "bank_account": {
    "bank_code": "001",
    "agencia": "1234",
    "conta": "00012345",
    "type": "checking"
  }
}
```
> Caso `bank_account` seja omitido, os dados cadastrados na conta são utilizados automaticamente.

Resposta:
```jsonc
{
  "movement_id": 11,
  "provider": "subadq_b",
  "withdraw_id": "WD_ADQB_95109d5b-d499-40e2-bf43-85136b3ac4c3",
  "transaction_id": "SP54127d18-e44c-4929-98fd-cf7dce2cdff2",
  "amount": 5000,
  "bank_account": {
    "bank_code": "001",
    "agencia": "1234",
    "conta": "00012345",
    "type": "checking"
  },
  "status": "DONE"
}
```

## Executando o Projeto

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan queue:listen --tries=1   # segundo terminal (modo simulation)
```

### Seeds e Dados de Teste

O `DatabaseSeeder` cria automaticamente:

1. Usuário `test@example.com`;
2. Conta `subadq_a` com merchant/seller configurados;
3. Conta `subadq_b` com credenciais e dados bancários.

Use os `account_id` gerados nessas contas ao testar os endpoints.

## Testes

```bash
php artisan test
```

- `PaymentEndpointsTest` cobre criação de PIX/saque com as novas validações e o disparo do job de webhook.
- O contrato padronizado é garantido por asserts nos responses.

## Como Expandir

1. **Nova subadquirente**: crie uma classe que implemente `SubadquirenteInterface`, registre no array `providers` do `config/subadquirentes.php` e configure as contas que usarão o provider.
2. **Novos campos de conta**: basta adicionar ao JSON `settings`; o `MovementService` pode passar a considerar esses campos no processo de normalização.
3. **Webhook real**: defina `SUBADQ_WEBHOOK_MODE=real` e aponte os endpoints documentados para as URLs externas. O parser já retorna os mesmos objetos usados no modo simulado.
4. **Novos canais (ex: TEF)**: reutilize `movements` como pivot e crie um novo modelo/serviço específico mantendo o padrão de normalização.

---

Com essa arquitetura, garantimos separação clara entre **contrato público** (API), **regras de negócio** (MovementService + Models) e **integrações externas** (Subadquirentes). A substituição ou adição de provedores passa a ser uma alteração localizada, e o time consumidor da API recebe sempre o mesmo payload, independe da origem real da transação.*** End Patch
