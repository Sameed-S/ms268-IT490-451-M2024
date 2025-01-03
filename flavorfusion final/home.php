<?php
session_start();

// Redirect to login page if user is not logged in
if (!isset($_SESSION['email'])) {
    $_SESSION['error'] = "You must be logged in to view this page.";
    header("Location: login.php");
    exit();
}

// Ensure role is set in session and it's admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $role = 'Admin';
} else {
    $role = 'User'; // Default role or handle other roles as needed
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <!-- Welcome Card -->
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>!</h1>
                        <p class="card-text">Your role: <?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link href="styles/styles.css" rel="stylesheet">

</body>
</html>
