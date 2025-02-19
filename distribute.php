<?php
include 'config.php'; // Include database connection

// Start the distribution process
function distributeData($conn) {
    try {
        // Begin transaction
        $conn->begin_transaction();

        echo "🔹 Starting data distribution...<br>";
        flush(); // Force immediate output

        // Insert into financial_trans for red entry modes
        echo "🔹 Inserting into financial_trans...<br>";
        flush();

        $financialTransQuery = "INSERT INTO financial_trans (
                                module_id, trans_id, admno, rollno, amount, br_id, academic_year, 
                                financial_year, entry_mode, voucher_no, type_of_concession
                            ) 
                            SELECT DISTINCT 
                                1 AS module_id,  
                                UUID_SHORT() AS trans_id, 
                                admission_no, 
                                roll_no, 
                                COALESCE(SUM(due_amount), 0) AS amount, 
                                1 AS br_id,  
                                '2023-2024' AS academic_year, 
                                '2023-2024' AS financial_year, 
                                1 AS entry_mode,   -- ✅ Corrected: Ensure entry_mode is correctly placed
                                voucher_no,        -- ✅ Corrected: voucher_no must be before type_of_concession
                                CASE 
                                    WHEN UPPER(voucher_type) = 'CONCESSION' THEN 1
                                    WHEN UPPER(voucher_type) = 'SCHOLARSHIP' THEN 2
                                    ELSE NULL 
                                END AS type_of_concession  
                            FROM temp_import 
                            WHERE UPPER(voucher_type) IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF') 
                            GROUP BY admission_no, roll_no, voucher_no
                            ORDER BY admission_no;
                            ";

        if (!$conn->query($financialTransQuery)) {
            throw new Exception("❌ Error inserting into financial_trans: " . $conn->error);
        }

        echo "✅ Data inserted into financial_trans.<br>";
        flush();

        // Insert into financial_tran_details
        echo "🔹 Inserting into financial_tran_details...<br>";
        flush();

        $financialTranDetailsQuery = "INSERT INTO financial_tran_details (financial_trans_id, module_id, amount, head_id, crdr, br_id, head_name)
            SELECT 
                f.id AS financial_trans_id, 
                1 AS module_id, 
                COALESCE(SUM(t.due_amount), 0) AS amount, 
                ft.id AS head_id, 
                'D' AS crdr, 
                1 AS br_id, 
                ft.f_name AS head_name
            FROM temp_import t
            JOIN financial_trans f ON t.admission_no = f.admno AND t.voucher_no = f.voucher_no
            JOIN feetypes ft ON t.fee_category = ft.f_name 
            WHERE UPPER(t.voucher_type) IN ('DUE', 'SCHOLARSHIP', 'CONCESSION', 'WRITE_OFF')
            GROUP BY f.id, ft.id
            ORDER BY f.id;";

        if (!$conn->query($financialTranDetailsQuery)) {
            throw new Exception("❌ Error inserting into financial_tran_details: " . $conn->error);
        }

        echo "✅ Data inserted into financial_tran_details.<br>";
        flush();

        // Insert into common_fee_collection
        echo "🔹 Inserting into common_fee_collection...<br>";
        flush();

        $commonFeeCollectionQuery = "INSERT INTO common_fee_collection (module_id, receipt_id, admno, rollno, amount, br_id, academic_year, financial_year, display_receipt_no, entry_mode, paid_date, inactive)
            SELECT DISTINCT 
                1 AS module_id,  
                UUID_SHORT() AS receipt_id, 
                admission_no, 
                roll_no, 
                COALESCE(SUM(paid_amount), 0) AS amount, 
                1 AS br_id,  
                '2023-2024' AS academic_year, 
                '2023-2024' AS financial_year, 
                voucher_no AS display_receipt_no, 
                1 AS entry_mode,  
                trans_date AS paid_date, 
                0 AS inactive
            FROM temp_import
            WHERE UPPER(voucher_type) IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER')
            GROUP BY admission_no, roll_no, voucher_no
            ORDER BY admission_no;";

        if (!$conn->query($commonFeeCollectionQuery)) {
            throw new Exception("❌ Error inserting into common_fee_collection: " . $conn->error);
        }

        echo "✅ Data inserted into common_fee_collection.<br>";
        flush();

        // Insert into common_fee_collection_headwise
        echo "🔹 Inserting into common_fee_collection_headwise...<br>";
        flush();

        $commonFeeCollectionHeadwiseQuery = "INSERT INTO common_fee_collection_headwise (module_id, receipt_id, head_id, head_name, br_id, amount)
            SELECT 
                1 AS module_id,  
                c.id AS receipt_id, 
                ft.id AS head_id,  
                ft.f_name AS head_name, 
                1 AS br_id,  
                COALESCE(SUM(t.paid_amount), 0) AS amount
            FROM temp_import t
            JOIN common_fee_collection c ON t.admission_no = c.admno AND t.voucher_no = c.display_receipt_no
            JOIN feetypes ft ON t.fee_category = ft.f_name
            WHERE UPPER(t.voucher_type) IN ('PAID', 'ADJUSTED', 'REFUND', 'FUND_TRANSFER')
            GROUP BY c.id, ft.id
            ORDER BY c.id;";

        if (!$conn->query($commonFeeCollectionHeadwiseQuery)) {
            throw new Exception("❌ Error inserting into common_fee_collection_headwise: " . $conn->error);
        }

        echo "✅ Data inserted into common_fee_collection_headwise.<br>";
        flush();

        // Commit transaction
        $conn->commit();
        echo "🎉 ✅ All data successfully distributed.<br>";
        flush();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "❌ Transaction failed: " . $e->getMessage() . "<br>";
        flush();
    }
}

// Call the function to distribute data
distributeData($conn);

// Close the database connection
$conn->close();
?>
