# Roteiro de aula — DevOps e deploy

## Resultado esperado

Empacotar a aplicação, subir serviços dependentes, validar saúde e executar um pipeline de qualidade e um deploy repetível.

## Preparação

No `projeto-inicial`, confirme `docker compose config`, execute `./vendor/bin/sail up -d`, `./vendor/bin/sail artisan migrate:fresh --seed` e `./vendor/bin/sail artisan test`.

## Desenvolvimento guiado

1. Identifique responsabilidades de aplicação, MySQL e Redis.
2. Crie imagem com dependências reproduzíveis e usuário não privilegiado.
3. Defina health check e variáveis de ambiente sem incluir segredos na imagem.
4. Monte CI com instalação determinística, lint, testes e falha imediata.
5. Monte deploy com modo de manutenção, migrações, caches e reversão operacional.
6. Discuta migrações retrocompatíveis e estratégia de rollback.

## Verificação

No projeto final, execute `docker compose config`, `docker compose build` e `php artisan test --filter=DeploymentConfigurationTest`. Revise `.github/workflows/ci.yml` e execute `bash -n scripts/deploy.sh`. A aula termina quando o build é reproduzível, a saúde é verificável e qualquer falha interrompe o pipeline.
