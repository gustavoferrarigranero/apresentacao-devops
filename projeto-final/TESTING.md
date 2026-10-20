# Roteiro de aula e testes — DevOps e Deploy

Este projeto contém a solução final da apresentação: Sail reproduzível, CI com quality gates, artefato imutável, deploy por releases, migration expand/backfill/contract, coordenação de workers, readiness check e rollback executável.

## 1. Ambiente reproduzível com Sail

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
./vendor/bin/sail test
```

Valide os serviços:

```bash
./vendor/bin/sail ps
curl -i http://127.0.0.1:8000/api/health
```

O Compose fixa PHP 8.3, MySQL 8.4 e Redis 7.4. App, worker e scheduler usam o mesmo código e a mesma configuração.

## 2. Pipeline local equivalente ao CI

```bash
composer validate --strict
php artisan test --parallel
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
npm ci
npm run build
```

O workflow `.github/workflows/ci.yml` executa as mesmas etapas e publica um arquivo `clinicflow-<sha>.tar.gz`. O deploy recebe o artefato já validado, em vez de reconstruir a aplicação no servidor.

Para demonstrar o bloqueio de regressões no GitHub:

1. publique o projeto em um repositório GitHub;
2. crie um branch com uma falha controlada em um teste;
3. abra um pull request e acompanhe o job `quality` falhar;
4. corrija o teste e faça um novo push;
5. confirme todos os quality gates em verde;
6. baixe o artefato `clinicflow-<sha>` da execução aprovada.

O workflow executa em pull requests e em pushes para `main`.

## 3. Migration expand e backfill

Após `migrate:fresh --seed`, as colunas `legacy_reference` e `external_reference` coexistem.

```bash
php artisan appointments:backfill-references --chunk=500
php artisan queue:work --stop-when-empty --tries=3
php artisan tinker --execute="dump(App\\Models\\Appointment::query()->whereNull('external_reference')->count());"
```

O resultado esperado é zero. Execute novamente para confirmar idempotência. A remoção da coluna antiga fica isolada em `database/contract-migrations` e pertence a um release posterior:

```bash
php artisan migrate --path=database/contract-migrations --force
```

## 4. Worker, scheduler e versão

```bash
php artisan queue:work --tries=3 --timeout=90
php artisan schedule:work
php artisan deployment:heartbeat --wait=30
php artisan schedule:list
php artisan queue:monitor redis:default --max=500
```

O heartbeat só termina com sucesso quando um worker processa o job. As respostas HTTP incluem `X-App-Release`, e o health check retorna o valor de `APP_RELEASE`.

## 5. Health check

```bash
curl -s http://127.0.0.1:8000/api/health
```

O endpoint valida banco, cache e heartbeat recente do worker. Retorna `200` quando todos estão saudáveis e `503` quando algum sinal está degradado.

## 6. Deploy e rollback

O artefato deve estar extraído em `$DEPLOY_PATH/releases/$RELEASE_ID`. O diretório compartilhado precisa conter `.env` e `storage`.

```bash
DEPLOY_PATH=/var/www/clinicflow \
RELEASE_ID=20261019-abcdef \
HEALTHCHECK_URL=https://clinicflow.example.com/api/health \
./scripts/deploy.sh
```

O script executa migrations compatíveis, aquece caches, troca o symlink ativo, reinicia workers, exige heartbeat e valida o endpoint. Qualquer falha após a ativação restaura o release anterior.

Rollback manual:

```bash
DEPLOY_PATH=/var/www/clinicflow \
TARGET_RELEASE=20261018-fedcba \
HEALTHCHECK_URL=https://clinicflow.example.com/api/health \
./scripts/rollback.sh
```

## 7. Validação automatizada

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
npm run build
sh -n scripts/deploy.sh
sh -n scripts/rollback.sh
```

Os testes cobrem pipeline, infraestrutura, scripts, migration compatível, backfill idempotente, chunks, health check, heartbeat e scheduler.
