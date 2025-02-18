<!DOCTYPE html>
<html>
<head>
    <title>CSV Upload</title>
</head>
<body>
    <h2>Upload CSV File</h2>
    <form action="import.php" method="post" enctype="multipart/form-data">
        <input type="file" name="csv_file" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
