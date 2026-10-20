# ClinicFlow — DevOps e Deploy

Projeto inicial da aula. A stack e os artefatos repetitivos já estão disponíveis, mas os exemplos da apresentação devem ser implementados com os alunos.

## Execução local com Sail

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail npm ci
./vendor/bin/sail test
```

O arquivo [TESTING.md](TESTING.md) contém a sequência didática.
