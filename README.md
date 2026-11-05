# Declaração Vacinal (decescolar)

Sistema para o responsável pedir o atestado/declaração vacinal do(s) filho(s), exigido na matrícula escolar. Identidade visual da Prefeitura de Nova Mutum-MT. Pensado primeiro para celular.

## Como funciona

1. O responsável preenche o formulário (dados pessoais, escola, unidade de saúde e um ou mais filhos).
2. O pedido é enviado à API do `chamados-multi-tenant`, que devolve um protocolo por filho.
3. A unidade de saúde gera o PDF; o chamados o entrega a este sistema (`POST /api/v1/atestados/{protocolo}/pronto`, com token Bearer).
4. O responsável baixa ou compartilha o PDF pelo link recebido ou pela página `/consultar` (CPF + protocolo).
5. Ao baixar, este sistema avisa o chamados (atendido).

## Requisitos

PHP 8.2+ (usado 8.4), PostgreSQL, Composer.

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

Variáveis de integração no `.env`: `CHAMADOS_API_URL`, `CHAMADOS_API_TOKEN`, `DECESCOLAR_API_TOKEN` (token que o chamados usa para chamar este sistema).

## LGPD

CPF e telefone são gravados criptografados; a busca por CPF usa hash HMAC; o CPF é exibido mascarado; o link de download usa token aleatório longo.

## Agendador

`php artisan schedule:run` a cada minuto (reenvia avisos de atendimento pendentes).
