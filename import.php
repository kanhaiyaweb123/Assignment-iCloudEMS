<?php

set_time_limit(0);
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 0);

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "assignment_icloudems";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$conn->autocommit(FALSE);

// Open CSV file
$file = fopen("data.csv", "r");
if (!$file) {
    die("Error opening file.");
}

// Skip header rows
fgetcsv($file); // Skip the first header row
fgetcsv($file); // Skip the second header row
fgetcsv($file); // Skip the third header row
fgetcsv($file); // Skip the fourth header row
fgetcsv($file); // Skip the fifth header row

// Batch insert setup
$batchSize = 2000; // Insert 2000 rows per query (adjust if needed)
$rows = [];
$totalInserted = 0;

while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
    
    // Ensure correct column mapping
    if (isset($row[0]) && $row[0] === 'Sr.') {
        continue; // Skip this row
    }
    $sr = (int) ($row[0] ?? 0); // Sr.
    $trans_date = date('Y-m-d', strtotime($row[1] ?? '1970-01-01')); // Date
    $academic_year = $conn->real_escape_string($row[2] ?? ''); // Academic Year
    $session = $conn->real_escape_string($row[3] ?? ''); // Session
    $alloted_category = $conn->real_escape_string($row[4] ?? ''); // Alloted Category
    $voucher_type = $conn->real_escape_string($row[5] ?? ''); // Voucher Type
    $voucher_no = $conn->real_escape_string($row[6] ?? ''); // Voucher No.
    $roll_no = $conn->real_escape_string($row[7] ?? ''); // Roll No.
    $admission_no = $conn->real_escape_string($row[8] ?? ''); // Admno/UniqueId
    $status = $conn->real_escape_string($row[9] ?? ''); // Status
    $fee_category = $conn->real_escape_string($row[10] ?? ''); // Fee Category
    $faculty = $conn->real_escape_string($row[11] ?? ''); // Faculty
    $program = $conn->real_escape_string($row[12] ?? ''); // Program
    $department = $conn->real_escape_string($row[13] ?? ''); // Department
    $batch = $conn->real_escape_string($row[14] ?? ''); // Batch
    $receipt_no = $conn->real_escape_string($row[15] ?? ''); // Receipt No.
    $fee_head = $conn->real_escape_string($row[16] ?? ''); // Fee Head
    $due_amount = (float) ($row[17] ?? 0.00); // Due Amount
    $paid_amount = (float) ($row[18] ?? 0.00); // Paid Amount
    $concession_amount = (float) ($row[19] ?? 0.00); // Concession Amount
    $scholarship_amount = (float) ($row[20] ?? 0.00); // Scholarship Amount
    $reverse_concession_amount = (float) ($row[21] ?? 0.00); // Reverse Concession Amount
    $write_off_amount = (float) ($row[22] ?? 0.00); // Write Off Amount
    $adjusted_amount = (float) ($row[23] ?? 0.00); // Adjusted Amount
    $refund_amount = (float) ($row[24] ?? 0.00); // Refund Amount
    $fund_transfer_amount = (float) ($row[25] ?? 0.00); // Fund Transfer Amount
    $remarks = $conn->real_escape_string($row[26] ?? ''); // Remarks

    // Prepare batch insert values
    $rows[] = "($sr, '$trans_date', '$academic_year', '$session', '$alloted_category', '$voucher_type', '$voucher_no', '$roll_no', '$admission_no', '$status', '$fee_category', '$faculty', '$program', '$department', '$batch', '$receipt_no', '$fee_head', $due_amount, $paid_amount, $concession_amount, $scholarship_amount, $reverse_concession_amount, $write_off_amount, $adjusted_amount, $refund_amount, $fund_transfer_amount, '$remarks')";

    // Insert when batch size is reached
    if (count($rows) >= $batchSize) {
        $query = "INSERT INTO temp_import (sr, trans_date, academic_year, session, alloted_category, voucher_type, voucher_no, roll_no, admission_no, status, fee_category, faculty, program, department, batch, receipt_no, fee_head, due_amount, paid_amount, concession_amount, scholarship_amount, reverse_concession_amount, write_off_amount, adjusted_amount, refund_amount, fund_transfer_amount, remarks) VALUES " . implode(',', $rows);
        $conn->query($query);
        $totalInserted += count($rows);
        $rows = []; // Reset batch
    }
}

// Insert remaining rows
if (!empty($rows)) {
    $query = "INSERT INTO temp_import (sr, trans_date, academic_year, session, alloted_category, voucher_type, voucher_no, roll_no, admission_no, status, fee_category, faculty, program, department, batch, receipt_no, fee_head, due_amount, paid_amount, concession_amount, scholarship_amount, reverse_concession_amount, write_off_amount, adjusted_amount, refund_amount, fund_transfer_amount, remarks) VALUES " . implode(',', $rows);
    $conn->query($query);
    $totalInserted += count($rows);
}

// Commit transaction
$conn->commit();
$conn->close();
fclose($file);

echo "Import completed! Total rows inserted: $totalInserted";
?>