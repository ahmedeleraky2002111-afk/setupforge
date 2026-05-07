<?php
include "../db.php";
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $country = $_POST['country'];
    $city = $_POST['city'];
    $street = $_POST['street'];

    $company_name = $_POST['company_name'];
    $description = $_POST['description'];
    $services = $_POST['services'] ?? [];
    $base_price = $_POST['base_price'];
    $company_size = $_POST['company_size'];
    $established_year = $_POST['established_year'];
    $location = $_POST['location'];

    // 1) Insert into users table
    $queryUser = "
        INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status)
        VALUES ($1, $2, $3, 'company', $4, $5, $6, $7, 'active')
        RETURNING id
    ";

    $resultUser = pg_query_params($conn, $queryUser, [
        $name, $email, $password, $phone, $country, $city, $street
    ]);

    if (!$resultUser) {
        die("Error inserting user.");
    }

    $userRow = pg_fetch_assoc($resultUser);
    $user_id = $userRow['id'];

    // 2) Insert into companies table
    $queryCompany = "
        INSERT INTO companies (user_id, company_name, description, services, base_price, company_size, established_year, location)
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
    ";

    $resultCompany = pg_query_params($conn, $queryCompany, [
$user_id, $company_name, $description, '{' . implode(',', $services) . '}', 
$base_price, $company_size, $established_year, $location    ]);

    if ($resultCompany) {
        header("Location: company_dashboard.php");
        exit();
    } else {
        echo "❌ Error inserting company.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Company Signup - SetupForge</title>
    <style>
        body { font-family: Arial; background:#f5f7fa; }
        .box { width:400px; margin:50px auto; padding:20px; background:white; border-radius:10px; }
        input, textarea, select { width:100%; padding:10px; margin:8px 0; }
        button { background:#004cac; color:white; padding:10px; border:none; width:100%; }
    </style>
</head>
<body>

<div class="box">
    <h2>Company Signup</h2>
    <form method="POST">
        <input name="name" placeholder="Full Name" required>
        <input name="email" type="email" placeholder="Email" required>
        <input name="password" type="password" placeholder="Password" required>
        <input name="phone" placeholder="Phone" required>
        <input name="country" placeholder="Country" required>
        <input name="city" placeholder="City" required>
        <input name="street" placeholder="Street" required>

        <input name="company_name" placeholder="Company Name" required>
        <textarea name="description" placeholder="Description"></textarea>
        
        <label>Services:</label><br>
        <input type="checkbox" name="services[]" value="pos"> POS<br>
        <input type="checkbox" name="services[]" value="electrical"> Electrical<br>
        <input type="checkbox" name="services[]" value="network"> Network<br>
        <input type="checkbox" name="services[]" value="ac"> AC<br>
        
        <input name="base_price" type="number" step="0.01" placeholder="Base Price" required>
        
        <select name="company_size" required>
            <option value="">Select Size</option>
            <option value="small">Small</option>
            <option value="medium">Medium</option>
            <option value="large">Large</option>
        </select>
        
        <input name="established_year" type="number" placeholder="Established Year" required>
        <input name="location" placeholder="Location" required>

        <button type="submit">Create Account</button>
    </form>
</div>

</body>
</html>