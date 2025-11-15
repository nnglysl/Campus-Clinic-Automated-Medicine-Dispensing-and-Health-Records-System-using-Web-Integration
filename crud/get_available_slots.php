<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $department = $_GET['department'] ?? null; // New parameter for filtering
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        throw new Exception('Invalid date format');
    }
    
    // Validate department
    if ($department && !in_array($department, ['dental', 'medical'])) {
        throw new Exception('Invalid department');
    }
    
    // Build query with optional department filter
    $query = "
        SELECT 
            schedule_date,
            time_slot,
            is_slot_available,
            schedule_type,
            booked_count,
            fname,
            lname
        FROM v_appointment_availability
        WHERE schedule_date BETWEEN ? AND ?
        ORDER BY schedule_date, time_slot
    ";
    
    $params = [$startDate, $endDate];
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $availabilityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If department filter is active, check appointment conflicts in a single query
    $departmentConflicts = [];
    if ($department) {
        $conflictQuery = "
            SELECT 
                appointment_date,
                appointment_time,
                COUNT(*) as dept_count
            FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
            AND appointment_type = ?
            AND status IN ('scheduled', 'confirmed')
            GROUP BY appointment_date, appointment_time
        ";
        $conflictStmt = $pdo->prepare($conflictQuery);
        $conflictStmt->execute([$startDate, $endDate, $department]);
        $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($conflicts as $conflict) {
            $key = $conflict['appointment_date'] . '|' . $conflict['appointment_time'];
            $departmentConflicts[$key] = (int)$conflict['dept_count'];
        }
    }
    
    // Build calendar dates and time slots
    $calendarDates = [];
    $timeSlotsByDate = [];
    
    // If department filter is active, also check appointment_type conflicts
    foreach ($availabilityData as $row) {
        $date = $row['schedule_date'];
        $timeSlot = $row['time_slot'];
        
        // If department is specified, check appointments for that specific type
        if ($department) {
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as dept_count
                FROM appointments
                WHERE appointment_date = ?
                AND appointment_time = ?
                AND appointment_type = ?
                AND status IN ('scheduled', 'confirmed')
            ");
            $checkStmt->execute([$date, $timeSlot, $department]);
            $deptCheck = $checkStmt->fetch();
            
            // Override availability if department slot is taken
            if ($deptCheck['dept_count'] >= 1) {
                $row['is_slot_available'] = 0;
                $row['booked_count'] = $deptCheck['dept_count'];
            }
        }
        
        if (!isset($calendarDates[$date])) {
            $calendarDates[$date] = [
                'total_slots' => 0,
                'available_slots' => 0,
                'schedule_type' => $row['schedule_type'],
                'department' => $department
            ];
        }
        
        if (!isset($timeSlotsByDate[$date])) {
            $timeSlotsByDate[$date] = [];
        }
        
        // Count slots
        $calendarDates[$date]['total_slots']++;
        if ($row['is_slot_available'] == 1) {
            $calendarDates[$date]['available_slots']++;
        }
        
        // Add time slot details
        $timeSlotsByDate[$date][] = [
            'date' => $date,
            'time' => $timeSlot,
            'formatted_time' => date('h:i A', strtotime($timeSlot)),
            'available' => $row['is_slot_available'] == 1,
            'booked_count' => (int)$row['booked_count'],
            'max_capacity' => 1,
            'doctor_name' => trim($row['fname'] . ' ' . $row['lname']),
            'department' => $department
        ];
    }
    
    // Determine status for each date
    foreach ($calendarDates as $date => &$dateInfo) {
        if ($dateInfo['schedule_type'] === 'unavailable') {
            $dateInfo['status'] = 'unavailable';
            $dateInfo['reason'] = 'Doctor unavailable';
        } elseif ($dateInfo['available_slots'] === 0) {
            $dateInfo['status'] = 'fully-booked';
        } elseif ($dateInfo['available_slots'] === $dateInfo['total_slots']) {
            $dateInfo['status'] = 'available';
        } else {
            $dateInfo['status'] = 'partially-available';
        }
    }
    
    // Fill in dates without schedules
    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);
    
    while ($currentDate <= $endDateTime) {
        $dateStr = $currentDate->format('Y-m-d');
        $dayOfWeek = (int)$currentDate->format('w');
        
        if (!isset($calendarDates[$dateStr])) {
            if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                $calendarDates[$dateStr] = [
                    'status' => 'unavailable',
                    'reason' => 'Weekend',
                    'available_slots' => 0,
                    'total_slots' => 0,
                    'department' => $department
                ];
            } else {
                $stmt = $pdo->prepare("
                    SELECT reason 
                    FROM doctor_schedules 
                    WHERE schedule_date = ? 
                    AND schedule_type = 'unavailable' 
                    AND is_available = 1
                    LIMIT 1
                ");
                $stmt->execute([$dateStr]);
                $unavailable = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($unavailable) {
                    $calendarDates[$dateStr] = [
                        'status' => 'unavailable',
                        'reason' => $unavailable['reason'] ?? 'Doctor unavailable',
                        'available_slots' => 0,
                        'total_slots' => 0,
                        'department' => $department
                    ];
                } else {
                    $calendarDates[$dateStr] = [
                        'status' => 'unavailable',
                        'reason' => 'No doctor schedule',
                        'available_slots' => 0,
                        'total_slots' => 0,
                        'department' => $department
                    ];
                }
            }
        }
        
        $currentDate->modify('+1 day');
    }
    
    // Flatten time slots array
    $allTimeSlots = [];
    foreach ($timeSlotsByDate as $date => $slots) {
        $allTimeSlots = array_merge($allTimeSlots, $slots);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'calendar_dates' => $calendarDates,
            'time_slots' => $allTimeSlots,
            'department' => $department,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_available_slots.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}