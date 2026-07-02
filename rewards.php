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
$points = 0;
$badges = [];
$rewards_history = [];
$all_badges = [];
$next_badge = null;

// Define badges (hardcoded - no database needed)
$all_badges = [
    ['id' => 1, 'badge_name' => 'First Report', 'description' => 'Submitted your first report', 'icon' => 'fa-star', 'points_required' => 1],
    ['id' => 2, 'badge_name' => 'Clean Streak', 'description' => 'Submitted 5+ reports', 'icon' => 'fa-fire', 'points_required' => 5],
    ['id' => 3, 'badge_name' => 'Community Hero', 'description' => 'Had 10+ reports completed', 'icon' => 'fa-heart', 'points_required' => 10],
    ['id' => 4, 'badge_name' => 'Clean Champion', 'description' => 'Earned 500+ points', 'icon' => 'fa-trophy', 'points_required' => 50],
    ['id' => 5, 'badge_name' => 'Super Contributor', 'description' => 'Submitted 20+ reports', 'icon' => 'fa-crown', 'points_required' => 20]
];

// Get user's reports data
$reports_sql = "SELECT * FROM reports WHERE user_id = '$user_id'";
$reports_result = $conn->query($reports_sql);

$total_reports = 0;
$completed_reports = 0;
$pending_reports = 0;
$in_progress_reports = 0;

if ($reports_result && $reports_result->num_rows > 0) {
    $total_reports = $reports_result->num_rows;
    $reports_result->data_seek(0); // Reset pointer
    
    while($row = $reports_result->fetch_assoc()) {
        // Count based on actual database status - using 'completed'
        if ($row['status'] == 'completed') {
            $completed_reports++;
        } elseif ($row['status'] == 'pending') {
            $pending_reports++;
        } elseif ($row['status'] == 'in_progress') {
            $in_progress_reports++;
        }
    }
}

// Calculate points based on reports (10 points per report + 25 per completed report)
$points = ($total_reports * 10) + ($completed_reports * 25);

// Determine earned badges based on user's activity
if ($total_reports >= 1) {
    $badges[] = $all_badges[0]; // First Report
}
if ($total_reports >= 5) {
    $badges[] = $all_badges[1]; // Clean Streak
}
if ($completed_reports >= 10) {
    $badges[] = $all_badges[2]; // Community Hero
}
if ($points >= 500) {
    $badges[] = $all_badges[3]; // Clean Champion
}
if ($total_reports >= 20) {
    $badges[] = $all_badges[4]; // Super Contributor
}

// Create rewards history from reports
$history_sql = "SELECT * FROM reports WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 10";
$history_result = $conn->query($history_sql);
if ($history_result && $history_result->num_rows > 0) {
    while($row = $history_result->fetch_assoc()) {
        $action_type = 'report_submission';
        $action_desc = 'Submitted report: ' . $row['title'];
        $points_earned = 10;
        
        if ($row['status'] == 'completed') {
            $action_type = 'report_completed';
            $action_desc = 'Report completed: ' . $row['title'];
            $points_earned = 25;
        } elseif ($row['priority'] == 'high') {
            $action_type = 'high_priority';
            $action_desc = 'High priority report: ' . $row['title'];
            $points_earned = 15;
        }
        
        $rewards_history[] = [
            'action_type' => $action_type,
            'action_description' => $action_desc,
            'points_earned' => $points_earned,
            'earned_at' => $row['created_at']
        ];
    }
}

