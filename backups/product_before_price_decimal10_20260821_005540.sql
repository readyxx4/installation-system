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
-- Table structure for table `product`
--

DROP TABLE IF EXISTS `product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product` (
  `pro_id` char(10) NOT NULL,
  `pro_name` varchar(50) DEFAULT NULL,
  `pro_price` decimal(6,2) DEFAULT NULL,
  `pro_price_install` decimal(6,2) DEFAULT NULL,
  `protype_id` char(10) DEFAULT NULL,
  PRIMARY KEY (`pro_id`),
  KEY `protype_id` (`protype_id`),
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`protype_id`) REFERENCES `product_type` (`protype_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product`
--

LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;
INSERT INTO `product` VALUES ('PR-000001','ปั๊มน้ำอัตโนมัติ 250W',5990.00,1000.00,'PT-0000002'),('PR-000002','ปั๊มน้ำอัตโนมัติ 150W',4290.00,800.00,'PT-0000002'),('PR-000003','เครื่องดูดควัน 90 ซม.',8990.00,1500.00,'PT-0000001'),('PR-000004','เครื่องดูดควัน 60 ซม.',5990.00,1000.00,'PT-0000001'),('PR-000005','เครื่องทำน้ำอุ่น 6,000W',6490.00,1200.00,'PT-0000003'),('PR-000006','เครื่องทำน้ำอุ่น 4,500W',4590.00,1000.00,'PT-0000003'),('PR-000007','เครื่องทำน้ำอุ่น 3,500W',3490.00,800.00,'PT-0000003'),('PR-000008','เครื่องปรับอากาศ 9,000 BTU',9999.99,2000.00,'PT-0000004'),('PR-000009','เครื่องปรับอากาศ 12,000 BTU',9999.99,2200.00,'PT-0000004');
/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-21  0:55:40
