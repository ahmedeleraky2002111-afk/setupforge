<?php
include "../db.php";
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name             = $_POST['name'];
    $email            = $_POST['email'];
    $password         = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone            = $_POST['phone'];
    $country          = $_POST['country'];
    $city             = $_POST['city'];
    $street           = $_POST['street'];

    $company_name     = $_POST['company_name'];
    $description      = $_POST['description'];
    $services         = $_POST['services'] ?? [];
    $base_price       = $_POST['base_price'];
    $starting_from    = $_POST['starting_from'];
    $company_size     = $_POST['company_size'];
    $established_year = $_POST['established_year'];
    $location         = $_POST['location'];
    $website          = $_POST['website'] ?? '';

    // Handle image upload
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename  = 'company_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/images/company/';
            $destPath  = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                $imagePath = 'assets/images/company/' . $filename;
            }
        }
    }

    // 1) Insert into users
    $resultUser = pg_query_params($conn, "
        INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status)
        VALUES ($1, $2, $3, 'company', $4, $5, $6, $7, 'active')
        RETURNING id
    ", [$name, $email, $password, $phone, $country, $city, $street]);

    if (!$resultUser) die("Error inserting user.");

    $user_id = pg_fetch_assoc($resultUser)['id'];

    // 2) Insert into companies
    $resultCompany = pg_query_params($conn, "
        INSERT INTO companies (user_id, company_name, description, services, base_price, starting_from, company_size, established_year, location, website, image, availability_status, status)
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, 'available', 'active')
    ", [
        $user_id,
        $company_name,
        $description,
        '{' . implode(',', $services) . '}',
        $base_price,
        $starting_from,
        $company_size,
        $established_year,
        $location,
        $website,
        $imagePath
    ]);

    if ($resultCompany) {
        header("Location: company_dashboard.php");
        exit();
    } else {
        echo "Error: " . pg_last_error($conn);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Company Signup - SetupForge</title>
    <style>
        body { font-family: Arial; background:#f5f7fa; }
        .box { width:480px; margin:50px auto; padding:28px; background:white; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
        h2 { margin-bottom:20px; color:#004cac; }
        label { font-size:.85rem; font-weight:700; color:#374151; display:block; margin:10px 0 4px; }
        input, textarea, select { width:100%; padding:10px; margin-bottom:4px; border:1px solid rgba(0,0,0,.14); border-radius:8px; font-size:.95rem; box-sizing:border-box; }
        .services-grid { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
        .services-grid label { font-weight:600; display:flex; align-items:center; gap:6px; margin:0; cursor:pointer; }
        .services-grid input { width:auto; margin:0; }
        button { background:#004cac; color:white; padding:12px; border:none; width:100%; border-radius:8px; font-weight:700; font-size:1rem; margin-top:16px; cursor:pointer; }
        button:hover { background:#003b86; }
        .section-title { font-size:.75rem; font-weight:800; color:#6b7280; text-transform:uppercase; letter-spacing:.08em; margin:18px 0 8px; border-top:1px solid rgba(0,0,0,.08); padding-top:14px; }
        .img-preview { width:100px; height:100px; border-radius:12px; object-fit:cover; margin-top:8px; display:none; border:1px solid rgba(0,0,0,.1); }
    </style>
</head>
<body>
<div class="box">
    <h2>Company Signup</h2>
    <form method="POST" enctype="multipart/form-data">

        <div class="section-title">Account Info</div>
        <label>Full Name</label>
        <input name="name" placeholder="Full Name" required>
        <label>Email</label>
        <input name="email" type="email" placeholder="Email" required>
        <label>Password</label>
        <input name="password" type="password" placeholder="Password" required>
        <label>Phone</label>
        <input name="phone" placeholder="Phone" required>
        <label>Country</label>
        <input name="country" placeholder="Country" required>
        <label>City</label>
        <input name="city" placeholder="City" required>
        <label>Street</label>
        <input name="street" placeholder="Street" required>

        <div class="section-title">Company Info</div>
        <label>Company Name</label>
        <input name="company_name" placeholder="Company Name" required>
        <label>Description</label>
        <textarea name="description" placeholder="Describe your company..." rows="3"></textarea>
        <label>Website</label>
        <input name="website" type="url" placeholder="https://yourcompany.com">
        <label>Location</label>
        <input name="location" placeholder="e.g. Cairo, Egypt" required>
        <label>Established Year</label>
        <input name="established_year" type="number" placeholder="e.g. 2010" required>
        <label>Company Size</label>
        <select name="company_size" required>
            <option value="">Select Size</option>
            <option value="small">Small</option>
            <option value="medium">Medium</option>
            <option value="large">Large</option>
        </select>

        <div class="section-title">Pricing</div>
        <label>Base Price (EGP)</label>
        <input name="base_price" type="number" step="0.01" placeholder="Base Price" required>
        <label>Starting From (EGP)</label>
        <input name="starting_from" type="number" placeholder="e.g. 4500" required>

        <div class="section-title">Services</div>
        <div class="services-grid">
            <label><input type="checkbox" name="services[]" value="pos"> POS</label>
            <label><input type="checkbox" name="services[]" value="electrical"> Electrical</label>
            <label><input type="checkbox" name="services[]" value="network"> Network</label>
            <label><input type="checkbox" name="services[]" value="ac"> AC</label>
            <label><input type="checkbox" name="services[]" value="kitchen"> Kitchen</label>
        </div>

        <div class="section-title">Company Image</div>
        <label>Upload Logo / Photo</label>
        <input type="file" name="image" accept="image/*" onchange="previewImg(this)">
        <img id="img-preview" class="img-preview">

        <button type="submit">Create Account</button>
    </form>
</div>

<script>
function previewImg(input) {
    const preview = document.getElementById('img-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>