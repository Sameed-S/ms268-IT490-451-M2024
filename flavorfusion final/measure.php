<?php
include 'sessiontimeout.php';
include 'nav.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Measurements Conversion Page</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles/styles.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row">
        <!-- Liquid Measurements Card -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Liquid Measurements
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Measurement</th>
                                <th>Equivalent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1 fluid ounce</td>
                                <td>2 tablespoons</td>
                            </tr>
                            <tr>
                                <td>1 cup</td>
                                <td>8 fluid ounces</td>
                            </tr>
                            <tr>
                                <td>1 pint</td>
                                <td>2 cups or 16 fluid ounces</td>
                            </tr>
                            <tr>
                                <td>1 quart</td>
                                <td>2 pints or 32 fluid ounces</td>
                            </tr>
                            <tr>
                                <td>1 gallon</td>
                                <td>4 quarts or 128 fluid ounces</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dry Measurements Card -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    Dry Measurements
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Measurement</th>
                                <th>Equivalent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1 tablespoon</td>
                                <td>3 teaspoons</td>
                            </tr>
                            <tr>
                                <td>1/4 cup</td>
                                <td>4 tablespoons</td>
                            </tr>
                            <tr>
                                <td>1/3 cup</td>
                                <td>5 tablespoons + 1 teaspoon</td>
                            </tr>
                            <tr>
                                <td>1/2 cup</td>
                                <td>8 tablespoons</td>
                            </tr>
                            <tr>
                                <td>1 cup</td>
                                <td>16 tablespoons</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kgs to lbs Card -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    Kilograms to Pounds
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kilograms (kg)</th>
                                <th>Pounds (lbs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>0.5 kg</td>
                                <td>1.10231 lbs</td>
                            </tr>
                            <tr>
                                <td>1 kg</td>
                                <td>2.20462 lbs</td>
                            </tr>
                            <tr>
                                <td>1.5 kg</td>
                                <td>3.30693 lbs</td>
                            </tr>
                            <tr>
                                <td>2 kg</td>
                                <td>4.40925 lbs</td>
                            </tr>
                            <tr>
                                <td>2.5 kg</td>
                                <td>5.51156 lbs</td>
                            </tr>
                            <tr>
                                <td>5 kg</td>
                                <td>11.0231 lbs</td>
                            </tr>
                            <tr>
                                <td>10 kg</td>
                                <td>22.0462 lbs</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Calculator -->
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    Measurement Calculator
                </div>
                <div class="card-body">
                    <form id="conversionForm">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="category">Category</label>
                                <select class="form-control" id="category" required>
                                    <option value="">Choose...</option>
                                    <option value="liquid">Liquid Measurements</option>
                                    <option value="dry">Dry Measurements</option>
                                    <option value="weight">Kilograms to Pounds</option>
                                    <option value="weight1">Pounds to Kilograms </option>

                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="value">Value</label>
                                <input type="number" class="form-control" id="value" step="0.01" required>
                            </div>
                            <div class="form-group col-md-4 align-self-end">
                                <button type="submit" class="btn btn-primary btn-block">Convert</button>
                            </div>
                        </div>
                    </form>
                    <div id="result" class="mt-4"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    $('#conversionForm').on('submit', function (e) {
        e.preventDefault();
        
        const category = $('#category').val();
        const value = parseFloat($('#value').val());
        let result = '';

        if (category === 'liquid') {
            result += `${value} fluid ounces = ${(value * 2).toFixed(2)} tablespoons<br>`;
            result += `${value} cups = ${(value * 8).toFixed(2)} fluid ounces<br>`;
            result += `${value} pints = ${(value * 2).toFixed(2)} cups<br>`;
            result += `${value} quarts = ${(value * 2).toFixed(2)} pints<br>`;
            result += `${value} gallons = ${(value * 4).toFixed(2)} quarts`;
        } else if (category === 'dry') {
            result += `${value} tablespoons = ${(value * 3).toFixed(2)} teaspoons<br>`;
            result += `${value} cups = ${(value * 16).toFixed(2)} tablespoons`;
        } else if (category === 'weight') {
            result += `${value} kilograms = ${(value * 2.20462).toFixed(2)} pounds`;
        } else if (category === 'weight1') {
            result += `${value} pounds = ${(value / 2.20462).toFixed(2)} kilograms`;
        } else {
            result = 'Please select a category';
        }

        $('#result').html(`<div class="alert alert-info">${result}</div>`);
    });
</script>
</body>
</html>
