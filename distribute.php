<?php
include 'config.php'; // Include your database connection

// Start the distribution process
function distributeData($conn) {
    // Start the distribution process
    try {
        // Begin transaction
        $conn->begin_transaction();

        // Insert into financial_trans for red entry modes
        $financialTransQuery = "INSERT INTO financial_trans (module_id, trans_id, admno, rollno, amount, br_id, academic_year, financial_year, entry_mode, voucher_no, type_of_concession) 
            SELECT DISTINCT 
                1 AS module_id,  
                UUID() AS trans_id, 
                admission_no, 
                roll_no, 
                SUM(due_amount) AS amount, 
                1 AS br_id,  
                '2023-2024' AS academic_year, 
                '2023-2024' AS financial_year, 
                1 AS entry_mode,  
                voucher_no, 
                NULL AS type_of_concession
            FROM temp_import 
            WHERE voucher_type IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF') 
            GROUP BY admission_no, roll_no, voucher_no;
        ";

        if ($conn->query($financialTransQuery) === TRUE) {
            echo "Data successfully inserted into financial_trans table.<br>";
        } else {
            throw new Exception("Error inserting into financial_trans: " . $conn->error);
        }

        // Insert into financial_tran_details for red entry modes
        $financialTranDetailsQuery = "INSERT INTO financial_tran_details (financial_trans_id, module_id, amount, head_id, crdr, br_id, head_name)
            SELECT 
                f.id AS financial_trans_id, 
                1 AS module_id, 
                SUM(t.due_amount) AS amount, 
                1 AS head_id, 
                'D' AS crdr, 
                1 AS br_id, 
                t.fee_category
            FROM temp_import t
            JOIN financial_trans f ON t.admission_no = f.admno AND t.voucher_no = f.voucher_no
            WHERE t.voucher_type IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF')
            GROUP BY f.id, t.fee_category;
        ";

        if ($conn->query($financialTranDetailsQuery) === TRUE) {
            echo "Data successfully inserted into financial_tran_details table.<br>";
        } else {
            throw new Exception("Error inserting into financial_tran_details: " . $conn->error);
        }

        // Insert into common_fee_collection for green entry modes
        $commonFeeCollectionQuery = "INSERT INTO common_fee_collection (module_id, receipt_id, admno, rollno, amount, br_id, academic_year, financial_year, display_receipt_no, entry_mode, paid_date, inactive)
            SELECT DISTINCT 
                1 AS module_id,  
                UUID() AS receipt_id, 
                admission_no, 
                roll_no, 
                SUM(paid_amount) AS amount, 
                1 AS br_id,  
                '2023-2024' AS academic_year, 
                '2023-2024' AS financial_year, 
                voucher_no AS display_receipt_no, 
                1 AS entry_mode,  
                trans_date AS paid_date, 
                0 AS inactive
            FROM temp_import
            WHERE voucher_type IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER')
            GROUP BY admission_no, roll_no, voucher_no;
        ";

        if ($conn->query($commonFeeCollectionQuery) === TRUE) {
            echo "Data successfully inserted into common_fee_collection table.<br>";
        } else {
            throw new Exception("Error inserting into common_fee_collection: " . $conn->error);
        }

        // Insert into common_fee_collection_headwise for green entry modes
        $commonFeeCollectionHeadwiseQuery = "INSERT INTO common_fee_collection_headwise (module_id, receipt_id, head_id, head_name, br_id, amount)
            SELECT 
                1 AS module_id,  
                c.id AS receipt_id, 
                1 AS head_id,  
                t.fee_category AS head_name, 
                1 AS br_id,  
                SUM(t.paid_amount) AS amount
            FROM temp_import t
            JOIN common_fee_collection c ON t.admission_no = c.admno AND t.voucher_no = c.display_receipt_no
            WHERE t.voucher_type IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER')
            GROUP BY c.id, t.fee_category;
        ";

        if ($conn->query($commonFeeCollectionHeadwiseQuery) === TRUE) {
            echo "Data successfully inserted into common_fee_collection_headwise table.<br>";
        } else {
            throw new Exception("Error inserting into common_fee_collection_headwise: " . $conn->error);
        }

        // Commit transaction
        $conn->commit();
        echo "All data successfully distributed.<br>";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "Transaction failed: " . $e->getMessage();
    }
}

// Call the function to distribute data
distributeData($conn);
?>