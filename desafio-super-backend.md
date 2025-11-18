# desafio-Super-backend
Teste para contratação de Backend para a Super

# 🧩 Desafio Técnico – Integração com Subadquirentes (Laravel)

## 🧠 Contexto

Você foi contratado para desenvolver um módulo de integração com **subadquirentes de pagamento** (gateways terceiros que processam PIX e saques).  
A aplicação deve permitir que **usuários diferentes** utilizem **subadquirentes diferentes**, de forma que o sistema suporte **multiadquirência**, onde cada usuario cadastrado poderá usar qualquer subadquirente, exemplo:


Cada **usuário** poderá estar vinculado a uma subadquirente diferente:

- Usuário A → usa **SubadqA**
- Usuário B → usa **SubadqA**
- Usuário C → usa **SubadqB**

Em produção, será possível trocar a subadquirente de cada usuário, mas **essa funcionalidade não precisa ser implementada neste desafio** — ela serve apenas para guiar o raciocínio da arquitetura.


Atualmente trabalhamos com duas subadquirentes:
- **SubadqA**
- **SubadqB**

Cada subadquirente oferece:
- **Geração de PIX**
- **Saque**
- **Webhook** de confirmação (PIX pago / saque concluído)

> ⚙️ No futuro, novas subadquirentes poderão ser integradas, portanto **a arquitetura deve ser extensível**.

---

## 🎯 Objetivo

Desenvolver uma aplicação **Laravel** que:

1. Permita simular a **geração de um PIX** via uma subadquirente.  
2. Simule o **recebimento do webhook** de confirmação do PIX.  
3. Permita simular um **saque** via uma subadquirente.  
4. Utilize uma estrutura de código que facilite a integração de **novas subadquirentes** no futuro.

---

## 🧱 Requisitos Técnicos

### Banco de Dados
Crie as tabelas necessárias para controlar:

- **Usuários**
- **PIX gerados**
- **Saques**
- **E o que mais for necessário**

---

### API / Fluxo principal

#### 🔹 Endpoint para gerar PIX
- **Método:** `POST`
- **Exemplo:** `/api/pix`
- Deve enviar o payload para a subadquirente configurada para o usuário.
- Após gerar o PIX, simular o **recebimento do webhook** (ex: chamada interna, job, ou fila) para atualizar o status do PIX.

#### 🔹 Endpoint para realizar saque
- **Método:** `POST`
- **Exemplo:** `/api/withdraw`
- Deve enviar o payload para a subadquirente e registrar o saque no sistema.

---

### ⚙️ Webhook simulado
Como o candidato **não terá uma URL pública**, o webhook deve ser **simulado internamente** após a requisição de geração de PIX ou saque.  
Isso pode ser feito de várias formas:
- Fique livre e a vontade para implementar como achar que é mais válido essa simulação, lembrando que a simulação trata de um cenário onde será recebido diversas requisições, sendo no mínimo 3 ou mais requisições por segundo no Pix e consequentemente nos Webhooks.

A intenção é avaliar o **fluxo que o candidato desenha** e como ele **estruturaria o processamento assíncrono**.

---
## 🧾 Webhooks — Estrutura de Exemplo

A seguir, seguem **exemplos de payloads** simulando notificações (webhooks) enviadas por duas subadquirentes diferentes.

Esses payloads devem ser processados pela aplicação após a criação do Pix ou Saque.

---

### 💸 Webhooks de Pix

#### 📍 Modelo 1 — SubadqA

```json
{
  "event": "pix_payment_confirmed",
  "transaction_id": "f1a2b3c4d5e6",
  "pix_id": "PIX123456789",
  "status": "CONFIRMED",
  "amount": 125.50,
  "payer_name": "João da Silva",
  "payer_cpf": "12345678900",
  "payment_date": "2025-11-13T14:25:00Z",
  "metadata": {
    "source": "SubadqA",
    "environment": "sandbox"
  }
}
```

### 📍 Modelo 2 - SubadqB
```json
{
  "type": "pix.status_update",
  "data": {
    "id": "PX987654321",
    "status": "PAID",
    "value": 250.00,
    "payer": {
      "name": "Maria Oliveira",
      "document": "98765432100"
    },
    "confirmed_at": "2025-11-13T14:40:00Z"
  },
  "signature": "d1c4b6f98eaa"
}
```

