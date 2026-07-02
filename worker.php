<?php
// Start session and database connection
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in as worker
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'worker') {
    header("Location: login.php");
    exit();
}

// Database configuration
$conn = new mysqli("localhost", "root", "", "swachh", 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$worker_id = $_SESSION['user_id'];
$worker_name = $_SESSION['user_name'];

// ✅ CREATE report_updates table if not exists (RUN THIS ONCE)
$conn->query("CREATE TABLE IF NOT EXISTS report_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    user_id INT NOT NULL,
    update_text TEXT,
    status_change VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Handle status update
if (isset($_POST['update_status'])) {
    $report_id = $conn->real_escape_string($_POST['report_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $update_text = $conn->real_escape_string($_POST['update_text']);
    
    // Check if this report is assigned to this worker
    $check_sql = "SELECT * FROM reports WHERE id = '$report_id' AND assigned_to = '$worker_id'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        // ✅ UPDATE reports table
        $update_sql = "UPDATE reports SET status = '$status' WHERE id = '$report_id'";
        
        if ($conn->query($update_sql)) {
            // ✅ ALWAYS INSERT INTO report_updates (with or without text)
            $insert_sql = "INSERT INTO report_updates (report_id, user_id, update_text, status_change, created_at) 
                           VALUES ('$report_id', '$worker_id', '$update_text', '$status', NOW())";
            
            if ($conn->query($insert_sql)) {
                // Successfully inserted into report_updates
                error_log("Inserted into report_updates: Report ID $report_id, Status: $status");
            } else {
                error_log("Error inserting into report_updates: " . $conn->error);
            }
            
            // ✅ Add rewards points to citizen when completed
            if ($status == 'completed') {
                $report_query = $conn->query("SELECT user_id FROM reports WHERE id='$report_id'");
                if ($report_query && $report_query->num_rows > 0) {
                    $report = $report_query->fetch_assoc();
                    $citizen_id = $report['user_id'];
                    
                    // Check if rewards_points column exists
                    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'rewards_points'");
                    if ($column_check && $column_check->num_rows > 0) {
                        $conn->query("UPDATE users SET rewards_points = IFNULL(rewards_points, 0) + 25 WHERE id = '$citizen_id'");
                    }
                    
                    // ✅ Insert into rewards table
                    $rewards_check = $conn->query("SHOW TABLES LIKE 'rewards'");
                    if ($rewards_check && $rewards_check->num_rows > 0) {
                        $conn->query("INSERT INTO rewards (user_id, points, reason, created_at) 
                                      VALUES ('$citizen_id', 25, 'Report #$report_id completed', NOW())");
                    }
                }
            }
            
            $success = "Status updated successfully!";
        } else {
            $error = "Error updating status: " . $conn->error;
        }
    } else {
        $error = "You are not authorized to update this report!";
    }
}

// Get worker's assigned reports
$assigned_reports = $conn->query("
    SELECT r.*, 
           u.name as citizen_name, 
           u.phone as citizen_phone
    FROM reports r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.assigned_to = '$worker_id'
    ORDER BY 
        CASE 
            WHEN r.priority = 'high' THEN 1
            WHEN r.priority = 'medium' THEN 2
            WHEN r.priority = 'low' THEN 3
            ELSE 4
        END,
        r.created_at DESC
");

// Get statistics
$total_assigned = 0;
$in_progress = 0;
$completed = 0;
$pending = 0;

$count_query = "SELECT COUNT(*) as total FROM reports WHERE assigned_to = '$worker_id'";
$count_result = $conn->query($count_query);
if ($count_result && $count_result->num_rows > 0) {
    $total_assigned = $count_result->fetch_assoc()['total'];
}

$progress_query = "SELECT COUNT(*) as total FROM reports WHERE assigned_to='$worker_id' AND status='in_progress'";
$progress_result = $conn->query($progress_query);
if ($progress_result && $progress_result->num_rows > 0) {
    $in_progress = $progress_result->fetch_assoc()['total'];
}

$completed_query = "SELECT COUNT(*) as total FROM reports WHERE assigned_to='$worker_id' AND status='completed'";
$completed_result = $conn->query($completed_query);
if ($completed_result && $completed_result->num_rows > 0) {
    $completed = $completed_result->fetch_assoc()['total'];
}

$pending_query = "SELECT COUNT(*) as total FROM reports WHERE assigned_to='$worker_id' AND status='pending'";
$pending_result = $conn->query($pending_query);
if ($pending_result && $pending_result->num_rows > 0) {
    $pending = $pending_result->fetch_assoc()['total'];
}

// Get notifications
$notifications = null;
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check && $table_check->num_rows > 0) {
    $notifications = $conn->query("SELECT * FROM notifications WHERE user_id='$worker_id' ORDER BY created_at DESC LIMIT 5");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Worker Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(180deg, #2c3e50, #28a745); color: white; z-index: 1000; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .sidebar-header h3 { font-weight: bold; }
        .sidebar-menu { padding: 20px 0; }
        .nav-link { color: rgba(255,255,255,0.9); padding: 12px 20px; margin: 5px 15px; border-radius: 8px; display: flex; align-items: center; }
        .nav-link i { width: 30px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .worker-avatar { width: 45px; height: 45px; background: linear-gradient(45deg, #2c3e50, #28a745); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px 20px; display: flex; align-items: center; justify-content: space-between; border-left: 6px solid; height: 130px; }
        .stat-card.total { border-left-color: #2c3e50; }
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.progress { border-left-color: #3498db; }
        .stat-card.completed { border-left-color: #27ae60; }
        .stat-info h3 { font-size: 36px; font-weight: 800; margin: 0; }
        .stat-card.total .stat-info h3 { color: #2c3e50; }
        .stat-card.pending .stat-info h3 { color: #e67e22; }
        .stat-card.progress .stat-info h3 { color: #2980b9; }
        .stat-card.completed .stat-info h3 { color: #27ae60; }
        .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; }
        .stat-card.total .stat-icon { background: #2c3e50; color: white; }
        .stat-card.pending .stat-icon { background: #f39c12; color: white; }
        .stat-card.progress .stat-icon { background: #3498db; color: white; }
        .stat-card.completed .stat-icon { background: #27ae60; color: white; }
        .notifications-panel, .reports-section { background: white; border-radius: 10px; margin-bottom: 25px; overflow: hidden; }
        .panel-header, .section-header { background: linear-gradient(135deg, #2c3e50, #28a745); color: white; padding: 15px 20px; }
        .notification-item { padding: 15px 20px; border-bottom: 1px solid #e9ecef; }
        .reports-grid { padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .report-card { border: 2px solid #e9ecef; border-radius: 10px; overflow: hidden; transition: all 0.3s; background: white; }
        .report-card:hover { border-color: #28a745; transform: translateY(-3px); }
        .report-header { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #e9ecef; display: flex; justify-content: space-between; }
        .report-id { font-family: monospace; font-weight: bold; color: #28a745; }
        .report-body { padding: 15px; }
        .report-title { font-weight: 600; margin-bottom: 10px; }
        .detail-item { margin-bottom: 8px; display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .priority-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .priority-high { background: #ffebee; color: #ef5350; }
        .priority-medium { background: #fff3e0; color: #f57c00; }
        .priority-low { background: #e8f5e9; color: #388e3c; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #ffebee; color: #ef5350; }
        .status-in_progress { background: #e3f2fd; color: #1976d2; }
        .status-completed { background: #e8f5e9; color: #388e3c; }
        .report-footer { background: #f8f9fa; padding: 15px; border-top: 2px solid #e9ecef; }
        .update-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .alert { border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 50px 20px; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h3, .nav-link span { display: none; } .main-content { margin-left: 70px; } .stats-grid { grid-template-columns: 1fr 1fr; } .reports-grid { grid-template-columns: 1fr; } }
        @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-tools"></i> Worker</h3><p>Municipal Corporation</p></div>
        <div class="sidebar-menu">
            <a href="worker_dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="worker_tasks.php" class="nav-link"><i class="fas fa-tasks"></i><span>My Tasks</span></a>
            <a href="worker_history.php" class="nav-link"><i class="fas fa-history"></i><span>History</span></a>
            <a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i><span>Profile</span></a>
            <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h4><i class="fas fa-tachometer-alt"></i> Worker Dashboard</h4>
            <div><strong><?php echo htmlspecialchars($worker_name); ?></strong><div class="text-muted">Municipal Worker</div><div class="worker-avatar"><?php echo strtoupper(substr($worker_name, 0, 1)); ?></div></div>
        </div>
        
        <?php if(isset($success)): ?><div class="alert alert-success">✅ <?php echo $success; ?></div><?php endif; ?>
        <?php if(isset($error)): ?><div class="alert alert-danger">❌ <?php echo $error; ?></div><?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card total"><div class="stat-info"><h3><?php echo $total_assigned; ?></h3><p>Total Tasks</p></div><div class="stat-icon"><i class="fas fa-clipboard-list"></i></div></div>
            <div class="stat-card pending"><div class="stat-info"><h3><?php echo $pending; ?></h3><p>Pending</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
            <div class="stat-card progress"><div class="stat-info"><h3><?php echo $in_progress; ?></h3><p>In Progress</p></div><div class="stat-icon"><i class="fas fa-tasks"></i></div></div>
            <div class="stat-card completed"><div class="stat-info"><h3><?php echo $completed; ?></h3><p>Completed</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
        </div>
        
        <?php if ($notifications && $notifications->num_rows > 0): ?>
        <div class="notifications-panel"><div class="panel-header"><h5><i class="fas fa-bell"></i> Notifications</h5></div>
            <?php while($note = $notifications->fetch_assoc()): ?>
            <div class="notification-item"><div class="notification-title"><?php echo htmlspecialchars($note['title']); ?></div><div class="notification-time"><i class="far fa-clock"></i> <?php echo date('d M Y H:i', strtotime($note['created_at'])); ?></div></div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <div class="reports-section"><div class="section-header"><h5><i class="fas fa-tasks"></i> My Assigned Tasks</h5></div>
            <div class="reports-grid">
                <?php if ($assigned_reports && $assigned_reports->num_rows > 0): while($report = $assigned_reports->fetch_assoc()): ?>
                <div class="report-card">
                    <div class="report-header"><span class="report-id">#<?php echo $report['id']; ?></span><span class="priority-badge priority-<?php echo $report['priority']; ?>"><?php echo ucfirst($report['priority']); ?> Priority</span></div>
                    <div class="report-body">
                        <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                        <div class="detail-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($report['citizen_name'] ?? 'Unknown'); ?></div>
                        <div class="detail-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location']); ?></div>
                        <div class="detail-item"><i class="fas fa-tag"></i> Category: <?php echo ucfirst($report['category']); ?></div>
                        <div class="mb-2 mt-2"><span class="status-badge status-<?php echo $report['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?></span></div>
                        <div class="text-muted"><i class="far fa-calendar"></i> Reported: <?php echo date('d M Y', strtotime($report['created_at'])); ?></div>
                    </div>
                    <div class="report-footer">
                        <form method="POST">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <div class="update-form">
                                <select class="form-select form-select-sm" name="status" required style="width: 120px;">
                                    <option value="pending" <?php echo $report['status']=='pending'?'selected':''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $report['status']=='in_progress'?'selected':''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $report['status']=='completed'?'selected':''; ?>>Completed</option>
                                </select>
                                <input type="text" class="form-control form-control-sm" name="update_text" placeholder="Update note" style="flex: 1;">
                                <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="empty-state"><i class="fas fa-check-circle fa-3x text-muted"></i><h5>No Tasks Assigned</h5><p>You don't have any pending tasks at the moment.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>setTimeout(()=>{document.querySelectorAll('.alert').forEach(a=>{a.style.opacity='0';setTimeout(()=>a.remove(),500)})},5000);</script>
</body>
</html>