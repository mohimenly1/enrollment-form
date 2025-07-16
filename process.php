<?php
// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_enrollment');
define('DB_PORT', 3308);

$response = [];
$conn = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed', 405);
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8");

    // Unified handler for both new and returning students
    if (isset($_POST['enrollment_type'])) {
        $response = handleStudentAndFamilyCreation($conn, $_POST, $_FILES);
    } else {
        throw new Exception('Unknown form type. "enrollment_type" is missing.');
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'debug' => [ 'post' => $_POST, 'files' => $_FILES ]
    ];
    http_response_code(500);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($response);
exit;


/**
 * =================================================================
 * THE NEW UNIFIED HANDLER FOR STUDENTS AND FAMILIES
 * =================================================================
 */
function handleStudentAndFamilyCreation($conn, $postData, $filesData) {
    $conn->begin_transaction();
    
    try {
        $familyId = $postData['family_id'] ?? null;

        // --- Step 1: Create or Retrieve Family ID ---
        if (empty($familyId)) {
            // This is the FIRST student, create a new family and parent record
            
            // a. Create Family Record
            $stmtFamily = $conn->prepare("INSERT INTO families (primary_contact_email, primary_contact_phone) VALUES (?, ?)");
            $parentEmail = $postData['parentEmail'][0] ?? null;
            $parentPhone = $postData['parentPhone'][0] ?? $postData['parent_phone']; // From new or returning form
            $stmtFamily->bind_param("ss", $parentEmail, $parentPhone);
            if (!$stmtFamily->execute()) throw new Exception("Failed to create family record: " . $stmtFamily->error);
            $familyId = $conn->insert_id;
            $stmtFamily->close();

            // b. Create Parent Record, linked to the new family
          // b. Create Parent Record, linked to the new family
$parentName = $postData['parentName'][0] ?? $postData['parent_name'];
$relationship = $postData['relationship'][0] ?? $postData['parent_relationship'];
$parentPhoneAlt = $postData['parentPhoneAlt'][0] ?? null;
$occupation = $postData['parentOccupation'][0] ?? null;
$company = $postData['parentCompany'][0] ?? null;

// === START OF THE FIX ===
if ($postData['enrollment_type'] === 'returning') {
    // For returning students, provide default values for required address fields
    $country = 'Not Provided';
    $city = 'Not Provided';
    $street = null;
    $postalCode = null;
    $fullAddress = null;
} else {
    // For new students, get data from the form
    $country = $postData['country'][0] ?? null;
    $city = $postData['city'][0] ?? null;
    $street = $postData['street'][0] ?? null;
    $postalCode = $postData['postalCode'][0] ?? null;
    $fullAddress = $postData['fullAddress'][0] ?? null;
}
// === END OF THE FIX ===

            $stmtParent = $conn->prepare("INSERT INTO parents (family_id, relationship, full_name, email, phone, alternate_phone, occupation, company, country, city, street, postal_code, full_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtParent->bind_param("issssssssssss", $familyId, $relationship, $parentName, $parentEmail, $parentPhone, $parentPhoneAlt, $occupation, $company, $country, $city, $street, $postalCode, $fullAddress);
            if (!$stmtParent->execute()) throw new Exception("Failed to create parent record: " . $stmtParent->error);
            $stmtParent->close();
        }

        // --- Step 2: Create the Student Record ---
        $enrollmentType = $postData['enrollment_type'];

        if ($enrollmentType === 'new') {
            // Data from the full "new student" form
            $fullName = $postData['fullName'][0];
            $birthDate = $postData['birthDate'][0];
            $gender = $postData['gender'][0];
            $nationality = $postData['nationality'][0];
            $passportNumber = $postData['passportNumber'][0];
            $birthPlace = $postData['birthPlace'][0] ?? null;
            $bloodType = $postData['bloodType'][0] ?? null;
            $allergies = $postData['allergies'][0] ?? null;
            $medicalConditions = $postData['medicalConditions'][0] ?? null;
            $gradeLevel = $postData['gradeLevel'][0];
            $academicYear = $postData['academicYear'][0];
            $previousSchool = $postData['previousSchool'][0] ?? null;
            $subjectsInterest = $postData['subjectsInterest'][0] ?? null;
            $specialNeeds = $postData['specialNeeds'][0] ?? null;
            $academicNotes = $postData['academicNotes'][0] ?? null;

        } elseif ($enrollmentType === 'returning') {
            // Data from the simple "returning student" form
            $fullName = $postData['student_name'];
            $gradeLevel = $postData['previous_grade']; // The previous grade becomes the new grade for simplicity, can be adjusted
            // Set other fields to defaults or null as they are not collected
            $birthDate = '1970-01-01'; // Placeholder, as this is required but not collected
            $gender = 'Not Specified';
            $nationality = 'Not Specified';
            $passportNumber = 'Not Specified';
            $academicYear = '2025-2026'; // Default
            // All other optional fields are null
            $birthPlace = $bloodType = $allergies = $medicalConditions = $previousSchool = $subjectsInterest = $specialNeeds = $academicNotes = null;
        }

        $stmtStudent = $conn->prepare("INSERT INTO students (family_id, full_name, birth_date, gender, nationality, passport_number, birth_place, blood_type, allergies, medical_conditions, grade_level, academic_year, enrollment_type, previous_school, subjects_interest, special_needs, academic_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtStudent->bind_param("issssssssssssssss", $familyId, $fullName, $birthDate, $gender, $nationality, $passportNumber, $birthPlace, $bloodType, $allergies, $medicalConditions, $gradeLevel, $academicYear, $enrollmentType, $previousSchool, $subjectsInterest, $specialNeeds, $academicNotes);
        if (!$stmtStudent->execute()) throw new Exception("Student Insert Error: " . $stmtStudent->error);
        $studentId = $conn->insert_id;
        $stmtStudent->close();

        // --- Step 3: Handle File Uploads (only for 'new' students) ---
        if ($enrollmentType === 'new') {
            $studentUploadDir = __DIR__ . '/uploads/' . $studentId;
            if (!file_exists($studentUploadDir)) {
                if (!mkdir($studentUploadDir, 0755, true)) {
                    throw new Exception("Failed to create directory for student ID: $studentId");
                }
            }
            $documentPaths = [];
            $fileFields = ['birthCertificate', 'passportCopy', 'vaccinationRecord', 'schoolReport', 'parentId', 'studentPhoto'];
            foreach ($fileFields as $field) {
                $fileInfo = null;
                if (isset($filesData[$field]['name'][0]) && $filesData[$field]['error'][0] === UPLOAD_ERR_OK) {
                    $fileInfo = ['name' => $filesData[$field]['name'][0], 'type' => $filesData[$field]['type'][0], 'tmp_name' => $filesData[$field]['tmp_name'][0], 'error' => $filesData[$field]['error'][0], 'size' => $filesData[$field]['size'][0]];
                }
                $documentPaths[$field] = $fileInfo ? uploadFile($fileInfo, $studentUploadDir, $studentId, $field) : null;
            }
            $stmtDocs = $conn->prepare("INSERT INTO documents (student_id, birth_certificate, passport_copy, vaccination_record, school_report, parent_id_copy, student_photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtDocs->bind_param("issssss", $studentId, $documentPaths['birthCertificate'], $documentPaths['passportCopy'], $documentPaths['vaccinationRecord'], $documentPaths['schoolReport'], $documentPaths['parentId'], $documentPaths['studentPhoto']);
            if (!$stmtDocs->execute()) throw new Exception("Documents Insert Error: " . $stmtDocs->error);
            $stmtDocs->close();
        }

        $conn->commit();
        
        return [
            'success' => true, 
            'message' => 'Student registered successfully!',
            'family_id' => $familyId, // Return the family ID to the frontend
            'student_name' => $fullName
        ];

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function uploadFile($file, $uploadDir, $studentId, $fieldName) {
    // ... (This function remains unchanged from the last version) ...
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!array_key_exists($file['type'], $allowedTypes)) { return null; }
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) { return null; }
    $extension = $allowedTypes[$file['type']];
    $safeFieldName = preg_replace("/[^a-zA-Z0-9_]/", "", $fieldName);
    $filename = sprintf("student_%d_%s_%d.%s", $studentId, $safeFieldName, time(), $extension);
    $destination = $uploadDir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $studentId . '/' . $filename;
    }
    return null;
}