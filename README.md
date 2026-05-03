# Axent

Ce dépôt est prêt à être publié sur GitHub sans exposer les secrets du site, à condition de respecter ces règles :

1. Ne pas versionner `httpdocs/config/config.php`.
2. Utiliser `httpdocs/config/config.php.dist` comme modèle local.
3. Installer les dépendances avec `composer install` dans `httpdocs/`.

## Mise en ligne GitHub

Le fichier `.gitignore` exclut déjà :

- les secrets applicatifs
- les logs, caches et uploads
- les dépendances Composer vendored
- les dossiers serveur locaux (`bin/`, `lib/`, `usr/`, etc.)

## Configuration locale

Copiez `httpdocs/config/config.php.dist` vers `httpdocs/config/config.php`, puis remplacez toutes les valeurs d'exemple par vos vraies valeurs locales.

## Vérification GitHub Actions

Le workflow `.github/workflows/php.yml` :

- valide `httpdocs/composer.json`
- lance `php -l` sur les fichiers PHP du projet
