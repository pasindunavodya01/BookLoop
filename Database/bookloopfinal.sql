-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2025 at 09:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bookloopfinal`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `availability_status` enum('Available','Unavailable') DEFAULT 'Available',
  `image_url` varchar(255) DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `title`, `author`, `genre`, `description`, `owner_id`, `availability_status`, `image_url`, `date_added`) VALUES
(8, 'Harry Potter 2', 'J.K. Rowling', 'Adventure', 'A counterintuitive approach to living a good life.', 2, 'Unavailable', 'uploads/67cdcedf551030.63695137.png', '2025-03-09 17:32:50'),
(25, 'The Alchemist', 'Paulo Coelho', 'Adventure', 'A novel about following your dreams.', 3, 'Available', 'uploads/alchemist.jpg', '2025-03-17 15:07:26'),
(36, 'Dune', 'Frank Herbert', 'Science Fiction', 'A science fiction novel set in a distant future amidst a huge interstellar empire.', 2, 'Unavailable', 'uploads/dune.jpg', '2025-03-17 15:20:54'),
(37, 'The Hobbit', 'J.R.R. Tolkien', 'Fantasy', 'A prelude to The Lord of the Rings, following Bilbo Baggins on an adventure.', 5, 'Available', 'uploads/theHobit.jpg', '2025-03-17 15:20:54'),
(38, 'The Catcher in the Rye', 'J.D. Salinger', 'Fiction', 'A novel about teenage rebellion and alienation.', 6, 'Unavailable', 'uploads/catcher.jpg', '2025-03-17 15:20:54'),
(39, 'Sapiens', 'Yuval Noah Harari', 'History', 'A book that explores the history of humankind.', 7, 'Available', 'uploads/sapiens.jpg', '2025-03-17 15:20:54'),
(40, 'Think and Grow Rich', 'Napoleon Hill', 'Self-Help', 'A classic book on success and wealth-building.', 2, 'Available', 'uploads/think.png', '2025-03-17 15:20:54'),
(41, 'The Subtle Art of Not Giving a F*ck', 'Mark Manson', 'Self-Help', 'A counterintuitive approach to living a good life.', 3, 'Unavailable', 'uploads/subtle.jpeg', '2025-03-17 15:20:54'),
(42, 'The 48 Laws of Power', 'Robert Greene', 'Business', 'A book about power dynamics and strategy.', 7, 'Available', 'uploads/laws48.jpeg', '2025-03-17 15:20:54'),
(43, 'The Midnight Library', 'Matt Haig', 'Fantasy', 'A story about a library between life and death.', 3, 'Unavailable', 'uploads/midnight.jpg', '2025-03-17 15:20:54'),
(44, 'The Lord of the Rings', 'J R Tolkein', 'Fiction', 'Fantasy fiction', 2, 'Unavailable', 'uploads/load.jpeg', '2025-03-20 07:46:45'),
(47, 'A Million to One\n', 'Adiba Jaigirdar', 'Fiction', 'Set during the fateful voyage of the Titanic, A Million to One follows four teenage girls—each with their own secrets and reasons for being aboard the ship. As they band together for a daring heist of a priceless book in the ship\'s safe, they must navigate danger, trust, and the growing bonds between them. A thrilling and emotional story of survival, identity, and the risks we take for love and freedom.', 3, 'Unavailable', 'uploads/67e4f2a7c51fb9.31941188.jpeg', '2025-03-27 06:39:35'),
(48, ' A Million to One\n', 'Adiba Jaigirdar', 'Fiction', 'Set during the fateful voyage of the Titanic, A Million to One follows four teenage girls—each with their own secrets and reasons for being aboard the ship. As they band together for a daring heist of a priceless book in the ship\'s safe, they must navigate danger, trust, and the growing bonds between them. A thrilling and emotional story of survival, identity, and the risks we take for love and freedom.', 6, 'Available', 'uploads/67e4f42c2bae75.10200822.jpeg', '2025-03-27 06:46:04'),
(49, 'Brida', 'Paulo Coelho', 'Fiction', 'Brida is a mystical tale of self-discovery, following a young Irish woman in search of wisdom and spiritual enlightenment. As she explores the traditions of the sun and the moon, she must make a choice between her soul mate and her spiritual destiny. With themes of love, magic, and transformation, Paulo Coelho crafts a philosophical journey that challenges the boundaries of fate and free will.', 5, 'Available', 'uploads/6802b339dea834.87397670.jpg', '2025-04-18 20:16:57'),
(50, 'Sophie\'s World', 'Jostein Gaarder', 'Fiction', 'When 14-year-old Sophie Amundsen receives a mysterious letter asking, \"Who are you?\", she\'s thrust into a philosophical adventure spanning centuries of thought. Sophie\'s World is both a coming-of-age story and an accessible introduction to the history of philosophy—from Socrates to Sartre. As Sophie delves deeper into philosophical ideas, the lines between fiction and reality begin to blur in surprising and profound ways.', 5, 'Available', 'uploads/6802b396e60f17.02724893.jpg', '2025-04-18 20:18:30'),
(52, 'Atomic Habits', 'James Clear', 'Self-Help', 'Atomic Habits is a practical guide to building good habits, breaking bad ones, and mastering the tiny behaviors that lead to remarkable results. James Clear draws on proven psychological research to show how small, consistent changes can transform your life.', 13, 'Available', 'uploads/6805093fd7c544.46008784.jpg', '2025-04-20 14:48:31'),
(53, 'Hide and Seek ', 'olivia wilson', 'Adventure', 'Hide and seek adventures come alive! Embark on captivating journeys as you search cleverly camouflaged creatures and objects. Interactive fun for curious minds!', 12, 'Unavailable', 'uploads/68066061e28ad9.99806615.jpg', '2025-04-21 15:12:33'),
(54, 'Faithing the native soil', 'Shanthimar Hettiarachchi', 'historical fiction', 'a book title by Shanthikumar Hettiarachchi exploring the dilemmas and aspirations of post-colonial Buddhists and Christians in Sri Lanka', 17, 'Available', 'uploads/6809060899fb43.76949655.jpg', '2025-04-23 15:23:52');

-- --------------------------------------------------------

--
-- Table structure for table `book_pickup_otp`
--

CREATE TABLE `book_pickup_otp` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `pickup_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `status` enum('pending','used','expired') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`comment_id`, `post_id`, `user_id`, `content`, `created_at`) VALUES
(5, 2, 3, 'Its amazing ', '2025-03-17 17:26:56'),
(6, 2, 2, 'No words ', '2025-03-17 17:27:30'),
(7, 2, 5, 'I really love it', '2025-03-17 17:30:03'),
(8, 2, 2, 'This is great \r\n', '2025-03-17 17:32:02'),
(9, 5, 2, 'its a master piece\r\n', '2025-03-17 17:55:00'),
(10, 5, 5, 'Its crazy', '2025-03-17 17:56:49'),
(11, 5, 3, 'its a master piece\r\n', '2025-03-17 17:57:21'),
(12, 6, 3, 'its nice \r\n', '2025-03-17 18:09:33'),
(13, 4, 3, 'Lovely Book❤', '2025-03-19 16:02:43'),
(14, 6, 5, 'Important book \r\n ', '2025-03-19 16:08:23'),
(15, 6, 2, 'nice', '2025-03-20 05:58:20'),
(16, 6, 2, 'nice', '2025-03-20 05:58:46'),
(17, 6, 2, 'nice', '2025-03-20 05:59:16'),
(18, 2, 2, 'test', '2025-03-20 07:47:24'),
(19, 6, 2, 'test', '2025-03-20 07:47:49'),
(33, 18, 13, 'Test', '2025-04-24 05:36:38'),
(34, 18, 13, 'love you', '2025-05-02 09:01:44'),
(35, 6, 13, 'hi', '2025-05-02 09:01:50'),
(36, 5, 13, 'ummmaaaa', '2025-05-02 09:02:02');

