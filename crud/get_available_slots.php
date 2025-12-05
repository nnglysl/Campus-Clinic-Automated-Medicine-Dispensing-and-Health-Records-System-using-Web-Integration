<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
$department = $_GET['department'] ?? null; // optional filter
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        throw new Exception('Invalid date format');
    }
    
    // Validate department
    if ($department && !in_array($department, ['dental', 'medical'])) {
        throw new Exception('Invalid department');
    }
    
if ($department) {
    $doctorRole = ($department === 'dental') ? 'dentist' : 'doctor';
    
    $scheduleStmt = $pdo->prepare("
        SELECT 
            ds.schedule_date,
            ds.start_time,
            ds.end_time,
            ds.schedule_type,
            ds.is_available,
            ds.reason,
            u.fname,
            u.lname
        FROM doctor_schedules ds
        INNER JOIN users u ON ds.user_id = u.id
        WHERE ds.schedule_date BETWEEN ? AND ?
          AND u.role = ?
          AND ds.is_available = 1
          AND ds.schedule_type = 'available'
          AND ds.start_time IS NOT NULL
          AND ds.end_time IS NOT NULL
        ORDER BY ds.schedule_date, ds.start_time
    ");
    $scheduleStmt->execute([$startDate, $endDate, $doctorRole]);
    $schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $appointmentsStmt = $pdo->prepare("
        SELECT appointment_date, TIME(appointment_time) as appointment_time
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ?
          AND appointment_type = ?
          AND status != 'cancelled'
    ");
    $appointmentsStmt->execute([$startDate, $endDate, $department]);
    $appointments = [];
    foreach ($appointmentsStmt as $appt) {
        // Normalize time format to H:i:00 for consistent matching
        $time = $appt['appointment_time'];
        if ($time) {
            // Extract hours and minutes, ensure seconds are 00
            $timeParts = explode(':', $time);
            $normalizedTime = sprintf('%02d:%02d:00', (int)$timeParts[0], (int)($timeParts[1] ?? 0));
            $date = $appt['appointment_date'];
            if (!isset($appointments[$date])) {
                $appointments[$date] = [];
            }
            $appointments[$date][$normalizedTime] = true;
        }
    }
    
    $calendarDates = [];
    $timeSlotsByDate = [];
    
    foreach ($schedules as $schedule) {
        $date = $schedule['schedule_date'];
        if (!isset($calendarDates[$date])) {
            $calendarDates[$date] = [
                'total_slots' => 0,
                'available_slots' => 0,
                'booked_slots' => 0,
                'status' => 'available',
                'reason' => null,
                'department' => $department
            ];
        }
        
        if ((int)$schedule['is_available'] === 0 || $schedule['schedule_type'] === 'unavailable') {
            $calendarDates[$date]['status'] = 'unavailable';
            $calendarDates[$date]['reason'] = $schedule['reason'] ?? 'Doctor unavailable';
            continue;
        }
        
        if (empty($schedule['start_time']) || empty($schedule['end_time'])) {
            continue;
        }
        
        $start = new DateTime($date . ' ' . $schedule['start_time']);
        $end = new DateTime($date . ' ' . $schedule['end_time']);
        
        // For backward compatibility: If schedule ends exactly at 17:00 (5:00 PM), 
        // extend it by 30 minutes to allow slot at 17:00 (5:00 PM)
        // This handles existing schedules saved before the fix
        if ($schedule['end_time'] === '17:00:00' || $schedule['end_time'] === '17:00') {
            $end->modify('+30 minutes');
        }
        
        // Generate time slots in 30-minute intervals
        // Create slots that can fit within the schedule (at least 30 minutes for appointment)
        while ($start < $end) {
            // Calculate the end time of this slot (start + 30 minutes)
            $slotEndTime = clone $start;
            $slotEndTime->modify('+30 minutes');
            
            $timeSlot = $start->format('H:i:00');
            
            // Only create slot if there's enough time (at least 30 minutes) before schedule ends
            if ($slotEndTime > $end) {
                // Not enough time for a 30-minute appointment, skip this and any remaining slots
                break;
            }
            // Check if this time slot is booked (normalize to ensure match)
            $isBooked = !empty($appointments[$date][$timeSlot]);
            
            if (!isset($timeSlotsByDate[$date])) {
                $timeSlotsByDate[$date] = [];
            }
            
            // Use time slot as key to prevent duplicates when multiple doctors have same schedule
            $timeSlotKey = $timeSlot;
            $isNewSlot = !isset($timeSlotsByDate[$date][$timeSlotKey]);
            
            // Only count slots once (when first encountered) to avoid double counting
            if ($isNewSlot) {
                $calendarDates[$date]['total_slots']++;
                if ($isBooked) {
                    $calendarDates[$date]['booked_slots']++;
                } else {
                    $calendarDates[$date]['available_slots']++;
                }
                
                $timeSlotsByDate[$date][$timeSlotKey] = [
                    'date' => $date,
                    'time' => $timeSlot,
                    'formatted_time' => $start->format('g:i A'),
                    'available' => !$isBooked,
                    'doctor_name' => trim(($schedule['fname'] ?? '') . ' ' . ($schedule['lname'] ?? '')),
                    'department' => $department
                ];
            } else {
                // If slot already exists from another doctor, update availability
                // A slot is available if ANY doctor has it available and not booked
                if (!$isBooked) {
                    // If any doctor has this slot available, mark it as available
                    $timeSlotsByDate[$date][$timeSlotKey]['available'] = true;
                    // Update booked/available counts if this makes it available
                    if ($timeSlotsByDate[$date][$timeSlotKey]['available']) {
                        // If it was previously counted as booked but now available, adjust counts
                        // (Note: We don't decrease booked_slots here since we're counting unique slots)
                    }
                }
                // Append doctor name if different
                $existingDoctor = $timeSlotsByDate[$date][$timeSlotKey]['doctor_name'];
                $currentDoctor = trim(($schedule['fname'] ?? '') . ' ' . ($schedule['lname'] ?? ''));
                if ($currentDoctor && $existingDoctor !== $currentDoctor && strpos($existingDoctor, $currentDoctor) === false) {
                    $timeSlotsByDate[$date][$timeSlotKey]['doctor_name'] = $existingDoctor . ', ' . $currentDoctor;
                }
            }
            
            $start->modify('+30 minutes');
        }
    }
    
    // Recalculate slot counts based on unique slots only
    foreach ($timeSlotsByDate as $date => &$slots) {
        // Count unique slots for accurate totals
        if (isset($calendarDates[$date])) {
            $uniqueSlots = array_keys($slots);
            $calendarDates[$date]['total_slots'] = count($uniqueSlots);
            $calendarDates[$date]['booked_slots'] = 0;
            $calendarDates[$date]['available_slots'] = 0;
            
            foreach ($slots as $slot) {
                if ($slot['available']) {
                    $calendarDates[$date]['available_slots']++;
                } else {
                    $calendarDates[$date]['booked_slots']++;
                }
            }
        }
        // Convert associative array back to indexed array
        $timeSlotsByDate[$date] = array_values($slots);
    }
    unset($slots);
    
    foreach ($calendarDates as $date => &$info) {
        if ($info['status'] === 'unavailable') {
            continue;
        }
        if ($info['total_slots'] === 0) {
            $info['status'] = 'unavailable';
            $info['reason'] = 'No doctor schedule';
        } elseif ($info['available_slots'] === 0) {
            $info['status'] = 'fully-booked';
        } elseif ($info['booked_slots'] > 0) {
            $info['status'] = 'partially-available';
        } else {
            $info['status'] = 'available';
        }
    }
    
    $currentDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    while ($currentDateObj <= $endDateObj) {
        $dateStr = $currentDateObj->format('Y-m-d');
        if (!isset($calendarDates[$dateStr])) {
            $isWeekend = in_array((int)$currentDateObj->format('w'), [0, 6], true);
            $calendarDates[$dateStr] = [
                'status' => 'unavailable',
                'reason' => $isWeekend ? 'Weekend' : 'No doctor schedule',
                'available_slots' => 0,
                'total_slots' => 0,
                'department' => $department
            ];
        }
        $currentDateObj->modify('+1 day');
    }
    
    $timeSlots = [];
    foreach ($timeSlotsByDate as $slots) {
        $timeSlots = array_merge($timeSlots, $slots);
    }
} else {
    // Fallback to existing aggregated view when no department filter is provided
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
    
    $calendarDates = [];
    $timeSlotsByDate = [];
    
    foreach ($availabilityData as $row) {
        $date = $row['schedule_date'];
        $timeSlot = $row['time_slot'];
        
        if (!isset($calendarDates[$date])) {
            $calendarDates[$date] = [
                'total_slots' => 0,
                'available_slots' => 0,
                'schedule_type' => $row['schedule_type']
            ];
        }
        
        if (!isset($timeSlotsByDate[$date])) {
            $timeSlotsByDate[$date] = [];
        }
        
        $calendarDates[$date]['total_slots']++;
        if ($row['is_slot_available'] == 1) {
            $calendarDates[$date]['available_slots']++;
        }
        
        $timeSlotsByDate[$date][] = [
            'date' => $date,
            'time' => $timeSlot,
            'formatted_time' => date('h:i A', strtotime($timeSlot)),
            'available' => $row['is_slot_available'] == 1,
            'booked_count' => (int)$row['booked_count'],
            'max_capacity' => 1,
            'doctor_name' => trim($row['fname'] . ' ' . $row['lname']),
            'department' => null
        ];
    }
    
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
    
    $timeSlots = [];
    foreach ($timeSlotsByDate as $slots) {
        $timeSlots = array_merge($timeSlots, $slots);
    }
}
    
    echo json_encode([
        'success' => true,
        'data' => [
        'calendar_dates' => $calendarDates,
        'time_slots' => $timeSlots,
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