-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: installation_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `setup`
--

DROP TABLE IF EXISTS `setup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setup` (
  `setup_id` char(11) NOT NULL,
  `user_id` char(13) DEFAULT NULL,
  `customer_id` char(13) DEFAULT NULL,
  `sale_id` char(13) DEFAULT NULL,
  `pro_id` char(10) DEFAULT NULL,
  `setup_date` datetime DEFAULT NULL,
  `setup_location` varchar(100) DEFAULT NULL,
  `setup_status` int(1) DEFAULT NULL,
  `setup_address` text DEFAULT NULL,
  `setup_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`setup_id`),
  KEY `user_id` (`user_id`),
  KEY `pro_id` (`pro_id`),
  KEY `idx_setup_customer_id` (`customer_id`),
  CONSTRAINT `fk_setup_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `setup_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  CONSTRAINT `setup_ibfk_2` FOREIGN KEY (`pro_id`) REFERENCES `product` (`pro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `setup`
--

LOCK TABLES `setup` WRITE;
/*!40000 ALTER TABLE `setup` DISABLE KEYS */;
INSERT INTO `setup` VALUES ('SET-0000025','USR-2569-0017','CUS-2569-0010','USR-2569-0004','PR-000007','2026-08-19 00:00:00','บ้านวังไห 257 หมู่ 4 ต.หนองหญ้าปล้อง อ.วังสะพุง จ.เลย 42130',1,'บ้านวังไห 257 หมู่ 4 ต.หนองหญ้าปล้อง อ.วังสะพุง จ.เลย 42130','ทดสอบ 1','2026-08-19 16:43:18'),('SET-0000026','USR-2569-0002','CUS-2569-0001','USR-2569-0004','PR-000007','2026-08-19 00:00:00','สารคาม',5,'สารคาม','ทดสอบสร้างใบติดตั้ง 2','2026-08-19 16:43:49'),('SET-0000027','USR-2569-0003','CUS-2569-0002','USR-2569-0004','PR-000007','2026-08-19 00:00:00','ร้อยเอ็ด',1,'ร้อยเอ็ด','ทดสอบใบงานติดตั้ง 2','2026-08-19 22:47:36'),('SET-0000028','USR-2569-0002','CUS-2569-0001','USR-2569-0004','PR-000002','2026-08-20 00:00:00','สารคาม',1,'สารคาม','ทดสอบสร้างใบงานติดตั้ง 3','2026-08-20 15:40:51'),('SET-0000029','USR-2569-0002','CUS-2569-0001','USR-2569-0004','PR-000007','2026-08-20 00:00:00','สารคาม',1,'สารคาม','บ้านอยู่ข้างวัดหลังสีเชียว','2026-08-20 17:08:41'),('SET-0000030','USR-2569-0017','CUS-2569-0010','USR-2569-0004','PR-000001','2026-08-20 00:00:00','บ้านวังไห 257 หมู่ 4 ต.หนองหญ้าปล้อง อ.วังสะพุง จ.เลย 42130',0,'บ้านวังไห 257 หมู่ 4 ต.หนองหญ้าปล้อง อ.วังสะพุง จ.เลย 42130','','2026-08-20 20:30:13');
/*!40000 ALTER TABLE `setup` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-20 22:21:31
