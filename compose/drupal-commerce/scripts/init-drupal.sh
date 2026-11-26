#!/usr/bin/env bash
set -euo pipefail
DBPASS="$(tr -d '\r\n' < "$DRUPAL_DB_PASSWORD_FILE")"
ADMINPASS="$(tr -d '\r\n' < "$DRUPAL_ADMIN_PASSWORD_FILE")"
export PGPASSWORD="$DBPASS"
until pg_isready -h "$DRUPAL_DB_HOST" -p "$DRUPAL_DB_PORT" -U "$DRUPAL_DB_USER" >/dev/null 2>&1; do
  echo 'En attente de PostgreSQL pour Drupal...'; sleep 3
done
cd /opt/drupal
DRUSH=./vendor/bin/drush
# Une installation n'a lieu que si les tables Drupal ne sont pas presentes.
installed="$(psql -h "$DRUPAL_DB_HOST" -U "$DRUPAL_DB_USER" -d "$DRUPAL_DB_NAME" -Atc "SELECT to_regclass('public.config');" || true)"
if [ "$installed" != config ]; then
  mkdir -p web/sites/default/files
  cp -n web/sites/default/default.settings.php web/sites/default/settings.php || true
  chmod u+w web/sites/default/settings.php || true
  "$DRUSH" site:install standard -y \
    --db-url="pgsql://${DRUPAL_DB_USER}:${DBPASS}@${DRUPAL_DB_HOST}:${DRUPAL_DB_PORT}/${DRUPAL_DB_NAME}" \
    --account-name=admin --account-pass="$ADMINPASS" --site-name='Groupe C'
fi
if ! grep -q 'GROUPEC V5 TRUSTED HOSTS' web/sites/default/settings.php; then
  cat >> web/sites/default/settings.php <<'PHP'
// GROUPEC V5 TRUSTED HOSTS: web publie exclusivement via Traefik.
$settings['trusted_host_patterns'] = ['^drupal\\.groupec\\.test$', '^drupal\\.localhost$'];
PHP
fi
# V5.5: correctif VALIDE sur la VM: le reverse proxy doit transmettre
# le scheme HTTPS et le Host original pour les redirects Commerce.
# L'IP 172.28.240.10 est celle de Traefik sur GroupeC-applicatif.
if ! grep -q 'GROUPEC V5.5 REVERSE PROXY' web/sites/default/settings.php; then
  cat >> web/sites/default/settings.php <<'PHP'
// GROUPEC V5.5 REVERSE PROXY: Traefik est l'unique proxy de confiance.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['172.28.240.10'];
$settings['reverse_proxy_trusted_headers'] =
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;
PHP
fi
chown -R www-data:www-data web/sites/default
chmod 0440 web/sites/default/settings.php
"$DRUSH" en -y views views_ui commerce commerce_store commerce_product commerce_order \
  commerce_cart commerce_checkout commerce_price commerce_payment groupec_n8n
"$DRUSH" updb -y
"$DRUSH" cr
"$DRUSH" php:script /usr/local/bin/init-content.php
"$DRUSH" cr
printf '%s\n' 'SUCCES Drupal Commerce : installation, module n8n et catalogue initialises.'
exec apache2-foreground