| Status      | Descrição                              |
| ----------- | -------------------------------------- |
| `PENDING`   | Pix criado, aguardando pagamento       |
| `PROCESSING`| Pix criado, aguardando pagamento       | 
| `CONFIRMED` | Pagamento confirmado                   |
| `PAID`      | Pagamento concluído com sucesso        |
| `CANCELLED` | Pagamento cancelado pela subadquirente |
| `FAILED`    | Erro no processamento do pagamento     |


### 💰 Webhooks de Saque

### 📍 Modelo 1 — SubadqA
```json
{
  "event": "withdraw_completed",
  "withdraw_id": "WD123456789",
  "transaction_id": "T987654321",
  "status": "SUCCESS",
  "amount": 500.00,
  "requested_at": "2025-11-13T13:10:00Z",
  "completed_at": "2025-11-13T13:12:30Z",
  "metadata": {
    "source": "SubadqA",
    "destination_bank": "Itaú"
  }
}
```

### 📍 Modelo 2 — SubadqB
```json
{
  "type": "withdraw.status_update",
  "data": {
    "id": "WDX54321",
    "status": "DONE",
    "amount": 850.00,
    "bank_account": {
      "bank": "Nubank",
      "agency": "0001",
      "account": "1234567-8"
    },
    "processed_at": "2025-11-13T13:45:10Z"
  },
  "signature": "aabbccddeeff112233"
}
```


| Status      | Descrição                               |
| ----------- | --------------------------------------- |
| `PENDING`   | Saque criado, aguardando processamento  |
| `SUCCESS`   | Saque realizado com sucesso             |
| `DONE`      | Saque concluído (equivalente a SUCCESS) |
| `FAILED`    | Falha no processamento do saque         |
| `CANCELLED` | Saque cancelado pela subadquirente      |
| `PROCESSING`| Saque criado, aguardando processamento  | 


## 🧩 Subadquirentes disponíveis (Mocks)

Os mocks foram criados no **Postman** e simulam as integrações com as subadquirentes.

| Subadquirente | Documentação | Base URL (Mock) |
|----------------|--------------|-----------------|
| **SubadqA** | [Ver documentação](https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p) | `https://0acdeaee-1729-4d55-80eb-d54a125e5e18.mock.pstmn.io` |
| **SubadqB** | [Ver documentação](https://documenter.getpostman.com/view/49994027/2sB3WvMJD7) | `https://ef8513c8-fd99-4081-8963-573cd135e133.mock.pstmn.io` |

---

### Rotas disponíveis

| Ação | Método | Endpoint | Header de Exemplo | 
|------|---------|-----------|------------------|
| **Gerar PIX (sucesso)** | `POST` | `/pix/create` | `x-mock-response-name: [SUCESSO_PIX] pix_create` | 
| **Gerar PIX (erro)** | `POST` | `/pix/create` | `x-mock-response-name: [ERRO_PIX] pix_create` | 
| **Saque (sucesso)** | `POST` | `/withdraw` | `x-mock-response-name: [SUCESSO_WD] withdraw` |
| **Saque (erro)** | `POST` | `/withdraw` | `x-mock-response-name: [ERROW_WD] withdraw` | 

> ⚠️ Todos os exemplos completos de request e response estão disponíveis nas documentações Postman acima.

---

## ✅ Entregáveis

O candidato deve entregar:

1. Um **repositório público** (GitHub ou GitLab) com o código-fonte da aplicação Laravel.  
2. Um **README** explicando:
   - Como executar o projeto
   - Estrutura e decisões técnicas adotadas  
3. Scripts de **migração do banco**.  
4. Exemplos de chamadas (via **cURL**, **Postman** ou **Swagger**).  

---

## 🧪 Critérios de Avaliação

| Critério | Peso |
|-----------|------|
| Organização e legibilidade do código | 🟩🟩🟩 |
| Arquitetura extensível e clara | 🟩🟩🟩 |
| Uso de boas práticas do Laravel | 🟩🟩 |
| Tratamento de erros e logs | 🟩🟩 |
| Funcionamento completo do fluxo | 🟩🟩🟩 |

---

## 🚀 Observações Finais

- O candidato **não precisa publicar o projeto online**.  
- O foco é **avaliar a organização, arquitetura e clareza do código**.  
- O desafio deve ser implementado **em até 1 dia** após o recebimento. 

Boa sorte 🍀  
Mostre sua criatividade e capacidade de estruturar uma solução escalável!