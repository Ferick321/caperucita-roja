-- ============================================================================
--  PLATAFORMA ESTILO - Base de datos lista para importar
-- ============================================================================
--
--  QUE CONTIENE
--    51 tablas (50 del sistema + 1 de control de migraciones) con la
--    estructura completa y los datos iniciales:
--      - 94 ajustes configurables desde el panel
--      - 6 categorias y 14 servicios de ejemplo
--      - 3 profesionales con horario de lunes a sabado
--      - 3 metodos de pago (efectivo, transferencia, tarjeta)
--      - 8 secciones de la pagina web
--      - 10 plantillas de correo
--      - 13 politicas de limpieza de datos
--      - 1 cuenta de administrador: admin@mibarberia.com
--
--  COMO IMPORTARLO
--    1. Crea la base de datos vacia (phpMyAdmin > Nueva > nombre: estilo,
--       cotejamiento: utf8mb4_unicode_ci).
--    2. Entra en esa base > pestana Importar > elige este archivo > Continuar.
--
--  IMPORTANTE - LA CONTRASENA DEL ADMINISTRADOR
--    La contrasena guardada aqui NO te va a servir: se cifro con una clave
--    (PASSWORD_PEPPER) distinta de la tuya. Despues de importar, abre una
--    terminal en la carpeta "backend" y ejecuta:
--
--      php cli/console.php usuario:clave --email=admin@mibarberia.com --password=TuClaveSegura#2026
--
--    Con eso ya puedes entrar en /panel.
--
--  Cotejamiento: utf8mb4_unicode_ci (admite tildes, enies y emojis)
-- ============================================================================

-- CREATE DATABASE IF NOT EXISTS `estilo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `appointment_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `service_name` varchar(140) NOT NULL COMMENT 'Copia historica del nombre',
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` smallint(5) unsigned NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_as_appointment` (`appointment_id`),
  KEY `idx_as_service` (`service_id`),
  CONSTRAINT `fk_as_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_as_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `appointment_services` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_services` ENABLE KEYS */;