// Calculate next badge progress
foreach ($all_badges as $badge) {
    $has_badge = false;
    foreach ($badges as $user_badge) {
        if ($user_badge['id'] == $badge['id']) {
            $has_badge = true;
            break;
        }
    }
    if (!$has_badge) {
        $next_badge = $badge;
        break;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh Bharat - Rewards</title>
    
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
        
        /* Points Display - Green theme matching worker dashboard */
        .points-container {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .points-container::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .points-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        
        .points-main {
            text-align: center;
        }
        
        .points-number {
            font-size: 72px;
            font-weight: 800;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .points-label {
            font-size: 24px;
            opacity: 0.9;
            font-weight: 600;
        }
        
        .points-details {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .points-detail-item {
            text-align: center;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            min-width: 120px;
        }
        
        .detail-number {
            font-size: 36px;
            font-weight: 800;
            display: block;
            line-height: 1.2;
        }
        
        .detail-label {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 600;
        }
        
        /* Progress Bar Section - Matching worker dashboard */
        .progress-container {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-left: 6px solid #28a745;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .progress-header h4 {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .progress-header strong {
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .progress-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 10px;
            transition: width 1s ease-in-out;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        /* History Section - Matching worker dashboard */
        .history-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #2c3e50, #28a745);
            color: white;
            padding: 15px 20px;
        }
        
        .section-header h2 {
            font-weight: 600;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .section-header h2 i {
            margin-right: 10px;
        }
        
        .section-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .section-body {
            padding: 20px;
        }
        
        .history-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s;
        }
        
        .history-item:hover {
            background: #f8f9fa;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-icon {
            width: 45px;
            height: 45px;
            background: #e8f5e9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #28a745;
            font-size: 20px;
        }
        
        .history-details {
            flex-grow: 1;
        }
        
        .history-action {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
            font-size: 15px;
        }
        
        .history-date {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .history-points {
            font-weight: 700;
            color: #28a745;
            font-size: 20px;
            background: #e8f5e9;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        /* Alert Messages - EXACT SAME AS WORKER DASHBOARD */
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
            border-radius: 8px;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #7f8c8d;
        }
        
        /* Text Muted */
        .text-muted {
            color: #7f8c8d !important;
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
            
            .points-display {
                flex-direction: column;
                text-align: center;
            }
            
            .points-details {
                justify-content: center;
            }
            
            .history-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .history-icon {
                margin-right: 0;
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
            <a href="report.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>
                <span>Report Issue</span>
            </a>
            <a href="my_reports.php" class="nav-link">
                <i class="fas fa-list"></i>
                <span>My Reports</span>
            </a>
            <a href="rewards.php" class="nav-link active">
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
            <h1><i class="fas fa-award"></i> Rewards & Recognition</h1>
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
        
        <!-- Points Display - Green theme matching worker dashboard -->
        <div class="points-container">
            <div class="points-display">
                <div class="points-main">
                    <div class="points-number"><?php echo $points; ?></div>
                    <div class="points-label">SWACHH POINTS</div>
                </div>
                
                <div class="points-details">
                    <div class="points-detail-item">
                        <span class="detail-number"><?php echo count($badges); ?></span>
                        <span class="detail-label">BADGES</span>
                    </div>
                    <div class="points-detail-item">
                        <span class="detail-number"><?php echo $total_reports; ?></span>
                        <span class="detail-label">REPORTS</span>
                    </div>
                    <div class="points-detail-item">
                        <span class="detail-number"><?php echo $completed_reports; ?></span>
                        <span class="detail-label">COMPLETED</span>
                    </div>
                    <div class="points-detail-item">
                        <span class="detail-number"><?php echo $in_progress_reports; ?></span>
                        <span class="detail-label">IN PROGRESS</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Progress to Next Badge -->
        <?php if ($next_badge): 
            // Calculate progress percentage
            $progress_value = 0;
            if ($next_badge['id'] == 1) $progress_value = min(($total_reports / 1) * 100, 100);
            elseif ($next_badge['id'] == 2) $progress_value = min(($total_reports / 5) * 100, 100);
            elseif ($next_badge['id'] == 3) $progress_value = min(($completed_reports / 10) * 100, 100);
            elseif ($next_badge['id'] == 4) $progress_value = min(($points / 500) * 100, 100);
            elseif ($next_badge['id'] == 5) $progress_value = min(($total_reports / 20) * 100, 100);
        ?>
        <div class="progress-container">
            <div class="progress-header">
                <h4>Progress to Next Badge</h4>
                <strong>
                    <?php 
                    if ($next_badge['id'] == 1) echo "$total_reports/1 Reports";
                    elseif ($next_badge['id'] == 2) echo "$total_reports/5 Reports";
                    elseif ($next_badge['id'] == 3) echo "$completed_reports/10 Completed";
                    elseif ($next_badge['id'] == 4) echo "$points/500 Points";
                    elseif ($next_badge['id'] == 5) echo "$total_reports/20 Reports";
                    ?>
                </strong>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" 
                     style="width: <?php echo $progress_value; ?>%">
                </div>
            </div>
            <div class="progress-text">
                <span>Current Progress</span>
                <span><?php echo htmlspecialchars($next_badge['badge_name']); ?></span>
            </div>
        </div>
        <?php elseif (count($badges) > 0): ?>
        <div class="progress-container">
            <div class="progress-header">
                <h4>🎉 Congratulations!</h4>
                <strong>All Badges Unlocked!</strong>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 100%"></div>
            </div>
            <div class="progress-text">
                <span>You've earned all available badges!</span>
                <span>Keep contributing!</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rewards History -->
        <div class="history-container">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <p>Your recent points and achievements</p>
            </div>
            
            <div class="section-body">
                <?php if (count($rewards_history) > 0): ?>
                    <?php foreach($rewards_history as $history): ?>
                        <div class="history-item">
                            <div class="history-icon">
                                <i class="fas 
                                    <?php 
                                    if ($history['action_type'] == 'report_submission') echo 'fa-file-alt';
                                    elseif ($history['action_type'] == 'report_completed') echo 'fa-check-circle';
                                    elseif ($history['action_type'] == 'high_priority') echo 'fa-star';
                                    else echo 'fa-award';
                                    ?>
                                "></i>
                            </div>
                            <div class="history-details">
                                <div class="history-action">
                                    <?php echo htmlspecialchars($history['action_description']); ?>
                                </div>
                                <div class="history-date">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($history['earned_at'])); ?>
                                </div>
                            </div>
                            <div class="history-points">
                                +<?php echo $history['points_earned']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h4>No Activity Yet</h4>
                        <p class="text-muted mb-3">Submit your first report to start earning points!</p>
                        <a href="report.php" class="btn btn-success">
                            <i class="fas fa-plus-circle"></i> Submit Report
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Animate progress bar on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressFill = document.getElementById('progressFill');
            if (progressFill) {
                const width = progressFill.style.width;
                progressFill.style.width = '0';
                setTimeout(() => {
                    progressFill.style.width = width;
                }, 500);
            }
            
            // Add celebration effect if all badges are earned
            <?php if (count($badges) > 0 && count($badges) === count($all_badges)): ?>
                setTimeout(() => {
                    showCelebration();
                }, 1000);
            <?php endif; ?>
        });
        
        function showCelebration() {
            const container = document.querySelector('.points-container');
            if (!container) return;
            
            const confettiCount = 20;
            for (let i = 0; i < confettiCount; i++) {
                createConfetti(container);
            }
        }
        
        function createConfetti(container) {
            const confetti = document.createElement('div');
            confetti.innerHTML = '🎉';
            confetti.style.position = 'absolute';
            confetti.style.fontSize = '20px';
            confetti.style.top = '-30px';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.zIndex = '1000';
            confetti.style.opacity = '0.8';
            container.appendChild(confetti);
            
            const duration = 1000 + Math.random() * 1000;
            const animation = confetti.animate([
                { transform: 'translateY(0) rotate(0deg)', opacity: 0.8 },
                { transform: `translateY(${container.offsetHeight + 30}px) rotate(${360 + Math.random() * 360}deg)`, opacity: 0 }
            ], {
                duration: duration,
                easing: 'cubic-bezier(0.215, 0.610, 0.355, 1)'
            });
            
            animation.onfinish = () => confetti.remove();
        }
        
        // Share achievement
        function shareAchievement() {
            const shareText = `I've earned <?php echo $points; ?> Swachh Bharat Points and <?php echo count($badges); ?> badges! Join me in keeping our city clean! #SwachhBharat`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'My Swachh Bharat Achievement',
                    text: shareText,
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(shareText).then(() => {
                    alert('Achievement copied to clipboard! Share it with your friends.');
                });
            }
        }
    </script>
</body>
</html>