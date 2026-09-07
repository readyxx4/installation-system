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
-- Table structure for table `assignment`
--

DROP TABLE IF EXISTS `assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment` (
  `assign_id` char(11) NOT NULL,
  `setup_id` char(11) DEFAULT NULL,
  `tech_id` char(13) DEFAULT NULL,
  `user_id` char(13) DEFAULT NULL,
  `assign_by` char(13) DEFAULT NULL,
  `assign_date` datetime DEFAULT NULL,
  `assign_install_date` date DEFAULT NULL,
  `assign_install_time` time DEFAULT NULL,
  `assign_install_end_time` time DEFAULT NULL,
  `assign_status` int(1) DEFAULT NULL,
  PRIMARY KEY (`assign_id`),
  KEY `setup_id` (`setup_id`),
  KEY `tech_id` (`tech_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `assignment_ibfk_1` FOREIGN KEY (`setup_id`) REFERENCES `setup` (`setup_id`) ON UPDATE CASCADE,
  CONSTRAINT `assignment_ibfk_2` FOREIGN KEY (`tech_id`) REFERENCES `technicians` (`tech_id`),
  CONSTRAINT `assignment_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment`
--

LOCK TABLES `assignment` WRITE;
/*!40000 ALTER TABLE `assignment` DISABLE KEYS */;
INSERT INTO `assignment` VALUES ('ASG-0000001','SET-0000025','TEC-2569-0002','USR-2569-0017','USR-2569-0009','2026-08-20 21:00:47','2026-08-28','09:00:00','18:00:00',1),('ASG-0000002','SET-0000027','TEC-2569-0002','USR-2569-0003','USR-2569-0009','2026-08-20 21:00:38','2026-08-31','16:00:00','18:00:00',1),('ASG-0000003','SET-0000029','TEC-2569-0002','USR-2569-0002','USR-2569-0009','2026-08-20 20:59:48','2026-08-31','09:00:00','12:00:00',1),('ASG-0000004','SET-0000028','TEC-2569-0002','USR-2569-0002','USR-2569-0009','2026-08-20 21:00:15','2026-08-31','13:00:00','15:00:00',1);
/*!40000 ALTER TABLE `assignment` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-20 22:03:56
