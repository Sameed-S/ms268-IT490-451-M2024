<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Flavor Fusion</title>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="home.php">
        <img src="styles/Flavor.png" alt="Flavor Fusion" style="height: 200px; width: auto;">
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <?php if (isset($_SESSION['email'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="adminpull.php">Admin Pull Data</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="adminedit.php">Admin Edit Data</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admincreate.php">Admin Create Data</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="favourite.php">Favorites</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="passwordchange.php">Change Email/Pass</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="randommeal.php">Random Meal</a>
                    </li>
                        <a class="nav-link" href="recipelist.php">Recipe List</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="measure.php">Measurement Conversion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
