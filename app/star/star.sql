-- MySQL dump 10.13  Distrib 8.0.27, for Win64 (x86_64)
--
-- Host: localhost    Database: app
-- ------------------------------------------------------
-- Server version	8.0.27

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
-- Table structure for table `ads`
--

DROP TABLE IF EXISTS `ads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ads` (
  `hash` char(12) NOT NULL,
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `expire` int unsigned NOT NULL COMMENT '过期时间',
  `view` int unsigned NOT NULL COMMENT '展示次数',
  `click` int unsigned NOT NULL COMMENT '点击次数',
  `seat` tinyint unsigned NOT NULL COMMENT '位置',
  `weight` tinyint unsigned NOT NULL COMMENT '权重',
  `change` enum('none','sync') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '是否同步',
  `acturl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'URL',
  `name` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '名称',
  PRIMARY KEY (`hash`),
  KEY `seat` (`seat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='广告';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ads`
--

LOCK TABLES `ads` WRITE;
/*!40000 ALTER TABLE `ads` DISABLE KEYS */;
/*!40000 ALTER TABLE `ads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banip`
--

DROP TABLE IF EXISTS `banip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banip` (
  `ip` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `tags` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '标签交集',
  PRIMARY KEY (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banip`
--

LOCK TABLES `banip` WRITE;
/*!40000 ALTER TABLE `banip` DISABLE KEYS */;
/*!40000 ALTER TABLE `banip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `channels`
--

DROP TABLE IF EXISTS `channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `channels` (
  `hash` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `phash` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '上级渠道ID',
  `type` enum('cpa','cpc','cpm','cps') NOT NULL COMMENT '类型',
  `rate` float(4,2) unsigned NOT NULL COMMENT '扣量比率',
  `max` tinyint NOT NULL COMMENT '最多',
  `pwd` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '密码',
  `name` varchar(32) NOT NULL COMMENT '名称',
  `url` varchar(64) NOT NULL COMMENT '网址',
  PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='渠道';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `channels`
--

LOCK TABLES `channels` WRITE;
/*!40000 ALTER TABLE `channels` DISABLE KEYS */;
/*!40000 ALTER TABLE `channels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configs`
--

DROP TABLE IF EXISTS `configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configs` (
  `key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='配置';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configs`
--

LOCK TABLES `configs` WRITE;
/*!40000 ALTER TABLE `configs` DISABLE KEYS */;
/*!40000 ALTER TABLE `configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `images`
--

DROP TABLE IF EXISTS `images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `images` (
  `hash` char(12) NOT NULL,
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `size` int unsigned NOT NULL COMMENT '图片大小',
  `sync` enum('pending','finished') NOT NULL COMMENT '同步',
  PRIMARY KEY (`hash`),
  KEY `sync` (`sync`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='图像';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `images`
--

LOCK TABLES `images` WRITE;
/*!40000 ALTER TABLE `images` DISABLE KEYS */;
/*!40000 ALTER TABLE `images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prods`
--

DROP TABLE IF EXISTS `prods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prods` (
  `hash` char(12) NOT NULL,
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `count` int unsigned NOT NULL COMMENT '数量（0：下架）',
  `price` int unsigned NOT NULL COMMENT '单价（元）',
  `sales` int unsigned NOT NULL COMMENT '销量',
  `name` varchar(128) NOT NULL COMMENT '名称',
  `desc` varchar(512) NOT NULL COMMENT '描述',
  `vtid` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '虚拟类型ID',
  PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='产品';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prods`
--

LOCK TABLES `prods` WRITE;
/*!40000 ALTER TABLE `prods` DISABLE KEYS */;
/*!40000 ALTER TABLE `prods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recordlog`
--

DROP TABLE IF EXISTS `recordlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recordlog` (
  `ciddate` char(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `cid` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '渠道ID',
  `date` date NOT NULL COMMENT '日期',
  `dpv` int unsigned NOT NULL COMMENT '落地页访问',
  `dpv_ios` int unsigned NOT NULL COMMENT '落地页访问（iOS）',
  `dpv_android` int unsigned NOT NULL COMMENT '落地页访问（Android）',
  `dpc` int unsigned NOT NULL COMMENT '落地页点击',
  `dpc_ios` int unsigned NOT NULL COMMENT '落地页点击（iOS）',
  `dpc_android` int unsigned NOT NULL COMMENT '落地页点击（Android）',
  `signin` int unsigned NOT NULL COMMENT '登录（日活）',
  `signin_ios` int unsigned NOT NULL COMMENT '登录（iOS）',
  `signin_android` int unsigned NOT NULL COMMENT '登录（Android）',
  `signup` int unsigned NOT NULL COMMENT '注册（新增）',
  `signup_ios` int unsigned NOT NULL COMMENT '注册（iOS）',
  `signup_android` int unsigned NOT NULL COMMENT '注册（Android）',
  `recharge` int unsigned NOT NULL COMMENT '充值',
  `recharge_new` int unsigned NOT NULL COMMENT '充值（新）',
  `recharge_old` int unsigned NOT NULL COMMENT '充值（老）',
  `recharge_coin` int unsigned NOT NULL COMMENT '充值（金币）',
  `recharge_vip` int unsigned NOT NULL COMMENT '充值（VIP）',
  `recharge_vip_new` int unsigned NOT NULL COMMENT '充值（VIP新人订单数）',
  `order` int unsigned NOT NULL COMMENT '订单',
  `order_ok` int unsigned NOT NULL COMMENT '订单（成功）',
  `order_ios` int unsigned NOT NULL COMMENT '订单（iOS）',
  `order_ios_ok` int unsigned NOT NULL COMMENT '订单（iOS成功）',
  `order_android` int unsigned NOT NULL COMMENT '订单（Android）',
  `order_android_ok` int unsigned NOT NULL COMMENT '订单（Android成功）',
  `hourdata` json NOT NULL COMMENT '小时数据',
  PRIMARY KEY (`ciddate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='统计';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recordlog`
--

LOCK TABLES `recordlog` WRITE;
/*!40000 ALTER TABLE `recordlog` DISABLE KEYS */;
/*!40000 ALTER TABLE `recordlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recordlogs`
--

DROP TABLE IF EXISTS `recordlogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recordlogs` (
  `ciddate` char(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `cid` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '渠道ID',
  `date` date NOT NULL COMMENT '日期',
  `dpv` double(10,2) unsigned NOT NULL COMMENT '落地页访问',
  `dpv_ios` double(10,2) unsigned NOT NULL COMMENT '落地页访问（iOS）',
  `dpv_android` double(10,2) unsigned NOT NULL COMMENT '落地页访问（Android）',
  `dpc` double(10,2) unsigned NOT NULL COMMENT '落地页点击',
  `dpc_ios` double(10,2) unsigned NOT NULL COMMENT '落地页点击（iOS）',
  `dpc_android` double(10,2) unsigned NOT NULL COMMENT '落地页点击（Android）',
  `signin` double(10,2) unsigned NOT NULL COMMENT '登录（日活）',
  `signin_ios` double(10,2) unsigned NOT NULL COMMENT '登录（iOS）',
  `signin_android` double(10,2) unsigned NOT NULL COMMENT '登录（Android）',
  `signup` double(10,2) unsigned NOT NULL COMMENT '注册（新增）',
  `signup_ios` double(10,2) unsigned NOT NULL COMMENT '注册（iOS）',
  `signup_android` double(10,2) unsigned NOT NULL COMMENT '注册（Android）',
  `recharge` double(10,2) unsigned NOT NULL COMMENT '充值',
  `recharge_new` double(10,2) unsigned NOT NULL COMMENT '充值（新）',
  `recharge_old` double(10,2) unsigned NOT NULL COMMENT '充值（老）',
  `recharge_coin` double(10,2) unsigned NOT NULL COMMENT '充值（金币）',
  `recharge_vip` double(10,2) unsigned NOT NULL COMMENT '充值（VIP）',
  `recharge_vip_new` double(10,2) unsigned NOT NULL COMMENT '充值（VIP新人订单数）',
  `order` double(10,2) unsigned NOT NULL COMMENT '订单',
  `order_ok` double(10,2) unsigned NOT NULL COMMENT '订单（成功）',
  `order_ios` double(10,2) unsigned NOT NULL COMMENT '订单（iOS）',
  `order_ios_ok` double(10,2) unsigned NOT NULL COMMENT '订单（iOS成功）',
  `order_android` double(10,2) unsigned NOT NULL COMMENT '订单（Android）',
  `order_android_ok` double(10,2) unsigned NOT NULL COMMENT '订单（Android成功）',
  PRIMARY KEY (`ciddate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='统计';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recordlogs`
--

LOCK TABLES `recordlogs` WRITE;
/*!40000 ALTER TABLE `recordlogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `recordlogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `records`
--

DROP TABLE IF EXISTS `records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `records` (
  `hash` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `userid` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '用户id',
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `result` enum('pending','success','failure') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '状态结果',
  `type` enum('vip','coin','game','prod','video','exchange') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '类型',
  `log` enum('pending','success') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '记录',
  `cid` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '渠道ID',
  `fee` int unsigned NOT NULL COMMENT '费用',
  `ext` json DEFAULT NULL COMMENT '数据',
  PRIMARY KEY (`hash`),
  KEY `type` (`type`),
  KEY `log` (`log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='记录';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `records`
--

LOCK TABLES `records` WRITE;
/*!40000 ALTER TABLE `records` DISABLE KEYS */;
/*!40000 ALTER TABLE `records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `hash` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `time` int unsigned NOT NULL COMMENT '时间',
  `date` date NOT NULL COMMENT '日期',
  `userid` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '用户ID',
  `clientip` char(32) NOT NULL COMMENT '客户端IP',
  `promise` enum('pending','resolve','reject') NOT NULL COMMENT '承诺状态',
  `question` varchar(256) NOT NULL COMMENT '问题描述',
  `reply` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '回复内容',
  PRIMARY KEY (`hash`),
  KEY `date` (`date`),
  KEY `userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `hash` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `sort` tinyint unsigned NOT NULL COMMENT '排序',
  `style` tinyint unsigned NOT NULL COMMENT '展示样式',
  `type` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '分类',
  `name` varchar(64) NOT NULL COMMENT '名称',
  `videos` varchar(120) NOT NULL COMMENT '推荐影片',
  `fetch_method` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '获取方法',
  `fetch_values` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '获取值',
  PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='专题';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `hash` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `time` int unsigned NOT NULL COMMENT '创建时间',
  `click` int unsigned NOT NULL COMMENT '点击量',
  `level` tinyint unsigned NOT NULL COMMENT '等级',
  `sort` tinyint unsigned NOT NULL COMMENT '排序',
  `name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '名称',
  PRIMARY KEY (`hash`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='标签';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uploaders`
--

DROP TABLE IF EXISTS `uploaders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `uploaders` (
  `uid` smallint unsigned NOT NULL,
  `date` date NOT NULL COMMENT '创建日期',
  `lasttime` int unsigned NOT NULL COMMENT '最后登录时间',
  `lastip` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '最后登录IP',
  `pwd` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '密码',
  `name` varchar(32) NOT NULL COMMENT '名称',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='创作者';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uploaders`
--

LOCK TABLES `uploaders` WRITE;
/*!40000 ALTER TABLE `uploaders` DISABLE KEYS */;
/*!40000 ALTER TABLE `uploaders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `date` date NOT NULL COMMENT '索引日期',
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `login` int unsigned NOT NULL COMMENT '登录次数',
  `watch` int unsigned NOT NULL COMMENT '观看次数',
  `share` int unsigned NOT NULL COMMENT '分享次数',
  `lasttime` int unsigned NOT NULL COMMENT '最后登录时间',
  `lastip` char(32) NOT NULL COMMENT '最后登录IP',
  `device` enum('android','ios','pad','pc') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '设备类型',
  `balance` double unsigned NOT NULL COMMENT '提现余额',
  `expire` int unsigned NOT NULL COMMENT '会员到期',
  `coin` int unsigned NOT NULL COMMENT '观影金币',
  `ticket` int unsigned NOT NULL COMMENT '金币观影券',
  `fid` tinyint unsigned NOT NULL COMMENT '头像ID',
  `uid` smallint unsigned NOT NULL COMMENT 'UP主ID',
  `cid` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '渠道ID',
  `iid` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '邀请ID',
  `did` char(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '设备ID',
  `tid` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '电话',
  `nickname` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '花名',
  `gender` enum('none','male','female') NOT NULL COMMENT '性别',
  `descinfo` varchar(128) NOT NULL COMMENT '简介',
  `historys` varchar(600) NOT NULL COMMENT '观看历史',
  `favorites` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '收藏视频',
  `followed_ids` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '关注的UP主ID',
  `follower_num` int unsigned NOT NULL COMMENT '被用户关注的数',
  `video_num` int unsigned NOT NULL COMMENT '上传的视频数',
  PRIMARY KEY (`id`),
  UNIQUE KEY `did` (`did`),
  UNIQUE KEY `tid` (`tid`),
  KEY `date` (`date`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `videos`
--

DROP TABLE IF EXISTS `videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `videos` (
  `hash` char(12) NOT NULL,
  `userid` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '用户ID',
  `mtime` int unsigned NOT NULL COMMENT '创建时间',
  `ctime` int unsigned NOT NULL COMMENT '修改时间',
  `ptime` int unsigned NOT NULL COMMENT '定时发布时间',
  `size` int unsigned NOT NULL COMMENT '字节大小',
  `tell` int unsigned NOT NULL COMMENT '上传字节',
  `cover` enum('finish','change') NOT NULL COMMENT '修改封面',
  `sync` enum('waiting','slicing','finished','exception','allow','deny') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '同步',
  `type` enum('h','v') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '横竖',
  `sort` tinyint unsigned NOT NULL COMMENT '排序',
  `duration` smallint unsigned NOT NULL COMMENT '时长',
  `preview` int unsigned NOT NULL COMMENT '预览16位',
  `require` int NOT NULL COMMENT '-1会员,0免费,大于0价格',
  `sales` int unsigned NOT NULL COMMENT '销量',
  `view` int unsigned NOT NULL COMMENT '观看次数',
  `like` int unsigned NOT NULL COMMENT '喜欢数量',
  `favorite` int unsigned NOT NULL COMMENT '收藏量',
  `tags` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '标签集',
  `subjects` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '专题集',
  `name` varchar(256) NOT NULL COMMENT '标题名称',
  `extdata` json DEFAULT NULL COMMENT '扩展数据',
  PRIMARY KEY (`hash`),
  KEY `userid` (`userid`),
  KEY `sync` (`sync`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='视频';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `videos`
--

LOCK TABLES `videos` WRITE;
/*!40000 ALTER TABLE `videos` DISABLE KEYS */;
/*!40000 ALTER TABLE `videos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-01-10 14:29:15
