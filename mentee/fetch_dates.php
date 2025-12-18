<?php
session_start();

// *** FIX: Set timezone to Philippine Time (PHT) ***
date_default_timezone_set('Asia/Manila');

require "../connection/db_connection.php";


$course = $_GET['course'] ?? '';

if ($course === '') {
    echo json_encode([]);
    exit;
}

$events = [];
$today = date('Y-m-d');


// Get all distinct session dates for this course
$stmt = $conn->prepare("SELECT DISTINCT Session_Date FROM sessions WHERE Course_Title = ?");
$stmt->bind_param("s", $course);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $date = $row['Session_Date'];

    // Skip past dates
    if ($date < $today) {
        continue;
    }

    // Count how many timeslots are defined for this date
    $stmt2 = $conn->prepare("SELECT COUNT(*) as slot_count FROM sessions WHERE Course_Title=? AND Session_Date=?");
    $stmt2->bind_param("ss", $course, $date);
    $stmt2->execute();
    $slotCount = $stmt2->get_result()->fetch_assoc()['slot_count'] ?? 0;

    // Each timeslot has 10 slots
    $capacity = $slotCount * 10;

    // Count bookings for this date
    $stmt3 = $conn->prepare("SELECT COUNT(*) as booked FROM session_bookings WHERE course_title=? AND session_date=?");
    $stmt3->bind_param("ss", $course, $date);
    $stmt3->execute();
    $booked = $stmt3->get_result()->fetch_assoc()['booked'] ?? 0;

    $available = $capacity - $booked;

    if ($available > 0) {
        $events[] = [
            'title' => 'Available',
            'display' => 'background',
            'start' => $date,
            'color' => '#6b2a7a',    // background color of tile
            'textColor' => '#ffffff' // title text color
        ];
    } else {
        $events[] = [
            'title' => 'Full',
            'display' => 'background',
            'start' => $date,
            'color' => 'red',        // background color of tile
            'textColor' => '#ffffff' // title text color
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($events);
