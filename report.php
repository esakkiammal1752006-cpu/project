<?php
// Start session and database connection
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'citizen') {
    header("Location: login.php");
    exit();
}

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "swachh";

// Create connection
$conn = new mysqli("localhost", "root", "", "swachh", 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Initialize variables
$error = "";
$success = "";
$title = $description = $location = $category = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $location = $conn->real_escape_string($_POST['location']);
    $category = $conn->real_escape_string($_POST['category']);
    $priority = $conn->real_escape_string($_POST['priority']);
    
    // Validate input
    if (empty($title) || empty($description) || empty($location) || empty($category)) {
        $error = "Please fill in all required fields!";
    } else {
        // Generate unique report ID
        $report_id = "REP" . date('Ymd') . rand(1000, 9999);
        
        // Check if reports table has image column
        $table_check = $conn->query("SHOW COLUMNS FROM reports LIKE 'image'");
        $has_image_column = $table_check->num_rows > 0;
        
        // Handle file upload only if image column exists
        $image_file_name = "";
        if ($has_image_column && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = "uploads/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image_file_name = "report_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
                $target_file = $upload_dir . $image_file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    // File uploaded successfully
                }
            }
        }
        
        // Build SQL query based on available columns
        if ($has_image_column) {
            $sql = "INSERT INTO reports (report_id, user_id, title, description, location, category, priority, image, status, created_at) 
                    VALUES ('$report_id', '$user_id', '$title', '$description', '$location', '$category', '$priority', '$image_file_name', 'pending', NOW())";
        } else {
            $sql = "INSERT INTO reports (report_id, user_id, title, description, location, category, priority, status, created_at) 
                    VALUES ('$report_id', '$user_id', '$title', '$description', '$location', '$category', '$priority', 'pending', NOW())";
        }
        
        if ($conn->query($sql) === TRUE) {
            $success = "Report submitted successfully! Your report ID is: <strong>$report_id</strong>";
            // Clear form
            $title = $description = $location = $category = "";
        } else {
            $error = "Error submitting report: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Report Issue</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS - EXACT SAME THEME AS worker_dashboard.php (Green) -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar - EXACT SAME AS WORKER DASHBOARD (Green gradient) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #2c3e50, #28a745);
            color: white;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.5rem;
        }
        
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .nav-link i {
            width: 30px;
            font-size: 18px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Top Bar - EXACT SAME AS WORKER DASHBOARD */
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.4rem;
        }
        
        .top-bar h1 i {
            color: #28a745;
            margin-right: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info div {
            text-align: right;
        }
        
        .user-info strong {
            font-size: 15px;
            color: #1e2b3a;
            display: block;
        }
        
        .user-info .text-muted {
            font-size: 12px;
            color: #7f8c8d !important;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(45deg, #2c3e50, #28a745);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        
        /* Report Form Card - Matching worker dashboard */
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 20px 25px;
            position: relative;
            overflow: hidden;
        }
        
        .report-header::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .report-header h2 {
            font-weight: 600;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .report-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .report-body {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 12px;
            transition: all 0.3s;
            background: white;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            color: #28a745;
        }
        
        .category-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .category-options {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .category-option {
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .category-option:hover {
            border-color: #28a745;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(40, 167, 69, 0.1);
        }
        
        .category-option.selected {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
            color: #28a745;
        }
        
        .category-option i {
            font-size: 28px;
            margin-bottom: 10px;
            display: block;
            color: #28a745;
        }
        
        .category-option.selected i {
            color: #28a745;
        }
        
        .priority-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .priority-option {
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            font-weight: 600;
            font-size: 13px;
        }
        
        .priority-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.05);
        }
        
        .priority-option.low.selected {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .priority-option.medium.selected {
            background: #f39c12;
            border-color: #f39c12;
            color: white;
        }
        
        .priority-option.high.selected {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .priority-option i {
            margin-right: 5px;
        }
        
        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .file-upload:hover {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }
        
        .file-upload i {
            font-size: 40px;
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .file-upload h5 {
            font-size: 16px;
            color: #2c3e50;
        }
        
        #previewImage {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            font-size: 16px;
            border-radius: 8px;
            width: 100%;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        
        .btn-submit::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(30deg);
            transition: all 0.5s ease;
        }
        
        .btn-submit:hover::after {
            left: 140%;
        }
        
        /* Messages - EXACT SAME AS WORKER DASHBOARD */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
            animation: shake 0.5s;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            animation: slideDown 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .location-hint {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .location-hint i {
            color: #28a745;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .character-count {
            font-size: 12px;
            color: #7f8c8d;
            text-align: right;
            margin-top: 5px;
        }
        
        .image-note {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
            font-style: italic;
        }
        
        .image-note i {
            color: #28a745;
        }
        
        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #2c3e50;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link a:hover {
            color: #28a745;
        }
        
        .back-link a i {
            color: #28a745;
        }
        
        /* Alert Messages */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Buttons */
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s;
            color: white;
            font-size: 14px;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-outline-success {
            border: 2px solid #28a745;
            color: #28a745;
            padding: 8px 16px;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s;
            background: transparent;
        }
        
        .btn-outline-success:hover {
            background: #28a745;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Responsive - EXACT SAME AS WORKER DASHBOARD */
        @media (max-width: 1024px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3, .sidebar-header p, .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 12px;
                margin: 5px;
            }
            
            .nav-link i {
                width: auto;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .top-bar h1 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - EXACT SAME AS WORKER DASHBOARD (Green gradient) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-trash-alt"></i> Swachh Bharat</h3>
            <p>Citizen Portal</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="report.php" class="nav-link active">
                <i class="fas fa-plus-circle"></i>
                <span>Report Issue</span>
            </a>
            <a href="my_reports.php" class="nav-link">
                <i class="fas fa-list"></i>
                <span>My Reports</span>
            </a>
            <a href="rewards.php" class="nav-link">
                <i class="fas fa-award"></i>
                <span>Rewards</span>
            </a>
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar - EXACT SAME AS WORKER DASHBOARD -->
        <div class="top-bar">
            <h1><i class="fas fa-plus-circle"></i> Report an Issue</h1>
            <div class="user-info">
                <div>
                    <strong><?php echo htmlspecialchars($user_name); ?></strong>
                    <div class="text-muted">Citizen</div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Report Form -->
        <div class="report-card">
            <div class="report-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Help Keep Our City Clean</h2>
                <p>Report garbage, road damage, or other civic issues</p>
            </div>
            
            <div class="report-body">
                <?php if($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <div class="mt-3">
                            <a href="my_reports.php" class="btn btn-success btn-sm">View My Reports</a>
                            <a href="dashboard.php" class="btn btn-outline-success btn-sm ms-2">Go to Dashboard</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" id="reportForm">
                    <!-- Issue Title -->
                    <div class="form-group">
                        <label class="form-label">Issue Title *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-heading"></i></span>
                            <input type="text" class="form-control" name="title" 
                                   value="<?php echo htmlspecialchars($title); ?>" 
                                   placeholder="Brief description of the issue" 
                                   maxlength="100" 
                                   required>
                        </div>
                        <div class="character-count" id="titleCount">0/100 characters</div>
                    </div>
                    
                    <!-- Category Selection -->
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <div class="category-options">
                            <div class="category-option" data-category="garbage">
                                <i class="fas fa-trash"></i>
                                <div>Garbage</div>
                            </div>
                            <div class="category-option" data-category="roads">
                                <i class="fas fa-road"></i>
                                <div>Roads</div>
                            </div>
                            <div class="category-option" data-category="water">
                                <i class="fas fa-tint"></i>
                                <div>Water</div>
                            </div>
                            <div class="category-option" data-category="electricity">
                                <i class="fas fa-bolt"></i>
                                <div>Electricity</div>
                            </div>
                            <div class="category-option" data-category="parks">
                                <i class="fas fa-tree"></i>
                                <div>Parks</div>
                            </div>
                            <div class="category-option" data-category="other">
                                <i class="fas fa-question-circle"></i>
                                <div>Other</div>
                            </div>
                        </div>
                        <input type="hidden" name="category" id="categoryInput" value="" required>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label">Description *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                            <textarea class="form-control" name="description" 
                                      rows="4" 
                                      placeholder="Describe the issue in detail (what, where, when, how severe)"
                                      maxlength="500"
                                      required><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        <div class="character-count" id="descriptionCount">0/500 characters</div>
                    </div>
                    
                    <!-- Location -->
                    <div class="form-group">
                        <label class="form-label">Location *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" class="form-control" name="location" 
                                   id="locationInput"
                                   value="<?php echo htmlspecialchars($location); ?>" 
                                   placeholder="Street, Landmark, Area" 
                                   required>
                        </div>
                        <div class="location-hint">
                            <i class="fas fa-info-circle"></i> Be specific about the location (e.g., "Near Central Park, Main Road")
                        </div>
                    </div>
                    
                    <!-- Priority -->
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <div class="priority-options">
                            <div class="priority-option low" data-priority="low">
                                <i class="fas fa-arrow-down"></i> Low
                            </div>
                            <div class="priority-option medium" data-priority="medium">
                                <i class="fas fa-equals"></i> Medium
                            </div>
                            <div class="priority-option high" data-priority="high">
                                <i class="fas fa-arrow-up"></i> High
                            </div>
                        </div>
                        <input type="hidden" name="priority" id="priorityInput" value="medium">
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label class="form-label">Upload Photo (Optional)</label>
                        <div class="file-upload" id="fileUpload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h5>Click to upload photo</h5>
                            <p class="text-muted">Upload a clear photo of the issue (JPG, PNG, GIF)</p>
                            <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;">
                        </div>
                        <div class="image-note">
                            <i class="fas fa-info-circle"></i> Image upload may not be available in all systems
                        </div>
                        <img id="previewImage" alt="Image Preview">
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Report
                    </button>
                    
                    <div class="back-link">
                        <a href="dashboard.php">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS (Keep your existing JavaScript) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Category selection
            const categoryOptions = document.querySelectorAll('.category-option');
            const categoryInput = document.getElementById('categoryInput');
            
            categoryOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    categoryOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set hidden input value
                    categoryInput.value = this.getAttribute('data-category');
                });
            });
            
            // Auto-select first category by default
            if (!categoryInput.value) {
                document.querySelector('.category-option[data-category="garbage"]').click();
            }
            
            // Priority selection
            const priorityOptions = document.querySelectorAll('.priority-option');
            const priorityInput = document.getElementById('priorityInput');
            
            priorityOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    priorityOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set hidden input value
                    priorityInput.value = this.getAttribute('data-priority');
                });
            });
            
            // Auto-select medium priority by default
            document.querySelector('.priority-option[data-priority="medium"]').click();
            
            // File upload
            const fileUpload = document.getElementById('fileUpload');
            const imageInput = document.getElementById('imageInput');
            const previewImage = document.getElementById('previewImage');
            
            fileUpload.addEventListener('click', function() {
                imageInput.click();
            });
            
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewImage.style.display = 'block';
                        fileUpload.style.padding = '20px';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                    
                    // Update upload text
                    fileUpload.innerHTML = `
                        <i class="fas fa-check-circle text-success"></i>
                        <h5>Image Selected</h5>
                        <p class="text-muted">${this.files[0].name}</p>
                    `;
                }
            });
            
            // Character counters
            const titleInput = document.querySelector('input[name="title"]');
            const descriptionInput = document.querySelector('textarea[name="description"]');
            const titleCount = document.getElementById('titleCount');
            const descriptionCount = document.getElementById('descriptionCount');
            
            titleInput.addEventListener('input', function() {
                titleCount.textContent = `${this.value.length}/100 characters`;
            });
            
            descriptionInput.addEventListener('input', function() {
                descriptionCount.textContent = `${this.value.length}/500 characters`;
            });
            
            // Initialize character counts
            titleCount.textContent = `${titleInput.value.length}/100 characters`;
            descriptionCount.textContent = `${descriptionInput.value.length}/500 characters`;
            
            // Form validation
            document.getElementById('reportForm').addEventListener('submit', function(e) {
                if (!categoryInput.value) {
                    e.preventDefault();
                    alert('Please select a category');
                    return false;
                }
                
                if (!priorityInput.value) {
                    e.preventDefault();
                    alert('Please select a priority level');
                    return false;
                }
                
                return true;
            });
            
            // Auto-fill category if previously selected
            <?php if(!empty($category)): ?>
                document.querySelector(`.category-option[data-category="<?php echo $category; ?>"]`).click();
            <?php endif; ?>
            
            // Auto-fill priority if previously selected
            <?php if(!empty($_POST['priority'])): ?>
                document.querySelector(`.priority-option[data-priority="<?php echo $_POST['priority']; ?>"]`).click();
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>