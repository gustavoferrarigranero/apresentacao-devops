# ClinicFlow — DevOps e Deploy

Projeto final da aula sobre ambientes reproduzíveis, integração contínua, artefatos imutáveis, migrations compatíveis, workers, health checks e rollback.

## Execução local com Sail

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
./vendor/bin/sail test
```

O arquivo [TESTING.md](TESTING.md) contém o roteiro completo da aula e das validações.
