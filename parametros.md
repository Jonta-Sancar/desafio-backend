# PIX `/pix/create`

- SubadqA: `https://0acdeaee-1729-4d55-80eb-d54a125e5e18.mock.pstmn.io`
- SubadqB: `https://ef8513c8-fd99-4081-8963-573cd135e133.mock.pstmn.io`

## SubadqA

### request 
```json
{
  "merchant_id": "m123",
  "amount": 12345,
  "currency": "BRL",
  "order_id": "order_001",
  "payer": {
    "name": "Fulano",
    "cpf_cnpj": "00000000000"
  },
  "expires_in": 3600
}
```

### response
```json
{
  "transaction_id": "SP_SUBADQA_30a126bf-154f-4fd7-a328-42d03c23ed12",
  "location": "https://subadqA.com/pix/loc/325",
  "qrcode": "00020126530014BR.GOV.BCB.PIX0131backendtest@superpagamentos.com52040000530398654075000.005802BR5901N6001C6205050116304ACDA",
  "expires_at": "1763445181",
  "status": "PENDING"
}
```

## SubadqB

### request 
```json
{
  "seller_id": "m123",
  "amount": 12345,
  "order": "order_001",
  "payer": {
    "name": "Fulano",
    "cpf_cnpj": "00000000000"
  },
  "expires_in": 3600
}
```

### response
```json
{
  "transaction_id": "SP_ADQB_54468434-8521-487c-96f5-f60b68147fa7",
  "location": "https://subadqB.com/pix/loc/782",
  "qrcode": "00020126530014BR.GOV.BCB.PIX0131backendtest@superpagamentos.com52040000530398654075000.005802BR5901N6001C6205050116304ACDA",
  "expires_at": "1763445257",
  "status": "PROCESSING"
}
```

---

# Withdraw

## SubadqA

### request
```json
{
  "merchant_id": "m123",
  "account": {
    "bank_code": "001",
    "agencia": "1234",
    "conta": "00012345",
    "type": "checking"
  },
  "amount": 5000,
  "transaction_id": "SP54127d18-e44c-4929-98fd-cf7dce2cdff2"
}
```

### response
```json
{
  "withdraw_id": "WD1c5ee46f-4cbd-462a-adf0-5622bfc334af",
  "status": "PROCESSING"
}
```

## SubadqB

### request
```json
{
  "merchant_id": "m123",
  "account": {
    "bank_code": "001",
    "agencia": "1234",
    "conta": "00012345",
    "type": "checking"
  },
  "amount": 5000,
  "transaction_id": "SP54127d18-e44c-4929-98fd-cf7dce2cdff2"
}
```

### response
```json
{
  "withdraw_id": "WD_ADQB_95109d5b-d499-40e2-bf43-85136b3ac4c3",
  "status": "DONE"
}
```