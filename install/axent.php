<?php
-- ================================================================
--  AXENT - Base de données complète RGPD-compliant
--  Version : 1.0.0
--  Compatibilité : MySQL 8.0+ | MariaDB 10.4+
--
--  📋 INSTALLATION :
--  1. Ouvrez PHPMyAdmin sur Plesk
--  2. Créez une base : axent_db (encodage : utf8mb4_unicode_ci)
--  3. Cliquez sur "Importer" → sélectionnez ce fichier
--  4. Cliquez sur "Exécuter"
-- ================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ================================================================
-- TABLE : axnt_users
-- Stocke les utilisateurs inscrits
-- RGPD : données minimales, champs anonymisables
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_users` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `uuid`              CHAR(36)        NOT NULL COMMENT 'Identifiant public non-séquentiel',
  `email`             VARCHAR(255)    NOT NULL COMMENT 'RGPD : chiffré si anonymisé',
  `email_verified`    TINYINT(1)      NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME        NULL,
  `password_hash`     VARCHAR(255)    NULL     COMMENT 'NULL si OAuth2 uniquement',
  `display_name`      VARCHAR(100)    NOT NULL DEFAULT '',
  `avatar_url`        VARCHAR(500)    NULL,
  `role`              ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user',
  `status`            ENUM('active','suspended','anonymized','deleted') NOT NULL DEFAULT 'active',
  `lang`              CHAR(2)         NOT NULL DEFAULT 'fr',
  `newsletter`        TINYINT(1)      NOT NULL DEFAULT 0,
  `newsletter_token`  VARCHAR(64)     NULL     COMMENT 'Token double opt-in',
  `2fa_enabled`       TINYINT(1)      NOT NULL DEFAULT 0,
  `2fa_secret`        VARCHAR(32)     NULL,
  `last_login_at`     DATETIME        NULL,
  `last_login_ip`     VARCHAR(45)     NULL     COMMENT 'IPv4 ou IPv6 haché après 13 mois',
  `login_attempts`    TINYINT         NOT NULL DEFAULT 0,
  `locked_until`      DATETIME        NULL,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `anonymized_at`     DATETIME        NULL     COMMENT 'Date d anonymisation RGPD',
  -- Données RGPD
  `rgpd_export_requested_at` DATETIME NULL,
  `rgpd_delete_requested_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uuid`  (`uuid`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_role`   (`role`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Utilisateurs Axent - RGPD compliant';

-- ================================================================
-- TABLE : axnt_oauth_accounts
-- Lie un utilisateur à ses comptes OAuth2 externes
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_oauth_accounts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `provider`     ENUM('google','discord','microsoft','facebook','github') NOT NULL,
  `provider_id`  VARCHAR(255) NOT NULL COMMENT 'ID chez le provider',
  `access_token` TEXT         NULL     COMMENT 'Chiffré en base',
  `refresh_token`TEXT         NULL     COMMENT 'Chiffré en base',
  `token_expires_at` DATETIME NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider` (`provider`, `provider_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_oauth_user` FOREIGN KEY (`user_id`)
    REFERENCES `axnt_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comptes OAuth2 liés';

-- ================================================================
-- TABLE : axnt_sessions
-- Sessions utilisateurs (alternative aux sessions PHP fichier)
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_sessions` (
  `id`         VARCHAR(128)  NOT NULL,
  `user_id`    INT UNSIGNED  NULL,
  `ip_address` VARCHAR(45)   NOT NULL,
  `user_agent` VARCHAR(500)  NULL,
  `payload`    TEXT          NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`      (`user_id`),
  KEY `idx_last_activity`(`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABLE : axnt_sites
-- Sites clients qui intègrent le widget Axent
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_sites` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `uuid`         CHAR(36)      NOT NULL COMMENT 'clientId public du widget',
  `name`         VARCHAR(255)  NOT NULL,
  `domain`       VARCHAR(255)  NOT NULL,
  `widget_config`JSON          NULL     COMMENT 'Config JSON du widget',
  `plan`         ENUM('free','pro','enterprise') NOT NULL DEFAULT 'free',
  `status`       ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uuid`   (`uuid`),
  UNIQUE KEY `uq_domain` (`domain`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_site_user` FOREIGN KEY (`user_id`)
    REFERENCES `axnt_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sites intégrant le widget';

-- ================================================================
-- TABLE : axnt_cookie_versions
-- Versions de configuration cookies pour chaque site
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_cookie_versions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    INT UNSIGNED NOT NULL,
  `uuid`       CHAR(36)     NOT NULL COMMENT 'cookiesVersion du widget',
  `name`       VARCHAR(255) NOT NULL,
  `config`     JSON         NOT NULL COMMENT 'Liste des cookies/catégories',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uuid` (`uuid`),
  KEY `idx_site` (`site_id`),
  CONSTRAINT `fk_cv_site` FOREIGN KEY (`site_id`)
    REFERENCES `axnt_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABLE : axnt_consents
-- Journal des consentements (RGPD : preuve de consentement)
-- RGPD : conservé 13 mois puis anonymisé
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_consents` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`         INT UNSIGNED    NOT NULL,
  `version_id`      INT UNSIGNED    NOT NULL,
  `visitor_id`      VARCHAR(64)     NOT NULL COMMENT 'ID pseudonymisé du visiteur',
  `user_id`         INT UNSIGNED    NULL      COMMENT 'Si connecté',
  -- Choix du visiteur
  `choice`          ENUM('accepted','refused','partial') NOT NULL,
  `categories`      JSON            NOT NULL COMMENT '{"analytics": true, "marketing": false, ...}',
  -- Données techniques (RGPD : minimisation)
  `ip_hashed`       VARCHAR(64)     NOT NULL COMMENT 'IP hashée SHA-256 (non réversible)',
  `user_agent_hash` VARCHAR(64)     NOT NULL COMMENT 'UA hashé',
  `country_code`    CHAR(2)         NULL,
  `lang`            CHAR(5)         NULL,
  -- Contexte Google Consent Mode
  `gcm_data`        JSON            NULL,
  -- Horodatage
  `consented_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`      DATETIME        NOT NULL COMMENT 'consented_at + 13 mois',
  `anonymized_at`   DATETIME        NULL,
  PRIMARY KEY (`id`),
  KEY `idx_site`       (`site_id`),
  KEY `idx_visitor`    (`visitor_id`),
  KEY `idx_consented`  (`consented_at`),
  KEY `idx_expires`    (`expires_at`),
  CONSTRAINT `fk_consent_site`    FOREIGN KEY (`site_id`)    REFERENCES `axnt_sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_consent_version` FOREIGN KEY (`version_id`) REFERENCES `axnt_cookie_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal des consentements RGPD';

-- ================================================================
-- TABLE : axnt_rgpd_requests
-- Demandes RGPD (export, suppression, rectification)
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_rgpd_requests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `email`       VARCHAR(255) NOT NULL,
  `type`        ENUM('export','delete','rectify','oppose') NOT NULL,
  `status`      ENUM('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
  `token`       VARCHAR(64)  NOT NULL UNIQUE COMMENT 'Token de vérification',
  `notes`       TEXT         NULL,
  `handled_by`  INT UNSIGNED NULL COMMENT 'Admin qui a traité',
  `handled_at`  DATETIME     NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_email`  (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Demandes RGPD des utilisateurs';

-- ================================================================
-- TABLE : axnt_newsletter_subscribers
-- Abonnés newsletter (double opt-in RGPD)
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_newsletter_subscribers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(255) NOT NULL,
  `user_id`         INT UNSIGNED NULL,
  `status`          ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
  `confirm_token`   VARCHAR(64)  NULL,
  `confirm_sent_at` DATETIME     NULL,
  `confirmed_at`    DATETIME     NULL,
  `unsub_token`     VARCHAR(64)  NOT NULL COMMENT 'Token de désinscription',
  `source`          VARCHAR(100) NULL COMMENT 'Origine de l inscription',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_status`(`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Newsletter - double opt-in RGPD';

-- ================================================================
-- TABLE : axnt_email_logs
-- Historique des emails envoyés
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_email_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `to_email`   VARCHAR(255) NOT NULL,
  `type`       VARCHAR(100) NOT NULL COMMENT 'welcome, rgpd_export, alert_admin...',
  `subject`    VARCHAR(255) NOT NULL,
  `status`     ENUM('sent','failed','queued') NOT NULL DEFAULT 'queued',
  `error`      TEXT         NULL,
  `sent_at`    DATETIME     NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_type`   (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABLE : axnt_audit_log
-- Journal d'audit (actions sensibles)
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED    NULL,
  `action`     VARCHAR(100)    NOT NULL COMMENT 'login, logout, consent_update...',
  `target`     VARCHAR(255)    NULL,
  `ip_hashed`  VARCHAR(64)     NOT NULL,
  `details`    JSON            NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal d audit';

-- ================================================================
-- TABLE : axnt_api_keys
-- Clés API pour l'intégration du widget
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_api_keys` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     INT UNSIGNED NOT NULL,
  `key_hash`    VARCHAR(64)  NOT NULL UNIQUE COMMENT 'SHA-256 de la clé',
  `key_prefix`  VARCHAR(8)   NOT NULL COMMENT '8 premiers chars pour identification',
  `name`        VARCHAR(100) NOT NULL DEFAULT 'Clé principale',
  `last_used_at`DATETIME     NULL,
  `expires_at`  DATETIME     NULL,
  `status`      ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site`(`site_id`),
  CONSTRAINT `fk_apikey_site` FOREIGN KEY (`site_id`)
    REFERENCES `axnt_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABLE : axnt_rate_limits
-- Anti-brute force (login, API)
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_rate_limits` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_hash`    VARCHAR(64)  NOT NULL COMMENT 'Hash IP+action',
  `action`      VARCHAR(50)  NOT NULL,
  `attempts`    SMALLINT     NOT NULL DEFAULT 1,
  `blocked_until` DATETIME   NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_action` (`key_hash`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TABLE : axnt_settings
-- Paramètres dynamiques (modifiables depuis l'admin)
-- ================================================================
CREATE TABLE IF NOT EXISTS `axnt_settings` (
  `key`        VARCHAR(100) NOT NULL,
  `value`      TEXT         NULL,
  `type`       ENUM('string','boolean','integer','json') NOT NULL DEFAULT 'string',
  `group`      VARCHAR(50)  NOT NULL DEFAULT 'general',
  `label`      VARCHAR(255) NULL,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- DONNÉES PAR DÉFAUT
-- ================================================================

-- Paramètres par défaut
INSERT INTO `axnt_settings` (`key`, `value`, `type`, `group`, `label`) VALUES
('maintenance_mode',    '0',     'boolean', 'general',  'Mode maintenance'),
('max_sites_free',      '3',     'integer', 'plans',    'Sites max plan gratuit'),
('max_sites_pro',       '20',    'integer', 'plans',    'Sites max plan pro'),
('consent_expire_days', '365',   'integer', 'rgpd',     'Durée cookie consentement (jours)'),
('anonymize_months',    '13',    'integer', 'rgpd',     'Anonymisation après N mois'),
('widget_humor',        '1',     'boolean', 'widget',   'Mode humour du widget'),
('newsletter_enabled',  '1',     'boolean', 'email',    'Newsletter activée'),
('registration_open',   '1',     'boolean', 'general',  'Inscriptions ouvertes');

-- Compte superadmin par défaut
-- ⚠️ CHANGEZ LE MOT DE PASSE IMMÉDIATEMENT APRÈS INSTALLATION !
-- Mot de passe par défaut : AxentAdmin2024! (hash bcrypt cost 12)
INSERT INTO `axnt_users` (
  `uuid`, `email`, `email_verified`, `email_verified_at`,
  `password_hash`, `display_name`, `role`, `status`
) VALUES (
  UUID(),
  'admin@axet.fr',
  1,
  NOW(),
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- CHANGEZ CE MOT DE PASSE !
  'Super Admin',
  'superadmin',
  'active'
);

-- ================================================================
-- ÉVÉNEMENTS AUTOMATIQUES (RGPD - Cron Plesk)
-- ================================================================

-- ⚠️  Ces événements sont des FALLBACKS si le cron Plesk échoue.
--    Configurez plutôt le cron Plesk (voir README).

DELIMITER ;;

-- Anonymisation automatique après 13 mois
CREATE EVENT IF NOT EXISTS `evt_anonymize_consents`
  ON SCHEDULE EVERY 1 DAY
  STARTS CURRENT_TIMESTAMP
  DO
  BEGIN
    UPDATE `axnt_consents`
    SET
      `visitor_id`      = CONCAT('anon_', SHA2(visitor_id, 256)),
      `ip_hashed`       = 'anonymized',
      `user_agent_hash` = 'anonymized',
      `gcm_data`        = NULL,
      `anonymized_at`   = NOW()
    WHERE
      `anonymized_at` IS NULL
      AND `expires_at` < NOW();
  END;;

-- Nettoyage des sessions expirées
CREATE EVENT IF NOT EXISTS `evt_clean_sessions`
  ON SCHEDULE EVERY 1 HOUR
  STARTS CURRENT_TIMESTAMP
  DO
  BEGIN
    DELETE FROM `axnt_sessions`
    WHERE `last_activity` < UNIX_TIMESTAMP(NOW() - INTERVAL 24 HOUR);
  END;;

-- Nettoyage des rate limits expirés
CREATE EVENT IF NOT EXISTS `evt_clean_rate_limits`
  ON SCHEDULE EVERY 6 HOUR
  STARTS CURRENT_TIMESTAMP
  DO
  BEGIN
    DELETE FROM `axnt_rate_limits`
    WHERE `blocked_until` IS NOT NULL
      AND `blocked_until` < NOW();
  END;;

DELIMITER ;

SET foreign_key_checks = 1;

-- ================================================================
-- ✅  Installation terminée !
-- Connectez-vous avec : admin@axet.fr / AxentAdmin2024!
-- ⚠️  CHANGEZ CE MOT DE PASSE IMMÉDIATEMENT !
-- ================================================================
?>