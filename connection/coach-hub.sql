-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2025 at 04:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `coach-hub`
--

-- --------------------------------------------------------


--
-- Drop Existing Tables
--
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `banned_users`;
DROP TABLE IF EXISTS `booking_notifications`;
DROP TABLE IF EXISTS `channel_participants`;
DROP TABLE IF EXISTS `chat_channels`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `forum_chats`;
DROP TABLE IF EXISTS `forum_participants`;
DROP TABLE IF EXISTS `general_forums`;
DROP TABLE IF EXISTS `menteescores`;
DROP TABLE IF EXISTS `mentee_answers`;
DROP TABLE IF EXISTS `mentee_assessment`;
DROP TABLE IF EXISTS `mentors_assessment`;
DROP TABLE IF EXISTS `pending_sessions`;
DROP TABLE IF EXISTS `post_likes`;
DROP TABLE IF EXISTS `quizassignments`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `resources`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `session_bookings`;
DROP TABLE IF EXISTS `session_ended`;
DROP TABLE IF EXISTS `session_participants`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `video_participants`;
SET FOREIGN_KEY_CHECKS = 1;
--
-- Table structure for table `banned_users`
--

CREATE TABLE `banned_users` (
  `ban_id` int(11) NOT NULL,
  `username` varchar(70) NOT NULL,
  `banned_by_admin` varchar(70) NOT NULL,
  `reason` text DEFAULT NULL,
  `ban_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_notifications`
--

CREATE TABLE `booking_notifications` (
  `notification_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `recipient_type` enum('admin','mentor','mentee') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_notifications`
--

INSERT INTO `booking_notifications` (`notification_id`, `booking_id`, `recipient_type`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 8, 'admin', 15, 'Mark Justie Lagnason has booked a HTML session on 2025-05-18 at 2:00 PM - 3:00 PM', 0, '2025-05-18 07:05:06'),
(2, 9, 'admin', 15, 'Faith Odessa Balajo has booked a HTML session on 2025-05-18 at 2:00 PM - 3:00 PM', 0, '2025-05-18 07:05:50'),
(3, 1, 'admin', 15, 'Faith Odessa Balajo has booked a CSS session on 2025-05-18 at 4:00 PM - 5:00 PM', 0, '2025-05-18 08:00:33'),
(4, 1, 'admin', 16, 'Faith Odessa Balajo has booked a CSS session on 2025-05-18 at 4:00 PM - 5:00 PM', 0, '2025-05-18 08:00:33'),
(5, 2, 'admin', 15, 'Cherwen Kirk Fuertes has booked a CSS session on 2025-05-18 at 4:00 PM - 5:00 PM', 0, '2025-05-18 08:01:08'),
(6, 2, 'admin', 16, 'Cherwen Kirk Fuertes has booked a CSS session on 2025-05-18 at 4:00 PM - 5:00 PM', 0, '2025-05-18 08:01:08'),
(7, 3, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-05-18 at 4:00 PM - 5:00 PM', 0, '2025-05-18 08:01:56'),
(8, 3, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-05-18 at 4:00 PM - 5:00 PM', 0, '2025-05-18 08:01:56'),
(9, 4, 'admin', 15, 'Mark Justie Lagnason has booked a HTML session on 2025-05-18 at 6:00 PM - 7:00 PM', 0, '2025-05-18 15:41:37'),
(10, 4, 'admin', 16, 'Mark Justie Lagnason has booked a HTML session on 2025-05-18 at 6:00 PM - 7:00 PM', 0, '2025-05-18 15:41:37'),
(11, 5, 'admin', 15, 'Angela Marie Gabriel has booked a PHP session on 2025-05-19 at 6:15 PM - 7:00 PM', 0, '2025-05-19 10:14:18'),
(12, 5, 'admin', 18, 'Angela Marie Gabriel has booked a PHP session on 2025-05-19 at 6:15 PM - 7:00 PM', 0, '2025-05-19 10:14:18'),
(13, 5, 'admin', 16, 'Angela Marie Gabriel has booked a PHP session on 2025-05-19 at 6:15 PM - 7:00 PM', 0, '2025-05-19 10:14:18'),
(14, 5, 'admin', 17, 'Angela Marie Gabriel has booked a PHP session on 2025-05-19 at 6:15 PM - 7:00 PM', 0, '2025-05-19 10:14:18'),
(15, 6, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-05-22 at 1:00 PM - 2:00 PM', 0, '2025-05-22 10:52:53'),
(16, 6, 'admin', 18, 'Mark Justie Lagnason has booked a CSS session on 2025-05-22 at 1:00 PM - 2:00 PM', 0, '2025-05-22 10:52:53'),
(17, 6, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-05-22 at 1:00 PM - 2:00 PM', 0, '2025-05-22 10:52:53'),
(18, 6, 'admin', 17, 'Mark Justie Lagnason has booked a CSS session on 2025-05-22 at 1:00 PM - 2:00 PM', 0, '2025-05-22 10:52:53'),
(19, 7, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 11:01 AM - 11:01 PM', 0, '2025-09-03 05:33:34'),
(20, 7, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 11:01 AM - 11:01 PM', 0, '2025-09-03 05:33:34'),
(21, 7, 'admin', 17, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 11:01 AM - 11:01 PM', 0, '2025-09-03 05:33:34'),
(22, 7, 'admin', 18, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 11:01 AM - 11:01 PM', 0, '2025-09-03 05:33:34'),
(23, 8, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:05 PM - 7:05 PM', 0, '2025-09-03 06:07:17'),
(24, 8, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:05 PM - 7:05 PM', 0, '2025-09-03 06:07:17'),
(25, 8, 'admin', 17, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:05 PM - 7:05 PM', 0, '2025-09-03 06:07:17'),
(26, 8, 'admin', 18, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:05 PM - 7:05 PM', 0, '2025-09-03 06:07:17'),
(27, 9, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 10:46 AM - 10:54 PM', 0, '2025-09-03 06:11:14'),
(28, 9, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 10:46 AM - 10:54 PM', 0, '2025-09-03 06:11:14'),
(29, 9, 'admin', 17, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 10:46 AM - 10:54 PM', 0, '2025-09-03 06:11:14'),
(30, 9, 'admin', 18, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 10:46 AM - 10:54 PM', 0, '2025-09-03 06:11:14'),
(31, 10, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:12 PM - 7:12 PM', 0, '2025-09-03 06:13:08'),
(32, 10, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:12 PM - 7:12 PM', 0, '2025-09-03 06:13:08'),
(33, 10, 'admin', 17, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:12 PM - 7:12 PM', 0, '2025-09-03 06:13:08'),
(34, 10, 'admin', 18, 'Mark Justie Lagnason has booked a CSS session on 2025-09-03 at 2:12 PM - 7:12 PM', 0, '2025-09-03 06:13:08'),
(35, 11, 'admin', 15, 'Mark Justie Lagnason has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:44:24'),
(36, 11, 'admin', 16, 'Mark Justie Lagnason has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:44:24'),
(37, 11, 'admin', 17, 'Mark Justie Lagnason has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:44:24'),
(38, 11, 'admin', 18, 'Mark Justie Lagnason has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:44:24'),
(39, 12, 'admin', 15, 'Cherwen Kirk Fuertes has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:52:47'),
(40, 12, 'admin', 16, 'Cherwen Kirk Fuertes has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:52:47'),
(41, 12, 'admin', 17, 'Cherwen Kirk Fuertes has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:52:47'),
(42, 12, 'admin', 18, 'Cherwen Kirk Fuertes has booked a CSS session on 2025-09-04 at 12:42 PM - 6:42 PM', 0, '2025-09-04 04:52:47');

-- --------------------------------------------------------

--
-- Table structure for table `channel_participants`
--

CREATE TABLE `channel_participants` (
  `id` int(11) NOT NULL,
  `channel_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_channels`
--

CREATE TABLE `chat_channels` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_general` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_channels`
--

INSERT INTO `chat_channels` (`id`, `name`, `description`, `is_general`, `created_at`) VALUES
(1, 'general', 'General discussion channel', 1, '2025-05-06 14:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0,
  `chat_type` enum('group','forum','comment') NOT NULL DEFAULT 'group',
  `forum_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `is_mentor` tinyint(1) DEFAULT 0,
  `likes` int(11) NOT NULL,
  `title` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `user_id`, `display_name`, `message`, `timestamp`, `is_admin`, `chat_type`, `forum_id`, `file_path`, `file_name`, `is_mentor`, `likes`, `title`) VALUES
(104, 1, 'Mark Justie Lagnason', 'Hello World', '2025-08-23 08:34:24', 0, 'forum', NULL, NULL, NULL, 0, 0, 'Sample Post'),
(105, 1, 'Mark Justie Lagnason', 'Hello World', '2025-08-23 08:34:43', 0, 'forum', NULL, NULL, NULL, 0, 0, 'Hello'),
(107, 1, 'Mark Justie Lagnason', '<p>Hello! This post is a quick demonstration of all the great features available in our post editor.</p><p>You can make your text <b>bold</b> to emphasize a point, or use <i>italics</i> for a different kind of stress. You can also <u>underline</u>&nbsp;important words.</p><p>Here are some things you can do:</p><ul><li><p>Create an unordered, bulleted list.</p></li><li><p>Perfect for highlighting key points.</p></li><li><p>It\'s very easy to use.</p></li></ul><p>You can also create a numbered list for step-by-step instructions:</p><ol start=\"1\"><li><p>First, think of what you want to write.</p></li><li><p>Next, use the formatting tools to make your post clear and readable.</p></li><li><p>Finally, click \"Post\"!</p></li></ol><p>Don\'t forget you can add a hyperlink&nbsp;to share resources with others. Happy posting!</p>', '2025-08-23 08:38:50', 0, 'forum', NULL, NULL, NULL, 0, 1, 'How to Use'),
(111, 1, 'Mark Justie Lagnason', 'Great!', '2025-08-23 08:41:28', 0, 'comment', 107, NULL, NULL, 0, 0, ''),
(112, 1, 'Mark Justie Lagnason', 'Hi', '2025-08-23 08:41:33', 0, 'comment', 105, NULL, NULL, 0, 0, ''),
(113, 10, 'John Kenneth Dizon', 'hello', '2025-09-03 02:53:18', 0, 'forum', 6, NULL, NULL, 1, 0, ''),
(114, 10, 'John Kenneth Dizon', 'hellop', '2025-09-03 03:04:09', 0, 'forum', 7, NULL, NULL, 1, 0, ''),
(115, 18, 'Kim Villafania', 'hi', '2025-09-03 03:42:46', 1, 'forum', 7, NULL, NULL, 0, 0, ''),
(116, 1, 'Mark Justie Lagnason', 'hello', '2025-09-03 06:19:01', 0, 'forum', 10, NULL, NULL, 0, 0, ''),
(117, 1, 'Mark Justie Lagnason', 'hi', '2025-09-03 06:19:05', 0, 'forum', 10, NULL, NULL, 0, 0, ''),
(118, 1, 'Mark Justie Lagnason', 'hi', '2025-09-03 06:27:05', 0, 'forum', 7, NULL, NULL, 0, 0, ''),
(119, 18, 'Kim Villafania', 'hello', '2025-09-04 04:43:55', 1, 'forum', 11, NULL, NULL, 0, 0, ''),
(120, 1, 'Mark Justie Lagnason', 'hi', '2025-09-04 04:45:10', 0, 'forum', 11, NULL, NULL, 0, 0, ''),
(121, 2, 'Cherwen Kirk Fuertes', 'hey', '2025-09-04 04:52:54', 0, 'forum', 11, NULL, NULL, 0, 0, ''),
(122, 2, 'Cherwen Kirk Fuertes', 'hi', '2025-09-04 04:55:52', 0, 'forum', 11, NULL, NULL, 0, 0, ''),
(123, 2, 'Cherwen Kirk Fuertes', 'afd', '2025-09-04 04:55:54', 0, 'forum', 11, NULL, NULL, 0, 0, ''),
(124, 18, 'Kim Villafania', 'hi', '2025-09-04 05:13:42', 1, 'forum', 11, NULL, NULL, 0, 0, ''),
(125, 10, 'John Kenneth Dizon', 'hi', '2025-09-04 05:17:12', 0, 'forum', 11, NULL, NULL, 1, 0, ''),
(126, 10, 'John Kenneth Dizon', 'hi', '2025-09-04 05:52:58', 0, 'forum', 11, NULL, NULL, 1, 0, ''),
(127, 10, 'John Kenneth Dizon', 'hewl', '2025-09-04 06:05:02', 0, 'forum', 11, NULL, NULL, 1, 0, ''),
(128, 10, 'John Kenneth Dizon', 'hi', '2025-09-04 12:47:02', 0, 'forum', 11, NULL, NULL, 1, 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `Course_ID` int(11) NOT NULL,
  `Course_Title` varchar(200) NOT NULL,
  `Course_Description` text NOT NULL,
  `Skill_Level` varchar(100) NOT NULL,
  `Assigned_Mentor` varchar(200) NOT NULL,
  `Course_Icon` varchar(100) NOT NULL,
  `Course_Status` varchar(70) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`Course_ID`, `Course_Title`, `Course_Description`, `Skill_Level`, `Assigned_Mentor`, `Course_Icon`, `Course_Status`) VALUES
(1, 'HTML', 'A language used to create the structure of web pages.', 'Intermediate', 'Kim Ashley Villafania', 'course_add_68297e7e68a2b2.08543640.png', 'Active'),
(2, 'CSS', 'A styling language that makes web pages look visually appealing.', 'Beginner', 'John Kenneth Dizon', 'course_add_682982bb4ac513.91197082.png', 'Active'),
(3, 'PHP', 'A server-side scripting language for building dynamic websites.\r\n', 'Beginner', 'Mark Angelo Capili', 'course_add_682b0306e394a4.74607573.png', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `Feedback_ID` int(11) NOT NULL,
  `Session` varchar(100) NOT NULL,
  `Forum_ID` int(11) NOT NULL,
  `Session_Mentor` varchar(100) NOT NULL,
  `Mentee` varchar(100) NOT NULL,
  `Mentee_Experience` text NOT NULL,
  `Experience_Star` varchar(100) NOT NULL,
  `Mentor_Reviews` text NOT NULL,
  `Mentor_Star` varchar(100) NOT NULL,
  `Session_Date` date NOT NULL,
  `Time_Slot` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`Feedback_ID`, `Session`, `Forum_ID`, `Session_Mentor`, `Mentee`, `Mentee_Experience`, `Experience_Star`, `Mentor_Reviews`, `Mentor_Star`, `Session_Date`, `Time_Slot`) VALUES
(1, 'HTML Session', 1, 'Kim Ashley Villafania', '', 'It was good!', '80', 'We have learned so much from her.', '80', '0000-00-00', '2:00 PM - 3:00 PM'),
(2, 'HTML Session', 1, 'Kim Ashley Villafania', '', 'It was average.\r\n', '60', 'She is good at explaining.', '80', '0000-00-00', '2:00 PM - 3:00 PM'),
(3, 'CSS Session', 2, 'John Kenneth Dizon', '', 'Great overall, but I wanted more hands-on practice or exercises.', '80', 'Sir Ken answered our questions well and encouraged participation.', '100', '0000-00-00', '4:00 PM - 5:00 PM'),
(4, 'CSS Session', 2, 'John Kenneth Dizon', '', 'Loved the live format! Super interactive and beginner-friendly.', '100', 'Sir Ken made CSS fun and easy to understand. Best mentor so far!', '100', '0000-00-00', '4:00 PM - 5:00 PM'),
(5, 'CSS Session', 2, 'John Kenneth Dizon', '', 'The session was engaging and not too fast-paced. I understood most of it.', '80', 'Sir Ken explained everything clearly and was very approachable!', '100', '0000-00-00', '4:00 PM - 5:00 PM'),
(7, 'PHP Session', 4, 'Mark Angelo Capili', '', 'Good.', '60', 'Magaling magturo', '80', '0000-00-00', '6:15 PM - 7:00 PM'),
(8, 'CSS Session', 5, 'John Kenneth Dizon', '', 'good', '80', 'Everything is clear.', '80', '0000-00-00', '1:00 PM - 2:00 PM'),
(9, 'CSS Session', 11, 'John Kenneth Dizon', 'Cherwen Kirk Fuertes', 'sfasf', '100', 'daad', '100', '0000-00-00', '12:42 PM - 6:42 PM');

-- --------------------------------------------------------

--
-- Table structure for table `forum_chats`
--

CREATE TABLE `forum_chats` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `course_title` varchar(200) NOT NULL,
  `session_date` date NOT NULL,
  `time_slot` varchar(200) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `max_users` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forum_chats`
--

INSERT INTO `forum_chats` (`id`, `title`, `course_title`, `session_date`, `time_slot`, `created_at`, `max_users`) VALUES
(1, 'HTML Session', 'HTML', '2025-05-18', '2:00 PM - 3:00 PM', '2025-05-18 06:32:17', 10),
(2, 'CSS Session', 'CSS', '2025-05-18', '4:00 PM - 5:00 PM', '2025-05-18 07:53:33', 10),
(4, 'PHP Session', 'PHP', '2025-05-19', '6:15 PM - 7:00 PM', '2025-05-19 10:12:58', 10),
(5, 'CSS Session', 'CSS', '2025-05-22', '1:00 PM - 2:00 PM', '2025-05-22 10:51:23', 10),
(6, 'CSS Session', 'CSS', '2025-09-03', '10:21 AM - 12:23 PM', '2025-09-03 02:22:08', 10),
(7, 'CSS Session', 'CSS', '2025-09-03', '10:46 AM - 10:54 PM', '2025-09-03 02:55:04', 10),
(8, 'CSS Session', 'CSS', '2025-09-03', '11:01 AM - 11:01 PM', '2025-09-03 05:33:34', 10),
(9, 'CSS Session', 'CSS', '2025-09-03', '2:05 PM - 7:05 PM', '2025-09-03 06:06:01', 10),
(10, 'CSS Session', 'CSS', '2025-09-03', '2:12 PM - 7:12 PM', '2025-09-03 06:12:48', 10),
(11, 'CSS Session', 'CSS', '2025-09-04', '12:42 PM - 6:42 PM', '2025-09-04 04:43:33', 10),
(12, 'CSS Session', 'CSS', '2025-09-04', '8:46 PM - 12:46 PM', '2025-09-04 12:52:29', 10);

-- --------------------------------------------------------

--
-- Table structure for table `forum_participants`
--

CREATE TABLE `forum_participants` (
  `id` int(11) NOT NULL,
  `forum_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forum_participants`
--

INSERT INTO `forum_participants` (`id`, `forum_id`, `user_id`, `joined_at`) VALUES
(1, 1, 9, '2025-05-18 06:33:26'),
(2, 1, 1, '2025-05-18 07:05:06'),
(3, 1, 3, '2025-05-18 07:05:50'),
(4, 2, 10, '2025-05-18 07:55:34'),
(5, 2, 3, '2025-05-18 08:00:33'),
(6, 2, 2, '2025-05-18 08:01:08'),
(7, 2, 1, '2025-05-18 08:01:56'),
(8, 3, 1, '2025-05-18 15:41:37'),
(9, 4, 11, '2025-05-19 10:13:30'),
(10, 4, 4, '2025-05-19 10:14:18'),
(11, 5, 1, '2025-05-22 10:52:53');

-- --------------------------------------------------------

--
-- Table structure for table `general_forums`
--

CREATE TABLE `general_forums` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `title` text NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0,
  `chat_type` enum('group','forum','comment') NOT NULL DEFAULT 'group',
  `forum_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `is_mentor` tinyint(1) DEFAULT 0,
  `likes` int(11) NOT NULL,
  `user_icon` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `general_forums`
--

INSERT INTO `general_forums` (`id`, `user_id`, `display_name`, `title`, `message`, `timestamp`, `is_admin`, `chat_type`, `forum_id`, `file_path`, `file_name`, `is_mentor`, `likes`, `user_icon`) VALUES
(133, 1, 'Mark Justie Lagnason', '', 'Hello', '2025-09-10 02:21:02', 0, 'comment', 132, NULL, NULL, 0, 0, '../uploads/profile_mjslagnason_1747550363.jpg'),
(134, 1, 'Mark Justie Lagnason', '', 'potza', '2025-09-10 02:21:21', 0, 'comment', 132, NULL, NULL, 0, 0, '../uploads/profile_mjslagnason_1747550363.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `menteescores`
--

CREATE TABLE `menteescores` (
  `Attempt_ID` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `Course_Title` varchar(255) NOT NULL,
  `Score` int(11) DEFAULT 0,
  `Total_Questions` int(11) NOT NULL,
  `Date_Taken` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menteescores`
--

INSERT INTO `menteescores` (`Attempt_ID`, `user_id`, `Course_Title`, `Score`, `Total_Questions`, `Date_Taken`) VALUES
(1, 1, 'HTML', 5, 10, '2025-05-18 15:22:35'),
(2, 3, 'HTML', 4, 10, '2025-05-18 15:23:47'),
(3, 4, 'PHP', 5, 10, '2025-05-19 18:29:13'),
(4, 2, 'CSS', 3, 10, '2025-05-22 18:26:32');

-- --------------------------------------------------------

--
-- Table structure for table `mentee_answers`
--

CREATE TABLE `mentee_answers` (
  `Answer_ID` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `Course_Title` varchar(255) DEFAULT NULL,
  `Question` text DEFAULT NULL,
  `Selected_Answer` text DEFAULT NULL,
  `Correct_Answer` text DEFAULT NULL,
  `Is_Correct` tinyint(1) DEFAULT NULL,
  `Date_Submitted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentee_answers`
--

INSERT INTO `mentee_answers` (`Answer_ID`, `user_id`, `Course_Title`, `Question`, `Selected_Answer`, `Correct_Answer`, `Is_Correct`, `Date_Submitted`) VALUES
(1, 1, 'HTML', 'HTML?', 'HyperText Markup Language', 'HyperText Markup Language', 1, '2025-05-18 15:22:35'),
(2, 1, 'HTML', 'Which attribute is used to provide alternative text for an image in HTML?', 'alt', 'alt', 1, '2025-05-18 15:22:35'),
(3, 1, 'HTML', 'What does HTML stand for?', 'Hypertext Markup Language', 'Hypertext Markup Language', 1, '2025-05-18 15:22:35'),
(4, 1, 'HTML', 'What is the correct way to add a comment in HTML?', '/* comment */', '<!-- comment -->', 0, '2025-05-18 15:22:35'),
(5, 1, 'HTML', 'What is the correct syntax for embedding a video in HTML5?', '<video src=\"video.mp4\">', '<video src=\"video.mp4\">', 1, '2025-05-18 15:22:35'),
(6, 1, 'HTML', 'What is the default value of the \"display\" property for a <div> element?', 'block', 'block', 1, '2025-05-18 15:22:35'),
(7, 1, 'HTML', 'What is the correct HTML tag for inserting an image?', '<image>', '<img>', 0, '2025-05-18 15:22:35'),
(8, 1, 'HTML', 'What is the purpose of the \"data-\" attribute in HTML?', 'To define custom styles', 'To store extra data for the element', 0, '2025-05-18 15:22:35'),
(9, 1, 'HTML', 'Which tag is used to define a form in HTML?', '<input>', '<form>', 0, '2025-05-18 15:22:35'),
(10, 1, 'HTML', 'Which tag is used to define a hyperlink in HTML?', '<link>', '<a>', 0, '2025-05-18 15:22:35'),
(11, 3, 'HTML', 'What is the correct syntax for embedding a video in HTML5?', '<embed src=\"video.mp4\">', '<video src=\"video.mp4\">', 0, '2025-05-18 15:23:47'),
(12, 3, 'HTML', 'Which tag is used to link to an external style sheet?', '<link>', '<link>', 1, '2025-05-18 15:23:47'),
(13, 3, 'HTML', 'Which tag is used to define a hyperlink in HTML?', '<href>', '<a>', 0, '2025-05-18 15:23:47'),
(14, 3, 'HTML', 'What is the correct way to add a comment in HTML?', '/* comment */', '<!-- comment -->', 0, '2025-05-18 15:23:47'),
(15, 3, 'HTML', 'What is the correct HTML tag for embedding a font from Google Fonts?', '<style>@import url(\"https://fonts.googleapis.com/css2?family=Roboto\");</style>', '<link href=\"https://fonts.googleapis.com/css2?family=Roboto\" rel=\"stylesheet\">', 0, '2025-05-18 15:23:47'),
(16, 3, 'HTML', 'Which tag is used to define a form in HTML?', '<form>', '<form>', 1, '2025-05-18 15:23:47'),
(17, 3, 'HTML', 'What is the correct HTML tag for inserting an image?', '<img>', '<img>', 1, '2025-05-18 15:23:47'),
(18, 3, 'HTML', 'Which of these tags is used for creating a drop-down list?', '<list>', '<select>', 0, '2025-05-18 15:23:47'),
(19, 3, 'HTML', 'HTML?', 'HyperText Markup Language', 'HyperText Markup Language', 1, '2025-05-18 15:23:47'),
(20, 3, 'HTML', 'What is the default value of the \"display\" property for a <div> element?', 'inline', 'block', 0, '2025-05-18 15:23:47'),
(21, 4, 'PHP', 'How do you connect to a MySQL database in PHP (procedural)?', 'mysqli_connect()', 'mysqli_connect()', 1, '2025-05-19 18:29:13'),
(22, 4, 'PHP', 'What is the purpose of the \"include\" statement in PHP?', 'Includes JS file', 'Includes and evaluates a specified file', 0, '2025-05-19 18:29:13'),
(23, 4, 'PHP', 'Which PHP function is used to prevent SQL injection?', 'mysqli_real_escape_string()', 'mysqli_real_escape_string()', 1, '2025-05-19 18:29:13'),
(24, 4, 'PHP', 'Which symbol is used to declare a variable in PHP?', '#', '$', 0, '2025-05-19 18:29:13'),
(25, 4, 'PHP', 'Which operator is used for concatenation in PHP?', '.', '.', 1, '2025-05-19 18:29:13'),
(26, 4, 'PHP', 'Which of the following is used to output data in PHP?', 'echo', 'echo', 1, '2025-05-19 18:29:13'),
(27, 4, 'PHP', 'What does PHP stand for?', 'Private Home Page', 'PHP: Hypertext Preprocessor', 0, '2025-05-19 18:29:13'),
(28, 4, 'PHP', 'Which tag is used to embed PHP code in an HTML file?', '<php>', '<?php ?>', 0, '2025-05-19 18:29:14'),
(29, 4, 'PHP', 'Which superglobal is used to collect form data sent with method=\"post\"?', '$_POST', '$_POST', 1, '2025-05-19 18:29:14'),
(30, 4, 'PHP', 'How do you create a function in PHP?', 'create myFunction()', 'function myFunction()', 0, '2025-05-19 18:29:14'),
(31, 2, 'CSS', 'Which property is used to change the font size in CSS?', 'text-size', 'font-size', 0, '2025-05-22 18:26:32'),
(32, 2, 'CSS', 'How do you specify multiple values for the box-shadow property in CSS?', 'box-shadow: 2px 2px 5px 0px #000;', 'box-shadow: 2px 2px 5px 0px #000;', 1, '2025-05-22 18:26:32'),
(33, 2, 'CSS', 'What does the flex-wrap property do in CSS?', 'Controls the alignment of items', 'Determines if flex items should wrap or not', 0, '2025-05-22 18:26:32'),
(34, 2, 'CSS', 'Which CSS property is used to control the visibility of an element without removing it from the document flow?', 'opacity', 'visibility', 0, '2025-05-22 18:26:32'),
(35, 2, 'CSS', 'How do you create a responsive layout in CSS?', 'Use flexbox', 'Use media queries', 0, '2025-05-22 18:26:32'),
(36, 2, 'CSS', 'What does the z-index property do in CSS?', 'Controls the position of elements', 'Controls the stacking order of elements', 0, '2025-05-22 18:26:32'),
(37, 2, 'CSS', 'What is the correct way to define a class in CSS?', '.myClass {}', '.myClass {}', 1, '2025-05-22 18:26:32'),
(38, 2, 'CSS', 'Which property is used to create space between content and the border in CSS?', 'border-spacing', 'padding', 0, '2025-05-22 18:26:32'),
(39, 2, 'CSS', 'How do you create a flexible container in CSS?', 'flex-direction: column;', 'display: flex;', 0, '2025-05-22 18:26:32'),
(40, 2, 'CSS', 'Which property is used to control the space between elements in a grid container?', 'grid-gap', 'grid-gap', 1, '2025-05-22 18:26:32');

-- --------------------------------------------------------

--
-- Table structure for table `mentee_assessment`
--

CREATE TABLE `mentee_assessment` (
  `Item_ID` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `CreatedBy` varchar(100) NOT NULL,
  `Course_Title` enum('HTML','PHP','JAVA','C#','CSS') NOT NULL,
  `Difficulty_Level` enum('Beginner','Intermediate','Advanced') NOT NULL,
  `Question` text NOT NULL,
  `Choice1` varchar(255) NOT NULL,
  `Choice2` varchar(255) NOT NULL,
  `Choice3` varchar(255) NOT NULL,
  `Choice4` varchar(255) NOT NULL,
  `Correct_Answer` varchar(255) NOT NULL,
  `Status` varchar(100) NOT NULL,
  `Reason` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentee_assessment`
--

INSERT INTO `mentee_assessment` (`Item_ID`, `user_id`, `CreatedBy`, `Course_Title`, `Difficulty_Level`, `Question`, `Choice1`, `Choice2`, `Choice3`, `Choice4`, `Correct_Answer`, `Status`, `Reason`) VALUES
(16, NULL, 'Angela Marie Gabriel', 'HTML', 'Beginner', 'What does HTML stand for?', 'Hypertext Markup Language', 'Home Tool Markup Language', 'Hyperlink Text Markup Language', 'Home Text Markup Language', 'Hypertext Markup Language', 'Under Review\r\n', ''),
(17, NULL, 'Angela Marie Gabriel', 'HTML', 'Beginner', 'Which tag is used to define a paragraph in HTML?', '<p>', '<h1>', '<br>', '<div>', '<p>', 'Rejected', ''),
(18, NULL, 'Angela Marie Gabriel', 'HTML', 'Beginner', 'Which attribute is used to define the background color of a webpage?', 'bgcolor', 'background', 'color', 'style', 'bgcolor', 'Approved', ''),
(19, NULL, 'Angela Marie Gabriel', 'HTML', 'Beginner', 'Which tag is used to link to an external style sheet?', '<link>', '<style>', '<script>', '<css>', '<link>', 'Approved', ''),
(20, NULL, 'Angela Marie Gabriel', 'HTML', 'Beginner', 'What is the correct HTML tag for inserting an image?', '<image>', '<img>', '<src>', '<pic>', '<img>', 'Approved', ''),
(21, NULL, 'Angela Marie Gabriel', 'HTML', 'Intermediate', 'What is the default value of the \"display\" property for a <div> element?', 'inline', 'block', 'none', 'flex', 'block', 'Approved', ''),
(22, NULL, 'Angela Marie Gabriel', 'HTML', 'Intermediate', 'Which of these tags is used for creating a drop-down list?', '<select>', '<dropdown>', '<list>', '<options>', '<select>', 'Approved', ''),
(23, NULL, 'Angela Marie Gabriel', 'HTML', 'Intermediate', 'What is the correct way to add a comment in HTML?', '<!-- comment -->', '/* comment */', '// comment', '## comment ##', '<!-- comment -->', 'Approved', ''),
(24, NULL, 'Angela Marie Gabriel', 'HTML', 'Intermediate', 'Which tag is used to define a table row in HTML?', '<tr>', '<td>', '<th>', '<table>', '<tr>', 'Approved', ''),
(25, NULL, 'Angela Marie Gabriel', 'HTML', 'Intermediate', 'Which attribute is used to provide alternative text for an image in HTML?', 'alt', 'title', 'src', 'text', 'alt', 'Approved', ''),
(26, NULL, 'Angela Marie Gabriel', 'HTML', 'Advanced', 'Which tag is used to define a hyperlink in HTML?', '<a>', '<link>', '<href>', '<url>', '<a>', 'Approved', ''),
(27, NULL, 'Angela Marie Gabriel', 'HTML', 'Advanced', 'What is the purpose of the \"data-\" attribute in HTML?', 'To store extra data for the element', 'To define custom styles', 'To bind events', 'To create a data model', 'To store extra data for the element', 'Approved', ''),
(28, NULL, 'Angela Marie Gabriel', 'HTML', 'Advanced', 'What is the correct syntax for embedding a video in HTML5?', '<video src=\"video.mp4\">', '<embed src=\"video.mp4\">', '<movie src=\"video.mp4\">', '<video file=\"video.mp4\">', '<video src=\"video.mp4\">', 'Approved', ''),
(29, NULL, 'Angela Marie Gabriel', 'HTML', 'Advanced', 'Which tag is used to define a form in HTML?', '<form>', '<input>', '<textarea>', '<button>', '<form>', 'Approved', ''),
(30, NULL, 'Angela Marie Gabriel', 'HTML', 'Advanced', 'What is the correct HTML tag for embedding a font from Google Fonts?', '<link href=\"https://fonts.googleapis.com/css2?family=Roboto\" rel=\"stylesheet\">', '<font src=\"https://fonts.googleapis.com/css2?family=Roboto\">', '<embed src=\"https://fonts.googleapis.com/css2?family=Roboto\">', '<style>@import url(\"https://fonts.googleapis.com/css2?family=Roboto\");</style>', '<link href=\"https://fonts.googleapis.com/css2?family=Roboto\" rel=\"stylesheet\">', 'Approved', ''),
(31, NULL, 'Angela Marie Gabriel', 'PHP', 'Beginner', 'What does PHP stand for?', 'Personal Home Page', 'Private Home Page', 'Preprocessor Hypertext', 'PHP: Hypertext Preprocessor', 'PHP: Hypertext Preprocessor', 'Approved', ''),
(32, NULL, 'Angela Marie Gabriel', 'PHP', 'Beginner', 'Which symbol is used to declare a variable in PHP?', '$', '#', '&', '@', '$', 'Approved', ''),
(33, NULL, 'Angela Marie Gabriel', 'PHP', 'Beginner', 'Which of the following is used to output data in PHP?', 'echo', 'display', 'printscreen', 'write', 'echo', 'Approved', ''),
(34, NULL, 'Angela Marie Gabriel', 'PHP', 'Beginner', 'How do you write a comment in PHP?', '// comment', '# comment', '/* comment */', 'All of the above', 'All of the above', 'Approved', ''),
(35, NULL, 'Angela Marie Gabriel', 'PHP', 'Beginner', 'Which tag is used to embed PHP code in an HTML file?', '<?php ?>', '<php>', '<script>', '<? ?>', '<?php ?>', 'Approved', ''),
(36, NULL, 'Angela Marie Gabriel', 'PHP', 'Intermediate', 'Which superglobal is used to collect form data sent with method=\"post\"?', '$_GET', '$_POST', '$_FORM', '$_DATA', '$_POST', 'Approved', ''),
(37, NULL, 'Angela Marie Gabriel', 'PHP', 'Intermediate', 'How do you create a function in PHP?', 'function myFunction()', 'create myFunction()', 'def myFunction()', 'fn myFunction()', 'function myFunction()', 'Approved', ''),
(38, NULL, 'Angela Marie Gabriel', 'PHP', 'Intermediate', 'Which operator is used for concatenation in PHP?', '+', '.', '&&', '&', '.', 'Approved', ''),
(39, NULL, 'Angela Marie Gabriel', 'PHP', 'Intermediate', 'What does the isset() function check for?', 'If a variable is null', 'If a variable exists and is not null', 'If a variable is empty', 'If a variable is boolean', 'If a variable exists and is not null', 'Rejected', ''),
(40, NULL, 'Angela Marie Gabriel', 'PHP', 'Intermediate', 'How do you access a value in an associative array?', '$array[index]', '$array->value', '$array[\"key\"]', '$array.key', '$array[\"key\"]', 'Approved', ''),
(41, NULL, 'Angela Marie Gabriel', 'PHP', 'Advanced', 'How do you connect to a MySQL database in PHP (procedural)?', 'mysqli_connect()', 'mysql_connect()', 'pdo_connect()', 'connect_db()', 'mysqli_connect()', 'Approved', ''),
(42, NULL, 'Angela Marie Gabriel', 'PHP', 'Advanced', 'What is the purpose of the \"include\" statement in PHP?', 'Includes external CSS', 'Includes JS file', 'Includes and evaluates a specified file', 'Creates a class', 'Includes and evaluates a specified file', 'Approved', ''),
(43, NULL, 'Angela Marie Gabriel', 'PHP', 'Advanced', 'What will count($array) return for an empty array?', 'null', '0', 'false', '1', '0', 'Approved', ''),
(44, NULL, 'Angela Marie Gabriel', 'PHP', 'Advanced', 'Which PHP function is used to prevent SQL injection?', 'escape_string()', 'mysqli_real_escape_string()', 'sanitize_input()', 'filter_sql()', 'mysqli_real_escape_string()', 'Approved', ''),
(45, NULL, 'Angela Marie Gabriel', 'PHP', 'Advanced', 'Which of these is a correct way to define a class in PHP?', 'class MyClass {}', 'function MyClass() {}', 'object MyClass {}', 'define class MyClass {}', 'class MyClass {}', 'Approved', ''),
(46, NULL, 'Angela Marie Gabriel', 'JAVA', 'Beginner', 'Which symbol is used to declare a variable in Java?', 'var', 'let', 'final', 'int', 'int', 'Approved', ''),
(47, NULL, 'Angela Marie Gabriel', 'JAVA', 'Beginner', 'What is the correct way to define a class in Java?', 'class MyClass {}', 'def MyClass {}', 'class = MyClass {}', 'class MyClass[] {}', 'class MyClass {}', 'Approved', ''),
(48, NULL, 'Angela Marie Gabriel', 'JAVA', 'Beginner', 'Which method is the entry point for a Java program?', 'start()', 'begin()', 'main()', 'run()', 'main()', 'Approved', ''),
(49, NULL, 'Angela Marie Gabriel', 'JAVA', 'Beginner', 'What is the correct syntax for a single-line comment in Java?', '// comment', '/* comment */', '# comment', '### comment ###', '// comment', 'Approved', ''),
(50, NULL, 'Angela Marie Gabriel', 'JAVA', 'Beginner', 'Which keyword is used to define a constant in Java?', 'const', 'constant', 'final', 'static', 'final', 'Approved', ''),
(51, NULL, 'Angela Marie Gabriel', 'JAVA', 'Intermediate', 'What does the \"public\" keyword mean in Java?', 'Accessible from any class', 'Accessible within the package', 'Accessible only inside the class', 'Accessible within the method', 'Accessible from any class', 'Approved', ''),
(52, NULL, 'Angela Marie Gabriel', 'JAVA', 'Intermediate', 'What is the correct way to create an array in Java?', 'int[] arr = new int[5];', 'int arr[5];', 'int arr[] = new int[];', 'arr = new int[5];', 'int[] arr = new int[5];', 'Approved', ''),
(53, NULL, 'Angela Marie Gabriel', 'JAVA', 'Intermediate', 'Which method is used to find the length of an array in Java?', 'length()', 'size()', 'getLength()', 'len()', 'length()', 'Approved', ''),
(54, NULL, 'Angela Marie Gabriel', 'JAVA', 'Intermediate', 'Which exception is thrown when trying to divide by zero in Java?', 'ArithmeticException', 'NullPointerException', 'IndexOutOfBoundsException', 'IOException', 'ArithmeticException', 'Approved', ''),
(55, NULL, 'Angela Marie Gabriel', 'JAVA', 'Intermediate', 'Which keyword is used to inherit a class in Java?', 'extends', 'inherits', 'super', 'implements', 'extends', 'Approved', ''),
(56, NULL, 'Angela Marie Gabriel', 'JAVA', 'Advanced', 'Which collection is used for storing unique elements in Java?', 'HashMap', 'HashSet', 'ArrayList', 'TreeSet', 'HashSet', 'Approved', ''),
(57, NULL, 'Angela Marie Gabriel', 'JAVA', 'Advanced', 'What is the result of the expression \"5 / 2\" in Java?', '2.5', '2', '2.0', '3', '2', 'Approved', ''),
(58, NULL, 'Angela Marie Gabriel', 'JAVA', 'Advanced', 'What is the correct way to handle exceptions in Java?', 'try-catch', 'catch-throw', 'throw-catch', 'try-throw', 'try-catch', 'Approved', ''),
(59, NULL, 'Angela Marie Gabriel', 'JAVA', 'Advanced', 'What does JVM stand for in Java?', 'Java Visual Machine', 'Java Virtual Memory', 'Java Virtual Machine', 'Java Version Manager', 'Java Virtual Machine', 'Approved', ''),
(60, NULL, 'Angela Marie Gabriel', 'JAVA', 'Advanced', 'Which method in Java is used to compare two strings?', 'compare()', 'equals()', '==', 'compareTo()', 'equals()', 'Approved', ''),
(61, NULL, 'Angela Marie Gabriel', 'C#', 'Beginner', 'Which keyword is used to declare a variable in C#?', 'var', 'let', 'int', 'const', 'int', 'Approved', ''),
(62, NULL, 'Angela Marie Gabriel', 'C#', 'Beginner', 'Which method is used to output data to the console in C#?', 'print()', 'write()', 'echo()', 'Console.WriteLine()', 'Console.WriteLine()', 'Approved', ''),
(63, NULL, 'Angela Marie Gabriel', 'C#', 'Beginner', 'How do you start a C# program?', 'using System;', 'import System;', 'namespace Program { }', 'public static void Main() { }', 'public static void Main() { }', 'Approved', ''),
(64, NULL, 'Angela Marie Gabriel', 'C#', 'Beginner', 'What is the correct syntax for a single-line comment in C#?', '// comment', '/* comment */', '# comment', '### comment ###', '// comment', 'Approved', ''),
(65, NULL, 'Angela Marie Gabriel', 'C#', 'Beginner', 'How do you declare a constant in C#?', 'const variableName = value;', 'constant variableName = value;', 'readonly variableName = value;', 'var variableName = value;', 'const variableName = value;', 'Approved', ''),
(66, NULL, 'Angela Marie Gabriel', 'C#', 'Intermediate', 'Which method is used to get the length of an array in C#?', 'length()', 'size()', 'GetLength()', 'Length()', 'Length()', 'Approved', ''),
(67, NULL, 'Angela Marie Gabriel', 'C#', 'Intermediate', 'What is the correct way to declare a class in C#?', 'class MyClass {}', 'class = MyClass {}', 'define class MyClass {}', 'MyClass class {}', 'class MyClass {}', 'Approved', ''),
(68, NULL, 'Angela Marie Gabriel', 'C#', 'Intermediate', 'How do you declare a method in C#?', 'method MyMethod()', 'void MyMethod()', 'def MyMethod()', 'public void MyMethod()', 'public void MyMethod()', 'Approved', ''),
(69, NULL, 'Angela Marie Gabriel', 'C#', 'Intermediate', 'Which data type is used to store a decimal value in C#?', 'float', 'decimal', 'int', 'double', 'decimal', 'Approved', ''),
(70, NULL, 'Angela Marie Gabriel', 'C#', 'Intermediate', 'What is the correct way to call a method in C#?', 'method()', 'MyMethod()', 'void MyMethod()', 'MyMethod()', 'MyMethod()', 'Approved', ''),
(71, NULL, 'Angela Marie Gabriel', 'C#', 'Advanced', 'Which collection class in C# is used to store unique elements?', 'List', 'ArrayList', 'HashSet', 'Queue', 'HashSet', 'Approved', ''),
(72, NULL, 'Angela Marie Gabriel', 'C#', 'Advanced', 'Which keyword is used to inherit a class in C#?', 'extends', 'implements', 'inherits', 'base', 'extends', 'Approved', ''),
(73, NULL, 'Angela Marie Gabriel', 'C#', 'Advanced', 'How do you define a delegate in C#?', 'delegate void MyDelegate();', 'function MyDelegate();', 'public MyDelegate();', 'define MyDelegate();', 'delegate void MyDelegate();', 'Approved', ''),
(74, NULL, 'Angela Marie Gabriel', 'C#', 'Advanced', 'What is the default value of a boolean in C#?', 'false', 'true', 'null', '0', 'false', 'Approved', ''),
(75, NULL, 'Angela Marie Gabriel', 'C#', 'Advanced', 'Which method is used to compare two strings in C#?', 'compare()', 'string.Equals()', 'string.Compare()', 'string.CompareTo()', 'string.Equals()', 'Approved', ''),
(76, NULL, 'Angela Marie Gabriel', 'CSS', 'Beginner', 'Which property is used to change the text color in CSS?', 'color', 'font-color', 'text-color', 'background-color', 'color', 'Approved', ''),
(77, NULL, 'Angela Marie Gabriel', 'CSS', 'Beginner', 'How do you center an element horizontally in CSS?', 'margin: auto;', 'text-align: center;', 'display: center;', 'align: center;', 'margin: auto;', 'Approved', ''),
(78, NULL, 'Angela Marie Gabriel', 'CSS', 'Beginner', 'What is the correct way to define a class in CSS?', '.myClass {}', '#myClass {}', 'myClass {}', 'class.myClass {}', '.myClass {}', 'Approved', ''),
(79, NULL, 'Angela Marie Gabriel', 'CSS', 'Beginner', 'Which property is used to change the font size in CSS?', 'font-size', 'text-size', 'size', 'font-style', 'font-size', 'Approved', ''),
(80, NULL, 'Angela Marie Gabriel', 'CSS', 'Beginner', 'How do you make a div element a block-level element in CSS?', 'display: block;', 'display: inline;', 'display: flex;', 'display: block-level;', 'display: block;', 'Approved', ''),
(81, NULL, 'Angela Marie Gabriel', 'CSS', 'Intermediate', 'Which property is used to create space between content and the border in CSS?', 'padding', 'margin', 'border-spacing', 'spacing', 'padding', 'Approved', ''),
(82, NULL, 'Angela Marie Gabriel', 'CSS', 'Intermediate', 'What does the z-index property do in CSS?', 'Controls the stacking order of elements', 'Changes the opacity of elements', 'Controls the position of elements', 'Aligns elements vertically', 'Controls the stacking order of elements', 'Approved', ''),
(83, NULL, 'Angela Marie Gabriel', 'CSS', 'Intermediate', 'Which property is used to make an element’s background transparent in CSS?', 'opacity', 'transparent', 'background-opacity', 'background-color', 'opacity', 'Approved', ''),
(84, NULL, 'Angela Marie Gabriel', 'CSS', 'Intermediate', 'How do you create a flexible container in CSS?', 'display: flex;', 'flex-direction: column;', 'display: grid;', 'position: flex;', 'display: flex;', 'Approved', ''),
(85, NULL, 'Angela Marie Gabriel', 'CSS', 'Intermediate', 'How do you specify multiple values for the box-shadow property in CSS?', 'box-shadow: 2px 2px 5px 0px #000;', 'box-shadow: inset 0 0 5px;', 'box-shadow: 0px 5px 10px;', 'box-shadow: 0 0 10px 0 rgba(0, 0, 0, 0.5);', 'box-shadow: 2px 2px 5px 0px #000;', 'Approved', ''),
(86, NULL, 'Angela Marie Gabriel', 'CSS', 'Advanced', 'How do you create a responsive layout in CSS?', 'Use media queries', 'Use flexbox', 'Use grid', 'Use float', 'Use media queries', 'Approved', ''),
(87, NULL, 'Angela Marie Gabriel', 'CSS', 'Advanced', 'Which CSS property is used to control the visibility of an element without removing it from the document flow?', 'visibility', 'display', 'opacity', 'position', 'visibility', 'Approved', ''),
(88, NULL, 'Angela Marie Gabriel', 'CSS', 'Advanced', 'What does the position: sticky; property do in CSS?', 'Makes the element fixed at a specific position within the viewport', 'Keeps the element at the top of the page', 'Makes the element visible on scroll', 'Keeps the element static', 'Makes the element fixed at a specific position within the viewport', 'Approved', ''),
(89, NULL, 'Angela Marie Gabriel', 'CSS', 'Advanced', 'What does the flex-wrap property do in CSS?', 'Determines if flex items should wrap or not', 'Sets the flex direction', 'Controls the alignment of items', 'Sets the item order', 'Determines if flex items should wrap or not', 'Approved', ''),
(90, NULL, 'Angela Marie Gabriel', 'CSS', 'Advanced', 'Which property is used to control the space between elements in a grid container?', 'grid-gap', 'gap', 'grid-spacing', 'spacing', 'grid-gap', 'Approved', ''),
(109, 10, 'John Kenneth Dizon', 'CSS', 'Advanced', 'hello', '231', '12', 'de', 'dx', '231', 'Under Review', ''),
(110, 10, 'John Kenneth Dizon', 'CSS', 'Advanced', 'hello', '231', '12', 'de', 'dx', '231', 'Under Review', ''),
(111, 10, 'John Kenneth Dizon', 'CSS', 'Advanced', 'hello', '231', '12', 'de', 'dx', '231', 'Under Review', ''),
(112, 10, 'John Kenneth Dizon', 'CSS', 'Advanced', 'hello', '231', '12', 'de', 'dx', '231', 'Under Review', ''),
(113, 10, 'John Kenneth Dizon', 'CSS', 'Advanced', 'hello', '231', '12', 'de', 'dx', '231', 'Under Review', '');

-- --------------------------------------------------------

--
-- Table structure for table `mentors_assessment`
--

CREATE TABLE `mentors_assessment` (
  `ITEM_ID` int(11) NOT NULL,
  `Course_Title` varchar(100) NOT NULL,
  `Difficulty_Level` enum('Beginner','Intermediate','Advanced') NOT NULL,
  `Question` text NOT NULL,
  `Choice1` text NOT NULL,
  `Choice2` text NOT NULL,
  `Choice3` text NOT NULL,
  `Choice4` text NOT NULL,
  `Correct_Answer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentors_assessment`
--

INSERT INTO `mentors_assessment` (`ITEM_ID`, `Course_Title`, `Difficulty_Level`, `Question`, `Choice1`, `Choice2`, `Choice3`, `Choice4`, `Correct_Answer`) VALUES
(1, 'HTML', 'Beginner', 'What does HTML stand for?', 'Hyper Trainer Marking Language', 'Hyper Text Markup Language', 'Hyper Text Marketing Language', 'Hyper Type Markup Language', 'Hyper Text Markup Language'),
(2, 'HTML', 'Beginner', 'Which tag creates a line break?', '<break>', '<br>', '<lb>', '<line>', '<br>'),
(3, 'HTML', 'Beginner', 'How do you create a hyperlink in HTML?', '<a link=\"http://example.com\">Link</a>', '<a src=\"http://example.com\">Link</a>', '<a href=\"http://example.com\">Link</a>', '<link>http://example.com</link>', '<a href=\"http://example.com\">Link</a>'),
(4, 'HTML', 'Beginner', 'Which element is used to display an image?', '<img>', '<src>', '<picture>', '<media>', '<img>'),
(5, 'HTML', 'Beginner', 'HTML files are saved with what file extension?', '.htm', '.html', '.xml', '.doc', '.html'),
(6, 'HTML', 'Intermediate', 'Which tag is used to define an internal style sheet?', '<style>', '<css>', '<script>', '<link>', '<style>'),
(7, 'HTML', 'Intermediate', 'What is the correct HTML element for the largest heading?', '<head>', '<h6>', '<h1>', '<header>', '<h1>'),
(8, 'HTML', 'Intermediate', 'Which HTML tag is used to define a table row?', '<th>', '<tr>', '<td>', '<row>', '<tr>'),
(9, 'HTML', 'Intermediate', 'What attribute makes a form field required?', 'validate', 'compulsory', 'required', 'mandatory', 'required'),
(10, 'HTML', 'Intermediate', 'What is the default method of a form submission?', 'GET', 'POST', 'SUBMIT', 'FETCH', 'GET'),
(11, 'HTML', 'Advanced', 'What is the correct HTML code to embed a video?', '<video src=\"movie.mp4\">', '<media src=\"movie.mp4\" controls>', '<video controls><source src=\"movie.mp4\" type=\"video/mp4\"></video>', '<embed video=\"movie.mp4\" />', '<video controls><source src=\"movie.mp4\" type=\"video/mp4\"></video>'),
(12, 'HTML', 'Advanced', 'Which HTML5 element defines footer for a document?', '<bottom>', '<end>', '<footer>', '<section>', '<footer>'),
(13, 'HTML', 'Advanced', 'How do you create a checkbox in HTML?', '<input type=\"checkbox\">', '<checkbox>', '<check>', '<input check=\"true\">', '<input type=\"checkbox\">'),
(14, 'HTML', 'Advanced', 'What tag is used for a self-contained piece of content?', '<section>', '<aside>', '<article>', '<block>', '<article>'),
(15, 'HTML', 'Advanced', 'What is the semantic HTML tag for navigation links?', '<links>', '<nav>', '<menu>', '<navigate>', '<nav>'),
(16, 'CSS', 'Beginner', 'Which property changes text color?', 'font-color', 'text-color', 'color', 'foreground', 'color'),
(17, 'CSS', 'Beginner', 'How do you add an external CSS file?', '<style src=\"style.css\">', '<link rel=\"stylesheet\" href=\"style.css\">', '<css href=\"style.css\">', '<import href=\"style.css\">', '<link rel=\"stylesheet\" href=\"style.css\">'),
(18, 'CSS', 'Beginner', 'Which selector targets a class called \"menu\"?', '#menu', '.menu', 'menu', '*menu', '.menu'),
(19, 'CSS', 'Beginner', 'What unit is used for relative font size?', 'px', 'em', 'pt', 'in', 'em'),
(20, 'CSS', 'Beginner', 'Which property sets background color?', 'background', 'bgcolor', 'background-color', 'color', 'background-color'),
(21, 'CSS', 'Intermediate', 'What is the default position value in CSS?', 'static', 'relative', 'absolute', 'fixed', 'static'),
(22, 'CSS', 'Intermediate', 'How do you make a flex container?', 'display: flex;', 'flex: container;', 'layout: flex;', 'box: flex;', 'display: flex;'),
(23, 'CSS', 'Intermediate', 'What does the z-index property control?', 'Font size', 'Stacking order', 'Color depth', 'Layout grid', 'Stacking order'),
(24, 'CSS', 'Intermediate', 'How do you apply a style to all <p> elements inside a div?', 'div > p', 'div p', '.div p', 'div:p', 'div p'),
(25, 'CSS', 'Intermediate', 'How do you remove underline from links?', 'text-decoration: none;', 'text-style: normal;', 'decoration: remove;', 'link-style: off;', 'text-decoration: none;'),
(26, 'CSS', 'Advanced', 'Which of the following uses a CSS grid layout?', 'display: block;', 'display: grid;', 'display: layout;', 'display: flexgrid;', 'display: grid;'),
(27, 'CSS', 'Advanced', 'Which is a valid CSS variable declaration?', '--main-color: blue;', 'var-color: blue;', 'let color = blue;', '$main-color: blue;', '--main-color: blue;'),
(28, 'CSS', 'Advanced', 'How do you apply a transition to background color over 1s?', 'transition: 1s background-color;', 'transition: background-color 1s;', 'effect: bg 1s;', 'background-transition: 1s;', 'transition: background-color 1s;'),
(29, 'CSS', 'Advanced', 'How do you create a media query for screens smaller than 600px?', '@media max-width: 600px', '@media (max-width: 600px)', '@screen <600px', '@responsive 600px', '@media (max-width: 600px)'),
(30, 'CSS', 'Advanced', 'What is specificity in CSS?', 'How styles override each other', 'How fast CSS loads', 'How deep styles go', 'How complex the file is', 'How styles override each other'),
(31, 'PHP', 'Beginner', 'How do you start a PHP script?', '<?php', '<php>', '{php}', '<script php>', '<?php'),
(32, 'PHP', 'Beginner', 'Which symbol is used for variables in PHP?', '$', '#', '&', '@', '$'),
(33, 'PHP', 'Beginner', 'How do you output text in PHP?', 'echo', 'print()', 'return', 'say()', 'echo'),
(34, 'PHP', 'Beginner', 'Which of these is a PHP comment?', '// comment', '/* comment */', '# comment', 'All of the above', 'All of the above'),
(35, 'PHP', 'Beginner', 'Which function checks if a file exists?', 'exists()', 'file_exists()', 'check_file()', 'file_check()', 'file_exists()'),
(36, 'PHP', 'Intermediate', 'Which of these is used to include a file once?', 'include()', 'include_once()', 'require()', 'require_always()', 'include_once()'),
(37, 'PHP', 'Intermediate', 'How do you define a constant in PHP?', 'const MYCONST = \"Hello\";', 'define(\"MYCONST\", \"Hello\");', '$MYCONST = \"Hello\";', 'setconst(\"MYCONST\", \"Hello\");', 'define(\"MYCONST\", \"Hello\");'),
(38, 'PHP', 'Intermediate', 'How do you create an associative array?', 'array(\"key\"=>\"value\")', '{\"key\":\"value\"}', '[\"key\":\"value\"]', 'map(key=>value)', 'array(\"key\"=>\"value\")'),
(39, 'PHP', 'Intermediate', 'How do you connect to a MySQL database?', 'new mysqli()', 'pdo_connect()', 'mysql.connect()', 'database.open()', 'new mysqli()'),
(40, 'PHP', 'Intermediate', 'What does $_SERVER do?', 'Stores server variables', 'Connects to server', 'Creates HTTP request', 'Stores database', 'Stores server variables'),
(41, 'PHP', 'Advanced', 'Which PHP feature allows you to catch exceptions?', 'try...catch', 'handle...error', 'throw...catch', 'rescue', 'try...catch'),
(42, 'PHP', 'Advanced', 'What is the purpose of \"namespace\" in PHP?', 'Group classes logically', 'Hide variables', 'Restrict access', 'Speed up code', 'Group classes logically'),
(43, 'PHP', 'Advanced', 'Which interface is used for iterating objects?', 'Iterator', 'Traversable', 'Iterable', 'Loopable', 'Iterator'),
(44, 'PHP', 'Advanced', 'How do you define a class in PHP?', 'class MyClass {}', 'define class {}', 'object MyClass', 'new class {}', 'class MyClass {}'),
(45, 'PHP', 'Advanced', 'What does \"final\" keyword mean in PHP?', 'Class/method cannot be overridden', 'It’s the last line', 'Script ends here', 'None of the above', 'Class/method cannot be overridden'),
(46, 'C#', 'Beginner', 'Which keyword declares a class in C#?', 'function', 'class', 'object', 'define', 'class'),
(47, 'C#', 'Beginner', 'What is the correct syntax for the Main method?', 'static void Main()', 'void Main()', 'public Main()', 'Main() void', 'static void Main()'),
(48, 'C#', 'Beginner', 'Which data type is used for whole numbers?', 'float', 'int', 'char', 'bool', 'int'),
(49, 'C#', 'Beginner', 'How do you declare a string variable?', 'string name;', 'char name;', 'text name;', 'str name;', 'string name;'),
(50, 'C#', 'Beginner', 'What does \"using\" mean in C#?', 'Import a namespace', 'Start a function', 'Create object', 'Include file', 'Import a namespace'),
(51, 'C#', 'Intermediate', 'Which loop is used to iterate a known number of times?', 'while', 'foreach', 'for', 'loop', 'for'),
(52, 'C#', 'Intermediate', 'Which access modifier allows access only within the same class?', 'private', 'public', 'protected', 'internal', 'private'),
(53, 'C#', 'Intermediate', 'Which keyword creates a new object?', 'create', 'object', 'class', 'new', 'new'),
(54, 'C#', 'Intermediate', 'How do you handle exceptions?', 'try...catch', 'error...handle', 'trap...except', 'catch...error', 'try...catch'),
(55, 'C#', 'Intermediate', 'Which data type stores true or false?', 'bool', 'bit', 'binary', 'flag', 'bool'),
(56, 'C#', 'Advanced', 'What is polymorphism?', 'Many functions in one class', 'Same method name, different behavior', 'Only one method per class', 'None of the above', 'Same method name, different behavior'),
(57, 'C#', 'Advanced', 'What is an abstract class?', 'A class with no methods', 'A class you cannot instantiate', 'A class that’s not real', 'A static class', 'A class you cannot instantiate'),
(58, 'C#', 'Advanced', 'What does \"override\" do in a method?', 'Replaces a base class method', 'Creates a new method', 'Ends inheritance', 'None of the above', 'Replaces a base class method'),
(59, 'C#', 'Advanced', 'Which keyword prevents inheritance?', 'final', 'private', 'sealed', 'lock', 'sealed'),
(60, 'C#', 'Advanced', 'What is LINQ in C#?', 'A database', 'A query language for data collections', 'An XML parser', 'A compiler tool', 'A query language for data collections'),
(61, 'Java', 'Beginner', 'What does JVM stand for?', 'Java Variable Machine', 'Java Visual Machine', 'Java Virtual Machine', 'Java View Model', 'Java Virtual Machine'),
(62, 'Java', 'Beginner', 'Which of the following is a valid declaration in Java?', 'int 1x;', 'int x;', 'x = int;', 'int x 1;', 'int x;'),
(63, 'Java', 'Beginner', 'Which of these is the main method signature in Java?', 'public static void main(String[] args)', 'public void main(String[] args)', 'static void main(String[] args)', 'main(String[] args)', 'public static void main(String[] args)'),
(64, 'Java', 'Beginner', 'Which keyword is used to define a class in Java?', 'class', 'define', 'struct', 'object', 'class'),
(65, 'Java', 'Beginner', 'Which of the following is used to read input from the user in Java?', 'System.out.println()', 'Scanner.next()', 'System.in.read()', 'Scanner.read()', 'Scanner.next()'),
(66, 'Java', 'Intermediate', 'What is the default value of a boolean variable in Java?', 'true', 'false', 'null', '0', 'false'),
(67, 'Java', 'Intermediate', 'Which of the following is not a Java data type?', 'String', 'int', 'real', 'boolean', 'real'),
(68, 'Java', 'Intermediate', 'Which of the following is a wrapper class for the int data type?', 'Integer', 'IntWrapper', 'Double', 'Byte', 'Integer'),
(69, 'Java', 'Intermediate', 'Which of the following is an example of method overloading in Java?', 'public void sum(int a, int b) { return a + b; }', 'public void sum(int a) { return a; }', 'public int sum(int a, double b) { return a + b; }', 'public void sum() { return 0; }', 'public int sum(int a, double b) { return a + b; }'),
(70, 'Java', 'Intermediate', 'Which of the following collections stores elements in a specific order?', 'HashSet', 'ArrayList', 'HashMap', 'TreeSet', 'ArrayList'),
(71, 'Java', 'Advanced', 'Which of the following is true about inheritance in Java?', 'A class can extend multiple classes.', 'A class can extend only one class.', 'A class can implement multiple interfaces but extend one class.', 'A class cannot implement an interface.', 'A class can implement multiple interfaces but extend one class.'),
(72, 'Java', 'Advanced', 'What is the purpose of the static keyword in Java?', 'It indicates that a variable or method is only accessible to the class that defines it.', 'It makes a method or variable shared among all instances of a class.', 'It indicates that a variable is only available in the current thread.', 'It restricts a variable or method to a single instance.', 'It makes a method or variable shared among all instances of a class.'),
(73, 'Java', 'Advanced', 'What is polymorphism in Java?', 'Overloading methods', 'Overriding methods', 'A class that can be used in multiple ways', 'A class that can extend multiple classes', 'A class that can be used in multiple ways'),
(74, 'Java', 'Advanced', 'What is the default access modifier for a class in Java?', 'public', 'protected', 'private', 'package-private', 'package-private'),
(75, 'Java', 'Advanced', 'Which of the following is true about Java interfaces?', 'An interface can contain both abstract and concrete methods.', 'An interface can only contain abstract methods.', 'An interface can contain constructors.', 'An interface can contain instance variables.', 'An interface can contain both abstract and concrete methods.');

-- --------------------------------------------------------

--
-- Table structure for table `pending_sessions`
--

CREATE TABLE `pending_sessions` (
  `Pending_ID` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `Course_Title` varchar(250) NOT NULL,
  `Session_Date` date NOT NULL,
  `Time_Slot` varchar(200) NOT NULL,
  `Submission_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('pending','approved','rejected') DEFAULT 'pending',
  `Admin_Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_sessions`
--

INSERT INTO `pending_sessions` (`Pending_ID`, `user_id`, `Course_Title`, `Session_Date`, `Time_Slot`, `Submission_Date`, `Status`, `Admin_Notes`) VALUES
(1, 10, 'CSS', '2025-05-18', '4:00 PM - 5:00 PM', '2025-05-18 07:53:07', 'approved', NULL),
(2, 9, 'HTML', '2025-05-18', '6:00 PM - 7:00 PM', '2025-05-18 15:40:48', 'approved', NULL),
(3, 11, 'PHP', '2025-05-19', '7:00 AM - 10:00 AM', '2025-05-19 10:11:32', 'rejected', 'Late na'),
(4, 11, 'PHP', '2025-05-19', '6:15 PM - 7:00 PM', '2025-05-19 10:12:27', 'approved', NULL),
(5, 10, 'CSS', '2025-05-22', '1:00 PM - 2:00 PM', '2025-05-22 03:12:12', 'approved', NULL),
(7, 10, 'CSS', '2025-09-03', '10:21 AM - 12:23 PM', '2025-09-03 02:21:47', 'approved', NULL),
(8, 10, 'CSS', '2025-09-03', '10:46 AM - 10:54 PM', '2025-09-03 02:54:43', 'approved', NULL),
(9, 10, 'CSS', '2025-09-03', '2:05 PM - 7:05 PM', '2025-09-03 06:05:49', 'approved', NULL),
(10, 10, 'CSS', '2025-09-03', '2:12 PM - 7:12 PM', '2025-09-03 06:12:36', 'approved', NULL),
(11, 10, 'CSS', '2025-09-04', '12:42 PM - 6:42 PM', '2025-09-04 04:43:01', 'approved', NULL),
(12, 10, 'CSS', '2025-09-04', '8:46 PM - 12:46 PM', '2025-09-04 12:46:47', 'approved', NULL),
(13, 10, 'CSS', '2025-09-05', '10:09 AM - 10:09 PM', '2025-09-05 02:09:40', 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `like_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_likes`
--

INSERT INTO `post_likes` (`like_id`, `post_id`, `user_id`) VALUES
(30, 129, 1),
(31, 132, 1);

-- --------------------------------------------------------

--
-- Table structure for table `quizassignments`
--

CREATE TABLE `quizassignments` (
  `Assignment_ID` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `Course_Title` varchar(255) NOT NULL,
  `Date_Assigned` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizassignments`
--

INSERT INTO `quizassignments` (`Assignment_ID`, `user_id`, `Course_Title`, `Date_Assigned`) VALUES
(1, 1, 'HTML', '2025-05-18 15:20:09'),
(2, 3, 'HTML', '2025-05-18 15:20:15'),
(3, 4, 'PHP', '2025-05-19 18:27:20'),
(4, 4, 'PHP', '2025-05-19 18:30:48'),
(5, 2, 'CSS', '2025-05-22 18:25:09'),
(6, 2, 'CSS', '2025-05-22 18:26:40'),
(7, 2, 'CSS', '2025-05-22 19:14:59'),
(8, 1, 'CSS', '2025-05-22 19:15:24'),
(9, 1, 'CSS', '2025-09-02 15:02:46'),
(10, 1, 'CSS', '2025-09-02 15:03:20'),
(11, 1, 'CSS', '2025-09-05 10:09:58'),
(12, 1, 'CSS', '2025-09-05 10:10:16'),
(13, 1, 'CSS', '2025-09-05 10:10:29');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `Resource_ID` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `UploadedBy` varchar(200) NOT NULL,
  `Resource_Title` varchar(200) NOT NULL,
  `Resource_Icon` varchar(200) NOT NULL,
  `Resource_Type` varchar(200) NOT NULL,
  `Resource_File` varchar(200) NOT NULL,
  `Category` varchar(70) NOT NULL,
  `Status` varchar(200) NOT NULL,
  `Reason` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`Resource_ID`, `user_id`, `UploadedBy`, `Resource_Title`, `Resource_Icon`, `Resource_Type`, `Resource_File`, `Category`, `Status`, `Reason`) VALUES
(1, 10, 'John Kenneth Dizon', 'Introduction to CSS', 'icon_6829842b923c4.png', 'PDF', 'file_6829842b926e1.pdf', 'CSS', 'Approved', ''),
(2, 10, 'John Kenneth Dizon', 'Tutorial to CSS', 'icon_68298532f093a.png', 'Video', 'file_68298532f0cdc.mp4', 'CSS', 'Approved', ''),
(3, 9, 'Kim Ashley Villafania', 'Introduction to HTML', 'icon_682985abba3ef.png', 'PDF', 'file_682985abba883.pdf', 'HTML', 'Rejected', 'Content is not related and helpful.'),
(4, 9, 'Kim Ashley Villafania', 'Introduction to HTML', 'icon_682986382226a.png', 'PPT', 'file_6829863822608.pptx', 'HTML', 'Approved', ''),
(5, 11, 'Mark Angelo Capili', 'Introduction to PHP', 'icon_682b068780315.png', 'PDF', 'file_682b0687806ad.pdf', 'PHP', 'Approved', ''),
(6, 10, 'John Kenneth Dizon', 'Habit', 'icon_68bff92d40e6c.jpg', 'PDF', 'file_68bff92d412c2.pdf', 'HTML', 'Under Review', '');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `Session_ID` int(11) NOT NULL,
  `Course_Title` varchar(250) NOT NULL,
  `Session_Date` date NOT NULL,
  `Time_Slot` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`Session_ID`, `Course_Title`, `Session_Date`, `Time_Slot`) VALUES
(15, 'HTML', '2025-05-18', '2:00 PM - 3:00 PM'),
(16, 'CSS', '2025-05-18', '4:00 PM - 5:00 PM'),
(17, 'HTML', '2025-05-18', '6:00 PM - 7:00 PM'),
(18, 'PHP', '2025-05-19', '6:15 PM - 7:00 PM'),
(19, 'CSS', '2025-05-22', '1:00 PM - 2:00 PM'),
(20, 'CSS', '2025-09-03', '10:21 AM - 12:23 PM'),
(21, 'CSS', '2025-09-03', '10:46 AM - 10:54 PM'),
(22, 'CSS', '2025-09-03', '2:05 PM - 7:05 PM'),
(23, 'CSS', '2025-09-03', '2:12 PM - 7:12 PM'),
(24, 'CSS', '2025-09-04', '12:42 PM - 6:42 PM'),
(25, 'CSS', '2025-09-04', '8:46 PM - 12:46 PM');

-- --------------------------------------------------------

--
-- Table structure for table `session_bookings`
--

CREATE TABLE `session_bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_title` varchar(200) NOT NULL,
  `session_date` date NOT NULL,
  `time_slot` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `booking_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `forum_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_bookings`
--

INSERT INTO `session_bookings` (`booking_id`, `user_id`, `course_title`, `session_date`, `time_slot`, `status`, `booking_time`, `forum_id`, `notes`) VALUES
(1, 3, 'CSS', '2025-05-18', '4:00 PM - 5:00 PM', 'approved', '2025-05-18 08:00:33', 2, ''),
(2, 2, 'CSS', '2025-05-18', '4:00 PM - 5:00 PM', 'approved', '2025-05-18 08:01:08', 2, ''),
(3, 1, 'CSS', '2025-05-18', '4:00 PM - 5:00 PM', 'approved', '2025-05-18 08:01:56', 2, ''),
(4, 1, 'HTML', '2025-05-18', '6:00 PM - 7:00 PM', 'approved', '2025-05-18 15:41:37', 3, ''),
(5, 4, 'PHP', '2025-05-19', '6:15 PM - 7:00 PM', 'approved', '2025-05-19 10:14:18', 4, ''),
(6, 1, 'CSS', '2025-05-22', '1:00 PM - 2:00 PM', 'approved', '2025-05-22 10:52:53', 5, ''),
(7, 1, 'CSS', '2025-09-03', '11:01 AM - 11:01 PM', 'approved', '2025-09-03 05:33:34', NULL, 'hi'),
(8, 1, 'CSS', '2025-09-03', '2:05 PM - 7:05 PM', 'approved', '2025-09-03 06:07:17', NULL, ''),
(9, 1, 'CSS', '2025-09-03', '10:46 AM - 10:54 PM', 'approved', '2025-09-03 06:11:14', 7, ''),
(10, 1, 'CSS', '2025-09-03', '2:12 PM - 7:12 PM', 'approved', '2025-09-03 06:13:08', 10, ''),
(11, 1, 'CSS', '2025-09-04', '12:42 PM - 6:42 PM', 'approved', '2025-09-04 04:44:24', 11, ''),
(12, 2, 'CSS', '2025-09-04', '12:42 PM - 6:42 PM', 'approved', '2025-09-04 04:52:47', 11, '');

-- --------------------------------------------------------

--
-- Table structure for table `session_ended`
--

CREATE TABLE `session_ended` (
  `id` int(11) NOT NULL,
  `forum_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ended_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `session_participants`
--

CREATE TABLE `session_participants` (
  `id` int(11) NOT NULL,
  `forum_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('active','left','review') DEFAULT 'active',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_participants`
--

INSERT INTO `session_participants` (`id`, `forum_id`, `user_id`, `status`, `last_activity`) VALUES
(1, 8, NULL, 'left', '2025-05-15 05:19:02'),
(2, 8, 1, 'left', '2025-08-26 06:54:10'),
(3, 6, 1, 'review', '2025-08-26 06:54:10'),
(4, 8, NULL, 'review', '2025-05-15 05:20:58'),
(5, 9, NULL, 'active', '2025-05-15 05:21:36'),
(6, 9, 1, 'left', '2025-08-26 06:54:10'),
(7, 10, NULL, 'left', '2025-05-16 12:53:51'),
(8, 10, NULL, 'review', '2025-05-16 13:01:59'),
(9, 1, NULL, 'left', '2025-05-17 07:06:16'),
(10, 11, NULL, 'left', '2025-05-17 13:34:36'),
(11, 11, NULL, 'left', '2025-05-17 14:20:38'),
(12, 12, NULL, 'active', '2025-05-17 23:49:33'),
(13, 12, 1, 'left', '2025-08-26 06:54:10'),
(14, 1, 1, 'left', '2025-08-26 06:54:10'),
(15, 1, 3, 'left', '2025-08-26 06:54:10'),
(16, 1, 9, 'left', '2025-08-26 06:54:10'),
(17, 2, 3, 'left', '2025-08-26 06:54:10'),
(18, 2, 2, 'left', '2025-08-26 06:54:10'),
(19, 2, 1, 'left', '2025-08-26 06:54:10'),
(20, 2, 10, 'left', '2025-08-26 06:54:10'),
(21, 3, 1, 'left', '2025-08-26 06:54:10'),
(22, 4, 4, 'left', '2025-08-26 06:54:10'),
(23, 4, 11, 'left', '2025-08-26 06:54:10'),
(24, 5, 1, 'left', '2025-08-26 06:54:10'),
(25, 7, 10, 'left', '2025-09-03 03:07:37'),
(26, 7, 18, 'left', '2025-09-03 03:43:36'),
(27, 6, 18, 'left', '2025-09-03 03:56:04'),
(28, 1, 18, 'review', '2025-09-03 04:00:04'),
(29, 2, 18, 'review', '2025-09-03 04:00:10'),
(30, 4, 18, 'review', '2025-09-03 04:00:13'),
(31, 5, 18, 'review', '2025-09-03 04:00:15'),
(34, 7, 1, 'left', '2025-09-03 06:27:07'),
(35, 10, 1, 'left', '2025-09-03 06:19:23'),
(36, 11, 1, 'left', '2025-09-04 04:45:17'),
(37, 11, 2, 'left', '2025-09-04 05:08:28'),
(38, 12, 18, 'review', '2025-09-04 12:52:38'),
(39, 11, 18, 'review', '2025-09-09 05:58:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(70) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('Mentee','Mentor','Admin','Super Admin') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(80) NOT NULL,
  `icon` varchar(200) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `contact_number` varchar(100) DEFAULT NULL,
  `mentored_before` varchar(20) DEFAULT NULL,
  `mentoring_experience` text DEFAULT NULL,
  `area_of_expertise` varchar(100) DEFAULT NULL,
  `resume` varchar(200) DEFAULT NULL,
  `certificates` varchar(200) DEFAULT NULL,
  `assessment_score` int(20) DEFAULT NULL,
  `status` varchar(60) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `email_verification` varchar(50) DEFAULT NULL,
  `contact_verification` varchar(50) DEFAULT NULL,
  `full_address` varchar(100) DEFAULT NULL,
  `student` varchar(20) DEFAULT NULL,
  `student_year_level` varchar(30) DEFAULT NULL,
  `occupation` varchar(40) DEFAULT NULL,
  `to_learn` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `user_type`, `first_name`, `last_name`, `email`, `icon`, `dob`, `gender`, `contact_number`, `mentored_before`, `mentoring_experience`, `area_of_expertise`, `resume`, `certificates`, `assessment_score`, `status`, `reason`, `email_verification`, `contact_verification`, `full_address`, `student`, `student_year_level`, `occupation`, `to_learn`) VALUES
(1, 'mjslagnason', '$2y$10$FSZb2BgndImTlAJ9DJD7muxQ9ghP4D5qN8BMFUleY5pEy18j6gvey', 'Mentee', 'Mark Justie', 'Lagnason', 'mjslagnason@bpsu.edu.ph', '../uploads/profile_mjslagnason_1747550363.jpg', '2003-07-15', 'Male', '+639646372643', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Kabyawan. Bagac, Bataan', 'yes', '3rd Year - College', '', 'I want to enhance my skills in programming more, especially in web development.'),
(2, 'ckmfuertes', '$2y$10$iuhoJjDDmWc/ldf8AMKhRudaHNa0HIZKsegI.ufHKCS6Dp2YX6ZeK', 'Mentee', 'Cherwen Kirk', 'Fuertes', 'ckmfuertes@gmail.com', '../uploads/profile_ckmfuertes_1747555295.jpg', '2003-02-05', 'Male', '+639898767876', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Laon, Abucay, Bataan', 'no', '', 'IT Specialist', 'I want to practice my skills.'),
(3, 'fobalajo', '$2y$10$CMEGrv/N7A4AtbLCt79.z.LRhxzjWao0ACSuMVWKfeEir0yeFAIvq', 'Mentee', 'Faith Odessa', 'Balajo', 'fobalajo@bpsu.edu.ph', '../uploads/profile_fobalajo_1747553068.jpg', '1998-02-18', 'Female', '+639666592022', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Cupang Central, Balanga, Bataan', 'no', '', 'None', 'I want to learn new skills in programming.'),
(4, 'angelacalimbas', '$2y$10$yACh/NRHBvJkFXyvFWYYVejGJZBFR2Y/o9otvOUs3m5k71v3lE27S', 'Mentee', 'Angela Marie', 'Gabriel', 'skboocoms@gmail.com', '', '2003-08-15', 'Female', '+639086531410', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Kabyawan. Bagac, Bataan', 'yes', '3rd Year - College', '', 'I want to improve my programming skills.'),
(5, 'JohnDoe', '$2y$10$RkRGZYJWucaqtxjoraqUWeru1Gy0DdcZsxn8WSHOwaZTk6py6MZMm', 'Mentee', 'John Rey', 'Doe', 'johndoe@gmail.com', '', '2015-12-01', 'Male', '+639123456789', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'P-2, Daet, Camarines Norte', 'no', '', 'Freelancer', 'I want to commission'),
(8, 'jpfrancisco', '$2y$10$XA99n3nPh/9SFtKPjkJCwuxVFJQnT2Do0AXNZoaTkaXbRT2N2P2ce', 'Mentor', 'Jana Patrisse', 'Francisco', 'jalfrancisco@bpsu.edu.ph', '', '2004-01-03', 'Female', '+63996665920', 'yes', 'Teaching is fun and fulfilling especially when the students learn new things and apply them in life.', 'C#', 'uploads/applications/resume/resume_68297bb52c6e7_Francisco, Jana Patrisse_Resume.pdf', 'uploads/applications/certificates/cert_68297bb52d833_Francisco, Jana Patrisse_Certificates.pdf', 50, 'Rejected', 'Lack of skills to teach.', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'kahvillafania', '$2y$10$IP.K2P9x4innQzlqHO8us.HONmQamiN08dZZz7WOcrO807233vzX6', 'Mentor', 'Kim Ashley', 'Villafania', 'kahvillafania@bpsu.edu.ph', '../uploads/mentor_682991a80a5ea.jpg', '2003-10-12', 'Female', '+63996463726', 'yes', 'Tutoring kids and even my classmates have given me the skill to communicate, so with COACH I want to further hone my skills for my future job.', 'CSS', 'uploads/applications/resume/resume_68297cad1da03_Villafania, Kim Ashley_Resume.pdf', 'uploads/applications/certificates/cert_68297cad1e671_Villafania, Kim Ashley_Certificates.pdf', 50, 'Approved', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'jkcdizon', '$2y$10$0JDxLFck1jICo/dwrI1n/upJ4be4Va15TbBQEqNxO2XcOKRb2RPuq', 'Mentor', 'John Kenneth', 'Dizon', 'jkcdizon@bpsu.edu.ph', '../uploads/mentor_682991ce49326.jpg', '2003-09-16', 'Male', '+63996463726', 'yes', 'Yes, I once mentored a group of junior students on developing their first mobile app, guiding them through UI design and basic coding principles. It was fulfilling to see their confidence grow as they successfully presented their project by the end of the term.\r\n', 'CSS', 'uploads/applications/resume/resume_682982207012a_Dizon, John Kenneth_Resume.pdf', 'uploads/applications/certificates/cert_6829822071dca_Dizon, John Kenneth_Certificates.pdf', 40, 'Approved', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'markcapili_22', '$2y$10$IM3nDNZQ8SsJnp3wsWenSuuy.GYvhlH2ao7Rg51VRi5VYvMGXkEhO', 'Mentor', 'Mark Angelo', 'Capili', 'mikaelacando425@gmail.com', '', '2000-03-22', 'Male', '+63996474159', 'yes', 'I have experienced mentoring before', 'PHP', 'uploads/applications/resume/resume_682b009999e51_Capili, Mark Angelo_Resume.pdf', 'uploads/applications/certificates/cert_682b00999b657_Capili, Mark Angelo_Certificates.pdf', 40, 'Approved', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'admin_angela', '$2y$10$/d.SxYVPxa8v9ryqRVRF.OlssSHGlN8A6RTZfgcOeqaKTaZ/soAkO', 'Admin', 'Angela', 'Gabriel', 'amqgabriel@bpsu.edu.ph', '../uploads/profile_68297d51a8c44.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'admin_mika', '$2y$10$0LBz8XilC2GQnD6nDVe2R.jAcIPV9eua6JPH/Kp3It/fGAit1c3jG', 'Admin', 'Mikaela', 'Cando', 'mntcando@bpsu.edu.ph', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'admin_robbie', '$2y$10$/dcymUqU48aNl366NFXz.OZ0h6M7Ig6821F2LEVfggB3GiziOPvNq', 'Admin', 'Robbie', 'Tria', 'rmtria2@bpsu.edu.ph', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'admin_kim1', '$2y$10$feJYKn.Lv7Ce3silMt5BYertF6ovp3eZEf49w48yPrBtIxOLg46p2', 'Admin', 'Kim', 'Villafania', 'hoshiannawooz@gmail.com', '../uploads/profile_68bedfc97793f.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'archwizsoc2024', '$2y$10$79RGGLUYClA6NNUG1XQmYu1S3wg4LmINDL8of2lmY1wnQYNYTXsL6', 'Super Admin', 'Noemi', 'Baltazar', '', '../uploads/superadmin_682459efa6142.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'john', '$2y$10$W/N8C8HED7MYcm0NIwfE.eJVKeW7qG3RxyOxgLGUkZ8l9PUKuj6fS', 'Mentee', 'jo', 'rey', 'leesongmin@gmail.com', NULL, '2015-12-30', 'Male', '+639132413543', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lnaaskdlhsa', 'yes', 'Grade 13', '', 'yes'),
(24, 'songmin', '$2y$10$oy2bWKfGcTOxvko4Ii64BeryqXlLW5jISxm7wAdbK5gGcy.qRuAIi', 'Mentor', 'songmin', 'lee', 'johndoe@gmail.com', NULL, '2007-12-30', 'Male', '+63913133423', 'no', '', 'CSS', 'uploads/applications/resume/resume_68b93c269785e_COACH.pdf', 'uploads/applications/certificates/cert_68b93c2697e26_COACH.pdf', 100, 'Approved', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `video_participants`
--

CREATE TABLE `video_participants` (
  `forum_id` int(11) NOT NULL,
  `username` varchar(70) NOT NULL,
  `peer_id` varchar(100) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking_notifications`
--
ALTER TABLE `booking_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `channel_participants`
--
ALTER TABLE `channel_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_channel_user` (`channel_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chat_channels`
--
ALTER TABLE `chat_channels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`Course_ID`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`Feedback_ID`);

--
-- Indexes for table `forum_chats`
--
ALTER TABLE `forum_chats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `forum_participants`
--
ALTER TABLE `forum_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_forum_user` (`forum_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `menteescores`
--
ALTER TABLE `menteescores`
  ADD PRIMARY KEY (`Attempt_ID`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mentee_answers`
--
ALTER TABLE `mentee_answers`
  ADD PRIMARY KEY (`Answer_ID`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mentee_assessment`
--
ALTER TABLE `mentee_assessment`
  ADD PRIMARY KEY (`Item_ID`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mentors_assessment`
--
ALTER TABLE `mentors_assessment`
  ADD PRIMARY KEY (`ITEM_ID`);

--
-- Indexes for table `pending_sessions`
--
ALTER TABLE `pending_sessions`
  ADD PRIMARY KEY (`Pending_ID`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `uk_post_user` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `quizassignments`
--
ALTER TABLE `quizassignments`
  ADD PRIMARY KEY (`Assignment_ID`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`Resource_ID`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`Session_ID`);

--
-- Indexes for table `session_bookings`
--
ALTER TABLE `session_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `session_ended`
--
ALTER TABLE `session_ended`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `forum_id` (`forum_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `session_participants`
--
ALTER TABLE `session_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_session_forum_user` (`forum_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `video_participants`
--
ALTER TABLE `video_participants`
  ADD PRIMARY KEY (`forum_id`,`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking_notifications`
--
ALTER TABLE `booking_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `channel_participants`
--
ALTER TABLE `channel_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_channels`
--
ALTER TABLE `chat_channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `Course_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `Feedback_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `forum_chats`
--
ALTER TABLE `forum_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `forum_participants`
--
ALTER TABLE `forum_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `general_forums`
--
ALTER TABLE `general_forums`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `menteescores`
--
ALTER TABLE `menteescores`
  MODIFY `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `mentee_answers`
--
ALTER TABLE `mentee_answers`
  MODIFY `Answer_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `mentee_assessment`
--
ALTER TABLE `mentee_assessment`
  MODIFY `Item_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `mentors_assessment`
--
ALTER TABLE `mentors_assessment`
  MODIFY `ITEM_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `pending_sessions`
--
ALTER TABLE `pending_sessions`
  MODIFY `Pending_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `quizassignments`
--
ALTER TABLE `quizassignments`
  MODIFY `Assignment_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `Resource_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `Session_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `session_bookings`
--
ALTER TABLE `session_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `session_ended`
--
ALTER TABLE `session_ended`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `session_participants`
--
ALTER TABLE `session_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_notifications`
--
ALTER TABLE `booking_notifications`
  ADD CONSTRAINT `booking_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `channel_participants`
--
ALTER TABLE `channel_participants`
  ADD CONSTRAINT `channel_participants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `chat_messages_ibfk_10` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_11` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_12` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_13` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_14` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_15` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_16` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_17` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_18` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_19` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_20` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_21` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_22` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_23` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_24` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_25` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_26` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_27` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_28` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_29` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_5` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_6` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_7` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_8` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_messages_ibfk_9` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `forum_participants`
--
ALTER TABLE `forum_participants`
  ADD CONSTRAINT `forum_participants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `menteescores`
--
ALTER TABLE `menteescores`
  ADD CONSTRAINT `menteescores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `mentee_answers`
--
ALTER TABLE `mentee_answers`
  ADD CONSTRAINT `mentee_answers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `mentee_assessment`
--
ALTER TABLE `mentee_assessment`
  ADD CONSTRAINT `mentee_assessment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `pending_sessions`
--
ALTER TABLE `pending_sessions`
  ADD CONSTRAINT `pending_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `quizassignments`
--
ALTER TABLE `quizassignments`
  ADD CONSTRAINT `quizassignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `session_bookings`
--
ALTER TABLE `session_bookings`
  ADD CONSTRAINT `session_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `session_ended`
--
ALTER TABLE `session_ended`
  ADD CONSTRAINT `session_ended_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `session_participants`
--
ALTER TABLE `session_participants`
  ADD CONSTRAINT `session_participants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