-- --------------------------------------------------------

--
-- Table structure for table `deleted_books`
--

CREATE TABLE `deleted_books` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `venue` varchar(255) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `user_id`, `event_name`, `description`, `event_date`, `event_time`, `venue`, `image_url`, `status`, `rejection_reason`, `created_at`) VALUES
(95, 17, 'Book Exhibition 2028', 'there will be book stall from different companies and popular book authors will be at the premises.', '2025-04-24', '08:48:00', 'APIIT CITY CAMPUS 5th Floor', 'Uploads/test.jpg', 'Approved', 'Because of a holiday ', '2025-04-23 15:18:27'),
(96, 16, 'Movie Night', 'Grab your coziest blanket, your favorite snacks, and settle in for an unforgettable evening! Join us on Friday.\\r\\nTickets will be printed on participation count', '2025-04-25', '10:44:00', 'APIIT CITY CAMPUS AUDITORIUM', 'Uploads/4545454545home.jpg', 'Approved', NULL, '2025-04-24 05:17:29'),
(97, 17, 'test 2', 'test', '2025-04-25', '11:14:00', 'Apiit', 'Uploads/1000_F_302126584_fHZesUdO6XbBgWKbiovcgV24gjCpwNnR.jpg', 'Approved', NULL, '2025-04-24 05:45:10'),
(98, 17, 'test 2', 'test', '2025-04-25', '11:16:00', 'Apiit', 'Uploads/1000_F_487786567_nNimNLmOw6crcj0Lp0vridZkS1V40o0r.jpg', 'Rejected', 'wrong info', '2025-04-24 05:46:43');