DROP TABLE IF EXISTS `appointment_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(20) NOT NULL DEFAULT '',
  `to_status` varchar(20) NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ash_appointment` (`appointment_id`,`created_at`),
  KEY `fk_ash_user` (`changed_by`),
  CONSTRAINT `fk_ash_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ash_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `appointment_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_status_history` ENABLE KEYS */;
DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT 'Codigo corto para el cliente, ej. CT-8H3K2',
  `branch_id` int(10) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL en reservas de invitado',
  `staff_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = sin preferencia',
  `resource_id` int(10) unsigned DEFAULT NULL,
  `client_name` varchar(160) NOT NULL,
  `client_phone` varchar(20) NOT NULL DEFAULT '',
  `client_email` varchar(190) NOT NULL DEFAULT '',
  `starts_at` datetime NOT NULL COMMENT 'UTC',
  `ends_at` datetime NOT NULL COMMENT 'UTC',
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `status` enum('pending','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `coupon_id` int(10) unsigned DEFAULT NULL,
  `payment_status` enum('unpaid','deposit_paid','awaiting_verification','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `client_notes` text DEFAULT NULL COMMENT 'Peticion libre del cliente',
  `custom_request` varchar(255) NOT NULL DEFAULT '' COMMENT 'Opcion "Otro: especifique"',
  `internal_notes` text DEFAULT NULL COMMENT 'Solo visible para el personal',
  `source` enum('web','app','panel','phone','walk_in') NOT NULL DEFAULT 'web',
  `reminder_sent_at` datetime DEFAULT NULL,
  `followup_sent_at` datetime DEFAULT NULL,
  `review_request_sent_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointments_code` (`code`),
  KEY `idx_appt_staff_time` (`staff_id`,`starts_at`,`ends_at`),
  KEY `idx_appt_branch_time` (`branch_id`,`starts_at`),
  KEY `idx_appt_client` (`client_id`,`starts_at`),
  KEY `idx_appt_status` (`status`,`starts_at`),
  KEY `idx_appt_payment` (`payment_status`),
  KEY `idx_appt_deleted` (`deleted_at`),
  KEY `idx_appt_phone` (`client_phone`),
  KEY `fk_appt_resource` (`resource_id`),
  KEY `fk_appt_canceller` (`cancelled_by`),
  CONSTRAINT `fk_appt_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_appt_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL DEFAULT '',
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `changes_before` text DEFAULT NULL,
  `changes_after` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(120) NOT NULL,
  `account_type` varchar(60) NOT NULL DEFAULT 'Ahorros',
  `account_number_enc` varchar(512) NOT NULL COMMENT 'Cifrado',
  `account_last4` varchar(8) NOT NULL DEFAULT '' COMMENT 'Ultimos digitos para listados',
  `holder_name` varchar(160) NOT NULL,
  `holder_document` varchar(60) NOT NULL DEFAULT '' COMMENT 'Cedula / RUC / NIT',
  `holder_email` varchar(190) NOT NULL DEFAULT '',
  `holder_phone` varchar(30) NOT NULL DEFAULT '',
  `instructions` text DEFAULT NULL COMMENT 'Aviso adicional para el cliente',
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bank_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
DROP TABLE IF EXISTS `banner_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `banner_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `banner_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `visitor_key` char(64) NOT NULL DEFAULT '' COMMENT 'Identificador anonimo del visitante',
  `event_type` enum('impression','click','dismiss') NOT NULL DEFAULT 'impression',
  `placement` varchar(40) NOT NULL DEFAULT '',
  `device` varchar(20) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bev_banner_time` (`banner_id`,`created_at`),
  KEY `idx_bev_visitor` (`visitor_key`,`banner_id`,`created_at`),
  KEY `idx_bev_user` (`user_id`,`banner_id`),
  CONSTRAINT `fk_bev_banner` FOREIGN KEY (`banner_id`) REFERENCES `banners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `banner_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `banner_events` ENABLE KEYS */;
DROP TABLE IF EXISTS `banner_placements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `banner_placements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `banner_id` int(10) unsigned NOT NULL,
  `placement` varchar(40) NOT NULL COMMENT 'web_hero, web_strip, web_sidebar, on_login, while_browsing, on_exit, app_splash, app_home_card, app_interstitial',
  `page_pattern` varchar(120) NOT NULL DEFAULT '*' COMMENT 'Rutas donde aplica, ej. /servicios*',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_placement` (`banner_id`,`placement`,`page_pattern`),
  KEY `idx_placement_lookup` (`placement`,`sort_order`),
  CONSTRAINT `fk_placement_banner` FOREIGN KEY (`banner_id`) REFERENCES `banners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `banner_placements` DISABLE KEYS */;
/*!40000 ALTER TABLE `banner_placements` ENABLE KEYS */;
DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(140) NOT NULL COMMENT 'Nombre interno para identificarlo',
  `title` varchar(200) NOT NULL DEFAULT '',
  `subtitle` varchar(300) NOT NULL DEFAULT '',
  `body` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL DEFAULT '' COMMENT 'Imagen para escritorio',
  `mobile_image_path` varchar(255) NOT NULL DEFAULT '' COMMENT 'Imagen para movil',
  `video_url` varchar(500) NOT NULL DEFAULT '',
  `cta_label` varchar(80) NOT NULL DEFAULT '',
  `cta_url` varchar(500) NOT NULL DEFAULT '',
  `background_color` varchar(7) NOT NULL DEFAULT '#111827',
  `text_color` varchar(7) NOT NULL DEFAULT '#ffffff',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `weekdays` varchar(20) NOT NULL DEFAULT '' COMMENT 'Ej. 1,2,3,4,5 (vacio = todos)',
  `daily_from` time DEFAULT NULL,
  `daily_to` time DEFAULT NULL,
  `audience` enum('all','guests','clients','new_clients','inactive_clients') NOT NULL DEFAULT 'all',
  `device_target` enum('all','desktop','mobile','app') NOT NULL DEFAULT 'all',
  `max_views_per_user` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '0 = sin limite',
  `cooldown_hours` smallint(5) unsigned NOT NULL DEFAULT 24,
  `delay_seconds` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Retraso antes de aparecer',
  `auto_close_seconds` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '0 = no se cierra solo',
  `is_dismissible` tinyint(1) NOT NULL DEFAULT 1,
  `priority` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Mayor valor gana',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `impressions` int(10) unsigned NOT NULL DEFAULT 0,
  `clicks` int(10) unsigned NOT NULL DEFAULT 0,
  `dismissals` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_banners_active` (`is_active`,`starts_at`,`ends_at`),
  KEY `idx_banners_deleted` (`deleted_at`),
  KEY `fk_banner_user` (`created_by`),
  CONSTRAINT `fk_banner_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
DROP TABLE IF EXISTS `branch_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch_closures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = aplica a todas las sucursales',
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `reason` varchar(160) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_closure_range` (`starts_on`,`ends_on`),
  KEY `fk_closure_branch` (`branch_id`),
  CONSTRAINT `fk_closure_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `branch_closures` DISABLE KEYS */;
/*!40000 ALTER TABLE `branch_closures` ENABLE KEYS */;
DROP TABLE IF EXISTS `branch_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch_hours` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned NOT NULL,
  `weekday` tinyint(3) unsigned NOT NULL,
  `opens_at` time NOT NULL DEFAULT '09:00:00',
  `closes_at` time NOT NULL DEFAULT '19:00:00',
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_weekday` (`branch_id`,`weekday`),
  CONSTRAINT `fk_hours_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `branch_hours` DISABLE KEYS */;
INSERT INTO `branch_hours` (`id`, `branch_id`, `weekday`, `opens_at`, `closes_at`, `break_start`, `break_end`, `is_closed`) VALUES (1,1,0,'09:00:00','17:00:00',NULL,NULL,1),
(2,1,1,'09:00:00','19:00:00',NULL,NULL,0),
(3,1,2,'09:00:00','19:00:00',NULL,NULL,0),
(4,1,3,'09:00:00','19:00:00',NULL,NULL,0),
(5,1,4,'09:00:00','19:00:00',NULL,NULL,0),
(6,1,5,'09:00:00','20:00:00',NULL,NULL,0),
(7,1,6,'09:00:00','17:00:00',NULL,NULL,0);
/*!40000 ALTER TABLE `branch_hours` ENABLE KEYS */;
DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `phone` varchar(30) NOT NULL DEFAULT '',
  `whatsapp` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `maps_url` varchar(500) NOT NULL DEFAULT '',
  `photo_path` varchar(255) NOT NULL DEFAULT '',
  `timezone` varchar(64) NOT NULL DEFAULT 'America/Guayaquil',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_slug` (`slug`),
  KEY `idx_branches_active` (`is_active`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` (`id`, `name`, `slug`, `address`, `city`, `phone`, `whatsapp`, `email`, `latitude`, `longitude`, `maps_url`, `photo_path`, `timezone`, `is_active`, `is_default`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Local principal','local-principal','','','','','',NULL,NULL,'','','America/Guayaquil',1,1,0,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
DROP TABLE IF EXISTS `campaign_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `destination` varchar(190) NOT NULL COMMENT 'Correo, telefono o token de dispositivo',
  `status` enum('pending','sent','failed','opened','clicked','unsubscribed') NOT NULL DEFAULT 'pending',
  `error_message` varchar(255) NOT NULL DEFAULT '',
  `tracking_token` char(32) NOT NULL DEFAULT '',
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recipient_campaign` (`campaign_id`,`status`),
  KEY `idx_recipient_user` (`user_id`),
  KEY `idx_recipient_token` (`tracking_token`),
  CONSTRAINT `fk_recipient_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recipient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `campaign_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_recipients` ENABLE KEYS */;
DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `channel` enum('email','sms','push','whatsapp') NOT NULL DEFAULT 'email',
  `subject` varchar(200) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `image_path` varchar(255) NOT NULL DEFAULT '',
  `cta_label` varchar(80) NOT NULL DEFAULT '',
  `cta_url` varchar(500) NOT NULL DEFAULT '',
  `audience` enum('all','new_clients','inactive_clients','frequent_clients','birthday','custom') NOT NULL DEFAULT 'all',
  `audience_filter` text DEFAULT NULL COMMENT 'JSON con filtros adicionales',
  `inactive_days` smallint(5) unsigned NOT NULL DEFAULT 60,
  `status` enum('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `total_recipients` int(10) unsigned NOT NULL DEFAULT 0,
  `total_sent` int(10) unsigned NOT NULL DEFAULT 0,
  `total_failed` int(10) unsigned NOT NULL DEFAULT 0,
  `total_opened` int(10) unsigned NOT NULL DEFAULT 0,
  `total_clicked` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_status` (`status`,`scheduled_at`),
  KEY `fk_campaign_user` (`created_by`),
  CONSTRAINT `fk_campaign_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL DEFAULT '',
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `replied_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_unread` (`is_read`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
DROP TABLE IF EXISTS `content_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `block_key` varchar(60) NOT NULL COMMENT 'hero, sobre_nosotros, servicios, equipo...',
  `section_type` varchar(40) NOT NULL DEFAULT 'generic',
  `title` varchar(200) NOT NULL DEFAULT '',
  `subtitle` varchar(300) NOT NULL DEFAULT '',
  `body` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL DEFAULT '',
  `background_path` varchar(255) NOT NULL DEFAULT '',
  `cta_label` varchar(80) NOT NULL DEFAULT '',
  `cta_url` varchar(500) NOT NULL DEFAULT '',
  `cta_secondary_label` varchar(80) NOT NULL DEFAULT '',
  `cta_secondary_url` varchar(500) NOT NULL DEFAULT '',
  `extra_json` text DEFAULT NULL COMMENT 'Campos adicionales segun el tipo de seccion',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocks_key` (`block_key`),
  KEY `idx_blocks_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `content_blocks` DISABLE KEYS */;
INSERT INTO `content_blocks` (`id`, `block_key`, `section_type`, `title`, `subtitle`, `body`, `image_path`, `background_path`, `cta_label`, `cta_url`, `cta_secondary_label`, `cta_secondary_url`, `extra_json`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'hero','hero','Tu estilo, en las mejores manos','Barberia, peluqueria y estetica con cita previa. Sin filas, sin esperas.','','','','Agendar mi cita','/agendar','Descargar la app','/app',NULL,1,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'app_promo','app_promo','Lleva tu barberia en el bolsillo','Agenda, reprograma y paga desde el celular. Recibe recordatorios y promociones.','Con la app puedes elegir tu profesional favorito, ver los horarios libres en tiempo real, subir tu comprobante de pago y acumular puntos en cada visita.','','','Descargar para Android','/app','','',NULL,1,10,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'services_intro','services','Nuestros servicios','Elige lo que necesitas; si no lo encuentras, cuentanos y lo resolvemos.',NULL,'','','','','','',NULL,1,20,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(4,'team_intro','team','Nuestro equipo','Profesionales con anos de experiencia listos para atenderte.',NULL,'','','','','','',NULL,1,30,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(5,'gallery_intro','gallery','Nuestro trabajo','Algunos de los resultados que nos enorgullecen.',NULL,'','','','','','',NULL,1,40,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(6,'reviews_intro','reviews','Lo que dicen nuestros clientes','',NULL,'','','','','','',NULL,1,50,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(7,'about','about','Sobre nosotros','Mas que un corte: una experiencia','Somos un espacio dedicado al cuidado personal donde cada detalle cuenta. Trabajamos con productos de primera calidad y un equipo que se capacita constantemente para ofrecerte exactamente lo que buscas.','','','','','','',NULL,1,60,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(8,'contact','contact','Visitanos','Estamos para atenderte',NULL,'','','','','','',NULL,1,70,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `content_blocks` ENABLE KEYS */;
DROP TABLE IF EXISTS `coupon_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_redemptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `discount_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_redemption_coupon` (`coupon_id`),
  KEY `idx_redemption_user` (`user_id`),
  KEY `fk_red_appointment` (`appointment_id`),
  CONSTRAINT `fk_red_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_red_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_red_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `coupon_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupon_redemptions` ENABLE KEYS */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `discount_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `service_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = aplica a todo',
  `first_visit_only` tinyint(1) NOT NULL DEFAULT 0,
  `usage_limit` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0 = ilimitado',
  `usage_limit_per_user` int(10) unsigned NOT NULL DEFAULT 1,
  `times_used` int(10) unsigned NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code` (`code`),
  KEY `idx_coupons_active` (`is_active`,`starts_at`,`ends_at`),
  KEY `fk_coupon_service` (`service_id`),
  CONSTRAINT `fk_coupon_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
DROP TABLE IF EXISTS `daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_stats` (
  `stat_date` date NOT NULL,
  `branch_id` int(10) unsigned NOT NULL DEFAULT 0,
  `appointments_total` int(10) unsigned NOT NULL DEFAULT 0,
  `appointments_done` int(10) unsigned NOT NULL DEFAULT 0,
  `appointments_cancel` int(10) unsigned NOT NULL DEFAULT 0,
  `appointments_noshow` int(10) unsigned NOT NULL DEFAULT 0,
  `new_clients` int(10) unsigned NOT NULL DEFAULT 0,
  `revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`stat_date`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `daily_stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_stats` ENABLE KEYS */;
DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `channel` enum('email','sms') NOT NULL DEFAULT 'email',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_verif_token` (`token_hash`),
  KEY `idx_verif_user` (`user_id`),
  CONSTRAINT `fk_verif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `email_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_verifications` ENABLE KEYS */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_faqs_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
INSERT INTO `faqs` (`id`, `question`, `answer`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'Necesito cita previa?','Recomendamos agendar para asegurar tu horario, pero tambien atendemos por orden de llegada segun disponibilidad.',1,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'Como cancelo o cambio mi cita?','Desde la app o la web, en la seccion \"Mis citas\". Se admite con la antelacion indicada en las condiciones.',1,1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'Que formas de pago aceptan?','Efectivo y tarjeta en el local, y transferencia bancaria subiendo el comprobante desde la app o la web.',1,2,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(4,'Puedo elegir a mi profesional?','Si. Al agendar puedes seleccionar a quien prefieras o dejar que asignemos al primero disponible.',1,3,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
DROP TABLE IF EXISTS `gallery_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gallery_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `image_path` varchar(255) NOT NULL,
  `before_path` varchar(255) NOT NULL DEFAULT '' COMMENT 'Foto "antes" opcional',
  `category_id` int(10) unsigned DEFAULT NULL,
  `staff_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_active` (`is_active`,`sort_order`),
  KEY `fk_gallery_category` (`category_id`),
  KEY `fk_gallery_staff` (`staff_id`),
  CONSTRAINT `fk_gallery_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gallery_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `gallery_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery_items` ENABLE KEYS */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(60) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_email_time` (`email`,`created_at`),
  KEY `idx_login_ip_time` (`ip_address`,`created_at`),
  KEY `idx_login_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
DROP TABLE IF EXISTS `loyalty_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `points` int(11) NOT NULL COMMENT 'Positivo suma, negativo canjea',
  `balance_after` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(160) NOT NULL DEFAULT '',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loyalty_user` (`user_id`,`created_at`),
  KEY `fk_loyalty_appointment` (`appointment_id`),
  CONSTRAINT `fk_loyalty_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_loyalty_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `loyalty_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_transactions` ENABLE KEYS */;
DROP TABLE IF EXISTS `maintenance_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(60) NOT NULL,
  `rows_affected` int(10) unsigned NOT NULL DEFAULT 0,
  `files_removed` int(10) unsigned NOT NULL DEFAULT 0,
  `bytes_freed` bigint(20) unsigned NOT NULL DEFAULT 0,
  `duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `detail` text DEFAULT NULL,
  `triggered_by` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = tarea programada',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_maint_task` (`task`,`created_at`),
  KEY `fk_maint_user` (`triggered_by`),
  CONSTRAINT `fk_maint_user` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `maintenance_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_runs` ENABLE KEYS */;
DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_path` varchar(255) NOT NULL,
  `file_mime` varchar(60) NOT NULL DEFAULT '',
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `file_hash` char(64) NOT NULL DEFAULT '',
  `width` smallint(5) unsigned NOT NULL DEFAULT 0,
  `height` smallint(5) unsigned NOT NULL DEFAULT 0,
  `original_name` varchar(160) NOT NULL DEFAULT '',
  `alt_text` varchar(255) NOT NULL DEFAULT '' COMMENT 'Accesibilidad y SEO',
  `caption` varchar(255) NOT NULL DEFAULT '',
  `folder` varchar(60) NOT NULL DEFAULT 'general',
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_media_folder` (`folder`,`created_at`),
  KEY `idx_media_hash` (`file_hash`),
  KEY `idx_media_deleted` (`deleted_at`),
  KEY `fk_media_user` (`uploaded_by`),
  CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `media` DISABLE KEYS */;
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(191) NOT NULL,
  `checksum` char(64) NOT NULL,
  `batch` int(10) unsigned NOT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `filename`, `checksum`, `batch`, `applied_at`) VALUES (1,'0001_identidad_y_seguridad.sql','eaa8f9d4bf65e09dd4da9d7fc3fa7fe77655094fcc7f2b26ce52ea60a143f18f',1,'2026-08-30 14:09:37'),
(2,'0002_negocio_y_catalogo.sql','9aa82feb390e85647455d7066f349cf0c95b9634a8faf2935f7154dfc390d867',1,'2026-08-30 14:09:37'),
(3,'0003_citas.sql','e6aa6506bcdc452c59ba1c9f23b54192c7ff23e4c0bf1f11dd31debc26c108c6',1,'2026-08-30 14:09:37'),
(4,'0004_pagos.sql','7cc2b2a5c9ee92b9549303424983c614b44241e8c4cb8063fcb6e8aac9948633',1,'2026-08-30 14:09:37'),
(5,'0005_contenido_y_publicidad.sql','fa1b43ff1bd8bd3eebd015710dc404b5deecb8fe3315e7cf1fa73b3c8e4b75df',1,'2026-08-30 14:09:37'),
(6,'0006_marketing_y_sistema.sql','71c080561495bec4df4ab173303383a7fe9bb38b8a6f643de84fd84c83fef9ea',1,'2026-08-30 14:09:37');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
DROP TABLE IF EXISTS `notification_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('email','sms','push','whatsapp') NOT NULL DEFAULT 'email',
  `destination` varchar(190) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `payload_json` text DEFAULT NULL COMMENT 'Datos extra para push',
  `template_key` varchar(60) NOT NULL DEFAULT '',
  `related_type` varchar(40) NOT NULL DEFAULT '',
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `last_error` varchar(500) NOT NULL DEFAULT '',
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_queue_pending` (`status`,`scheduled_at`),
  KEY `idx_queue_related` (`related_type`,`related_id`),
  KEY `fk_queue_user` (`user_id`),
  CONSTRAINT `fk_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notification_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_queue` ENABLE KEYS */;
DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_key` varchar(60) NOT NULL COMMENT 'cita_confirmada, recordatorio_24h, bienvenida...',
  `channel` enum('email','sms','push','whatsapp') NOT NULL DEFAULT 'email',
  `name` varchar(140) NOT NULL,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `available_vars` varchar(500) NOT NULL DEFAULT '' COMMENT 'Variables admitidas, ej. {cliente},{fecha}',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_template` (`template_key`,`channel`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notification_templates` DISABLE KEYS */;
INSERT INTO `notification_templates` (`id`, `template_key`, `channel`, `name`, `subject`, `body`, `available_vars`, `is_active`, `created_at`, `updated_at`) VALUES (1,'cita_recibida','email','Solicitud de cita recibida','Recibimos tu solicitud de cita {codigo}','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'cita_confirmada','email','Cita confirmada','Tu cita {codigo} esta confirmada','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'cita_cancelada','email','Cita cancelada','Tu cita {codigo} fue cancelada','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(4,'cita_reprogramada','email','Cita reprogramada','Tu cita {codigo} cambio de horario','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(5,'recordatorio_cita','email','Recordatorio de cita','Recordatorio: tu cita es el {fecha} a las {hora}','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(6,'cita_completada','email','Gracias por tu visita','Gracias por visitarnos, {cliente}','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(7,'solicitar_resena','email','Solicitud de resena','Como estuvo tu visita a {negocio}?','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(8,'pago_aprobado','email','Pago aprobado','Confirmamos tu pago de la cita {codigo}','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(9,'pago_rechazado','email','Pago rechazado','Necesitamos revisar el pago de tu cita {codigo}','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(10,'bienvenida','email','Bienvenida al registrarse','Bienvenido a {negocio}','<p>Hola <strong>{cliente}</strong>,</p><p>Tu cita <strong>{codigo}</strong> es el <strong>{fecha_hora}</strong> con {profesional}.</p><p>Servicios: {servicios}<br>Total: {total}</p><p><a href=\"{url_cita}\">Ver mi cita</a></p><p>{negocio} &middot; {direccion} &middot; {telefono}</p>','{cliente},{codigo},{fecha},{hora},{fecha_hora},{profesional},{servicios},{total},{negocio},{telefono},{direccion},{url_sitio},{url_cita}',1,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `notification_templates` ENABLE KEYS */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 del token; el original solo viaja por correo',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token_hash`),
  KEY `idx_reset_user` (`user_id`),
  KEY `idx_reset_expires` (`expires_at`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL COMMENT 'efectivo, transferencia, tarjeta, ...',
  `name` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `instructions` text DEFAULT NULL COMMENT 'Texto que ve el cliente al elegirlo',
  `icon` varchar(60) NOT NULL DEFAULT '',
  `requires_proof` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Exige subir comprobante',
  `shows_bank_accounts` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Muestra los datos para transferir',
  `requires_verification` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'El personal debe aprobarlo',
  `is_online` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Disponible al reservar por web/app',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_methods_code` (`code`),
  KEY `idx_pm_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` (`id`, `code`, `name`, `description`, `instructions`, `icon`, `requires_proof`, `shows_bank_accounts`, `requires_verification`, `is_online`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'efectivo','Efectivo','Paga en el local al momento de tu cita.','Acercate unos minutos antes y paga en caja. Aceptamos billetes y monedas.','cash',0,0,0,1,1,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'transferencia','Transferencia bancaria','Transfiere a nuestra cuenta y sube el comprobante.','Realiza la transferencia a cualquiera de las cuentas indicadas y sube la foto o el archivo del comprobante. Verificamos el pago y confirmamos tu cita.','bank',1,1,1,1,1,1,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'tarjeta_local','Tarjeta en el local','Paga con tarjeta al llegar.','Contamos con datafono para debito y credito.','card',0,0,0,1,1,2,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
DROP TABLE IF EXISTS `payment_proofs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_proofs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL COMMENT 'Ruta relativa fuera del directorio publico',
  `file_mime` varchar(60) NOT NULL DEFAULT '',
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `file_hash` char(64) NOT NULL DEFAULT '' COMMENT 'Detecta comprobantes reutilizados',
  `original_name` varchar(160) NOT NULL DEFAULT '',
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `uploaded_from` enum('web','app','panel') NOT NULL DEFAULT 'web',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proof_payment` (`payment_id`),
  KEY `idx_proof_hash` (`file_hash`),
  KEY `fk_proof_user` (`uploaded_by`),
  CONSTRAINT `fk_proof_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_proof_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `payment_proofs` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_proofs` ENABLE KEYS */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method_id` int(10) unsigned DEFAULT NULL,
  `bank_account_id` int(10) unsigned DEFAULT NULL COMMENT 'Cuenta destino en transferencias',
  `method_code` varchar(40) NOT NULL DEFAULT '' COMMENT 'Copia historica',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `kind` enum('deposit','full','balance','refund') NOT NULL DEFAULT 'full',
  `status` enum('pending','awaiting_verification','approved','rejected','refunded') NOT NULL DEFAULT 'pending',
  `reference` varchar(120) NOT NULL DEFAULT '' COMMENT 'Numero de comprobante o transaccion',
  `transferred_at` datetime DEFAULT NULL COMMENT 'Fecha declarada por el cliente',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) NOT NULL DEFAULT '',
  `notes` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payments_appointment` (`appointment_id`),
  KEY `idx_payments_status` (`status`,`created_at`),
  KEY `idx_payments_client` (`client_id`),
  KEY `fk_pay_method` (`payment_method_id`),
  KEY `fk_pay_bank` (`bank_account_id`),
  KEY `fk_pay_verifier` (`verified_by`),
  CONSTRAINT `fk_pay_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL COMMENT 'modulo.accion, admite comodin modulo.*',
  `module` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `slug`, `module`, `name`, `created_at`) VALUES (1,'panel.ver','panel','Entrar al panel','2026-08-30 14:09:37'),
(2,'citas.ver','citas','Ver la agenda','2026-08-30 14:09:37'),
(3,'citas.crear','citas','Crear citas','2026-08-30 14:09:37'),
(4,'citas.editar','citas','Editar citas','2026-08-30 14:09:37'),
(5,'citas.cancelar','citas','Cancelar citas','2026-08-30 14:09:37'),
(6,'citas.eliminar','citas','Eliminar citas','2026-08-30 14:09:37'),
(7,'clientes.ver','clientes','Ver clientes','2026-08-30 14:09:37'),
(8,'clientes.crear','clientes','Crear clientes','2026-08-30 14:09:37'),
(9,'clientes.editar','clientes','Editar clientes','2026-08-30 14:09:37'),
(10,'clientes.eliminar','clientes','Eliminar clientes','2026-08-30 14:09:37'),
(11,'clientes.exportar','clientes','Exportar la base de clientes','2026-08-30 14:09:37'),
(12,'servicios.ver','servicios','Ver el catalogo','2026-08-30 14:09:37'),
(13,'servicios.editar','servicios','Editar el catalogo','2026-08-30 14:09:37'),
(14,'personal.ver','personal','Ver el equipo','2026-08-30 14:09:37'),
(15,'personal.editar','personal','Editar el equipo','2026-08-30 14:09:37'),
(16,'personal.horarios','personal','Gestionar horarios','2026-08-30 14:09:37'),
(17,'pagos.ver','pagos','Ver pagos','2026-08-30 14:09:37'),
(18,'pagos.verificar','pagos','Verificar comprobantes','2026-08-30 14:09:37'),
(19,'pagos.cuentas','pagos','Editar cuentas bancarias','2026-08-30 14:09:37'),
(20,'publicidad.ver','publicidad','Ver la publicidad','2026-08-30 14:09:37'),
(21,'publicidad.editar','publicidad','Crear y editar anuncios','2026-08-30 14:09:37'),
(22,'campanas.ver','campanas','Ver campanas','2026-08-30 14:09:37'),
(23,'campanas.enviar','campanas','Enviar campanas','2026-08-30 14:09:37'),
(24,'contenido.ver','contenido','Ver el contenido web','2026-08-30 14:09:37'),
(25,'contenido.editar','contenido','Editar la pagina web','2026-08-30 14:09:37'),
(26,'ajustes.ver','ajustes','Ver los ajustes','2026-08-30 14:09:37'),
(27,'ajustes.editar','ajustes','Cambiar los ajustes','2026-08-30 14:09:37'),
(28,'reportes.ver','reportes','Ver informes','2026-08-30 14:09:37'),
(29,'sistema.mantenimiento','sistema','Ejecutar mantenimiento','2026-08-30 14:09:37'),
(30,'sistema.auditoria','sistema','Ver la auditoria','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
DROP TABLE IF EXISTS `push_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `platform` enum('android','ios','web') NOT NULL DEFAULT 'android',
  `device_name` varchar(120) NOT NULL DEFAULT '',
  `app_version` varchar(20) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_token` (`token`),
  KEY `idx_push_user` (`user_id`,`is_active`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `push_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_devices` ENABLE KEYS */;
DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `bucket_key` char(64) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`bucket_key`),
  KEY `idx_rate_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
DROP TABLE IF EXISTS `refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `refresh_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `device_id` varchar(80) NOT NULL DEFAULT '',
  `device_name` varchar(120) NOT NULL DEFAULT '',
  `platform` varchar(20) NOT NULL DEFAULT '',
  `app_version` varchar(20) NOT NULL DEFAULT '',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `parent_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cadena de rotacion, detecta reutilizacion',
  `revoked_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refresh_token` (`token_hash`),
  KEY `idx_refresh_user` (`user_id`,`revoked_at`),
  KEY `idx_refresh_expires` (`expires_at`),
  CONSTRAINT `fk_refresh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `refresh_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `refresh_tokens` ENABLE KEYS */;
DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'estacion',
  `capacity` smallint(5) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resources_branch` (`branch_id`,`is_active`),
  CONSTRAINT `fk_resources_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `resources` DISABLE KEYS */;
INSERT INTO `resources` (`id`, `branch_id`, `name`, `type`, `capacity`, `is_active`, `created_at`) VALUES (1,1,'Estacion 1','estacion',1,1,'2026-08-30 14:09:37'),
(2,1,'Estacion 2','estacion',1,1,'2026-08-30 14:09:37'),
(3,1,'Estacion 3','estacion',1,1,'2026-08-30 14:09:37');
/*!40000 ALTER TABLE `resources` ENABLE KEYS */;
DROP TABLE IF EXISTS `retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retention_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `policy_key` varchar(60) NOT NULL,
  `label` varchar(160) NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `target_table` varchar(64) NOT NULL,
  `date_column` varchar(64) NOT NULL DEFAULT 'created_at',
  `retention_days` int(10) unsigned NOT NULL DEFAULT 365,
  `condition_sql` varchar(255) NOT NULL DEFAULT '' COMMENT 'Filtro adicional fijado por el sistema',
  `deletes_files` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Borra tambien los archivos asociados',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `last_deleted_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_retention_key` (`policy_key`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `retention_policies` DISABLE KEYS */;
INSERT INTO `retention_policies` (`id`, `policy_key`, `label`, `description`, `target_table`, `date_column`, `retention_days`, `condition_sql`, `deletes_files`, `is_active`, `last_run_at`, `last_deleted_count`, `created_at`, `updated_at`) VALUES (1,'registro_accesos','Intentos de acceso','Historial de inicios de sesion correctos y fallidos.','login_attempts','created_at',90,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'limites_peticiones','Contadores de limite','Ventanas del limitador de peticiones ya vencidas.','rate_limits','expires_at',1,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'eventos_publicidad','Eventos de publicidad','Impresiones y clics de banners.','banner_events','created_at',180,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(4,'avisos_enviados','Avisos ya enviados','Correos y notificaciones procesados.','notification_queue','created_at',60,'status IN (\'sent\',\'cancelled\',\'failed\')',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(5,'auditoria','Bitacora de auditoria','Registro de acciones del panel.','audit_logs','created_at',730,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(6,'tokens_expirados','Tokens caducados','Sesiones de la app movil ya vencidas.','refresh_tokens','expires_at',30,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(7,'recuperacion_clave','Enlaces de recuperacion','Enlaces de restablecimiento usados o vencidos.','password_resets','expires_at',7,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(8,'verificaciones','Codigos de verificacion','Codigos de correo y telefono vencidos.','email_verifications','expires_at',7,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(9,'destinatarios_campana','Destinatarios de campanas','Detalle de envios de campanas antiguas.','campaign_recipients','created_at',365,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(10,'mensajes_contacto','Mensajes de contacto','Mensajes del formulario web ya atendidos.','contact_messages','created_at',365,'is_read = 1',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(11,'comprobantes_antiguos','Comprobantes de pago','Imagenes de comprobantes de citas muy antiguas.','payment_proofs','created_at',1095,'',1,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(12,'lista_espera','Lista de espera','Solicitudes de lista de espera vencidas.','waitlist','created_at',90,'status IN (\'expired\',\'converted\')',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(13,'historial_mantenimiento','Historial de mantenimiento','Registro de tareas de limpieza ejecutadas.','maintenance_runs','created_at',365,'',0,1,NULL,0,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `retention_policies` ENABLE KEYS */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `staff_id` int(10) unsigned DEFAULT NULL,
  `author_name` varchar(120) NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `comment` text DEFAULT NULL,
  `reply` text DEFAULT NULL COMMENT 'Respuesta publica del negocio',
  `replied_at` datetime DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se publica solo tras moderacion',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_appointment` (`appointment_id`),
  KEY `idx_reviews_approved` (`is_approved`,`created_at`),
  KEY `idx_reviews_staff` (`staff_id`,`is_approved`),
  KEY `fk_review_client` (`client_id`),
  CONSTRAINT `fk_review_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_review_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_review_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,1),
(2,2),
(2,3),
(2,4),
(2,5),
(2,6),
(2,7),
(2,8),
(2,9),
(2,10),
(2,11),
(2,12),
(2,13),
(2,14),
(2,15),
(2,16),
(2,17),
(2,18),
(2,19),
(2,20),
(2,21),
(2,22),
(2,23),
(2,24),
(2,25),
(2,26),
(2,27),
(2,28),
(2,29),
(2,30),
(3,1),
(3,2),
(3,3),
(3,4),
(3,5),
(3,7),
(3,8),
(3,9),
(3,12),
(3,14),
(3,17),
(3,18),
(3,20),
(3,22),
(3,28),
(4,1),
(4,2),
(4,4),
(4,7),
(4,12);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Los roles de sistema no se pueden eliminar',
  `priority` smallint(6) NOT NULL DEFAULT 100,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `slug`, `name`, `description`, `is_system`, `priority`, `created_at`, `updated_at`) VALUES (1,'super_admin','Super administrador','Control total del sistema.',1,10,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'admin','Administrador','Gestiona el negocio completo salvo la seguridad avanzada.',1,20,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'manager','Recepcion','Agenda, clientes y cobros del dia a dia.',1,30,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(4,'staff','Profesional','Ve y atiende su propia agenda.',1,40,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(5,'client','Cliente','Agenda y consulta sus citas.',1,90,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
DROP TABLE IF EXISTS `service_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `icon` varchar(60) NOT NULL DEFAULT '' COMMENT 'Nombre del icono en la interfaz',
  `color` varchar(7) NOT NULL DEFAULT '#8b5cf6',
  `image_path` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_on_home` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` (`id`, `name`, `slug`, `description`, `icon`, `color`, `image_path`, `is_active`, `show_on_home`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Barberia','barberia','Cortes clasicos y modernos, barba y afeitado.','scissors','#c9a227','',1,1,0,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(2,'Peluqueria','peluqueria','Corte, peinado, color y tratamientos.','sparkles','#8b5cf6','',1,1,1,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(3,'Manicure','manicure','Cuidado y decoracion de unias de las manos.','hand','#ec4899','',1,1,2,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(4,'Pedicure','pedicure','Cuidado completo de los pies.','foot','#14b8a6','',1,1,3,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(5,'Estetica','estetica','Faciales, cejas, pestanas y depilacion.','face','#f97316','',1,1,4,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(6,'Otros servicios','otros','Solicita algo que no esta en la lista.','plus','#64748b','',1,1,5,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL);
/*!40000 ALTER TABLE `service_categories` ENABLE KEYS */;
DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `name` varchar(140) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `short_description` varchar(255) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL DEFAULT '',
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `buffer_before_minutes` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Preparacion previa',
  `buffer_after_minutes` smallint(5) unsigned NOT NULL DEFAULT 5 COMMENT 'Limpieza posterior',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promo_price` decimal(10,2) DEFAULT NULL,
  `promo_starts_at` datetime DEFAULT NULL,
  `promo_ends_at` datetime DEFAULT NULL,
  `deposit_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Exige abono para reservar',
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_is_percentage` tinyint(1) NOT NULL DEFAULT 0,
  `requires_consultation` tinyint(1) NOT NULL DEFAULT 0,
  `max_per_day` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '0 = sin limite',
  `loyalty_points` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `bookable_online` tinyint(1) NOT NULL DEFAULT 1,
  `gender_target` enum('all','male','female','kids') NOT NULL DEFAULT 'all',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_slug` (`slug`),
  KEY `idx_services_category` (`category_id`,`is_active`),
  KEY `idx_services_featured` (`is_featured`,`is_active`),
  KEY `idx_services_deleted` (`deleted_at`),
  CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` (`id`, `category_id`, `name`, `slug`, `short_description`, `description`, `image_path`, `duration_minutes`, `buffer_before_minutes`, `buffer_after_minutes`, `price`, `promo_price`, `promo_starts_at`, `promo_ends_at`, `deposit_required`, `deposit_amount`, `deposit_is_percentage`, `requires_consultation`, `max_per_day`, `loyalty_points`, `is_active`, `is_featured`, `bookable_online`, `gender_target`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Corte de cabello clasico','corte-de-cabello-clasico','Corte a tijera y maquina con lavado.',NULL,'',30,0,5,10.00,NULL,NULL,NULL,0,0.00,0,0,0,10,1,1,1,'all',0,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(2,1,'Corte + barba','corte-barba','Corte completo con perfilado y arreglo de barba.',NULL,'',50,0,5,16.00,NULL,NULL,NULL,0,0.00,0,0,0,16,1,1,1,'all',1,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(3,1,'Afeitado clasico','afeitado-clasico','Toalla caliente, navaja y balsamo.',NULL,'',30,0,5,8.00,NULL,NULL,NULL,0,0.00,0,0,0,8,1,1,1,'all',2,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(4,1,'Corte infantil','corte-infantil','Para ninos hasta 12 anos.',NULL,'',25,0,5,8.00,NULL,NULL,NULL,0,0.00,0,0,0,8,1,1,1,'all',3,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(5,2,'Corte y peinado','corte-y-peinado','Corte, lavado y secado con peinado.',NULL,'',45,0,5,15.00,NULL,NULL,NULL,0,0.00,0,0,0,15,1,0,1,'all',4,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(6,2,'Color completo','color-completo','Tinte de raiz a puntas.',NULL,'',90,0,5,40.00,NULL,NULL,NULL,0,0.00,0,0,0,40,1,0,1,'all',5,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(7,2,'Mechas / balayage','mechas-balayage','Tecnica de iluminacion personalizada.',NULL,'',150,0,5,75.00,NULL,NULL,NULL,0,0.00,0,0,0,75,1,0,1,'all',6,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(8,2,'Tratamiento de hidratacion','tratamiento-de-hidratacion','Reparacion profunda para cabello danado.',NULL,'',60,0,5,25.00,NULL,NULL,NULL,0,0.00,0,0,0,25,1,0,1,'all',7,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(9,3,'Manicure clasica','manicure-clasica','Limado, cuticula y esmalte.',NULL,'',40,0,5,12.00,NULL,NULL,NULL,0,0.00,0,0,0,12,1,0,1,'all',8,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(10,3,'Unias acrilicas','unias-acrilicas','Aplicacion completa con diseno.',NULL,'',90,0,5,30.00,NULL,NULL,NULL,0,0.00,0,0,0,30,1,0,1,'all',9,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(11,4,'Pedicure spa','pedicure-spa','Exfoliacion, masaje y esmalte.',NULL,'',60,0,5,18.00,NULL,NULL,NULL,0,0.00,0,0,0,18,1,0,1,'all',10,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(12,5,'Limpieza facial profunda','limpieza-facial-profunda','Higiene facial con extraccion.',NULL,'',60,0,5,28.00,NULL,NULL,NULL,0,0.00,0,0,0,28,1,0,1,'all',11,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(13,5,'Diseno de cejas','diseno-de-cejas','Perfilado y depilacion.',NULL,'',25,0,5,10.00,NULL,NULL,NULL,0,0.00,0,0,0,10,1,0,1,'all',12,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL),
(14,5,'Lifting de pestanas','lifting-de-pestanas','Curvatura y tinte.',NULL,'',60,0,5,30.00,NULL,NULL,NULL,0,0.00,0,0,0,30,1,0,1,'all',13,'2026-08-30 14:09:37','2026-08-30 14:09:37',NULL);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(40) NOT NULL COMMENT 'business, theme, booking, seo, app, system...',
  `setting_key` varchar(120) NOT NULL COMMENT 'grupo.clave, ej. business.name',
  `setting_value` text DEFAULT NULL,
  `value_type` enum('string','text','html','int','float','bool','json','color','image','file','url','email','time','select') NOT NULL DEFAULT 'string',
  `label` varchar(160) NOT NULL DEFAULT '',
  `help_text` varchar(500) NOT NULL DEFAULT '',
  `options_json` text DEFAULT NULL COMMENT 'Opciones para el tipo select',
  `is_public` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Se expone a la web y a la app movil',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`group_name`,`sort_order`),
  KEY `idx_settings_public` (`is_public`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`id`, `group_name`, `setting_key`, `setting_value`, `value_type`, `label`, `help_text`, `options_json`, `is_public`, `is_encrypted`, `sort_order`, `updated_by`, `created_at`, `updated_at`) VALUES (1,'business','business.name','Mi Barberia & Estilo','string','Nombre del negocio','Aparece en la web, la app y los correos.',NULL,1,0,1,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(2,'business','business.tagline','Tu mejor version empieza aqui','string','Lema','Frase corta bajo el nombre.',NULL,1,0,2,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(3,'business','business.description','Barberia, peluqueria y estetica. Cortes, color, barba, manicure y pedicure con cita previa.','text','Descripcion','Texto de presentacion del negocio.',NULL,1,0,3,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(4,'business','business.phone','','string','Telefono','Numero visible para los clientes.',NULL,1,0,4,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(5,'business','business.whatsapp','','string','WhatsApp','Numero con codigo de pais, sin signos.',NULL,1,0,5,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(6,'business','business.email','','email','Correo de contacto','',NULL,1,0,6,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(7,'business','business.address','','string','Direccion','',NULL,1,0,7,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(8,'business','business.city','','string','Ciudad','',NULL,1,0,8,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(9,'business','business.maps_url','','url','Enlace de Google Maps','Para el boton \"Como llegar\".',NULL,1,0,9,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(10,'business','business.logo','','image','Logotipo','PNG o WEBP con fondo transparente.',NULL,1,0,10,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(11,'business','business.favicon','','image','Icono del navegador','',NULL,1,0,11,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(12,'business','business.timezone','America/Guayaquil','string','Zona horaria','Determina los horarios de la agenda.',NULL,1,0,12,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(13,'business','business.currency','USD','string','Moneda','Codigo de 3 letras.',NULL,1,0,13,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(14,'business','business.currency_symbol','$','string','Simbolo de moneda','',NULL,1,0,14,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(15,'business','business.currency_position','before','select','Posicion del simbolo','','{\"before\":\"Antes del importe ($ 10)\",\"after\":\"Despues del importe (10 $)\"}',1,0,15,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(16,'business','business.currency_decimals','2','int','Decimales','',NULL,1,0,16,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(17,'business','business.tax_percent','0','float','Impuesto (%)','Se suma al total de la cita. 0 para no aplicar.',NULL,1,0,17,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(18,'theme','theme.primary_color','#c9a227','color','Color principal','Botones y acentos.',NULL,1,0,18,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(19,'theme','theme.secondary_color','#111827','color','Color secundario','',NULL,1,0,19,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(20,'theme','theme.accent_color','#e11d48','color','Color de realce','Ofertas y avisos.',NULL,1,0,20,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(21,'theme','theme.background_color','#0b0f19','color','Fondo','',NULL,1,0,21,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(22,'theme','theme.surface_color','#141b2d','color','Superficie de tarjetas','',NULL,1,0,22,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(23,'theme','theme.text_color','#e5e7eb','color','Color del texto','',NULL,1,0,23,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(24,'theme','theme.font_heading','Poppins','string','Tipografia de titulos','',NULL,1,0,24,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(25,'theme','theme.font_body','Inter','string','Tipografia del texto','',NULL,1,0,25,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(26,'theme','theme.dark_mode','1','bool','Tema oscuro','Aplica a la web y a la app.',NULL,1,0,26,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(27,'theme','theme.rounded_corners','16','int','Redondeo de esquinas (px)','',NULL,1,0,27,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(28,'booking','booking.enabled','1','bool','Agendamiento en linea activo','Desactivalo para pausar las reservas.',NULL,1,0,28,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(29,'booking','booking.require_login','0','bool','Exigir cuenta para reservar','Si esta apagado se admiten reservas de invitado.',NULL,1,0,29,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(30,'booking','booking.slot_interval_minutes','15','int','Intervalo entre horarios (min)','',NULL,1,0,30,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(31,'booking','booking.min_hours_before','2','int','Antelacion minima (horas)','',NULL,1,0,31,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(32,'booking','booking.max_days_ahead','60','int','Dias maximos de anticipacion','',NULL,1,0,32,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(33,'booking','booking.allow_multiple_services','1','bool','Permitir varios servicios por cita','',NULL,1,0,33,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(34,'booking','booking.max_services_per_appointment','4','int','Servicios maximos por cita','',NULL,1,0,34,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(35,'booking','booking.auto_confirm','0','bool','Confirmar automaticamente','Si no, las citas quedan pendientes de tu aprobacion.',NULL,1,0,35,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(36,'booking','booking.allow_staff_choice','1','bool','El cliente elige profesional','',NULL,1,0,36,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(37,'booking','booking.allow_no_preference','1','bool','Ofrecer \"sin preferencia\"','',NULL,1,0,37,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(38,'booking','booking.cancellation_hours','4','int','Antelacion para cancelar (horas)','',NULL,1,0,38,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(39,'booking','booking.allow_client_cancel','1','bool','El cliente puede cancelar','',NULL,1,0,39,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(40,'booking','booking.allow_client_reschedule','1','bool','El cliente puede reprogramar','',NULL,1,0,40,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(41,'booking','booking.max_active_per_client','3','int','Citas activas por cliente','',NULL,1,0,41,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(42,'booking','booking.custom_request_enabled','1','bool','Permitir peticion libre','Campo \"Otro: especifica lo que necesitas\".',NULL,1,0,42,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(43,'booking','booking.custom_request_label','Otro (especifica lo que necesitas)','string','Texto de la peticion libre','',NULL,1,0,43,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(44,'booking','booking.custom_request_minutes','30','int','Duracion de una peticion libre (min)','',NULL,1,0,44,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(45,'booking','booking.terms_text','','text','Condiciones al reservar','Se muestra antes de confirmar.',NULL,1,0,45,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(46,'payments','payments.enabled','1','bool','Cobros activos','',NULL,1,0,46,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(47,'payments','payments.require_deposit','0','bool','Exigir abono para reservar','',NULL,1,0,47,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(48,'payments','payments.deposit_percent','30','float','Porcentaje de abono','',NULL,1,0,48,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(49,'payments','payments.proof_required_for_transfer','1','bool','Exigir comprobante en transferencias','',NULL,1,0,49,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(50,'payments','payments.transfer_instructions','Realiza la transferencia y sube el comprobante para confirmar tu cita.','text','Instrucciones de transferencia','Texto que ve el cliente al elegir transferencia.',NULL,1,0,50,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(51,'ads','ads.enabled','1','bool','Publicidad activa','Interruptor general de banners y ventanas.',NULL,1,0,51,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(52,'ads','ads.show_on_login','1','bool','Mostrar al iniciar sesion','',NULL,1,0,52,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(53,'ads','ads.show_while_browsing','1','bool','Mostrar mientras navega','',NULL,1,0,53,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(54,'ads','ads.show_on_exit','1','bool','Mostrar al intentar salir','',NULL,1,0,54,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(55,'ads','ads.browsing_delay_seconds','45','int','Segundos antes de la ventana','',NULL,1,0,55,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(56,'ads','ads.max_popups_per_session','2','int','Maximo de ventanas por visita','Evita saturar al visitante.',NULL,1,0,56,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(57,'ads','ads.respect_do_not_track','1','bool','Respetar \"no rastrear\"','',NULL,1,0,57,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(58,'ads','ads.inactive_days','60','int','Dias para considerar cliente inactivo','',NULL,1,0,58,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(59,'app','app.download_url_android','','url','Enlace de descarga (Android)','Google Play o enlace directo.',NULL,1,0,59,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(60,'app','app.download_url_ios','','url','Enlace de descarga (iOS)','',NULL,1,0,60,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(61,'app','app.apk_direct_url','','url','Enlace directo al APK','Para instalar sin tienda.',NULL,1,0,61,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(62,'app','app.latest_version','1.0.0','string','Ultima version publicada','',NULL,1,0,62,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(63,'app','app.min_supported_version','1.0.0','string','Version minima admitida','Por debajo se pide actualizar.',NULL,1,0,63,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(64,'app','app.force_update','0','bool','Forzar actualizacion','',NULL,1,0,64,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(65,'app','app.show_splash_ad','1','bool','Anuncio en la bienvenida de la app','',NULL,1,0,65,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(66,'app','app.promo_text','Descarga la app y agenda en segundos','string','Texto promocional de la app','',NULL,1,0,66,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(67,'notifications','notifications.confirm_email','1','bool','Correo al agendar','',NULL,0,0,67,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(68,'notifications','notifications.reminder_enabled','1','bool','Recordatorio de cita','',NULL,0,0,68,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(69,'notifications','notifications.reminder_hours_before','24','int','Horas antes del recordatorio','',NULL,0,0,69,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(70,'notifications','notifications.followup_enabled','1','bool','Mensaje de seguimiento','',NULL,0,0,70,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(71,'notifications','notifications.review_request_enabled','1','bool','Pedir resena tras la visita','',NULL,0,0,71,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(72,'notifications','notifications.review_request_hours_after','3','int','Horas despues para pedir resena','',NULL,0,0,72,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(73,'loyalty','loyalty.enabled','1','bool','Programa de puntos activo','',NULL,1,0,73,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(74,'loyalty','loyalty.points_per_currency','1','float','Puntos por unidad gastada','',NULL,1,0,74,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(75,'loyalty','loyalty.points_to_currency','100','float','Puntos que equivalen a 1 unidad','',NULL,1,0,75,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(76,'loyalty','loyalty.welcome_points','50','int','Puntos de bienvenida','',NULL,1,0,76,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(77,'loyalty','loyalty.referral_points','100','int','Puntos por recomendar','',NULL,1,0,77,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(78,'seo','seo.meta_title','','string','Titulo para buscadores','',NULL,1,0,78,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(79,'seo','seo.meta_description','','text','Descripcion para buscadores','Hasta 160 caracteres.',NULL,1,0,79,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(80,'seo','seo.og_image','','image','Imagen al compartir','1200x630 px.',NULL,1,0,80,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(81,'seo','seo.google_analytics_id','','string','ID de Google Analytics','',NULL,0,0,81,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(82,'seo','seo.facebook_pixel_id','','string','ID del pixel de Facebook','',NULL,0,0,82,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(83,'social','social.facebook','','url','Facebook','',NULL,1,0,83,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(84,'social','social.instagram','','url','Instagram','',NULL,1,0,84,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(85,'social','social.tiktok','','url','TikTok','',NULL,1,0,85,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(86,'social','social.youtube','','url','YouTube','',NULL,1,0,86,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(87,'legal','legal.privacy_policy','','html','Politica de privacidad','',NULL,1,0,87,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(88,'legal','legal.terms','','html','Terminos y condiciones','',NULL,1,0,88,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(89,'legal','legal.show_cookie_banner','1','bool','Aviso de cookies','',NULL,1,0,89,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(90,'push','push.fcm_server_key','','string','Clave del servidor FCM','Para las notificaciones push de la app.',NULL,0,1,90,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(91,'system','system.maintenance_mode','0','bool','Modo mantenimiento','Cierra la web al publico; el panel sigue activo.',NULL,0,0,91,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(92,'system','system.maintenance_message','Estamos realizando mejoras. Volvemos en unos minutos.','text','Mensaje de mantenimiento','',NULL,1,0,92,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(93,'system','system.auto_purge_enabled','1','bool','Limpieza automatica','Aplica las politicas de retencion cada noche.',NULL,0,0,93,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37'),
(94,'system','system.installed_at','2026-08-30 14:09:37','string','System installed at','',NULL,1,0,0,NULL,'2026-08-30 14:09:37','2026-08-30 14:09:37');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cuenta de acceso, si la tiene',
  `branch_id` int(10) unsigned NOT NULL,
  `display_name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT 'Barbero, Estilista, Manicurista...',
  `bio` text DEFAULT NULL,
  `photo_path` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `instagram` varchar(120) NOT NULL DEFAULT '',
  `color` varchar(7) NOT NULL DEFAULT '#0ea5e9' COMMENT 'Color en la agenda',
  `commission_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `accepts_online` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_on_web` tinyint(1) NOT NULL DEFAULT 1,
  `rating_average` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(10) unsigned NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_slug` (`slug`),
  KEY `idx_staff_branch` (`branch_id`,`is_active`),
  KEY `idx_staff_user` (`user_id`),
  CONSTRAINT `fk_staff_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
INSERT INTO `staff` (`id`, `user_id`, `branch_id`, `display_name`, `slug`, `title`, `bio`, `photo_path`, `phone`, `email`, `instagram`, `color`, `commission_percent`, `accepts_online`, `is_active`, `show_on_web`, `rating_average`, `rating_count`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,NULL,1,'Profesional 1','profesional-1-barbero','Barbero','Edita esta ficha desde el panel para contar la experiencia de tu equipo.','','','','','#0ea5e9',0.00,1,1,1,0.00,0,0,'2026-08-30 14:12:39','2026-08-30 14:12:39',NULL),
(3,NULL,1,'Profesional 2','profesional-2-estilista','Estilista','Edita esta ficha desde el panel para contar la experiencia de tu equipo.','','','','','#8b5cf6',0.00,1,1,1,0.00,0,1,'2026-08-30 14:12:39','2026-08-30 14:12:39',NULL),
(4,NULL,1,'Profesional 3','profesional-3-manicurista','Manicurista','Edita esta ficha desde el panel para contar la experiencia de tu equipo.','','','','','#ec4899',0.00,1,1,1,0.00,0,2,'2026-08-30 14:12:39','2026-08-30 14:12:39',NULL);
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
DROP TABLE IF EXISTS `staff_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) unsigned NOT NULL,
  `weekday` tinyint(3) unsigned NOT NULL COMMENT '0=domingo ... 6=sabado',
  `starts_at` time NOT NULL,
  `ends_at` time NOT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_schedule_staff_day` (`staff_id`,`weekday`,`is_active`),
  CONSTRAINT `fk_schedule_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `staff_schedules` DISABLE KEYS */;
INSERT INTO `staff_schedules` (`id`, `staff_id`, `weekday`, `starts_at`, `ends_at`, `break_start`, `break_end`, `is_active`, `valid_from`, `valid_until`) VALUES (1,2,1,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(2,2,2,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(3,2,3,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(4,2,4,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(5,2,5,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(6,2,6,'09:00:00','17:00:00','13:00:00','14:00:00',1,NULL,NULL),
(7,3,1,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(8,3,2,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(9,3,3,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(10,3,4,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(11,3,5,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(12,3,6,'09:00:00','17:00:00','13:00:00','14:00:00',1,NULL,NULL),
(13,4,1,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(14,4,2,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(15,4,3,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(16,4,4,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(17,4,5,'09:00:00','19:00:00','13:00:00','14:00:00',1,NULL,NULL),
(18,4,6,'09:00:00','17:00:00','13:00:00','14:00:00',1,NULL,NULL);
/*!40000 ALTER TABLE `staff_schedules` ENABLE KEYS */;
DROP TABLE IF EXISTS `staff_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_services` (
  `staff_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `custom_duration` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`staff_id`,`service_id`),
  KEY `idx_ss_service` (`service_id`),
  CONSTRAINT `fk_ss_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `staff_services` DISABLE KEYS */;
INSERT INTO `staff_services` (`staff_id`, `service_id`, `custom_price`, `custom_duration`) VALUES (2,1,NULL,NULL),
(2,2,NULL,NULL),
(2,3,NULL,NULL),
(2,4,NULL,NULL),
(3,5,NULL,NULL),
(3,6,NULL,NULL),
(3,7,NULL,NULL),
(3,8,NULL,NULL),
(3,12,NULL,NULL),
(3,13,NULL,NULL),
(3,14,NULL,NULL),
(4,9,NULL,NULL),
(4,10,NULL,NULL),
(4,11,NULL,NULL);
/*!40000 ALTER TABLE `staff_services` ENABLE KEYS */;
DROP TABLE IF EXISTS `staff_time_off`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_time_off` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) unsigned NOT NULL,
  `starts_at` datetime NOT NULL COMMENT 'UTC',
  `ends_at` datetime NOT NULL COMMENT 'UTC',
  `reason` varchar(160) NOT NULL DEFAULT '',
  `is_full_day` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_timeoff_staff_range` (`staff_id`,`starts_at`,`ends_at`),
  KEY `fk_timeoff_user` (`created_by`),
  CONSTRAINT `fk_timeoff_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timeoff_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `staff_time_off` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_time_off` ENABLE KEYS */;
DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscribers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL DEFAULT '',
  `source` varchar(40) NOT NULL DEFAULT 'web',
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `confirmed_at` datetime DEFAULT NULL,
  `unsubscribed_at` datetime DEFAULT NULL,
  `unsubscribe_token` char(32) NOT NULL,
  `consent_ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriber_email` (`email`),
  UNIQUE KEY `uq_subscriber_token` (`unsubscribe_token`),
  KEY `idx_subscriber_active` (`is_confirmed`,`unsubscribed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscribers` ENABLE KEYS */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `role` varchar(40) NOT NULL DEFAULT 'client',
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `phone` varchar(20) NOT NULL DEFAULT '',
  `phone_verified_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `password_changed_at` datetime DEFAULT NULL,
  `avatar_path` varchar(255) NOT NULL DEFAULT '',
  `birth_date` date DEFAULT NULL COMMENT 'Permite campanas de cumpleanos',
  `gender` varchar(20) NOT NULL DEFAULT '',
  `notes` text DEFAULT NULL COMMENT 'Notas internas del personal sobre el cliente',
  `status` enum('active','pending','blocked') NOT NULL DEFAULT 'active',
  `locale` varchar(10) NOT NULL DEFAULT 'es',
  `accepts_marketing` tinyint(1) NOT NULL DEFAULT 0,
  `accepts_email` tinyint(1) NOT NULL DEFAULT 1,
  `accepts_sms` tinyint(1) NOT NULL DEFAULT 0,
  `accepts_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `accepts_push` tinyint(1) NOT NULL DEFAULT 1,
  `marketing_consent_at` datetime DEFAULT NULL,
  `marketing_consent_ip` varchar(45) NOT NULL DEFAULT '',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(255) NOT NULL DEFAULT '' COMMENT 'Cifrado con la clave de la aplicacion',
  `two_factor_recovery` text DEFAULT NULL COMMENT 'Codigos de respaldo cifrados',
  `failed_logins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) NOT NULL DEFAULT '',
  `tokens_valid_after` datetime DEFAULT NULL COMMENT 'Invalida tokens emitidos antes de esta fecha',
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `total_visits` int(10) unsigned NOT NULL DEFAULT 0,
  `total_spent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_visit_at` datetime DEFAULT NULL,
  `referral_code` varchar(20) NOT NULL DEFAULT '',
  `referred_by_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(40) NOT NULL DEFAULT 'web' COMMENT 'web, app, panel, importacion',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `anonymized_at` datetime DEFAULT NULL COMMENT 'Marca de derecho al olvido aplicado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_uuid` (`uuid`),
  KEY `idx_users_role_status` (`role`,`status`),
  KEY `idx_users_phone` (`phone`),
  KEY `idx_users_deleted` (`deleted_at`),
  KEY `idx_users_marketing` (`accepts_marketing`,`status`),
  KEY `idx_users_referral` (`referral_code`),
  KEY `fk_users_referrer` (`referred_by_id`),
  CONSTRAINT `fk_users_referrer` FOREIGN KEY (`referred_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `uuid`, `role`, `first_name`, `last_name`, `email`, `email_verified_at`, `phone`, `phone_verified_at`, `password_hash`, `password_changed_at`, `avatar_path`, `birth_date`, `gender`, `notes`, `status`, `locale`, `accepts_marketing`, `accepts_email`, `accepts_sms`, `accepts_whatsapp`, `accepts_push`, `marketing_consent_at`, `marketing_consent_ip`, `two_factor_enabled`, `two_factor_secret`, `two_factor_recovery`, `failed_logins`, `locked_until`, `last_login_at`, `last_login_ip`, `tokens_valid_after`, `loyalty_points`, `total_visits`, `total_spent`, `last_visit_at`, `referral_code`, `referred_by_id`, `source`, `created_at`, `updated_at`, `deleted_at`, `anonymized_at`) VALUES (1,'ffaabe71-9676-433e-b3f8-90ff9af6da4b','super_admin','Administrador','','admin@mibarberia.com','2026-08-30 14:09:37','',NULL,'$argon2id$v=19$m=65536,t=4,p=2$WTNiaEZZS1RqQ2JVZHozOA$WcFKdLFpFyexu4Wi8crtsSP6YxPWwML4DHaw5zQtgxs','2026-08-31 14:50:24','',NULL,'',NULL,'active','es',0,1,0,0,1,NULL,'',0,'',NULL,0,NULL,'2026-08-31 14:50:26','127.0.0.1','2026-08-31 14:50:24',0,0,0.00,NULL,'D4A3DDCF',NULL,'instalacion','2026-08-30 14:09:37','2026-08-31 14:50:24',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
DROP TABLE IF EXISTS `waitlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waitlist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `staff_id` int(10) unsigned DEFAULT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `client_name` varchar(160) NOT NULL,
  `client_phone` varchar(20) NOT NULL DEFAULT '',
  `desired_date` date NOT NULL,
  `desired_from` time DEFAULT NULL,
  `desired_to` time DEFAULT NULL,
  `status` enum('waiting','notified','converted','expired') NOT NULL DEFAULT 'waiting',
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_waitlist_date` (`desired_date`,`status`),
  KEY `idx_waitlist_client` (`client_id`),
  KEY `fk_wl_branch` (`branch_id`),
  KEY `fk_wl_staff` (`staff_id`),
  KEY `fk_wl_service` (`service_id`),
  CONSTRAINT `fk_wl_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wl_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wl_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wl_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `waitlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `waitlist` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS = 1;
-- Fin del volcado.
