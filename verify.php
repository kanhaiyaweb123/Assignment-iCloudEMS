<?php
include 'config.php';

// Query to get total amounts and record count
$query = "
    SELECT 
        (SELECT SUM(due_amount) FROM temp_import) AS total_due_amount,
        (SELECT SUM(paid_amount) FROM temp_import) AS total_paid_amount,
        (SELECT SUM(concession_amount) FROM temp_import) AS total_concession,
        (SELECT SUM(scholarship_amount) FROM temp_import) AS total_scholarship,
        (SELECT SUM(refund_amount) FROM temp_import) AS total_refund,
        (SELECT COUNT(*) FROM temp_import) AS total_records
";

$result = $conn->query($query);

// Check for query execution errors
if (!$result) {
    die("Query failed: " . $conn->error);
}

$data = $result->fetch_assoc();
// Display the results
echo "<h2>Data Verification</h2>";
echo "<p> Due Amount: " . round($data['total_due_amount']) . "</p>";
echo "<p> Paid Amount: " . round($data['total_paid_amount']) . "</p>";
echo "<p> Concession: " . round($data['total_concession']) . "</p>";
echo "<p> Scholarship: " . round($data['total_scholarship']) . "</p>";
echo "<p> Refund: " . round($data['total_refund']) . "</p>";

// echo "<a href='distribute.php'>Distribute Data</a>";

$conn->close();
?>