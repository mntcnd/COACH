<?php
session_start();

// *** FIX: Set timezone to Philippine Time (PHT) ***
date_default_timezone_set('Asia/Manila');


$course = $_GET['course'] ?? '';
$date   = $_GET['date'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

if ($course === '' || $date === '' || !$userId) {
    echo json_encode([]);
    exit;
}

require "../connection/db_connection.php";

// Fetch all defined timeslots for this course/date, ORDER BY start time
$stmt = $conn->prepare("
    SELECT Time_Slot 
    FROM sessions 
    WHERE Course_Title = ? AND Session_Date = ? 
    ORDER BY STR_TO_DATE(SUBSTRING_INDEX(Time_Slot, ' - ', 1), '%h:%i %p')
");
$stmt->bind_param("ss", $course, $date);
$stmt->execute();
$result = $stmt->get_result();

$response = [];

while ($row = $result->fetch_assoc()) {
    $slot = $row['Time_Slot'];

    // Split time range: "7:39 PM - 8:39 PM"
    [$startTime, $endTime] = array_map('trim', explode('-', $slot));

    // Build full datetime for comparison (use session date + end time)
    // The strtotime function will now use 'Asia/Manila' for calculation
    $slotEndDateTime = strtotime($date . ' ' . $endTime);
    $now = time(); 

    // Count total bookings
    $stmt2 = $conn->prepare("SELECT COUNT(*) as total 
                             FROM session_bookings 
                             WHERE course_title=? AND session_date=? AND time_slot=?");
    $stmt2->bind_param("sss", $course, $date, $slot);
    $stmt2->execute();
    $res2 = $stmt2->get_result()->fetch_assoc();
    $slotsTaken = $res2['total'] ?? 0;
    $slotsLeft  = 10 - $slotsTaken;

    // Check if this user already booked this slot
    $stmt3 = $conn->prepare("SELECT COUNT(*) as mine 
                             FROM session_bookings 
                             WHERE user_id=? AND course_title=? AND session_date=? AND time_slot=?");
    $stmt3->bind_param("isss", $userId, $course, $date, $slot);
    $stmt3->execute();
    $res3 = $stmt3->get_result()->fetch_assoc();
    $alreadyBooked = ($res3['mine'] > 0);

    // Default status
    $status = "available";

    // Past session check (priority 1)
    if ($slotEndDateTime < $now) {
        $status = "past";
    } elseif ($alreadyBooked) {
        $status = "already_booked";
    } elseif ($slotsLeft <= 0) {
        $status = "full";
    }

    $response[] = [
        "slot"      => $slot,
        "slotsLeft" => max(0, $slotsLeft),
        "status"    => $status
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
