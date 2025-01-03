   <?php include 'nav.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form</title>
</head>
<body>
    <h2>Registration Form</h2>
    <form action="process.php" method="POST">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required maxlength="30">
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div>
            <label for="confirm">Confirm Password:</label>
            <input type="password" id="confirm" name="confirm" required minlength="8">
        </div>
        <div>
            <input type="submit" value="Register">
        </div>
    </form>
</body>
</html>
