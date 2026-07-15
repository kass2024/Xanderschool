<!DOCTYPE html>
<html>
<head>
    <title>Edit Registration Number</title>
</head>
<body>
    <h2>Change Registration Number</h2>

    <form action="<?= base_url('/home/updateRegno/'.$student['id']) ?>" method="post">
        <label>New Registration Number:</label>
        <input type="text" name="regno" value="<?= $student['regno'] ?>" required>
        <br><br>
        <button type="submit">Save</button>
    </form>
</body>
</html>
