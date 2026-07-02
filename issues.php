<?php
// Start session and database connection
session_start();

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

// Initialize variables for filtering
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'recent';

// Build the query
$sql = "SELECT r.*, u.name as reporter_name FROM reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE 1=1";

// Apply filters
if (!empty($category_filter)) {
    $sql .= " AND r.category = '$category_filter'";
}

if (!empty($status_filter)) {
    $sql .= " AND r.status = '$status_filter'";
}

if (!empty($search_query)) {
    $sql .= " AND (r.title LIKE '%$search_query%' OR 
                   r.description LIKE '%$search_query%' OR 
                   r.location LIKE '%$search_query%' OR
                   u.name LIKE '%$search_query%')";
}

// Apply sorting
switch ($sort_by) {
    case 'oldest':
        $sql .= " ORDER BY r.created_at ASC";
        break;
    case 'priority':
        $sql .= " ORDER BY 
            CASE 
                WHEN r.priority = 'high' THEN 1
                WHEN r.priority = 'medium' THEN 2
                WHEN r.priority = 'low' THEN 3
                ELSE 4
            END,
            r.created_at DESC";
        break;
    case 'recent':
    default:
        $sql .= " ORDER BY r.created_at DESC";
        break;
}

// Execute query
$result = $conn->query($sql);