-- --------------------------------------------------------

--
-- Table structure for table `event_participations`
--

CREATE TABLE `event_participations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participations`
--

INSERT INTO `event_participations` (`id`, `event_id`, `user_id`, `created_at`) VALUES
(57, 97, 17, '2025-04-24 05:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `like_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `likes`
--

INSERT INTO `likes` (`like_id`, `post_id`, `user_id`) VALUES
(1, 2, 2),
(8, 2, 3),
(2, 4, 2),
(7, 4, 3),
(3, 5, 2),
(6, 5, 3),
(4, 6, 2),
(5, 6, 3),
(17, 18, 13);

-- --------------------------------------------------------

--
-- Table structure for table `mails`
--

CREATE TABLE `mails` (
  `mails` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mails`
--

INSERT INTO `mails` (`mails`) VALUES
('cb000001@students.apiit.lk'),
('cb000002@students.apiit.lk'),
('cb000003@students.apiit.lk'),
('cb000004@students.apiit.lk'),
('cb000005@students.apiit.lk'),
('cb000006@students.apiit.lk'),
('cb000007@students.apiit.lk'),
('cb000008@students.apiit.lk'),
('cb000009@students.apiit.lk'),
('cb000010@students.apiit.lk'),
('cb000001@students.apiit.lk'),
('cb000002@students.apiit.lk'),
('cb000003@students.apiit.lk'),
('cb000004@students.apiit.lk'),
('cb000005@students.apiit.lk'),
('cb000006@students.apiit.lk'),
('cb000007@students.apiit.lk'),
('cb000008@students.apiit.lk'),
('cb000009@students.apiit.lk'),
('cb000010@students.apiit.lk'),
('cb013343@students.apiit.lk'),
('CB013126@students.apiit.lk'),
('CB012062@students.apiit.lk'),
('CB011672@students.apiit.lk');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_status` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `created_at`, `read_status`, `is_read`) VALUES
(1, 3, 2, 'Hi! I am interested in the book.', '2025-03-10 07:55:57', 1, 1),
(2, 2, 3, 'hello', '2025-03-10 03:46:51', 1, 1),
(3, 3, 2, 'hello', '2025-03-10 03:47:11', 1, 1),
(4, 2, 3, 'hello', '2025-03-10 03:53:30', 1, 1),
(5, 6, 2, 'hello', '2025-03-10 04:12:26', 1, 1),
(6, 6, 2, 'hello', '2025-03-10 04:13:46', 1, 1),
(7, 6, 2, 'hi', '2025-03-10 04:26:46', 1, 1),
(8, 2, 6, 'hello', '2025-03-10 04:29:01', 1, 1),
(9, 2, 6, 'hi', '2025-03-10 04:29:06', 1, 1),
(10, 6, 2, 'hello', '2025-03-10 04:30:35', 1, 1),
(11, 6, 2, 'hello', '2025-03-10 04:30:39', 1, 1),
(12, 2, 6, 'hi', '2025-03-10 04:32:53', 1, 1),
(13, 2, 6, 'hello', '2025-03-10 04:32:58', 1, 1),
(14, 2, 6, 'hello', '2025-03-10 04:34:32', 1, 1),
(15, 2, 6, 'hi', '2025-03-10 04:34:36', 1, 1),
(16, 3, 2, 'hello', '2025-03-10 04:35:42', 1, 1),
(17, 3, 2, 'hello', '2025-03-10 04:35:48', 1, 1),
(18, 6, 2, 'hello', '2025-03-10 04:36:42', 1, 1),
(19, 6, 2, 'helloooo', '2025-03-10 04:36:51', 1, 1),
(20, 7, 2, 'hello', '2025-03-10 04:40:38', 1, 1),
(21, 2, 7, 'hi', '2025-03-10 04:42:35', 1, 1),
(22, 7, 2, 'hi hi', '2025-03-10 04:42:56', 1, 1),
(23, 7, 2, 'helloooo', '2025-03-10 05:08:06', 1, 1),
(24, 2, 3, 'Mokada krnne ', '2025-03-10 09:11:41', 1, 1),
(25, 3, 2, 'Innawa nikan ', '2025-03-10 09:13:19', 1, 1),
(27, 2, 6, 'Aa aththada', '2025-03-10 09:20:43', 1, 1),
(29, 3, 2, 'Aa aththada', '2025-03-19 03:20:32', 1, 1),
(30, 2, 3, 'ado ado', '2025-03-21 11:10:03', 1, 1),
(31, 3, 2, 'adoooo', '2025-03-21 11:11:30', 1, 1),
(32, 2, 3, 'ado ado', '2025-03-21 11:28:35', 1, 1),
(33, 8, 2, 'hello', '2025-03-21 11:53:49', 1, 1),
(34, 2, 6, 'Hi', '2025-03-23 10:25:59', 0, 0),
(35, 2, 8, 'Hello ', '2025-03-23 10:26:10', 0, 0),
(36, 2, 3, 'Hi', '2025-03-23 10:26:15', 1, 1),
(37, 2, 6, 'hello', '2025-03-23 10:26:44', 0, 0),
(38, 3, 2, 'Hii', '2025-03-23 14:14:20', 1, 1),
(39, 2, 5, 'Hi', '2025-04-18 17:09:30', 1, 1),
(40, 5, 2, 'Hii', '2025-04-18 17:09:41', 1, 1),
(41, 5, 2, 'How can I help you?', '2025-04-18 17:09:53', 1, 1),
(42, 2, 5, 'is brida book available?', '2025-04-18 17:14:31', 1, 1),
(43, 5, 2, 'Yes it is ', '2025-04-18 17:16:00', 1, 1),
(44, 3, 2, 'Hii again ', '2025-04-19 04:16:18', 1, 1),
(45, 2, 3, 'Hi Hi', '2025-04-19 04:51:54', 1, 1),
(46, 3, 2, 'Can you give me a favor ', '2025-04-19 04:52:17', 1, 1),
(47, 12, 13, 'Hey! I\'m interested in your book. Is it still available?', '2025-04-20 17:53:10', 1, 1),
(48, 13, 12, 'Yes', '2025-04-20 17:53:29', 1, 1),
(49, 12, 13, 'Awesome, thanks!', '2025-04-20 17:54:45', 1, 1),
(50, 9, 13, 'Hi', '2025-04-20 18:58:05', 1, 1),
(51, 13, 9, 'Hii', '2025-04-20 20:07:49', 1, 1),
(52, 13, 9, 'How can I help you?', '2025-04-20 20:08:12', 1, 1),
(53, 13, 9, 'Test', '2025-04-20 20:38:51', 1, 1),
(54, 9, 13, 'Hii', '2025-04-20 20:49:49', 1, 1),
(55, 13, 9, 'Hello', '2025-04-20 20:50:22', 1, 1),
(56, 9, 13, 'Yes', '2025-04-20 21:06:07', 1, 1),
(57, 13, 9, 'Why?', '2025-04-20 21:07:26', 1, 1),
(58, 9, 13, 'What do you want?', '2025-04-20 21:09:00', 1, 1),
(59, 13, 9, 'I want a book recommendation from you', '2025-04-20 21:20:39', 1, 1),
(60, 13, 9, 'Can you?', '2025-04-20 21:23:01', 1, 1),
(61, 13, 9, 'I want a book recommendation from you', '2025-04-20 21:23:25', 1, 1),
(62, 13, 9, 'hi', '2025-04-20 21:26:14', 1, 1),
(63, 9, 13, 'Hello', '2025-04-20 21:32:40', 1, 1),
(64, 9, 13, 'I recommend you to read brida', '2025-04-20 21:33:03', 1, 1),
(65, 9, 13, 'Its a master peace', '2025-04-20 21:33:24', 1, 1),
(66, 9, 13, 'Piece *', '2025-04-20 21:33:32', 1, 1),
(67, 13, 9, 'Thanks. do you have that book', '2025-04-20 21:35:49', 1, 1),
(68, 9, 13, 'No but i seen it is in this platform. Go and search. You may be able to find it', '2025-04-20 21:36:39', 1, 1),
(69, 13, 9, 'Thank you ', '2025-04-20 21:48:05', 1, 1),
(70, 9, 13, 'Welcome', '2025-04-20 21:48:30', 1, 1),
(71, 13, 9, 'Hee', '2025-04-20 21:49:37', 1, 1),
(72, 9, 13, 'Hee', '2025-04-20 21:55:32', 1, 1),
(73, 9, 13, 'Good night ', '2025-04-20 21:55:51', 1, 1),
(74, 9, 13, 'Nice', '2025-04-20 21:58:59', 1, 1),
(77, 13, 2, 'Hello', '2025-04-21 10:09:56', 1, 1),
(82, 17, 13, 'hi', '2025-04-23 18:19:36', 1, 1),
(83, 13, 17, 'Hii', '2025-04-23 18:19:48', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `picked_up_books`
--

CREATE TABLE `picked_up_books` (
  `pickup_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL,
  `borrower_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `pickup_date` datetime DEFAULT NULL,
  `return_deadline` date DEFAULT NULL,
  `status` enum('Borrowed','Returned') DEFAULT 'Borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `post_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`post_id`, `user_id`, `content`, `image_url`, `created_at`) VALUES
(2, 5, 'Dune is a science fiction epic that explores themes of politics, religion, and ecology. Set in a distant future, it follows Paul Atreides as he navigates the complex universe of the desert planet Arrakis.', 'uploads/dune.jpg', '2025-03-17 16:56:51'),
(4, 2, 'The Great Gatsby by F. Scott Fitzgerald is a classic American novel that delves into themes of wealth, love, and the American Dream during the Roaring Twenties.', 'uploads/TheGreatGatsby.png', '2025-03-17 17:31:53'),
(5, 3, '1984 by George Orwell is a chilling dystopian novel that explores totalitarianism, surveillance, and the manipulation of truth and language.', 'uploads/1984.jpeg', '2025-03-17 17:32:55'),
(6, 5, 'To Kill a Mockingbird by Harper Lee addresses important themes such as racial injustice, moral growth, and compassion in the American South during the Great Depression.', 'uploads/HowToKill.jpg', '2025-03-17 17:33:27'),
(18, 13, 'Test 1', 'uploads/6809cdd381bf0.jpg', '2025-04-24 05:36:19');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `status` enum('Pending','Accepted','Returned','Picked Up') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pickup_otp` varchar(6) DEFAULT NULL,
  `return_otp` varchar(6) DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`request_id`, `book_id`, `requester_id`, `owner_id`, `status`, `created_at`, `pickup_otp`, `return_otp`, `accepted_at`) VALUES
(1, 44, 6, 2, 'Returned', '2025-03-23 06:04:44', NULL, NULL, NULL),
(2, 44, 6, 2, 'Returned', '2025-03-23 06:15:43', NULL, NULL, NULL),
(3, 44, 6, 2, 'Accepted', '2025-03-23 06:26:09', NULL, NULL, NULL),
(9, 44, 3, 2, 'Returned', '2025-03-23 07:44:48', NULL, NULL, NULL),
(13, 25, 2, 3, 'Accepted', '2025-03-23 15:13:54', NULL, NULL, NULL),
(16, 39, 2, 7, 'Accepted', '2025-03-23 19:27:13', NULL, NULL, NULL),
(20, 39, 3, 7, 'Accepted', '2025-03-27 03:45:54', '123123', '321321', NULL),
(22, 47, 2, 3, 'Accepted', '2025-03-27 06:41:55', NULL, NULL, NULL),
(24, 47, 2, 3, 'Accepted', '2025-03-27 06:42:50', NULL, NULL, NULL),
(25, 25, 2, 3, 'Picked Up', '2025-04-12 12:19:13', '112233', '223311', '2025-05-13 00:26:57'),
(26, 39, 6, 7, 'Accepted', '2025-04-14 15:34:40', '220203', '429861', NULL),
(27, 41, 2, 3, 'Accepted', '2025-04-16 14:56:22', '336403', '240082', NULL),
(28, 37, 2, 5, 'Pending', '2025-04-16 14:58:58', NULL, NULL, NULL),
(29, 43, 6, 3, 'Accepted', '2025-04-16 15:24:32', '171997', '406044', NULL),
(30, 41, 7, 3, 'Returned', '2025-04-16 17:31:58', NULL, NULL, NULL),
(31, 43, 2, 3, 'Returned', '2025-04-16 18:51:49', '158571', '509491', NULL),
(32, 39, 3, 7, 'Returned', '2025-04-16 20:26:10', '832689', '270493', NULL),
(33, 39, 3, 7, 'Picked Up', '2025-04-16 20:29:20', '663821', '319242', NULL),
(35, 43, 7, 3, 'Picked Up', '2025-04-16 21:29:17', '969787', '585214', NULL),
(37, 25, 7, 3, 'Returned', '2025-04-16 21:38:29', '046394', '336516', NULL),
(44, 36, 3, 2, NULL, '2025-04-15 19:42:09', NULL, NULL, NULL),
(45, 36, 3, 2, NULL, '2025-04-08 19:42:14', NULL, NULL, NULL),
(46, 36, 3, 2, 'Accepted', '2025-04-10 19:42:34', '352073', '967797', NULL),
(47, 36, 3, 2, NULL, '2025-04-09 19:42:54', NULL, NULL, NULL),
(48, 36, 5, 2, 'Accepted', '2025-04-18 20:00:07', '256645', '080719', '2025-05-13 17:53:39'),
(49, 25, 5, 3, 'Returned', '2025-04-18 20:07:45', '183058', '589390', NULL),
(50, 50, 3, 5, 'Pending', '2025-04-19 04:30:04', NULL, NULL, NULL),
(51, 8, 3, 2, 'Accepted', '2025-04-19 04:45:32', '145492', '735668', NULL),
(53, 48, 3, 6, 'Pending', '2025-04-19 04:50:10', NULL, NULL, NULL),
(54, 50, 3, 5, 'Pending', '2025-04-19 05:08:10', NULL, NULL, NULL),
(55, 42, 3, 7, 'Pending', '2025-04-19 05:09:26', NULL, NULL, NULL),
(56, 49, 3, 5, 'Pending', '2025-04-19 05:14:37', NULL, NULL, NULL),
(57, 37, 3, 5, 'Pending', '2025-04-19 05:23:21', NULL, NULL, NULL),
(58, 36, 3, 2, 'Returned', '2025-04-19 05:53:39', '884906', '977315', NULL),
(59, 8, 3, 2, 'Returned', '2025-04-20 13:13:51', '272547', '377963', NULL),
(60, 52, 12, 13, 'Returned', '2025-04-20 14:55:26', '657946', '483462', NULL),
(61, 52, 12, 13, 'Accepted', '2025-04-20 14:58:27', '188893', '241284', '2025-05-13 17:35:33'),
(62, 52, 12, 13, 'Accepted', '2025-04-20 14:58:57', '918659', '221390', '2025-05-13 17:34:30'),
(63, 53, 13, 12, 'Accepted', '2025-04-21 15:12:55', '413209', '482075', NULL),
(64, 52, 5, 13, 'Accepted', '2025-04-22 18:18:37', '787674', '170992', NULL),
(65, 54, 13, 17, 'Returned', '2025-04-23 15:24:01', '902730', '256707', NULL),
(66, 54, 13, 17, '', '2025-04-23 15:27:43', NULL, NULL, NULL),
(67, 54, 13, 17, 'Pending', '2025-04-24 05:35:02', NULL, NULL, NULL),
(68, 52, 5, 13, 'Accepted', '2025-04-24 05:38:01', '382513', '513100', NULL),
(69, 52, 17, 13, 'Returned', '2025-04-24 05:39:52', '897387', '025445', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Open','In Progress','Closed') DEFAULT 'Open',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `user_id`, `subject`, `message`, `status`, `created_at`, `updated_at`) VALUES
(1, 13, 'Test', 'Test', 'Closed', '2025-05-06 09:37:22', '2025-05-06 09:57:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `profile_pic` varchar(255) DEFAULT 'uploads/prof1.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `profile_pic`) VALUES
(2, 'Dhulwin', 'Senith', 'cb000001@students.apiit.lk', '$2y$10$rAHt/0KiFAd4Gny0PJ88cOOpEgKeibTq.4pXeHdPvGDcLePiaiUia', 'user', 'uploads/profile_2_1745007778.jpg'),
(3, 'Senesh', 'Maleesha', 'cb000002@students.apiit.lk', '$2y$10$facPWjgd1WN0ghdD1.BHw.2jV.Nyk1RuBIdfVIBrlO4yzGOBQP5DS', 'user', 'uploads/profile_3_1744989474.jpg'),
(5, 'Bookloop', 'Admin', 'admin@students.apiit.lk', '$2y$10$OwXZG/a4vRxiSj0D6YgUHe5LksA6N5mfozGo.TYP6jEObDdiqhKHa', 'admin', 'uploads/profile_5_1745006037.jpg'),
(6, 'Pathum', 'Sampath', 'cb000003@students.apiit.lk', '$2y$10$9JSmpW9MG0lrlC2Q4m495.O4kfgwxZhqHIhLmFCMCdW.nAar.s2JC', 'user', 'uploads/prof1.png'),
(7, 'student', 'apiit', 'cb000004@students.apiit.lk', '$2y$10$vq9xdZyNBAkTBiemvhoZc.M54KVr3w9/c8vDNC0cktIAVHcU2CwVy', 'user', 'uploads/prof1.png'),
(8, 'Nickel', 'Fabio', 'cb000005@students.apiit.lk', '$2y$10$b/AjJjkq.RT6CNvXvKAo5eLvOR7oA5ayba9sYlwCD0pcmJee4WcLG', 'user', 'uploads/prof1.png'),
(9, 'Pasindu', 'Navodya', 'pasinavod@gmail.com', '$2y$10$rAHt/0KiFAd4Gny0PJ88cOOpEgKeibTq.4pXeHdPvGDcLePiaiUia', 'user', 'uploads/profile_5_1745006037.jpg'),
(10, 'Nickel', 'Fabio', 'Nickelgunasekera123@gmail.com', '$2y$10$rAHt/0KiFAd4Gny0PJ88cOOpEgKeibTq.4pXeHdPvGDcLePiaiUia', 'user', 'uploads/profile_5_1745006037.jpg'),
(11, 'Dhulwin', 'Senath', 'dhulwijewardena0405@gmail.com', '$2y$10$rAHt/0KiFAd4Gny0PJ88cOOpEgKeibTq.4pXeHdPvGDcLePiaiUia', 'user', 'uploads/profile_5_1745006037.jpg'),
(12, 'Senesh', 'Maleesha', 'cb011672@students.apiit.lk', '$2y$10$EOQL0VHIC6DoQWnxkTkuvO3OU4dnAEHfyUrK/qjsJpKEYIFRcsVx2', 'user', 'uploads/prof1.png'),
(13, 'Pasindu', 'Rathnayake', 'cb013343@students.apiit.lk', '$2y$10$rAHt/0KiFAd4Gny0PJ88cOOpEgKeibTq.4pXeHdPvGDcLePiaiUia', 'user', 'Uploads/profile_13_1745175519.png'),
(16, 'Nickel', 'Gunasekera', 'cb013126@students.apiit.lk', '$2y$10$ai/ZdyZzWAlw51wjFpmdKOYS0DmRkWSe0l0qeiBkvVPA4On0KZKOu', 'user', 'uploads/prof1.png'),
(17, 'Dhul', 'Wijewardena', 'cb012062@students.apiit.lk', '$2y$10$rAHt/0KiFAd4Gny0PJ88cOOpEgKeibTq.4pXeHdPvGDcLePiaiUia', 'user', 'uploads/prof1.png');

-- --------------------------------------------------------

--
-- Table structure for table `user_ratings`
--

CREATE TABLE `user_ratings` (
  `id` int(11) NOT NULL,
  `rated_user_id` int(11) NOT NULL,
  `rater_user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_ratings`
--

INSERT INTO `user_ratings` (`id`, `rated_user_id`, `rater_user_id`, `request_id`, `rating`, `review`, `created_at`) VALUES
(1, 3, 2, 9, 4, 'Good', '2025-05-07 21:00:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `book_pickup_otp`
--
ALTER TABLE `book_pickup_otp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `borrower_id` (`borrower_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `idx_book_pickup` (`book_id`,`pickup_id`,`status`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `deleted_books`
--
ALTER TABLE `deleted_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_participations`
--
ALTER TABLE `event_participations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participation` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `post_id` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `picked_up_books`
--
ALTER TABLE `picked_up_books`
  ADD PRIMARY KEY (`pickup_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `borrower_id` (`borrower_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_ratings`
--
ALTER TABLE `user_ratings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `book_pickup_otp`
--
ALTER TABLE `book_pickup_otp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `user_ratings`
--
ALTER TABLE `user_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
