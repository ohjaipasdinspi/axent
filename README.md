# 🍪 Axent — Guide d'installation complet

> **Solution de gestion du consentement par l'Association Axent.**  
> Ce projet est la propriété de l'Association Axent. L'accès au code est public, mais son utilisation et sa reproduction sont strictement encadrées.

---

## 📚 Table des matières

1. [C'est quoi Axent ?](#-cest-quoi-axent)
2. [Ce qu'il vous faut avant de commencer](#-ce-quil-vous-faut-avant-de-commencer)
3. [Structure du projet](#-structure-du-projet)
4. [Étape 1 — Créer la base de données](#-étape-1--créer-la-base-de-données)
5. [Étape 2 — Uploader les fichiers sur Plesk](#-étape-2--uploader-les-fichiers-sur-plesk)
6. [Étape 3 — Configurer config.php](#-étape-3--configurer-configphp)
7. [Étape 4 — Créer les sous-domaines sur Plesk](#-étape-4--créer-les-sous-domaines-sur-plesk)
8. [Étape 5 — Installer Composer (PHPMailer)](#-étape-5--installer-composer-phpmailer)
9. [Étape 6 — Configurer le Cron RGPD sur Plesk](#-étape-6--configurer-le-cron-rgpd-sur-plesk)
10. [Étape 7 — Configurer OAuth2](#-étape-7--configurer-oauth2)
11. [Étape 8 — Intégrer le widget sur votre site](#-étape-8--intégrer-le-widget-sur-votre-site)
12. [Accès aux interfaces](#-accès-aux-interfaces)
13. [Contribution](#-contribution)
14. [Sécurité — Checklist](#-sécurité--checklist)
15. [Dépannage courant](#-dépannage-courant)
16. [FAQ](#-faq)
17. [Glossaire](#-glossaire)

---

## 🤔 C'est quoi Axent ?

Axent est une plateforme que vous installez sur **votre propre serveur** pour :

- **Afficher une popup de consentement cookies** sur vos sites web (comme ce bandeau cookie que tout le monde connaît)
- **Respecter le RGPD** : enregistrer les preuves de consentement, les garder 13 mois, les anonymiser automatiquement
- **Gérer plusieurs sites** depuis un seul tableau de bord
- **Permettre à vos utilisateurs** de gérer leurs données (export, suppression)

```
Votre site web                    Vos serveurs (axet.fr)
     │                                      │
     │  <script src="cdn.axet.fr/sdk.js">   │
     │ ─────────────────────────────────►   │
     │                                      │
     │  "L'utilisateur a accepté analytics" │
     │ ◄─────────────────────────────────   │
     │                                      │
     ↓                                      ↓
Popup s'affiche              Consentement enregistré en BDD
```

---

## 📋 Ce qu'il vous faut avant de commencer

### Obligatoire
- ✅ Un serveur Plesk avec accès au panneau de contrôle
- ✅ Le domaine `axet.fr` pointant vers votre serveur
- ✅ PHP 8.0 ou supérieur (vérifiez dans Plesk → Paramètres PHP)
- ✅ MySQL 8.0 **ou** MariaDB 10.4 ou supérieur
- ✅ Le module `mod_rewrite` activé sur Apache (normalement actif sur Plesk)
- ✅ Un accès SSH ou FTP/SFTP pour uploader les fichiers
- ✅ `curl` activé dans PHP (normalement actif)

### Optionnel mais recommandé
- 📧 Un compte email `noreply@axet.fr` créé dans Plesk (pour les emails système)
- 🔐 Comptes développeur sur les services OAuth2 souhaités (Google, Discord, etc.)

### Comment vérifier votre version PHP sur Plesk
```
Connexion Plesk
→ Cliquez sur votre domaine "axet.fr"
→ "Paramètres PHP" (ou "PHP Settings")
→ Vous voyez la version PHP en haut
→ Si < 8.0 : changez-la dans le menu déroulant
```

---

## 📁 Structure du projet

Voici à quoi ressemble le projet une fois uploadé :

```
/var/www/vhosts/axet.fr/httpdocs/          ← Racine de axet.fr
│
├── index.html                              ← Page d'accueil publique
├── .htaccess                               ← Configuration Apache (sécurité, redirections)
│
├── config/
│   └── config.php                          ← ⚙️ CONFIGURATION PRINCIPALE (à remplir !)
│
├── core/
│   ├── Database.php                        ← Connexion base de données
│   ├── Security.php                        ← Fonctions sécurité (CSRF, tokens...)
│   ├── Lang.php                            ← Système de traduction
│   └── Router.php                          ← Routeur URL
│
├── auth/
│   ├── Auth.php                            ← Gestion des connexions (local + OAuth2)
│   ├── login.html                          ← Page de connexion (auth.axet.fr)
│   ├── callback.php                        ← Retour OAuth2
│   └── providers/                          ← (réservé)
│
├── mail/
│   ├── Mailer.php                          ← Envoi d'emails (PHPMailer)
│   └── templates/                          ← Templates HTML des emails
│
├── api/
│   └── index.php                           ← API REST (api.axet.fr)
│
├── widget/                                 ← Widget configurable
│
├── cdn/
│   └── js/
│       └── sdk.js                          ← SDK JavaScript du widget (cdn.axet.fr)
│
├── app/                                    ← Interface utilisateur (app.axet.fr)
│   ├── dashboard/index.php                 ← Tableau de bord
│   ├── settings/index.php                  ← Paramètres du compte
│   └── rgpd/index.php                      ← Droits RGPD
│
├── admin/                                  ← Interface admin (admin.axet.fr)
│   ├── dashboard/index.html                ← Dashboard admin
│   └── users/index.php                     ← Gestion utilisateurs
│
├── lang/                                   ← Traductions
│   ├── fr/messages.php
│   ├── en/messages.php
│   ├── es/messages.php
│   └── de/messages.php
│
├── assets/
│   ├── logo/logo.svg                       ← Logo SVG
│   └── images/                             ← Images
│
├── cron/
│   └── cron_rgpd.php                       ← Script d'anonymisation RGPD (Cron Plesk)
│
├── install/
│   └── axent.sql                           ← Script SQL d'installation
│
└── errors/
    ├── 403.php                             ← Page "Accès refusé"
    ├── 404.php                             ← Page "Introuvable"
    └── 500.php                             ← Page "Erreur serveur"
```

---

## 🗃️ Étape 1 — Créer la base de données

> 💡 **C'est quoi une base de données ?** C'est l'endroit où Axent stocke toutes les informations : utilisateurs, consentements, sites... Pensez-y comme un énorme tableur Excel, mais plus puissant.

### 1.1 Créer la base dans Plesk

```
Connexion Plesk
→ Domaines → axet.fr
→ "Bases de données" (ou "Databases")
→ "Ajouter une base de données"

Remplissez :
  Nom de la base : axent_db
  Nom d'utilisateur : axent_user
  Mot de passe : [inventez un mot de passe fort - notez-le !]

→ Cliquez "OK"
```

> ⚠️ **Notez bien** le nom de la base, l'utilisateur et le mot de passe. Vous en aurez besoin à l'étape 3.

### 1.2 Importer le fichier SQL

```
Dans Plesk, toujours dans "Bases de données"
→ Cliquez sur "phpMyAdmin" (icône à côté de votre base)
→ phpMyAdmin s'ouvre dans un nouvel onglet
→ Dans la colonne de gauche, cliquez sur "axent_db"
→ En haut, cliquez sur l'onglet "Importer"
→ "Choisir un fichier" → sélectionnez le fichier "install/axent.sql"
→ Laissez tout par défaut
→ Cliquez sur "Exécuter" (tout en bas)
```

Si tout va bien, vous verrez un message vert : **"L'importation a réussi"**

> ✅ **Résultat attendu :** Vous verrez dans la colonne gauche de phpMyAdmin une liste de tables qui commencent par `axnt_` (axnt_users, axnt_sites, axnt_consents, etc.)

---

## 📤 Étape 2 — Uploader les fichiers sur Plesk

### Option A : Via le Gestionnaire de fichiers Plesk (le plus simple)

```
Connexion Plesk
→ Domaines → axet.fr
→ "Gestionnaire de fichiers"
→ Naviguez jusqu'au dossier "httpdocs"
→ Bouton "Télécharger" → sélectionnez tous vos fichiers
   (ou uploadez un .zip et extrayez-le sur place)
```

### Option B : Via FTP/SFTP (recommandé pour les gros volumes)

Vous aurez besoin d'un client FTP comme **FileZilla** (gratuit).

```
Hôte     : axet.fr (ou l'IP de votre serveur)
Port     : 22 (SFTP) ou 21 (FTP)
Login    : votre identifiant Plesk
Mot de passe : votre mot de passe Plesk

Destination : /var/www/vhosts/axet.fr/httpdocs/
```

> 💡 **Conseil :** Uploadez tout le contenu du dossier `axent/` dans `httpdocs/`, pas le dossier `axent/` lui-même.

---

## ⚙️ Étape 3 — Configurer config.php

C'est **l'étape la plus importante**. Ouvrez le fichier `config/config.php` avec un éditeur de texte (Notepad++, VS Code, ou même le Gestionnaire de fichiers Plesk).

### 3.1 Base de données

Trouvez ces lignes et remplacez les valeurs :

```php
define('DB_HOST',     'localhost');          // ← Ne pas changer
define('DB_PORT',     3306);                 // ← Ne pas changer
define('DB_NAME',     'axent_db');           // ← Le nom créé à l'étape 1.1
define('DB_USER',     'axent_user');         // ← L'utilisateur créé à l'étape 1.1
define('DB_PASS',     'VOTRE_MOT_DE_PASSE_ICI'); // ← ⚠️ REMPLACEZ !
```

### 3.2 Clé secrète (OBLIGATOIRE)

Cette clé protège votre installation. Elle doit être unique et longue.

**Pour en générer une automatiquement :**

```bash
# Via SSH sur votre serveur :
php -r "echo bin2hex(random_bytes(32));"

# Ou si vous n'avez pas SSH, utilisez ce site :
# https://www.random.org/strings/
# (64 caractères aléatoires)
```

```php
define('SECRET_KEY', 'COLLEZ-VOTRE-CLE-ICI-64-CHARS-MINIMUM');
```

> ⚠️ **NE JAMAIS partager cette clé.** Si elle est compromise, changez-la immédiatement et regénérez toutes les sessions.

### 3.3 Email

Pour utiliser Postfix (serveur mail Plesk intégré) :

```php
define('MAIL_ENABLED', true);
define('MAIL_HOST',    'localhost');   // ← Ne pas changer pour Postfix
define('MAIL_AUTH',    false);         // ← false = Postfix local
define('MAIL_FROM',    'noreply@axet.fr');  // ← Votre email
```

### 3.4 Vérification finale de config.php

Après modification, votre config devrait ressembler à :

```php
define('DB_NAME',  'axent_db');       // ✅ Rempli
define('DB_USER',  'axent_user');     // ✅ Rempli
define('DB_PASS',  'MonMotDePasse!'); // ✅ Rempli (exemple)
define('SECRET_KEY', 'a1b2c3d4...');  // ✅ 64 chars minimum
define('MAIL_FROM', 'noreply@axet.fr'); // ✅ Rempli
```

---

## 🌐 Étape 4 — Créer les sous-domaines sur Plesk

Axent utilise plusieurs sous-domaines. Voici comment les créer :

```
Connexion Plesk
→ Domaines → axet.fr
→ "Sous-domaines" (ou "Subdomains")
→ Pour chaque sous-domaine ci-dessous, cliquez "Ajouter un sous-domaine"
```

| Sous-domaine | Répertoire racine | Rôle |
|---|---|---|
| `app.axet.fr` | `httpdocs/app` | Interface utilisateurs |
| `admin.axet.fr` | `httpdocs/admin` | Interface administrateurs |
| `api.axet.fr` | `httpdocs/api` | API REST du widget |
| `auth.axet.fr` | `httpdocs/auth` | Connexion / OAuth2 |
| `cdn.axet.fr` | `httpdocs/cdn` | SDK JavaScript |
| `docs.axet.fr` | `httpdocs/docs` | Documentation |

### Comment ajouter chaque sous-domaine

```
"Ajouter un sous-domaine"

Sous-domaine : app       ← juste "app", Plesk ajoute ".axet.fr"
Répertoire   : /var/www/vhosts/axet.fr/httpdocs/app

→ Répétez pour admin, api, auth, cdn, docs
```

### Activer HTTPS sur chaque sous-domaine

```
Pour chaque sous-domaine créé :
→ Cliquez sur le sous-domaine
→ "SSL/TLS Certificates"
→ "Installer le certificat Let's Encrypt" (gratuit !)
→ Cochez "Sécuriser aussi les sous-domaines"
→ "Obtenir un certificat"
```

> ✅ Let's Encrypt est gratuit et se renouvelle automatiquement. Pas d'excuse !

### Créer les fichiers .htaccess pour chaque sous-domaine

Créez un fichier `.htaccess` dans chaque sous-domaine avec ce contenu de base :

**Pour `httpdocs/app/.htaccess` :**
```apache
Options -Indexes
RewriteEngine On
RewriteBase /

# HTTPS forcé
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^(.*)$ $1.php [NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?_route=$1 [QSA,L]
```

*Faites pareil pour `admin/`, `api/`, `auth/`.*

---

## 📦 Étape 5 — Installer Composer (PHPMailer)

PHPMailer est la bibliothèque qui gère l'envoi d'emails. Il faut l'installer via Composer.

### 5.1 Vérifier que Composer est disponible

```bash
# Via SSH :
composer --version
# Si vous voyez "Composer version X.X.X" → ✅ OK
# Sinon → voir 5.2
```

### 5.2 Installer Composer (si absent)

```bash
# Via SSH, dans le dossier httpdocs/ :
cd /var/www/vhosts/axet.fr/httpdocs/

# Télécharger et installer Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### 5.3 Installer PHPMailer

```bash
cd /var/www/vhosts/axet.fr/httpdocs/

# Initialiser Composer (si pas de composer.json)
composer init --no-interaction

# Installer PHPMailer
composer require phpmailer/phpmailer

# Vous devriez voir un dossier "vendor/" créé
```

### 5.4 Ajouter l'autoload dans config.php

Ajoutez cette ligne **au début** de votre fichier `config/config.php`, avant tout le reste :

```php
// Autoload Composer (PHPMailer, etc.)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
```

> ℹ️ **Sans SSH ?** Vous pouvez télécharger PHPMailer manuellement depuis https://github.com/PHPMailer/PHPMailer/releases et uploader le dossier `src/` dans `mail/phpmailer/`. Dans ce cas, modifiez les `use` dans `Mailer.php`.

---

## ⏰ Étape 6 — Configurer le Cron RGPD sur Plesk

Le cron anonymise automatiquement les données de plus de 13 mois (obligation CNIL).

```
Connexion Plesk
→ Tâches planifiées (ou "Scheduled Tasks" ou "Cron Jobs")
→ "Ajouter une tâche"

Commande  : /usr/bin/php /var/www/vhosts/axet.fr/httpdocs/cron/cron_rgpd.php
Planifier : Tous les jours à 02h00 du matin

Format cron : 0 2 * * *
              │ │ │ │ │
              │ │ │ │ └── Jour de la semaine (*)  = tous
              │ │ │ └──── Mois (*)               = tous
              │ │ └────── Jour du mois (*)        = tous
              │ └──────── Heure (2)               = 2h du matin
              └────────── Minutes (0)             = à pile

→ Enregistrez
```

### Tester le cron manuellement

```bash
# Via SSH :
php /var/www/vhosts/axet.fr/httpdocs/cron/cron_rgpd.php

# Résultat attendu :
[2024-01-15 14:23:01] === DÉMARRAGE CRON RGPD ===
[2024-01-15 14:23:01] Consentements anonymisés : 0
[2024-01-15 14:23:01] Sessions nettoyées : 3
[2024-01-15 14:23:01] Rate limits nettoyés : 0
[2024-01-15 14:23:01] Inscriptions newsletter expirées nettoyées : 0
[2024-01-15 14:23:01] === CRON RGPD TERMINÉ AVEC SUCCÈS ===
```

---

## 🔐 Étape 7 — Configurer OAuth2

> 💡 **OAuth2, c'est quoi ?** C'est le système qui permet de se connecter avec Google, Discord, etc. sans créer un mot de passe. Optionnel, mais très apprécié des utilisateurs.

### Google OAuth2

```
1. Allez sur https://console.cloud.google.com
2. Créez un nouveau projet (ou sélectionnez-en un)
3. Menu gauche → "APIs & Services" → "Credentials"
4. "+ CREATE CREDENTIALS" → "OAuth client ID"
5. Application type : "Web application"
6. Name : "Axent"
7. Authorized redirect URIs :
      https://auth.axet.fr/callback/google
8. Cliquez "Create"
9. Copiez "Client ID" et "Client Secret"
```

Dans `config.php` :
```php
define('OAUTH_GOOGLE_ENABLED',        true);
define('OAUTH_GOOGLE_CLIENT_ID',      'VOTRE_ID.apps.googleusercontent.com');
define('OAUTH_GOOGLE_CLIENT_SECRET',  'VOTRE_SECRET');
```

### Discord OAuth2

```
1. Allez sur https://discord.com/developers/applications
2. "New Application" → donnez un nom
3. Menu gauche → "OAuth2"
4. "Redirects" → ajoutez : https://auth.axet.fr/callback/discord
5. "Client ID" et "Client Secret" sont affichés en haut
```

Dans `config.php` :
```php
define('OAUTH_DISCORD_ENABLED',       true);
define('OAUTH_DISCORD_CLIENT_ID',     '1234567890');
define('OAUTH_DISCORD_CLIENT_SECRET', 'VOTRE_SECRET');
```

### GitHub OAuth2

```
1. Allez sur https://github.com/settings/developers
2. "OAuth Apps" → "New OAuth App"
3. Remplissez :
      Application name : Axent
      Homepage URL : https://axet.fr
      Authorization callback URL : https://auth.axet.fr/callback/github
4. "Register application"
5. "Generate a new client secret"
```

*Même principe pour Microsoft et Facebook — consultez leur documentation développeur.*

> 💡 **Pas besoin d'activer tous les providers.** Mettez `false` pour ceux que vous ne voulez pas dans `config.php`.

---

## 🔌 Étape 8 — Intégrer le widget sur votre site

C'est la partie fun ! Voici comment afficher la popup de consentement sur n'importe quel site.

### 8.1 Créer un site dans Axent

```
Connectez-vous sur https://app.axet.fr
→ Dashboard → "Ajouter un site"
→ Remplissez :
     Nom : Mon site e-commerce
     Domaine : monsite.fr
→ Créez une version de cookies
→ Notez le "Client ID" et le "Version ID" affichés
```

### 8.2 Ajouter le code sur votre site

Copiez-collez ces 2 lignes dans le `<head>` de votre site HTML :

```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Mon site</title>

  <!-- ↓ AXENT - Ajoutez ces 2 blocs ici ↓ -->
  <script>
    window.axentSettings = {
      clientId: "VOTRE-CLIENT-ID",          // ← Remplacez
      cookiesVersion: "VOTRE-VERSION-ID",   // ← Remplacez
      lang: "fr",                           // fr | en | es | de
      position: "popup",                    // popup | bottom-bar | bottom-right

      // Google Consent Mode (optionnel, pour Google Analytics/Ads)
      googleConsentMode: {
        default: {
          analytics_storage: "denied",
          ad_storage: "denied",
          ad_user_data: "denied",
          ad_personalization: "denied"
        }
      },

      // Callback quand l'utilisateur fait un choix (optionnel)
      onConsent: function(choices, type) {
        console.log("Choix :", type, choices);
        // Vous pouvez charger Google Analytics ici si accepté :
        if (choices.analytics) {
          // charger gtag, etc.
        }
      }
    };
  </script>
  <script async src="https://cdn.axet.fr/sdk.js"></script>
  <!-- ↑ FIN AXENT ↑ -->

</head>
<body>
  <!-- Votre site normal... -->
</body>
</html>
```

### 8.3 Tester l'intégration

```
1. Ouvrez votre site dans un navigateur
2. La popup de consentement devrait apparaître
3. Si elle n'apparaît pas : ouvrez la console (F12) et cherchez des erreurs
4. Pour réafficher la popup (si vous avez déjà accepté) :
   Ouvrez la console (F12) et tapez : Axent.reset()
```

### 8.4 API JavaScript disponible

```javascript
// Savoir si l'utilisateur a consenti aux analytics
if (window.Axent && Axent.hasConsent('analytics')) {
  // Charger Google Analytics, etc.
}

// Récupérer tous les choix
var consent = Axent.getConsent();
console.log(consent.choices);
// { essential: true, analytics: true, marketing: false }

// Réinitialiser le consentement (pour tests)
Axent.reset();
```

---

## 🖥️ Accès aux interfaces

| Interface | URL | Pour qui |
|---|---|---|
| **Site public** | https://axet.fr | Tout le monde |
| **Connexion** | https://auth.axet.fr/login | Utilisateurs & Admins |
| **Dashboard utilisateur** | https://app.axet.fr | Utilisateurs inscrits |
| **Dashboard admin** | https://admin.axet.fr | Administrateurs seulement |
| **API** | https://api.axet.fr | Widget (automatique) |
| **SDK** | https://cdn.axet.fr/sdk.js | Widget (automatique) |
| **Documentation** | https://docs.axet.fr | Tout le monde |

### Compte administrateur par défaut

> ⚠️⚠️⚠️ **CHANGEZ CE MOT DE PASSE IMMÉDIATEMENT APRÈS INSTALLATION !** ⚠️⚠️⚠️

```
Email    : admin@axet.fr
Mot de passe : AxentAdmin2024!
```

Pour changer le mot de passe :
```
1. Connectez-vous sur auth.axet.fr avec les identifiants ci-dessus
2. app.axet.fr → Paramètres → Mot de passe
3. Changez-le pour quelque chose de solide (16+ caractères)
```

---

## 🤝 Contribution

Axent est un projet géré par une association. Pour proposer des améliorations techniques, consultez notre fichier [CONTRIBUTING.md](./CONTRIBUTING.md).

---

## 🔒 Sécurité — Checklist

Avant de mettre en production, vérifiez chaque point :

- [ ] **Mot de passe admin changé** (`admin@axet.fr` / `AxentAdmin2024!` → votre nouveau mot de passe)
- [ ] **SECRET_KEY personnalisée** dans `config.php` (64 chars aléatoires)
- [ ] **HTTPS actif** sur tous les sous-domaines (Let's Encrypt)
- [ ] **DB_PASS sécurisé** (pas "password123" !)
- [ ] **config.php dans .gitignore** (si vous utilisez Git)
- [ ] **Dossier install/ protégé** (le .htaccess bloque l'accès à axent.sql)
- [ ] **APP_DEBUG = false** en production
- [ ] **APP_ENV = 'production'** dans config.php
- [ ] **Cron RGPD configuré** sur Plesk
- [ ] **Emails testés** (envoi de vérification, etc.)

### Ajouter config.php à .gitignore (si vous utilisez Git)

```bash
# Créez ou modifiez le fichier .gitignore à la racine :
echo "config/config.php" >> .gitignore
echo "vendor/" >> .gitignore
echo "logs/" >> .gitignore
echo "cache/" >> .gitignore
```

---

## 🔧 Dépannage courant

### ❌ "Erreur de connexion à la base de données"

```
Vérifiez dans config.php :
✓ DB_HOST = 'localhost'
✓ DB_NAME correspond au nom créé dans Plesk
✓ DB_USER correspond à l'utilisateur créé dans Plesk
✓ DB_PASS est le bon mot de passe

Test rapide : dans phpMyAdmin, connectez-vous avec
l'utilisateur DB_USER et le mot de passe DB_PASS.
Si ça ne marche pas → le mot de passe est faux.
```

### ❌ "La page affiche le code PHP brut"

```
Cela signifie que PHP n'est pas activé pour ce dossier.

Dans Plesk :
→ Domaines → axet.fr (ou le sous-domaine)
→ "Paramètres PHP"
→ Activez PHP
→ Choisissez la version (8.0 ou +)
→ Enregistrez
```

### ❌ "Erreur 404 sur toutes les pages"

```
Le fichier .htaccess ne fonctionne pas.
Vérifiez que mod_rewrite est activé :

Plesk → Serveur web Apache → Modules → "rewrite" activé

Ou contactez le support Plesk.
```

### ❌ "Les emails ne partent pas"

```
1. Vérifiez que MAIL_ENABLED = true dans config.php
2. Dans les logs email (admin.axet.fr → Emails), regardez si "failed"
3. Testez Postfix sur le serveur :
   echo "Test" | mail -s "Test Axent" votre@email.fr
4. Si ça ne marche pas → contactez votre hébergeur Plesk
```

### ❌ "OAuth2 redirige vers une erreur"

```
Vérifiez que :
✓ L'URL de callback dans la config du provider est EXACTEMENT :
  https://auth.axet.fr/callback/PROVIDER
  (pas de slash final, pas de www, bien en HTTPS)
✓ Les CLIENT_ID et CLIENT_SECRET sont corrects
✓ Les APIs sont bien activées (ex: Google People API pour Google OAuth)
```

### ❌ "Le widget ne s'affiche pas"

```
1. Ouvrez la console du navigateur (F12 → Console)
2. Cherchez des erreurs en rouge
3. Vérifiez que cdn.axet.fr est accessible :
   https://cdn.axet.fr/sdk.js
   (vous devriez voir du code JavaScript)
4. Vérifiez que clientId est correct dans axentSettings
```

---

## ❓ FAQ

**Q : Axent est-il vraiment gratuit ?**  
R : Oui, à 100%. Vous hébergez vous-même, il n'y a pas d'abonnement.

**Q : Combien de sites puis-je gérer ?**  
R : Autant que vous voulez sur votre serveur. La limite "3 sites en gratuit" dans le code est configurable dans `config.php` → `max_sites_free`.

**Q : Mes données restent-elles sur mon serveur ?**  
R : Oui. Axent est auto-hébergé. Vos données ne transitent nulle part.

**Q : Est-ce que ça marche avec WordPress ?**  
R : Oui ! Ajoutez simplement les 2 lignes de code dans `<head>` de votre thème WordPress (`header.php`).

**Q : Et avec Shopify, Wix, etc. ?**  
R : Ces plateformes permettent d'ajouter du JavaScript personnalisé. Cherchez "custom JavaScript" ou "balises supplémentaires" dans leurs paramètres.

**Q : Dois-je avoir des connaissances en PHP ?**  
R : Non. Si vous avez suivi ce guide pas à pas, ça devrait fonctionner sans toucher au code PHP.

**Q : Que se passe-t-il si mon serveur tombe ?**  
R : Si cdn.axet.fr n'est pas accessible, le SDK charge en mode silencieux (il ne bloque pas votre site). Par contre, les consentements ne sont pas enregistrés jusqu'au retour du service.

**Q : Comment mettre à jour Axent ?**  
R : Remplacez les fichiers (sauf `config/config.php` !), puis vérifiez le fichier SQL pour les éventuelles migrations de base.

**Q : La CNIL exige-t-elle vraiment tout ça ?**  
R : Oui, depuis 2022. Sans consentement valide, vous risquez jusqu'à 150 000€ d'amende. Mais avec Axent, vous êtes couverts ! ✅

---

## 📖 Glossaire

| Terme | Définition |
|---|---|
| **RGPD** | Règlement Général sur la Protection des Données. La loi européenne sur la vie privée. |
| **CNIL** | Commission Nationale de l'Informatique et des Libertés. Le gendarme des données en France. |
| **Consentement** | L'accord explicite d'un visiteur pour utiliser ses cookies. |
| **Cookie** | Un petit fichier que votre navigateur enregistre pour mémoriser des infos (connexion, préférences, tracking...). |
| **OAuth2** | Protocole permettant de se connecter avec Google, Discord, etc. sans partager son mot de passe. |
| **CSRF** | Cross-Site Request Forgery. Une attaque que notre système de tokens prévient. |
| **Bcrypt** | Algorithme de hachage des mots de passe. Transforme un mot de passe en chaîne illisible. |
| **Cron** | Tâche automatique qui s'exécute à heure fixe (comme une alarme programmée). |
| **API REST** | Interface permettant à des programmes de communiquer via des URLs. |
| **SDK** | Software Development Kit. Le fichier JavaScript que vous intégrez sur vos sites. |
| **Anonymisation** | Rendre des données personnelles impossibles à rattacher à une personne. |
| **HSTS** | Header HTTP qui force HTTPS. Sécurité renforcée. |
| **CSRF Token** | Code unique généré côté serveur pour valider les formulaires. Empêche les attaques CSRF. |

---

## 📞 Support

- **Documentation complète :** https://docs.axet.fr
- **API Reference :** https://docs.axet.fr/api
- **Email :** contact@axet.fr
- **Logs du serveur :** `/var/www/vhosts/axet.fr/logs/`
- **Logs PHP :** `/var/www/vhosts/axet.fr/logs/php_errors.log`
- **Logs cron RGPD :** `/var/www/vhosts/axet.fr/httpdocs/logs/cron_rgpd.log`

---

*Fait avec ☕ et quelques cookies — Axent v1.0.0 — © Association Axent (Propriété Privée)*

*🍪 Gérez vos cookies proprement. Vos utilisateurs (et la CNIL) vous remercieront.*
