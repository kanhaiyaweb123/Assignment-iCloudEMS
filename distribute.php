<?php
include 'config.php'; // Include your database connection

// Ensure indexes exist for faster queries
function ensureIndexes($conn) {
    $indexes = [
        "CREATE INDEX idx_admission_no ON temp_import(admission_no)",
        "CREATE INDEX idx_voucher_no ON temp_import(voucher_no)",
        "CREATE INDEX idx_trans_date ON temp_import(trans_date)",
        "CREATE INDEX idx_voucher_type ON temp_import(voucher_type)"
    ];

    foreach ($indexes as $index) {
        if (!$conn->query($index)) {
            echo "Index creation failed: " . $conn->error . "<br>";
        }
    }
}

// Batch processing function
function distributeData($conn, $batchSize = 5000) {
    try {
        // Ensure indexes for better performance
        ensureIndexes($conn);

        // Start batch processing
        $offset = 0;
        do {
            $conn->begin_transaction();

            // Step 1: Insert into financial_trans
            $financialTransQuery = "
                INSERT INTO financial_trans (transid, admission_no, amount, trans_date, entry_mode, voucher_no)
                SELECT UUID(), admission_no, SUM(due_amount), trans_date, 
                    CASE WHEN voucher_type IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF') 
                         THEN voucher_type ELSE NULL END AS entry_mode, voucher_no
                FROM temp_import
                WHERE voucher_type IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF')
                GROUP BY voucher_no, trans_date, admission_no
                LIMIT $batchSize OFFSET $offset
            ";
            if (!$conn->query($financialTransQuery)) {
                throw new Exception("Error in financial_trans: " . $conn->error);
            }

            // Step 2: Insert into financial_tran_details
            $financialTranDetailsQuery = "
                INSERT INTO financial_tran_details (financialTranId, admission_no, amount, headId, crdr, brid, head_name)
                SELECT f.transid, t.admission_no, SUM(t.due_amount), 1, 'D', 1, t.fee_category
                FROM temp_import t
                JOIN financial_trans f ON t.admission_no = f.admission_no AND t.voucher_no = f.voucher_no
                WHERE t.voucher_type IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF')
                GROUP BY t.admission_no, f.transid
                LIMIT $batchSize OFFSET $offset
            ";
            if (!$conn->query($financialTranDetailsQuery)) {
                throw new Exception("Error in financial_tran_details: " . $conn->error);
            }

            // Step 3: Insert into common_fee_collection
            $commonFeeCollectionQuery = "
                INSERT INTO common_fee_collection (transid, admission_no, amount, paid_date, entry_mode, display_receipt_no)
                SELECT UUID(), admission_no, SUM(paid_amount), trans_date, 
                    CASE WHEN voucher_type IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER') 
                         THEN voucher_type ELSE NULL END AS entry_mode, voucher_no
                FROM temp_import
                WHERE voucher_type IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER')
                GROUP BY display_receipt_no, admission_no, roll_no, trans_date
                LIMIT $batchSize OFFSET $offset
            ";
            if (!$conn->query($commonFeeCollectionQuery)) {
                throw new Exception("Error in common_fee_collection: " . $conn->error);
            }

            // Step 4: Insert into common_fee_collection_headwise
            $commonFeeCollectionHeadwiseQuery = "
                INSERT INTO common_fee_collection_headwise (receiptId, headId, headName, brid, amount)
                SELECT c.transid, 1, t.fee_category, 1, SUM(t.paid_amount)
                FROM temp_import t
                JOIN common_fee_collection c ON t.admission_no = c.admission_no AND t.voucher_no = c.display_receipt_no
                WHERE t.voucher_type IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER')
                GROUP BY c.transid, t.fee_category
                LIMIT $batchSize OFFSET $offset
            ";
            if (!$conn->query($commonFeeCollectionHeadwiseQuery)) {
                throw new Exception("Error in common_fee_collection_headwise: " . $conn->error);
            }

            $conn->commit();
            echo "Batch processed: $offset to " . ($offset + $batchSize) . "<br>";

            $offset += $batchSize;
            usleep(50000); // Small delay to reduce load

        } while ($conn->affected_rows > 0);

        echo "Data distribution completed successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        echo "Failed to distribute data: " . $e->getMessage();
    }
}

// Run with retries
$maxRetries = 3;
$attempt = 0;
while ($attempt < $maxRetries) {
    distributeData($conn);
    if ($conn->error) {
        $attempt++;
        echo "Retrying... Attempt $attempt of $maxRetries.<br>";
        sleep(1); // Wait for a second before retrying
    } else {
        break; // Exit the loop if successful
    }
}

$conn->close();
?>
