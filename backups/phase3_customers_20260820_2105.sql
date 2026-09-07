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
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_id` char(13) NOT NULL,
  `user_id` char(13) DEFAULT NULL,
  `customer_password` varchar(255) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` char(10) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_address` text NOT NULL,
  `customer_status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uniq_customers_user_id` (`user_id`),
  KEY `idx_customers_status` (`customer_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES ('CUS-2569-0001','USR-2569-0002','1234','พีรภัทร ปัตถาคำมี','0123456789','phee@gmail.com','สารคาม',1),('CUS-2569-0002','USR-2569-0003','1234','ศิริชัย ตาลสิทธิ์','0801234568','po@gmail.com','ร้อยเอ็ด',1),('CUS-2569-0003','USR-2569-0005','1234','ติน','0235456787','tin@gmail.com','-',1),('CUS-2569-0004','USR-2569-0006','1234','ต้น','0805468785','ton@gmail.com','ขอนแก่น',1),('CUS-2569-0005','USR-2569-0007','1234','โฟ','0856475212','four@gmail.com','เลย',1),('CUS-2569-0006','USR-2569-0008','1234','ต้นข้าว','0865412358','rice@gmail.com','เลย',1),('CUS-2569-0007','USR-2569-0011','1234','kimmy','0926569248','Arpiwat24@gmail.com','kimmyland 252 55555',1),('CUS-2569-0008','USR-2569-0013','1234','ธนวัตน์','0953212145','tanawat@gmail.com','ขอนแก่นน',1),('CUS-2569-0009','USR-2569-0014','1234','ส้ม มาลี','0858585858','som@gmail.com','เลย',1),('CUS-2569-0010','USR-2569-0017','1234','รัฐติพงษ์ บุญปัน','0801234676','rattiphong@gmail.com','บ้านวังไห 257 หมู่ 4 ต.หนองหญ้าปล้อง อ.วังสะพุง จ.เลย 42130',1),('CUS-2569-0011','USR-2569-0019','Test1234','มา ติน','5555555555','matin@gmail.com','55/55 ต.ในเมือง อ.เมืองขอนแก่น จ.ขอนแก่น 40000',1),('CUS-2569-0012','USR-2569-0001','r12345678','ทดสอบ ทดสอบ','0924462527','testt@gmail.com','บ้านทดสอบ หมู่ที่ 1 ต.วังน้ำเขียว อ.วังน้ำเขียว จ.นครราชสีมา 30370',1),('CUS-2569-0013',NULL,'a1234567','ทดสอบ ครั้งที่2','1231231231','test2@gmail.com','asdasd ต.ศรีษะทอง อ.นครชัยศรี จ.นครปฐม 73120',1);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
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