// Get statistics for filters
$category_stats = $conn->query("SELECT category, COUNT(*) as count FROM reports GROUP BY category");
$status_stats = $conn->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Issues</title>
    
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
        
        /* Navigation Bar - Green gradient matching worker_dashboard */
        .navbar {
            background: linear-gradient(135deg, #2c3e50, #28a745) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            padding: 12px 0;
        }
        
        .navbar-brand {
            font-size: 1.6rem;
            font-weight: bold;
            color: white !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .navbar-brand i {
            font-size: 1.8rem;
            margin-right: 10px;
            color: #ffd700;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
            font-weight: 600;
        }
        
        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        /* Dropdown Menu */
        .dropdown-menu {
            background: white;
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 10px;
        }
        
        .dropdown-item {
            color: #2c3e50 !important;
            border-radius: 5px;
            padding: 8px 15px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white !important;
            transform: translateX(5px);
        }
        
        /* Page Header - Green gradient */
        .page-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 80px 0 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .page-header h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }
        
        .page-header .btn-light {
            background: white;
            color: #28a745;
            border: 2px solid white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .page-header .btn-light:hover {
            background: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            padding: 25px;
            border-left: 6px solid #28a745;
        }
        
        .filter-card h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .filter-card h4 i {
            color: #28a745;
        }
        
        /* Filter Buttons */
        .filter-btn {
            margin: 3px;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-success {
            border: 2px solid #28a745;
            color: #28a745;
            background: white;
        }
        
        .btn-outline-success:hover {
            background: #28a745;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .active-filter {
            background: #28a745 !important;
            color: white !important;
        }
        
        /* Status Badges - EXACT SAME AS WORKER DASHBOARD */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { 
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in_progress { 
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed { 
            background: #d4edda;
            color: #155724;
        }
        
        /* Priority Badges */
        .priority-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }
        
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-low {
            background: #d4edda;
            color: #155724;
        }
        
        /* Category Badge */
        .category-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            background: #e9ecef;
            color: #2c3e50;
        }
        
        /* Issue Cards - Matching worker dashboard */
        .issue-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border-left: 6px solid #28a745;
            overflow: hidden;
            height: 100%;
        }
        
        .issue-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .issue-card h5 {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .issue-image {
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            width: 100%;
        }
        
        /* Action Buttons */
        .btn-outline-success {
            border: 2px solid #28a745;
            color: #28a745;
            background: white;
            padding: 5px 12px;
            font-size: 0.85rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .btn-outline-success:hover {
            background: #28a745;
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid #3498db;
            color: #3498db;
            background: white;
            padding: 5px 12px;
            font-size: 0.85rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .btn-outline-primary:hover {
            background: #3498db;
            color: white;
        }
        
        .btn-outline-warning {
            border: 2px solid #f39c12;
            color: #f39c12;
            background: white;
            padding: 5px 12px;
            font-size: 0.85rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .btn-outline-warning:hover {
            background: #f39c12;
            color: white;
        }
        
        /* Issue Actions */
        .issue-actions {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        /* Stats Badge */
        .stats-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
            background: #28a745;
            color: white;
            margin-left: 5px;
        }
        
        /* Form Controls - Matching worker dashboard */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 30px;
            padding: 10px 15px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 30px 0 0 30px;
            color: #28a745;
        }
        
        .input-group .form-control {
            border-radius: 0 30px 30px 0;
        }
        
        /* Pagination */
        .pagination {
            margin-top: 30px;
        }
        
        .page-link {
            color: #28a745;
            border: 2px solid #e9ecef;
            margin: 0 3px;
            border-radius: 30px !important;
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-color: #28a745;
            color: white;
        }
        
        .page-link:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-left: 6px solid #28a745;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #2c3e50;
            font-weight: 600;
        }
        
        /* Modal - Green theme */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 15px 25px;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Alert */
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
            border-radius: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            border-radius: 10px;
        }
        
        /* Footer */
        footer {
            background: #1e2b3a;
            color: white;
            padding: 60px 0 30px;
            margin-top: 60px;
            position: relative;
        }
        
        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #2c3e50, #28a745);
        }
        
        footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        footer a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        footer h4, footer h5 {
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        footer i {
            color: #28a745;
            width: 25px;
        }
        
        hr {
            opacity: 0.2;
        }
        
        /* Text Muted */
        .text-muted {
            color: #7f8c8d !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-card {
                padding: 15px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .issue-image {
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar - Green gradient matching worker_dashboard -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-trash-alt"></i> Swachh Bharat
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="issues.php">
                            <i class="fas fa-list"></i> Issues
                        </a>
                    </li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['user_type'] == 'citizen'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                        <?php elseif($_SESSION['user_type'] == 'worker'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="worker_dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="report.php">
                                <i class="fas fa-plus-circle"></i> Report Issue
                            </a>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo $_SESSION['user_name'] ?? 'User'; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header - Green gradient -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Reported Issues</h1>
                    <p class="lead mb-0">Browse, filter, and track all reported civic issues in your area</p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="report.php" class="btn btn-light btn-lg">
                            <i class="fas fa-plus-circle"></i> Report New Issue
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-light btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Login to Report
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Filters Section -->
        <div class="filter-card">
            <h4 class="mb-4"><i class="fas fa-filter me-2"></i>Filter Issues</h4>
            
            <!-- Search Bar -->
            <form method="GET" action="" class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search by title, description, location, or reporter..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if($search_query || $category_filter || $status_filter): ?>
                        <a href="issues.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Category Filters -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <h6 class="mb-2">Category</h6>
                    <div class="d-flex flex-wrap">
                        <a href="?category=" class="btn btn-sm <?php echo empty($category_filter) ? 'btn-success active-filter' : 'btn-outline-success'; ?> filter-btn">
                            All Categories
                            <span class="stats-badge">
                                <?php
                                $total_count = $conn->query("SELECT COUNT(*) as count FROM reports")->fetch_assoc()['count'];
                                echo $total_count;
                                ?>
                            </span>
                        </a>
                        
                        <?php if($category_stats && $category_stats->num_rows > 0): 
                            $category_stats->data_seek(0); // Reset pointer
                            while($cat = $category_stats->fetch_assoc()): ?>
                                <a href="?category=<?php echo $cat['category']; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="btn btn-sm <?php echo $category_filter == $cat['category'] ? 'btn-success active-filter' : 'btn-outline-success'; ?> filter-btn">
                                    <?php echo ucfirst($cat['category']); ?>
                                    <span class="stats-badge"><?php echo $cat['count']; ?></span>
                                </a>
                            <?php endwhile; 
                        endif; ?>
                    </div>
                </div>
                
                <!-- Status Filters -->
                <div class="col-md-6">
                    <h6 class="mb-2">Status</h6>
                    <div class="d-flex flex-wrap">
                        <a href="?status=" class="btn btn-sm <?php echo empty($status_filter) ? 'btn-success active-filter' : 'btn-outline-success'; ?> filter-btn">
                            All Status
                        </a>
                        
                        <?php if($status_stats && $status_stats->num_rows > 0): 
                            $status_stats->data_seek(0); // Reset pointer
                            while($stat = $status_stats->fetch_assoc()): 
                                $status_class = '';
                                if($stat['status'] == 'pending') $status_class = 'status-pending';
                                elseif($stat['status'] == 'in_progress') $status_class = 'status-in_progress';
                                else $status_class = 'status-completed';
                        ?>
                            <a href="?status=<?php echo $stat['status']; ?><?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                               class="btn btn-sm filter-btn <?php echo $status_filter == $stat['status'] ? 'btn-success active-filter' : 'btn-outline-success'; ?>" style="border-color: transparent;">
                                <?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?>
                                <span class="stats-badge"><?php echo $stat['count']; ?></span>
                            </a>
                        <?php endwhile; 
                        endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sort Options -->
            <div class="row">
                <div class="col-md-12">
                    <h6 class="mb-2">Sort By</h6>
                    <div class="d-flex flex-wrap">
                        <a href="?sort=recent<?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                           class="btn btn-sm <?php echo $sort_by == 'recent' ? 'btn-success active-filter' : 'btn-outline-success'; ?> filter-btn">
                            <i class="fas fa-clock"></i> Most Recent
                        </a>
                        <a href="?sort=oldest<?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                           class="btn btn-sm <?php echo $sort_by == 'oldest' ? 'btn-success active-filter' : 'btn-outline-success'; ?> filter-btn">
                            <i class="fas fa-history"></i> Oldest First
                        </a>
                        <a href="?sort=priority<?php echo !empty($category_filter) ? '&category=' . $category_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                           class="btn btn-sm <?php echo $sort_by == 'priority' ? 'btn-success active-filter' : 'btn-outline-success'; ?> filter-btn">
                            <i class="fas fa-exclamation-triangle"></i> Priority
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issues List -->
        <h4 class="mb-4">
            <?php 
            $total_issues = $result ? $result->num_rows : 0;
            echo "Showing " . $total_issues . " Issue" . ($total_issues != 1 ? 's' : '');
            ?>
        </h4>
        
        <?php if($result && $total_issues > 0): ?>
            <div class="row">
                <?php while($issue = $result->fetch_assoc()): 
                    // Determine status badge class
                    $status_class = '';
                    if($issue['status'] == 'pending') $status_class = 'status-pending';
                    elseif($issue['status'] == 'in_progress') $status_class = 'status-in_progress';
                    else $status_class = 'status-completed';
                    
                    // Determine priority badge class
                    $priority_class = '';
                    $priority_text = '';
                    if(isset($issue['priority'])) {
                        if($issue['priority'] == 'high') {
                            $priority_class = 'priority-high';
                            $priority_text = 'High';
                        } elseif($issue['priority'] == 'medium') {
                            $priority_class = 'priority-medium';
                            $priority_text = 'Medium';
                        } elseif($issue['priority'] == 'low') {
                            $priority_class = 'priority-low';
                            $priority_text = 'Low';
                        }
                    }
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="issue-card">
                            <div class="p-4">
                                <!-- Issue Header -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($issue['title']); ?></h5>
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="category-badge me-2"><?php echo ucfirst($issue['category']); ?></span>
                                            <?php if($priority_text): ?>
                                                <span class="priority-badge <?php echo $priority_class; ?>"><?php echo $priority_text; ?> Priority</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $issue['status'])); ?>
                                    </span>
                                </div>
                                
                                <!-- Issue Description -->
                                <p class="text-muted mb-3">
                                    <?php echo substr(htmlspecialchars($issue['description']), 0, 150); ?>
                                    <?php if(strlen($issue['description']) > 150): ?>...<?php endif; ?>
                                </p>
                                
                                <!-- Issue Details -->
                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1 text-success"></i>
                                                <?php echo htmlspecialchars($issue['location']); ?>
                                            </small>
                                        </div>
                                        <div class="col-6 text-end">
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1 text-success"></i>
                                                <?php echo date('d M Y', strtotime($issue['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Reporter Info -->
                                <?php if(isset($issue['reporter_name'])): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1 text-success"></i>
                                            Reported by: <?php echo htmlspecialchars($issue['reporter_name']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Issue Image (if exists) -->
                                <?php if(!empty($issue['image_path'])): ?>
                                    <div class="mb-3">
                                        <img src="<?php echo $issue['image_path']; ?>" alt="Issue Image" class="img-fluid issue-image w-100">
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Action Buttons -->
                                <div class="issue-actions">
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal<?php echo $issue['id']; ?>">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        
                                        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $issue['user_id']): ?>
                                            <a href="edit_issue.php?id=<?php echo $issue['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                                            <a href="admin/update_status.php?id=<?php echo $issue['id']; ?>" class="btn btn-outline-warning btn-sm">
                                                <i class="fas fa-cog"></i> Update Status
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal for Detailed View -->
                    <div class="modal fade" id="issueModal<?php echo $issue['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?php echo htmlspecialchars($issue['title']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="category-badge me-2"><?php echo ucfirst($issue['category']); ?></span>
                                                <?php if($priority_text): ?>
                                                    <span class="priority-badge <?php echo $priority_class; ?> me-2"><?php echo $priority_text; ?> Priority</span>
                                                <?php endif; ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $issue['status'])); ?>
                                                </span>
                                            </div>
                                            <p class="mb-2"><i class="fas fa-map-marker-alt text-success me-2"></i> <strong>Location:</strong> <?php echo htmlspecialchars($issue['location']); ?></p>
                                            <p class="mb-2"><i class="far fa-calendar-alt text-success me-2"></i> <strong>Reported on:</strong> <?php echo date('d M Y, h:i A', strtotime($issue['created_at'])); ?></p>
                                            <?php if(isset($issue['reporter_name'])): ?>
                                                <p class="mb-2"><i class="fas fa-user text-success me-2"></i> <strong>Reported by:</strong> <?php echo htmlspecialchars($issue['reporter_name']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <?php if(!empty($issue['image_path'])): ?>
                                                <img src="<?php echo $issue['image_path']; ?>" alt="Issue Image" class="img-fluid rounded">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="fw-bold">Description</h6>
                                        <p><?php echo nl2br(htmlspecialchars($issue['description'])); ?></p>
                                    </div>
                                    
                                    <?php if(!empty($issue['admin_notes'])): ?>
                                        <div class="mb-3">
                                            <h6 class="fw-bold"><i class="fas fa-clipboard-check text-success me-1"></i> Admin Notes</h6>
                                            <div class="alert alert-info">
                                                <?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if($issue['status'] == 'completed' && !empty($issue['completed_at'])): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            This issue was completed on <?php echo date('d M Y', strtotime($issue['completed_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $issue['user_id']): ?>
                                        <a href="edit_issue.php?id=<?php echo $issue['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit Issue
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination (You can implement actual pagination here) -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </ul>
            </nav>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4 class="mt-3">No Issues Found</h4>
                <p class="text-muted">No issues match your current filters. Try adjusting your search or filters.</p>
                <a href="issues.php" class="btn btn-success mt-3">
                    <i class="fas fa-redo"></i> Reset Filters
                </a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="report.php" class="btn btn-outline-success mt-3 ms-2">
                        <i class="fas fa-plus-circle"></i> Report First Issue
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-4">
                        <i class="fas fa-trash-alt"></i> Swachh Bharat
                    </h4>
                    <p class="text-white-50">A citizen-centric platform for reporting and resolving municipal issues efficiently.</p>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php"><i class="fas fa-home me-2"></i> Home</a></li>
                        <li class="mb-2"><a href="issues.php"><i class="fas fa-list me-2"></i> All Issues</a></li>
                        <li class="mb-2"><a href="report.php"><i class="fas fa-plus-circle me-2"></i> Report Issue</a></li>
                        <li class="mb-2"><a href="login.php"><i class="fas fa-sign-in-alt me-2"></i> Login</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5 class="mb-4">Need Help?</h5>
                    <p class="text-white-50"><i class="fas fa-phone me-2"></i> 1800-123-4567</p>
                    <p class="text-white-50"><i class="fas fa-envelope me-2"></i> help@swachhbharat.gov</p>
                </div>
            </div>
            
            <hr class="bg-light opacity-25 my-4">
            
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0 text-white-50">
                        &copy; <?php echo date('Y'); ?> Swachh Bharat Citizen Portal. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS (Keep your existing JavaScript) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to issue cards on hover
            const issueCards = document.querySelectorAll('.issue-card');
            issueCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Make filter buttons more interactive
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons in the same group
                    const parentDiv = this.parentElement;
                    if (parentDiv) {
                        parentDiv.querySelectorAll('.filter-btn').forEach(b => {
                            b.classList.remove('btn-success', 'active-filter');
                            b.classList.add('btn-outline-success');
                        });
                    }
                    
                    // Add active class to clicked button
                    this.classList.remove('btn-outline-success');
                    this.classList.add('btn-success', 'active-filter');
                });
            });
            
            // Auto-close dropdown after selection on mobile
            const dropdownItems = document.querySelectorAll('.dropdown-item');
            dropdownItems.forEach(item => {
                item.addEventListener('click', function() {
                    const navbarCollapse = document.querySelector('.navbar-collapse');
                    if(navbarCollapse && navbarCollapse.classList.contains('show')) {
                        const navbarToggler = document.querySelector('.navbar-toggler');
                        if (navbarToggler) {
                            navbarToggler.click();
                        }
                    }
                });
            });
            
            // Smooth scroll to top when clicking logo
            const logo = document.querySelector('.navbar-brand');
            if (logo) {
                logo.addEventListener('click', function(e) {
                    if(window.location.pathname.includes('issues.php')) {
                        e.preventDefault();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }
            
            // Show loading state when filtering
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if(submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after 10 seconds in case of error
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 10000);
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>