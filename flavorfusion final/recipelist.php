<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Recipe List</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">


</head>
<body>
<?php
// Start or resume session
session_start();

// Check if user is logged in
if (isset($_SESSION['email'])) {
    include 'nav.php'; // Include navigation bar for logged-in users
} else {
    // Redirect to login page if user is not logged in
    $_SESSION['error'] = "You must be logged in to view this page.";
    header("Location: login.php");
    exit();
}
?>

<div class="container">
    <h2 class="my-4">Recipe List</h2>
    
    <!-- Search and Filter Inputs -->
    <div class="row mb-4">
        <div class="col-md-6">
            <label for="searchInput">Search:</label>
            <input type="text" class="form-control" id="searchInput" placeholder="Enter search term">
        </div>
        <div class="col-md-6">
            <button id="searchBtn" class="btn btn-primary mt-4">Search</button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <label for="originSelect">Origin:</label>
            <select id="originSelect" class="form-control">
                <option value="">All Origins</option>
                <option value="turkish">Turkish</option>
                <option value="american">American</option>
                <option value="british">British</option>
                <option value="malaysian">Malaysian</option>
                <option value="canadian">Canadian</option>
                <option value="indian">Indian</option>
                <option value="vietnamese">Vietnamese</option>
                <option value="filipino">Filipino</option>
                <option value="chinese">Chinese</option>
                <option value="russian">Russian</option>
                <option value="dutch">Dutch</option>
                <option value="ukrainian">Ukrainian</option>
                <option value="irish">Irish</option>
                <option value="mexican">Mexican</option>
                <option value="jamican">Jamican</option>
                <option value="tunisian">Tunisian</option>
                <option value="croatian">Croatian</option>
                <option value="italian">Italian</option>
                <option value="greek">Greek</option>
                <option value="japanese">Japanese</option>
                <option value="egyptian">Egyptian</option>
                <option value="portuguese">Portuguese</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="ingredientTypeSelect">Type of Category:</label>
            <select id="ingredientTypeSelect" class="form-control">
                <option value="">All Types</option>
                <option value="Beef">Beef</option>
                <option value="Chicken">Chicken</option>
                <option value="Pasta">Pasta</option>
                <option value="dessert">Dessert</option>
                <option value="Starter">Starter</option>
                <option value="seafood">Seafood</option>
                <option value="lamb">Lamb</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="sortSelect">Sort By:</label>
            <select id="sortSelect" class="form-control">
                <option value="name_asc">Name (Ascending)</option>
                <option value="name_desc">Name (Descending)</option>
                <option value="origin_asc">Origin (Ascending)</option>
                <option value="origin_desc">Origin (Descending)</option>
                <option value="likes_desc">Likes (Ascending)</option>
                <option value="likes_asc">Likes (Descending)</option>
            </select>
        </div>
    </div>

    <!-- Recipe Cards -->
    <div class="row" id="recipeList">
    </div>

    <!-- Pagination Controls -->
    <nav aria-label="Page navigation">
        <ul class="pagination" id="paginationControls">
        </ul>
    </nav>
</div>

<script>
    let currentPage = 1;
    const itemsPerPage = 10; 
    document.addEventListener('DOMContentLoaded', function() {
        fetchRecipeData(); // Fetch initial data when page loads

        // Event listener for search button
        document.getElementById('searchBtn').addEventListener('click', function() {
            fetchRecipeData(); // Fetch data on search button click
        });

        // Event listener for search input on Enter key press
        document.getElementById('searchInput').addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                fetchRecipeData(); // Fetch data on Enter key press in search input
            }
        });

        // Event listener for filter and sort changes
        document.getElementById('originSelect').addEventListener('change', fetchRecipeData);
        document.getElementById('ingredientTypeSelect').addEventListener('change', fetchRecipeData);
        document.getElementById('sortSelect').addEventListener('change', fetchRecipeData);

        // Event delegation for dynamically loaded content
        document.getElementById('recipeList').addEventListener('click', function(event) {
            const recipeCard = event.target.closest('.recipe-card');
            if (recipeCard) {
                const recipeId = recipeCard.dataset.recipeId;
                window.location.href = `recipedetails.php?id=${recipeId}`; // Adjust URL as per your backend setup
            }
        });
    });

    // Function to fetch recipe data based on search term, filter, and sort
    function fetchRecipeData() {
        const searchInput = document.getElementById('searchInput').value.trim();
        const origin = document.getElementById('originSelect').value;
        const ingredientType = document.getElementById('ingredientTypeSelect').value;
        const sort = document.getElementById('sortSelect').value;

        fetch('fetch_data.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'fetch_data',
                search: searchInput,
                origin: origin,
                ingredientType: ingredientType,
                sort: sort,
                page: currentPage,
                perPage: itemsPerPage
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            let recipeListDiv = document.getElementById('recipeList');
            recipeListDiv.innerHTML = ''; // Clear previous data

            if (data.error) {
                recipeListDiv.innerHTML = `<div class="col-12"><p class="alert alert-danger">Error fetching data: ${data.error}</p></div>`;
            } else {
                data.recipes.forEach(recipe => {
                    let card = `
                        <div class="col-md-4">
                            <div class="card recipe-card" data-recipe-id="${recipe.idMeal}">
                                <a href="#" class="card-link">
                                    <img src="${recipe.strMealThumb}" class="card-img-top" alt="${recipe.strMeal}">
                                    <div class="card-body">
                                        <h5 class="card-title">${recipe.strMeal}</h5>
                                        <p class="card-text">${recipe.strCategory}</p>
                                        <p class="card-text">${recipe.strArea}</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    `;
                    recipeListDiv.innerHTML += card;
                });
                renderPagination(data.totalPages);
            }
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            let recipeListDiv = document.getElementById('recipeList');
            recipeListDiv.innerHTML = `<div class="col-12"><p class="alert alert-danger">Error fetching data. Please try again later.</p></div>`;
        });
    }

    function renderPagination(totalPages) {
        const paginationControls = document.getElementById('paginationControls');
        paginationControls.innerHTML = ''; // Clear previous pagination

        for (let i = 1; i <= totalPages; i++) {
            const button = document.createElement('li');
            button.className = 'page-item';
            button.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            button.addEventListener('click', function(event) {
                event.preventDefault();
                currentPage = i;
                fetchRecipeData();
            });
            paginationControls.appendChild(button);
        }
    }
</script>
<link href="styles/styles.css" rel="stylesheet">

</body>
</html>
