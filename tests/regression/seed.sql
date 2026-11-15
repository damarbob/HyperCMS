
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
 SET NAMES utf8mb4 ;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `auth_groups_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `auth_groups_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_groups_users_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_groups_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_groups_users` WRITE;
/*!40000 ALTER TABLE `auth_groups_users` DISABLE KEYS */;
INSERT INTO `auth_groups_users` VALUES (1,1,'superadmin','2026-11-14 11:13:34');
/*!40000 ALTER TABLE `auth_groups_users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `auth_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `auth_identities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `secret` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `secret2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `extra` text COLLATE utf8mb4_general_ci,
  `force_reset` tinyint(1) NOT NULL DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_secret` (`type`,`secret`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `auth_identities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_identities` WRITE;
/*!40000 ALTER TABLE `auth_identities` DISABLE KEYS */;
INSERT INTO `auth_identities` VALUES (1,1,'email_password',NULL,'capture_admin@example.test','$2y$12$s.1kadlkphT/Q/ojvRZrGORjTdMFv/oBFsLMcvlOGveO74KnQPBRK',NULL,NULL,0,NULL,'2026-11-14 11:13:34','2026-11-14 11:13:34'),(2,1,'access_token','api-login','b03e3b724a361de13db8e601fa318ffd9fbc937863cf920932a7dd8709becc7b',NULL,NULL,'a:1:{i:0;s:12:\"entries.read\";}',0,'2026-11-14 11:15:28','2026-11-14 11:15:24','2026-11-14 11:15:28'),(3,1,'access_token','api-login','a95a229edce81046258897e900bd4145e6d01f20cd14054cd7053e0c9339e250',NULL,NULL,'a:1:{i:0;s:12:\"entries.read\";}',0,'2026-11-14 11:15:53','2026-11-14 11:15:50','2026-11-14 11:15:53'),(4,1,'access_token','api-login','398d146a0b95a85a3f6e24bbfdea71c8024f4147d31cb1178d319482657d1ffd',NULL,NULL,'a:1:{i:0;s:12:\"entries.read\";}',0,'2026-11-14 11:16:35','2026-11-14 11:16:32','2026-11-14 11:16:35'),(5,1,'access_token','api-login','7b74e7e95af0021594c3a8e74600c3e23e66907d9fef9cd76fc50b97c1a4a4bd',NULL,NULL,'a:1:{i:0;s:12:\"entries.read\";}',0,'2026-11-14 11:16:51','2026-11-14 11:16:47','2026-11-14 11:16:51');
/*!40000 ALTER TABLE `auth_identities` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `auth_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `auth_logins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_identifier` (`id_type`,`identifier`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_logins` WRITE;
/*!40000 ALTER TABLE `auth_logins` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_logins` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `auth_permissions_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `auth_permissions_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_permissions_users_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_permissions_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_permissions_users` WRITE;
/*!40000 ALTER TABLE `auth_permissions_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_permissions_users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `auth_remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `auth_remember_tokens` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `selector` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `hashedValidator` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `expires` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `auth_remember_tokens_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_remember_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_remember_tokens` WRITE;
/*!40000 ALTER TABLE `auth_remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `auth_token_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `auth_token_logins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_identifier` (`id_type`,`identifier`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_token_logins` WRITE;
/*!40000 ALTER TABLE `auth_token_logins` DISABLE KEYS */;
INSERT INTO `auth_token_logins` VALUES (1,'::1','','access_token','',NULL,'2026-11-14 11:15:28',0),(2,'::1','','access_token','',NULL,'2026-11-14 11:15:29',0),(3,'::1','','access_token','',NULL,'2026-11-14 11:15:29',0),(4,'::1','','access_token','',NULL,'2026-11-14 11:15:54',0),(5,'::1','','access_token','',NULL,'2026-11-14 11:15:54',0),(6,'::1','','access_token','',NULL,'2026-11-14 11:15:54',0),(7,'::1','','access_token','',NULL,'2026-11-14 11:16:36',0),(8,'::1','','access_token','',NULL,'2026-11-14 11:16:36',0),(9,'::1','','access_token','',NULL,'2026-11-14 11:16:36',0),(10,'::1','','access_token','',NULL,'2026-11-14 11:16:51',0),(11,'::1','','access_token','',NULL,'2026-11-14 11:16:51',0),(12,'::1','','access_token','',NULL,'2026-11-14 11:16:51',0);
/*!40000 ALTER TABLE `auth_token_logins` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `current_entry_data_id` int(11) DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `deleter_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entries_model_history` (`model_id`,`deleted_at`,`id`),
  KEY `idx_entries_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `entries` WRITE;
/*!40000 ALTER TABLE `entries` DISABLE KEYS */;
INSERT INTO `entries` VALUES (1,1,45,1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:38',NULL),(2,1,46,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:38',NULL),(3,1,3,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(4,1,4,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(5,1,5,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(6,1,6,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(7,1,7,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(8,1,8,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(9,1,9,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(10,1,10,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(11,1,11,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(12,1,12,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(13,1,13,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(14,1,14,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(15,1,15,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(16,1,16,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(17,1,17,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(18,1,18,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(19,1,19,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(20,1,20,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(21,1,21,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(22,1,22,1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(23,1,23,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(24,1,24,1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(25,1,25,1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(26,2,26,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(27,2,27,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(28,2,28,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(29,2,29,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(30,2,30,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(31,2,31,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(32,2,32,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(33,2,33,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(34,2,34,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(35,2,35,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(36,2,36,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(37,2,37,1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(38,3,38,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(39,3,39,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(40,3,40,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(41,4,41,1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(42,4,42,1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(43,5,43,1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL);
/*!40000 ALTER TABLE `entries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `entry_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `entry_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NOT NULL,
  `fields` longtext NOT NULL,
  `creator_id` int(11) NOT NULL,
  `deleter_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entry_data_history` (`entry_id`,`deleted_at`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `entry_data` WRITE;
/*!40000 ALTER TABLE `entry_data` DISABLE KEYS */;
INSERT INTO `entry_data` VALUES (1,1,'{\"title\":\"Getting started with HyperCMS\",\"body\":\"<div class=\\\"x\\\">Post 1</div>\",\"views\":\"10\",\"published_at\":\"2026-02-02T14:05:00\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(2,2,'{\"title\":\"apple pie recipe\",\"body\":\"<div class=\\\"x\\\">Post 2</div>\",\"views\":\"20\",\"published_at\":\"2026-01-03T09:30\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(3,3,'{\"title\":\"Apple pie recipe\",\"body\":\"Plain body 3\",\"views\":\"\",\"published_at\":\"2026-02-04T14:05:00\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(4,4,'{\"title\":\"Éclair for beginners\",\"body\":\"<div class=\\\"x\\\">Post 4</div>\",\"views\":\"40\",\"published_at\":\"\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(5,5,'{\"title\":\"eclair filling\",\"body\":\"<p>Body of post 5 with <em>markup</em> and a \\\"quote\\\" and a back\\\\slash ?</p>\",\"views\":\"50\",\"published_at\":\"2026-02-06T14:05:00\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(6,6,'{\"title\":\"  Leading space title\",\"body\":\"Plain body 6\",\"views\":\"60\",\"published_at\":\"2026-01-07T09:30\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(7,7,'{\"title\":\"<b>Bold</b> headline\",\"body\":\"<div class=\\\"x\\\">Post 7</div>\",\"views\":\"12.50\",\"published_at\":\"2026-02-08T14:05:00\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(8,8,'{\"title\":\"Zebra crossing\",\"body\":\"<div class=\\\"x\\\">Post 8</div>\",\"views\":\"80\",\"published_at\":\"\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(9,9,'{\"title\":\"Ångström units explained\",\"body\":\"Plain body 9\",\"views\":\"90\",\"published_at\":\"2026-02-10T14:05:00\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(10,10,'{\"title\":\"Quarterly report 2026\",\"body\":\"<p>Body of post 10 with <em>markup</em> and a \\\"quote\\\" and a back\\\\slash ?</p>\",\"views\":\"100\",\"published_at\":\"2026-01-11T09:30\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(11,11,'{\"title\":\"Sample post 11\",\"body\":\"<div class=\\\"x\\\">Post 11</div>\",\"views\":\"110\",\"published_at\":\"2026-02-12T14:05:00\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(12,12,'{\"title\":\"Sample post 12\",\"body\":\"Plain body 12\",\"views\":\"\",\"published_at\":\"\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(13,13,'{\"title\":\"Sample post 13\",\"body\":\"<div class=\\\"x\\\">Post 13</div>\",\"views\":\"130\",\"published_at\":\"2026-02-14T14:05:00\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(14,14,'{\"title\":\"Sample post 14\",\"body\":\"<div class=\\\"x\\\">Post 14</div>\",\"views\":\"140\",\"published_at\":\"2026-01-15T09:30\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(15,15,'{\"title\":\"Sample post 15\",\"body\":\"<p>Body of post 15 with <em>markup</em> and a \\\"quote\\\" and a back\\\\slash ?</p>\",\"views\":\"150\",\"published_at\":\"2026-02-16T14:05:00\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(16,16,'{\"title\":\"Sample post 16\",\"body\":\"<div class=\\\"x\\\">Post 16</div>\",\"views\":\"160\",\"published_at\":\"\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(17,17,'{\"title\":\"Sample post 17\",\"body\":\"<div class=\\\"x\\\">Post 17</div>\",\"views\":\"170\",\"published_at\":\"2026-02-18T14:05:00\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(18,18,'{\"title\":\"Sample post 18\",\"body\":\"Plain body 18\",\"views\":\"180\",\"published_at\":\"2026-01-19T09:30\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(19,19,'{\"title\":\"Sample post 19\",\"body\":\"<div class=\\\"x\\\">Post 19</div>\",\"views\":\"190\",\"published_at\":\"2026-02-20T14:05:00\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(20,20,'{\"title\":\"Sample post 20\",\"body\":\"<p>Body of post 20 with <em>markup</em> and a \\\"quote\\\" and a back\\\\slash ?</p>\",\"views\":\"200\",\"published_at\":\"\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(21,21,'[{\"id\":\"title\",\"value\":\"Sample post 21\"},{\"id\":\"views\",\"value\":\"210\"},{\"id\":\"body\",\"value\":\"Plain body 21\"}]',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(22,22,'[{\"id\":\"title\",\"value\":\"Sample post 22\"},{\"id\":\"views\",\"value\":\"220\"},{\"id\":\"body\",\"value\":\"<div class=\\\"x\\\">Post 22</div>\"}]',1,NULL,'2026-11-14 11:13:36','2026-11-14 11:13:36',NULL),(23,23,'[{\"id\":\"title\",\"value\":\"Sample post 23\"},{\"id\":\"views\",\"value\":\"230\"},{\"id\":\"body\",\"value\":\"<div class=\\\"x\\\">Post 23</div>\"}]',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(24,24,'[{\"id\":\"title\",\"value\":\"Sample post 24\"},{\"id\":\"views\",\"value\":\"240\"},{\"id\":\"body\",\"value\":\"Plain body 24\"}]',1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(25,25,'[{\"id\":\"title\",\"value\":\"Sample post 25\"},{\"id\":\"views\",\"value\":\"250\"},{\"id\":\"body\",\"value\":\"<p>Body of post 25 with <em>markup</em> and a \\\"quote\\\" and a back\\\\slash ?</p>\"}]',1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(26,26,'{\"sku\":\"SKU-001\",\"price\":\"3.50\",\"category\":\"a\",\"added\":\"2026-03-01T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(27,27,'{\"sku\":\"SKU-002\",\"price\":\"7.00\",\"category\":\"b\",\"added\":\"2026-03-02T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(28,28,'{\"sku\":\"SKU-003\",\"price\":\"10.50\",\"category\":\"a\",\"added\":\"2026-03-03T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(29,29,'{\"sku\":\"SKU-004\",\"price\":\"\",\"category\":\"b\",\"added\":\"2026-03-04T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(30,30,'{\"sku\":\"SKU-005\",\"price\":\"17.50\",\"category\":\"a\",\"added\":\"2026-03-05T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(31,31,'{\"sku\":\"SKU-006\",\"price\":\"21.00\",\"category\":\"b\",\"added\":\"2026-03-06T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(32,32,'{\"sku\":\"SKU-007\",\"price\":\"24.50\",\"category\":\"a\",\"added\":\"2026-03-07T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(33,33,'{\"sku\":\"SKU-008\",\"price\":\"\",\"category\":\"b\",\"added\":\"2026-03-08T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(34,34,'{\"sku\":\"SKU-009\",\"price\":\"31.50\",\"category\":\"a\",\"added\":\"2026-03-09T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(35,35,'{\"sku\":\"SKU-010\",\"price\":\"35.00\",\"category\":\"b\",\"added\":\"2026-03-10T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(36,36,'{\"sku\":\"SKU-011\",\"price\":\"38.50\",\"category\":\"a\",\"added\":\"2026-03-11T08:00\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(37,37,'{\"sku\":\"SKU-012\",\"price\":\"\",\"category\":\"b\",\"added\":\"2026-03-12T08:00\"}',1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(38,38,'{\"title\":\"Link 1\",\"related_model\":\"1\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(39,39,'{\"title\":\"Link 2\",\"related_model\":\"2\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(40,40,'{\"title\":\"Link 3\",\"related_model\":\"3\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(41,41,'{\"note\":\"one\"}',1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(42,42,'{\"note\":\"two\"}',1,1,'2026-11-14 11:13:37','2026-11-14 11:13:38','2026-11-14 11:13:38'),(43,43,'{\"secret\":\"hidden\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(44,1,'{\"title\":\"Getting started with HyperCMS (edited)\",\"body\":\"<p>v2</p>\",\"views\":\"15\",\"published_at\":\"2026-01-05T09:30\",\"tags\":[\"news\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:37','2026-11-14 11:13:37',NULL),(45,1,'{\"title\":\"Getting started with HyperCMS (edited twice)\",\"body\":\"<p>v3</p>\",\"views\":\"16\",\"published_at\":\"2026-01-05T09:30\",\"tags\":[\"news\",\"tech\"],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:38','2026-11-14 11:13:38',NULL),(46,2,'{\"title\":\"apple pie recipe v2\",\"body\":\"changed\",\"views\":\"25\",\"published_at\":\"\",\"tags\":[],\"cover\":\"\"}',1,NULL,'2026-11-14 11:13:38','2026-11-14 11:13:38',NULL);
/*!40000 ALTER TABLE `entry_data` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `class` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `namespace` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2020-12-28-223112','CodeIgniter\\Shield\\Database\\Migrations\\CreateAuthTables','default','CodeIgniter\\Shield',1794654701,1),(2,'2021-07-04-041948','CodeIgniter\\Settings\\Database\\Migrations\\CreateSettingsTable','default','CodeIgniter\\Settings',1794654701,1),(3,'2021-11-14-143905','CodeIgniter\\Settings\\Database\\Migrations\\AddContextColumn','default','CodeIgniter\\Settings',1794654701,1),(4,'2023-10-12-112040','CodeIgniter\\Queue\\Database\\Migrations\\AddQueueTables','default','CodeIgniter\\Queue',1794654702,1),(5,'2023-11-05-064053','CodeIgniter\\Queue\\Database\\Migrations\\AddPriorityField','default','CodeIgniter\\Queue',1794654702,1),(6,'2024-12-27-110712','CodeIgniter\\Queue\\Database\\Migrations\\ChangePayloadFieldTypeInSqlsrv','default','CodeIgniter\\Queue',1794654702,1),(7,'2025-01-01-000000','StarDust\\Database\\Migrations\\CreateAuthTablesPolyfill','default','StarDust',1794654702,1),(8,'2025-03-24-025000','StarDust\\Database\\Migrations\\CreateHyperTables','default','StarDust',1794654747,1),(9,'2025-12-16-095000','StarDust\\Database\\Migrations\\AddPerformanceIndexes','default','StarDust',1794654748,2),(10,'2025-12-16-110000','StarDust\\Database\\Migrations\\AddCurrentVersionColumns','default','StarDust',1794654748,2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `model_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `model_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `fields` longtext NOT NULL,
  `group` text,
  `user_groups` text,
  `icon` text,
  `creator_id` int(11) NOT NULL,
  `deleter_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_model_data_history` (`model_id`,`deleted_at`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `model_data` WRITE;
/*!40000 ALTER TABLE `model_data` DISABLE KEYS */;
INSERT INTO `model_data` VALUES (1,1,'Article','[{\"id\":\"title\",\"label\":\"Title\",\"type\":\"text\"},{\"id\":\"body\",\"label\":\"Body\",\"type\":\"textarea\"},{\"id\":\"views\",\"label\":\"Views\",\"type\":\"number\"},{\"id\":\"published_at\",\"label\":\"Published\",\"type\":\"datetime-local\"},{\"id\":\"tags\",\"label\":\"Tags\",\"type\":\"checkboxes\",\"options\":[{\"value\":\"news\",\"label\":\"News\"},{\"value\":\"tech\",\"label\":\"Tech\"}]},{\"id\":\"cover\",\"label\":\"Cover\",\"type\":\"file\"}]','Content','[]','file-text',1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(2,2,'Product','[{\"id\":\"sku\",\"label\":\"SKU\",\"type\":\"text\"},{\"id\":\"price\",\"label\":\"Price\",\"type\":\"number\"},{\"id\":\"category\",\"label\":\"Category\",\"type\":\"select\",\"options\":[{\"value\":\"a\",\"label\":\"A\"},{\"value\":\"b\",\"label\":\"B\"}]},{\"id\":\"added\",\"label\":\"Added\",\"type\":\"datetime-local\"}]','Content','[\"superadmin\"]','file-text',1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(3,3,'Linked','[{\"id\":\"title\",\"label\":\"Title\",\"type\":\"text\"},{\"id\":\"related_model\",\"label\":\"Model\",\"type\":\"select\",\"options\":{\"type\":\"data\",\"content\":{\"table\":\"models\",\"select\":\"id as value, name as label\"}}}]','Content','[]','file-text',1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(4,4,'Scratch','[{\"id\":\"note\",\"label\":\"Note\",\"type\":\"text\"}]','Content','[]','file-text',1,1,'2026-11-14 11:13:35','2026-11-14 11:13:38','2026-11-14 11:13:38'),(5,5,'Restricted','[{\"id\":\"secret\",\"label\":\"Secret\",\"type\":\"text\"}]','Content','[\"developer\"]','file-text',1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL);
/*!40000 ALTER TABLE `model_data` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `current_model_data_id` int(11) DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `deleter_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `models` WRITE;
/*!40000 ALTER TABLE `models` DISABLE KEYS */;
INSERT INTO `models` VALUES (1,1,1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(2,2,1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(3,3,1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL),(4,4,1,1,'2026-11-14 11:13:35','2026-11-14 11:13:38','2026-11-14 11:13:38'),(5,5,1,NULL,'2026-11-14 11:13:35','2026-11-14 11:13:35',NULL);
/*!40000 ALTER TABLE `models` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `queue_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `queue_jobs` (
  `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` text COLLATE utf8mb4_general_ci NOT NULL,
  `priority` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'default',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `queue_priority_status_available_at` (`queue`,`priority`,`status`,`available_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `queue_jobs` WRITE;
/*!40000 ALTER TABLE `queue_jobs` DISABLE KEYS */;
INSERT INTO `queue_jobs` VALUES (1,'stardust-indexes','{\"job\":\"StarDust\\\\Jobs\\\\SyncIndexerJob\",\"data\":{\"modelDefinition\":[{\"id\":\"title\",\"label\":\"Title\",\"type\":\"text\"},{\"id\":\"body\",\"label\":\"Body\",\"type\":\"textarea\"},{\"id\":\"views\",\"label\":\"Views\",\"type\":\"number\"},{\"id\":\"published_at\",\"label\":\"Published\",\"type\":\"datetime-local\"},{\"id\":\"tags\",\"label\":\"Tags\",\"type\":\"checkboxes\",\"options\":[{\"value\":\"news\",\"label\":\"News\"},{\"value\":\"tech\",\"label\":\"Tech\"}]},{\"id\":\"cover\",\"label\":\"Cover\",\"type\":\"file\"}],\"modelId\":1},\"metadata\":[]}','default',0,0,1794654815,1794654815),(2,'stardust-indexes','{\"job\":\"StarDust\\\\Jobs\\\\SyncIndexerJob\",\"data\":{\"modelDefinition\":[{\"id\":\"sku\",\"label\":\"SKU\",\"type\":\"text\"},{\"id\":\"price\",\"label\":\"Price\",\"type\":\"number\"},{\"id\":\"category\",\"label\":\"Category\",\"type\":\"select\",\"options\":[{\"value\":\"a\",\"label\":\"A\"},{\"value\":\"b\",\"label\":\"B\"}]},{\"id\":\"added\",\"label\":\"Added\",\"type\":\"datetime-local\"}],\"modelId\":2},\"metadata\":[]}','default',0,0,1794654815,1794654815),(3,'stardust-indexes','{\"job\":\"StarDust\\\\Jobs\\\\SyncIndexerJob\",\"data\":{\"modelDefinition\":[{\"id\":\"title\",\"label\":\"Title\",\"type\":\"text\"},{\"id\":\"related_model\",\"label\":\"Model\",\"type\":\"select\",\"options\":{\"type\":\"data\",\"content\":{\"table\":\"models\",\"select\":\"id as value, name as label\"}}}],\"modelId\":3},\"metadata\":[]}','default',0,0,1794654815,1794654815),(4,'stardust-indexes','{\"job\":\"StarDust\\\\Jobs\\\\SyncIndexerJob\",\"data\":{\"modelDefinition\":[{\"id\":\"note\",\"label\":\"Note\",\"type\":\"text\"}],\"modelId\":4},\"metadata\":[]}','default',0,0,1794654815,1794654815),(5,'stardust-indexes','{\"job\":\"StarDust\\\\Jobs\\\\SyncIndexerJob\",\"data\":{\"modelDefinition\":[{\"id\":\"secret\",\"label\":\"Secret\",\"type\":\"text\"}],\"modelId\":5},\"metadata\":[]}','default',0,0,1794654815,1794654815);
/*!40000 ALTER TABLE `queue_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `queue_jobs_failed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `queue_jobs_failed` (
  `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
  `connection` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `queue` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` text COLLATE utf8mb4_general_ci NOT NULL,
  `priority` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'default',
  `exception` text COLLATE utf8mb4_general_ci NOT NULL,
  `failed_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `queue_jobs_failed` WRITE;
/*!40000 ALTER TABLE `queue_jobs_failed` DISABLE KEYS */;
/*!40000 ALTER TABLE `queue_jobs_failed` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `settings` (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `class` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` text COLLATE utf8mb4_general_ci,
  `type` varchar(31) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'string',
  `context` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
 SET character_set_client = utf8mb4 ;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_message` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `last_active` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'capture_admin',NULL,NULL,1,'2026-11-14 11:16:51','2026-11-14 11:13:33','2026-11-14 11:13:34',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

