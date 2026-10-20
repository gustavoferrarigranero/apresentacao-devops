# Roteiro de implementação — DevOps e Deploy

Este projeto contém Laravel, Sail, MySQL, Redis, worker, scheduler, análise estática e todos os artefatos básicos da aula. A implementação específica da apresentação foi deixada em branco para ser construída com os alunos.

## Preparação

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
./vendor/bin/sail test
```

## Artefatos preparados

- workflow `.github/workflows/ci.yml`;
- `Dockerfile`, `.dockerignore` e `compose.yaml`;
- `phpstan.neon` e Larastan instalado;
- scripts `deploy.sh` e `rollback.sh`;
- middleware `AttachReleaseContext`;
- controller `HealthCheckController`;
- jobs `BackfillAppointmentReferences` e `RecordDeploymentProbe`;
- commands `appointments:backfill-references` e `deployment:heartbeat`;
- model e migration de `DeploymentProbe`;
- migration expand e contract migration separada.

Não é necessário executar comandos `make:*` durante a aula.

## Etapa 1 — Pipeline

1. Complete o workflow com validação do Composer, testes paralelos, PHPStan, Pint e build de assets.
2. Gere um artefato identificado pelo SHA do commit.
3. Garanta que nenhuma etapa dependa de arquivos locais não versionados.
4. Abra um pull request com uma falha controlada e confirme que o job `quality` bloqueia a entrega.
5. Corrija a falha, faça um novo push e baixe o artefato da execução aprovada.

O exercício usa o GitHub Actions real.

## Etapa 2 — Imagem de produção

1. Separe build de dependências, assets e runtime no `Dockerfile`.
2. Use PHP 8.3 e Node 22 com versões explícitas.
3. Execute a imagem com utilizador sem privilégios.
4. Adicione um `HEALTHCHECK` apontando para a readiness da aplicação.

## Etapa 3 — Migration compatível

1. Complete a migration expand adicionando `external_reference` como nullable e indexada.
2. Complete o job e o command de backfill em chunks de 500.
3. Comprove idempotência executando o backfill duas vezes.
4. Complete a contract migration somente para um release posterior.

## Etapa 4 — Worker e health check

1. Complete o job de heartbeat e o command que aguarda seu processamento.
2. Registre heartbeat e monitorização da fila no scheduler.
3. Complete o health check de banco, cache e worker.
4. Adicione `APP_RELEASE` ao contexto e ao header HTTP.

## Etapa 5 — Deploy e rollback

1. Complete `deploy.sh` com migrations, caches e troca atômica de release.
2. Reinicie processos que mantêm código em memória.
3. Interrompa o rollout quando heartbeat ou health check falhar.
4. Complete `rollback.sh` para reativar um artefato anterior.

## Validação

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
npm run build
sh -n scripts/deploy.sh
sh -n scripts/rollback.sh
```

Compare com o projeto final somente depois de concluir os exercícios.
