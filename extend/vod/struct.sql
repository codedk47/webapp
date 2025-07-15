-- MySQL dump 10.13  Distrib 8.3.0, for Win64 (x86_64)
--
-- Host: localhost    Database: webapp
-- ------------------------------------------------------
-- Server version	8.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `vod_channels`
--

DROP TABLE IF EXISTS `vod_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vod_channels` (
  `cid` char(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `time` datetime NOT NULL,
  `rate` float(6,4) NOT NULL,
  `pwd` varbinary(16) NOT NULL,
  `name` varchar(16) NOT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vod_channels`
--

LOCK TABLES `vod_channels` WRITE;
/*!40000 ALTER TABLE `vod_channels` DISABLE KEYS */;
/*!40000 ALTER TABLE `vod_channels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vod_nfs`
--

DROP TABLE IF EXISTS `vod_nfs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vod_nfs` (
  `hash` char(12) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `sort` tinyint unsigned NOT NULL,
  `type` tinyint unsigned NOT NULL COMMENT '0:tree,1:file,2:mixed',
  `t0` int unsigned NOT NULL COMMENT 'insert time',
  `t1` int unsigned NOT NULL COMMENT 'update time',
  `size` bigint unsigned NOT NULL,
  `views` bigint unsigned NOT NULL,
  `likes` bigint unsigned NOT NULL,
  `shares` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `node` char(12) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `key` binary(16) DEFAULT NULL COMMENT 'masker',
  `extdata` json DEFAULT NULL,
  PRIMARY KEY (`hash`),
  KEY `sort` (`sort`),
  KEY `type` (`type`),
  KEY `node` (`node`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vod_nfs`
--

LOCK TABLES `vod_nfs` WRITE;
/*!40000 ALTER TABLE `vod_nfs` DISABLE KEYS */;
/*!40000 ALTER TABLE `vod_nfs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vod_records`
--

DROP TABLE IF EXISTS `vod_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vod_records` (
  `dcid` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `init` bigint unsigned NOT NULL,
  `ic` bigint unsigned NOT NULL,
  `iu` bigint unsigned NOT NULL,
  `pv` bigint unsigned NOT NULL,
  `pc` bigint unsigned NOT NULL,
  `ac` bigint unsigned NOT NULL,
  `vw` bigint unsigned NOT NULL,
  `signup` bigint unsigned NOT NULL,
  `signin` bigint unsigned NOT NULL,
  `oi` bigint unsigned NOT NULL,
  `op` bigint unsigned NOT NULL,
  PRIMARY KEY (`dcid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vod_records`
--

LOCK TABLES `vod_records` WRITE;
/*!40000 ALTER TABLE `vod_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `vod_records` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-15 20:07:22
