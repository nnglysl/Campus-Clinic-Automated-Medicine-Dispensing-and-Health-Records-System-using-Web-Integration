<?php
/**
 * Email Helper Functions for Appointment Notifications
 * Uses PHPMailer to send appointment confirmation emails
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

/**
 * Send appointment confirmation email
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param array $appointmentData Appointment details
 * @return array ['success' => bool, 'message' => string]
 */
function sendAppointmentConfirmationEmail($pdo, $userId, $appointmentData) {
    try {
        // Get user email from database
        $stmt = $pdo->prepare("SELECT email, fname, lname FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['email'])) {
            return [
                'success' => false,
                'message' => 'User email not found'
            ];
        }
        
        $userEmail = $user['email'];
        $userName = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
        if (empty($userName)) {
            $userName = 'Student';
        }
        
        // Email configuration (using Gmail SMTP)
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'wanaconnect20@gmail.com'; // SMTP username
        $mail->Password = 'wcno jvyd hdvw caby'; // SMTP password (App Password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom('wanaconnect20@gmail.com', 'BSU Clinic System');
        $mail->addAddress($userEmail, $userName);
        $mail->addReplyTo('wanaconnect20@gmail.com', 'BSU Clinic');
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation - BSU Clinic';
        
        // Format appointment details
        $appointmentType = ucfirst($appointmentData['type'] ?? 'appointment');
        $appointmentDate = $appointmentData['date'] ?? '';
        $appointmentTime = $appointmentData['time'] ?? '';
        $appointmentId = $appointmentData['id'] ?? '';
        
        // Format date and time
        $formattedDate = date('F j, Y', strtotime($appointmentDate));
        $formattedTime = date('g:i A', strtotime($appointmentTime));
        $dayOfWeek = date('l', strtotime($appointmentDate));
        
        // Department icon and name
        $deptIcon = ($appointmentType === 'dental') ? '🦷' : '🩺';
        $deptName = ($appointmentType === 'dental') ? 'Dental Department' : 'Medical Department';
        
        // Create HTML email body
        $mail->Body = getAppointmentEmailTemplate($userName, $deptIcon, $deptName, $formattedDate, $dayOfWeek, $formattedTime, $appointmentId, $appointmentType);
        
        // Plain text alternative
        $mail->AltBody = getAppointmentEmailPlainText($userName, $deptName, $formattedDate, $dayOfWeek, $formattedTime, $appointmentId);
        
        // Send email
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Confirmation email sent successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Get HTML email template for appointment confirmation
 */
function getAppointmentEmailTemplate($userName, $deptIcon, $deptName, $formattedDate, $dayOfWeek, $formattedTime, $appointmentId, $appointmentType) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Appointment Confirmation</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
        <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4; padding: 20px;">
            <tr>
                <td align="center">
                    <table role="presentation" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #6b0000 0%, #8b0000 100%); padding: 30px 20px; text-align: center;">
                                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">Batangas State University</h1>
                                <p style="color: #ffffff; margin: 5px 0 0 0; font-size: 16px;">Clinic Appointment Confirmation</p>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 30px 20px;">
                                <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    Dear <strong>' . htmlspecialchars($userName) . '</strong>,
                                </p>
                                
                                <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    Your appointment has been successfully confirmed!
                                </p>
                                
                                <!-- Appointment Details Box -->
                                <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin: 20px 0;">
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <p style="margin: 0; color: #6b0000; font-size: 18px; font-weight: bold; text-align: center;">
                                                ' . $deptIcon . ' ' . htmlspecialchars($deptName) . '
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 15px 0; border-top: 2px solid #e0e0e0;">
                                            <table role="presentation" style="width: 100%;">
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px; width: 40%;"><strong>Date:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">' . htmlspecialchars($formattedDate) . ' (' . htmlspecialchars($dayOfWeek) . ')</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px;"><strong>Time:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">' . htmlspecialchars($formattedTime) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px;"><strong>Appointment ID:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">#' . htmlspecialchars($appointmentId) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px;"><strong>Department:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">' . htmlspecialchars($deptName) . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Important Instructions -->
                                <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                    <p style="margin: 0 0 10px 0; color: #856404; font-size: 14px; font-weight: bold;">
                                        📋 Important Instructions:
                                    </p>
                                    <ul style="margin: 0; padding-left: 20px; color: #856404; font-size: 14px; line-height: 1.8;">
                                        <li>Please arrive 10-15 minutes before your scheduled appointment time</li>
                                        <li>Bring a valid ID and your student ID card</li>
                                        <li>If you need to cancel or reschedule, please contact the clinic at least 24 hours in advance</li>
                                        <li>For dental appointments, please avoid eating 30 minutes before your visit</li>
                                    </ul>
                                </div>
                                
                                <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                    If you have any questions or need to make changes to your appointment, please contact the clinic office.
                                </p>
                                
                                <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                    We look forward to seeing you!
                                </p>
                                
                                <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
                                    Best regards,<br>
                                    <strong>BSU Clinic Team</strong>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;">
                                <p style="color: #666666; font-size: 12px; margin: 0; line-height: 1.6;">
                                    This is an automated email. Please do not reply to this message.<br>
                                    Batangas State University Clinic | All rights reserved
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/**
 * Get plain text email template for appointment confirmation
 */
function getAppointmentEmailPlainText($userName, $deptName, $formattedDate, $dayOfWeek, $formattedTime, $appointmentId) {
    return "
Batangas State University - Clinic Appointment Confirmation

Dear {$userName},

Your appointment has been successfully confirmed!

APPOINTMENT DETAILS:
--------------------
Department: {$deptName}
Date: {$formattedDate} ({$dayOfWeek})
Time: {$formattedTime}
Appointment ID: #{$appointmentId}

IMPORTANT INSTRUCTIONS:
- Please arrive 10-15 minutes before your scheduled appointment time
- Bring a valid ID and your student ID card
- If you need to cancel or reschedule, please contact the clinic at least 24 hours in advance
- For dental appointments, please avoid eating 30 minutes before your visit

If you have any questions or need to make changes to your appointment, please contact the clinic office.

We look forward to seeing you!

Best regards,
BSU Clinic Team

---
This is an automated email. Please do not reply to this message.
Batangas State University Clinic | All rights reserved
";
}

/**
 * Send appointment cancellation notification to all registered students
 * Only sends to students with email addresses ending in '@g.batstate-u.edu.ph'
 * 
 * @param PDO $pdo Database connection
 * @param array $appointmentData Appointment details (date, time, type)
 * @return array ['success' => bool, 'message' => string, 'sent_count' => int]
 */
function sendCancellationNotificationToStudents($pdo, $appointmentData) {
    try {
        // Get all registered students with @g.batstate-u.edu.ph email domain
        $stmt = $pdo->prepare("
            SELECT id, email, fname, lname 
            FROM users 
            WHERE email LIKE '%@g.batstate-u.edu.ph' 
            AND (role = 'patient' OR role = 'student')
            AND email IS NOT NULL 
            AND email != ''
        ");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            return [
                'success' => true,
                'message' => 'No students found with @g.batstate-u.edu.ph email domain',
                'sent_count' => 0
            ];
        }
        
        // Format appointment details
        $appointmentType = ucfirst($appointmentData['type'] ?? 'appointment');
        $appointmentDate = $appointmentData['date'] ?? '';
        $appointmentTime = $appointmentData['time'] ?? '';
        
        $formattedDate = date('F j, Y', strtotime($appointmentDate));
        $formattedTime = date('g:i A', strtotime($appointmentTime));
        $dayOfWeek = date('l', strtotime($appointmentDate));
        
        // Department icon and name
        $deptIcon = ($appointmentType === 'dental') ? '🦷' : '🩺';
        $deptName = ($appointmentType === 'dental') ? 'Dental Department' : 'Medical Department';
        
        // Email configuration
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'wanaconnect20@gmail.com';
        $mail->Password = 'wcno jvyd hdvw caby';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        
        // Sender
        $mail->setFrom('wanaconnect20@gmail.com', 'BSU Clinic System');
        $mail->addReplyTo('wanaconnect20@gmail.com', 'BSU Clinic');
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Slot Available - BSU Clinic';
        
        // Create email body
        $emailBody = getCancellationNotificationEmailTemplate($deptIcon, $deptName, $formattedDate, $dayOfWeek, $formattedTime, $appointmentType);
        $plainTextBody = getCancellationNotificationEmailPlainText($deptName, $formattedDate, $dayOfWeek, $formattedTime);
        
        $sentCount = 0;
        $failedCount = 0;
        
        // Send email to each student
        foreach ($students as $student) {
            try {
                // Clear previous recipients
                $mail->clearAddresses();
                $mail->clearAttachments();
                
                // Add recipient
                $studentName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
                if (empty($studentName)) {
                    $studentName = 'Student';
                }
                $mail->addAddress($student['email'], $studentName);
                
                // Set personalized body (can be customized per student if needed)
                $mail->Body = $emailBody;
                $mail->AltBody = $plainTextBody;
                
                // Send email
                $mail->send();
                $sentCount++;
                
            } catch (Exception $e) {
                $failedCount++;
                error_log("Failed to send cancellation notification to {$student['email']}: " . $mail->ErrorInfo);
                // Continue with next student even if one fails
            }
        }
        
        return [
            'success' => true,
            'message' => "Cancellation notifications sent to {$sentCount} student(s). " . ($failedCount > 0 ? "Failed: {$failedCount}" : ""),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount
        ];
        
    } catch (Exception $e) {
        error_log("Error sending cancellation notifications: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send cancellation notifications: ' . $e->getMessage(),
            'sent_count' => 0
        ];
    }
}

/**
 * Get HTML email template for appointment cancellation notification
 */
function getCancellationNotificationEmailTemplate($deptIcon, $deptName, $formattedDate, $dayOfWeek, $formattedTime, $appointmentType) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Appointment Slot Available</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
        <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4; padding: 20px;">
            <tr>
                <td align="center">
                    <table role="presentation" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #6b0000 0%, #8b0000 100%); padding: 30px 20px; text-align: center;">
                                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">Batangas State University</h1>
                                <p style="color: #ffffff; margin: 5px 0 0 0; font-size: 16px;">Appointment Slot Available</p>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 30px 20px;">
                                <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    Dear <strong>Student</strong>,
                                </p>
                                
                                <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    An appointment slot has become available at the BSU Clinic!
                                </p>
                                
                                <!-- Available Slot Details Box -->
                                <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #e8f5e9; border-radius: 6px; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;">
                                    <tr>
                                        <td style="padding: 10px 0;">
                                            <p style="margin: 0; color: #2e7d32; font-size: 18px; font-weight: bold; text-align: center;">
                                                ' . $deptIcon . ' ' . htmlspecialchars($deptName) . ' - Slot Available
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 15px 0; border-top: 2px solid #c8e6c9;">
                                            <table role="presentation" style="width: 100%;">
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px; width: 40%;"><strong>Date:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">' . htmlspecialchars($formattedDate) . ' (' . htmlspecialchars($dayOfWeek) . ')</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px;"><strong>Time:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">' . htmlspecialchars($formattedTime) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px;"><strong>Department:</strong></td>
                                                    <td style="padding: 8px 0; color: #333333; font-size: 14px;">' . htmlspecialchars($deptName) . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Call to Action -->
                                <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                    <p style="margin: 0 0 10px 0; color: #856404; font-size: 14px; font-weight: bold;">
                                        📅 Book This Slot Now!
                                    </p>
                                    <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                        This appointment slot is now available. Log in to your student dashboard to book this time slot before it\'s taken!
                                    </p>
                                </div>
                                
                                <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                    Don\'t miss this opportunity! Visit the BSU Clinic appointment system to secure your spot.
                                </p>
                                
                                <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
                                    Best regards,<br>
                                    <strong>BSU Clinic Team</strong>
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;">
                                <p style="color: #666666; font-size: 12px; margin: 0; line-height: 1.6;">
                                    This is an automated email. Please do not reply to this message.<br>
                                    Batangas State University Clinic | All rights reserved
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/**
 * Get plain text email template for appointment cancellation notification
 */
function getCancellationNotificationEmailPlainText($deptName, $formattedDate, $dayOfWeek, $formattedTime) {
    return "
Batangas State University - Appointment Slot Available

Dear Student,

An appointment slot has become available at the BSU Clinic!

AVAILABLE SLOT DETAILS:
-----------------------
Department: {$deptName}
Date: {$formattedDate} ({$dayOfWeek})
Time: {$formattedTime}

BOOK THIS SLOT NOW!
This appointment slot is now available. Log in to your student dashboard to book this time slot before it's taken!

Don't miss this opportunity! Visit the BSU Clinic appointment system to secure your spot.

Best regards,
BSU Clinic Team

---
This is an automated email. Please do not reply to this message.
Batangas State University Clinic | All rights reserved
";
}

