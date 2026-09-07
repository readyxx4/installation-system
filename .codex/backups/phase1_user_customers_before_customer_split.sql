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
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `user_id` char(13) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_password` varchar(50) NOT NULL,
  `user_phone` char(10) DEFAULT NULL,
  `user_email` varchar(50) DEFAULT NULL,
  `user_address` text DEFAULT NULL,
  `user_role` int(1) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--
-- WHERE:  user_role = 0

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES ('USR-2569-0001','ทดสอบ ทดสอบ','r12345678','0924462527','testt@gmail.com','บ้านทดสอบ หมู่ที่ 1 ต.วังน้ำเขียว อ.วังน้ำเขียว จ.นครราชสีมา 30370',0),('USR-2569-0002','พีรภัทร ปัตถาคำมี','1234','0123456789','phee@gmail.com','สารคาม',0),('USR-2569-0003','ศิริชัย ตาลสิทธิ์','1234','0801234568','po@gmail.com','ร้อยเอ็ด',0),('USR-2569-0005','ติน','1234','0235456787','tin@gmail.com','-',0),('USR-2569-0006','ต้น','1234','0805468785','ton@gmail.com','ขอนแก่น',0),('USR-2569-0007','โฟ','1234','0856475212','four@gmail.com','เลย',0),('USR-2569-0008','ต้นข้าว','1234','0865412358','rice@gmail.com','เลย',0),('USR-2569-0011','kimmy','1234','0926569248','Arpiwat24@gmail.com','kimmyland 252 55555',0),('USR-2569-0013','ธนวัตน์','1234','0953212145','tanawat@gmail.com','ขอนแก่นน',0),('USR-2569-0014','ส้ม มาลี','1234','0858585858','som@gmail.com','เลย',0),('USR-2569-0017','รัฐติพงษ์ บุญปัน','1234','0801234676','rattiphong@gmail.com','บ้านวังไห 257 หมู่ 4 ต.หนองหญ้าปล้อง อ.วังสะพุง จ.เลย 42130',0),('USR-2569-0019','มา ติน','Test1234','5555555555','matin@gmail.com','55/55 ต.ในเมือง อ.เมืองขอนแก่น จ.ขอนแก่น 40000',0);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-20 20:43:28
