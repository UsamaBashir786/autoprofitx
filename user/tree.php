<?php
// Include database connection and tree commission system
include 'config/db.php';
require_once('tree_commission.php');

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php");
  exit();
}

// Get user ID from query string
$userId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$userId) {
  // Redirect to admin dashboard if no user ID provided
  header("Location: admin_tree_dashboard.php");
  exit();
}

// Function to get detailed user tree data
function getDetailedUserTree($userId, $maxDepth = 5)
{
  $conn = getConnection();
  $treeData = [];

  try {
    // Get user information
    $stmt = $conn->prepare("
            SELECT id, full_name, email, phone, referral_code, registration_date
            FROM users 
            WHERE id = ?
        ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return ['error' => 'User not found'];
    }

    $rootUser = $result->fetch_assoc();

    // Get user's wallet balance
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result->fetch_assoc();

    $rootUser['balance'] = $wallet ? $wallet['balance'] : 0;

    // Get user's commission statistics
    $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_commissions,
                COALESCE(SUM(commission_amount), 0) as total_earned,
                MAX(created_at) as last_commission_date
            FROM tree_commissions
            WHERE user_id = ? AND status = 'paid'
        ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $commissionStats = $result->fetch_assoc();

    $rootUser['commission_stats'] = $commissionStats;

    // Function to recursively build the tree
    function buildTree($conn, $parentId, $level = 1, $maxDepth = 5)
    {
      if ($level > $maxDepth) {
        return [
          'has_more' => true,
          'children' => []
        ];
      }

      $stmt = $conn->prepare("
                SELECT 
                    u.id, 
                    u.full_name, 
                    u.email, 
                    u.referral_code,
                    u.registration_date,
                    (SELECT balance FROM wallets WHERE user_id = u.id) as balance,
                    (SELECT COUNT(*) FROM referral_tree WHERE parent_id = u.id) as children_count,
                    (SELECT COUNT(*) FROM tree_commissions WHERE user_id = u.id) as commission_count,
                    (SELECT COALESCE(SUM(commission_amount), 0) FROM tree_commissions WHERE user_id = u.id) as total_earned
                FROM 
                    referral_tree rt
                    JOIN users u ON rt.user_id = u.id
                WHERE 
                    rt.parent_id = ? AND rt.level = 1
                ORDER BY
                    u.registration_date DESC
            ");
      $stmt->bind_param("i", $parentId);
      $stmt->execute();
      $result = $stmt->get_result();

      $children = [];
      $hasMore = false;

      while ($row = $result->fetch_assoc()) {
        $childNode = [
          'id' => $row['id'],
          'name' => $row['full_name'],
          'email' => $row['email'],
          'referral_code' => $row['referral_code'],
          'registration_date' => $row['registration_date'],
          'balance' => $row['balance'],
          'children_count' => $row['children_count'],
          'commission_count' => $row['commission_count'],
          'total_earned' => $row['total_earned'],
          'level' => $level
        ];

        // Recursively build children if they exist
        if ($row['children_count'] > 0) {
          $subTree = buildTree($conn, $row['id'], $level + 1, $maxDepth);
          $childNode['children'] = $subTree['children'];
          $childNode['has_more'] = $subTree['has_more'];

          // If any child has more, then this node also has more
          if ($subTree['has_more']) {
            $hasMore = true;
          }
        } else {
          $childNode['children'] = [];
          $childNode['has_more'] = false;
        }

        $children[] = $childNode;
      }

      return [
        'has_more' => $hasMore,
        'children' => $children
      ];
    }

    // Build the tree structure
    $tree = buildTree($conn, $userId, 1, $maxDepth);

    $treeData = [
      'root' => $rootUser,
      'tree' => $tree['children'],
      'has_more' => $tree['has_more']
    ];

    return $treeData;
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

// Get the tree data
$maxDepth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;
$treeData = getDetailedUserTree($userId, $maxDepth);

// Get user information for breadcrumb
$userName = '';
if (isset($treeData['root']['full_name'])) {
  $userName = $treeData['root']['full_name'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Tree Viewer - <?php echo htmlspecialchars($userName); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/orgchart/3.1.1/css/jquery.orgchart.min.css">
  <style>
    .user-card {
      min-width: 250px;
      margin-bottom: 10px;
    }

    .user-info {
      font-size: 0.9rem;
    }

    .orgchart-container {
      min-height: 600px;
      border: 1px solid #ddd;
      border-radius: 5px;
      background-color: #f8f9fa;
      overflow: auto;
    }

    .orgchart {
      background: #f8f9fa;
    }

    .depth-control {
      max-width: 150px;
    }

    /* OrgChart node styling */
    .orgchart .node {
      box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
    }

    .orgchart .node .title {
      background-color: #007bff;
    }

    .orgchart .node .content {
      border-color: #007bff;
    }

    .level-1 .title {
      background-color: #28a745 !important;
    }

    .level-1 .content {
      border-color: #28a745 !important;
    }

    .level-2 .title {
      background-color: #6610f2 !important;
    }

    .level-2 .content {
      border-color: #6610f2 !important;
    }

    .level-3 .title {
      background-color: #fd7e14 !important;
    }

    .level-3 .content {
      border-color: #fd7e14 !important;
    }

    .level-4 .title {
      background-color: #20c997 !important;
    }

    .level-4 .content {
      border-color: #20c997 !important;
    }

    .level-5 .title {
      background-color: #e83e8c !important;
    }

    .level-5 .content {
      border-color: #e83e8c !important;
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar - you can include your admin sidebar here -->
      <div class="col-md-2 bg-dark text-white py-3 min-vh-100">
        <h3 class="text-center mb-4">Admin Panel</h3>
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link text-white" href="admin_tree_dashboard.php">
              <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white active" href="#">
              <i class="fas fa-sitemap me-2"></i> Tree Viewer
            </a>
          </li>
          <!-- Add more navigation items as needed -->
        </ul>
      </div>

      <!-- Main Content -->
      <div class="col-md-10 p-4">
        <div class="d-flex justify-content-between mb-4">
          <div>
            <h2><i class="fas fa-sitemap me-2"></i> User Referral Tree</h2>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_tree_dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tree Viewer: <?php echo htmlspecialchars($userName); ?></li>
              </ol>
            </nav>
          </div>
          <div>
            <a href="admin_tree_dashboard.php" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
          </div>
        </div>

        <?php if (isset($treeData['error'])): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> Error: <?php echo $treeData['error']; ?>
          </div>
        <?php else: ?>

          <!-- User Info Card -->
          <div class="card mb-4">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0"><i class="fas fa-user me-2"></i> User Information</h5>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <h4><?php echo htmlspecialchars($treeData['root']['full_name']); ?></h4>
                  <p class="text-muted">
                    <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($treeData['root']['email']); ?><br>
                    <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($treeData['root']['phone']); ?><br>
                    <i class="fas fa-key me-2"></i> Referral Code: <code><?php echo htmlspecialchars($treeData['root']['referral_code']); ?></code><br>
                    <i class="fas fa-calendar me-2"></i> Joined: <?php echo date('F j, Y', strtotime($treeData['root']['registration_date'])); ?>
                  </p>
                </div>
                <div class="col-md-6">
                  <div class="card bg-light">
                    <div class="card-body">
                      <div class="row">
                        <div class="col-6">
                          <h5 class="text-primary mb-0">$<?php echo number_format($treeData['root']['balance'], 2); ?></h5>
                          <small class="text-muted">Wallet Balance</small>
                        </div>
                        <div class="col-6">
                          <h5 class="text-success mb-0">$<?php echo number_format($treeData['root']['commission_stats']['total_earned'], 2); ?></h5>
                          <small class="text-muted">Total Commissions</small>
                        </div>
                      </div>
                      <hr>
                      <div class="row">
                        <div class="col-6">
                          <h5 class="text-info mb-0"><?php echo number_format($treeData['root']['commission_stats']['total_commissions']); ?></h5>
                          <small class="text-muted">Commission Count</small>
                        </div>
                        <div class="col-6">
                          <h5 class="text-secondary mb-0">
                            <?php
                            if (!empty($treeData['root']['commission_stats']['last_commission_date'])) {
                              echo date('M d, Y', strtotime($treeData['root']['commission_stats']['last_commission_date']));
                            } else {
                              echo 'N/A';
                            }
                            ?>
                          </h5>
                          <small class="text-muted">Last Commission</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tree Visualization Controls -->
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i> Tree Visualization Controls</h5>
            </div>
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-md-6">
                  <div class="input-group">
                    <span class="input-group-text">Tree Depth</span>
                    <select id="depthControl" class="form-select depth-control" onchange="changeDepth(this.value)">
                      <option value="1" <?php echo $maxDepth == 1 ? 'selected' : ''; ?>>1 Level</option>
                      <option value="2" <?php echo $maxDepth == 2 ? 'selected' : ''; ?>>2 Levels</option>
                      <option value="3" <?php echo $maxDepth == 3 ? 'selected' : ''; ?>>3 Levels</option>
                      <option value="4" <?php echo $maxDepth == 4 ? 'selected' : ''; ?>>4 Levels</option>
                      <option value="5" <?php echo $maxDepth == 5 ? 'selected' : ''; ?>>5 Levels</option>
                    </select>
                    <button class="btn btn-primary" onclick="changeDepth(document.getElementById('depthControl').value)">
                      <i class="fas fa-sync-alt me-1"></i> Update
                    </button>
                  </div>
                </div>
                <div class="col-md-6 text-end">
                  <button id="expandAll" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-expand-arrows-alt me-1"></i> Expand All
                  </button>
                  <button id="collapseAll" class="btn btn-outline-secondary">
                    <i class="fas fa-compress-arrows-alt me-1"></i> Collapse All
                  </button>
                  <button id="zoomIn" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-search-plus"></i>
                  </button>
                  <button id="zoomOut" class="btn btn-outline-secondary">
                    <i class="fas fa-search-minus"></i>
                  </button>
                  <button id="resetZoom" class="btn btn-outline-secondary">
                    <i class="fas fa-expand"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Tree Visualization -->
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i> Referral Tree Visualization</h5>
            </div>
            <div class="card-body">
              <div class="orgchart-container">
                <div id="tree-chart"></div>
              </div>

              <?php if ($treeData['has_more']): ?>
                <div class="alert alert-info mt-3">
                  <i class="fas fa-info-circle me-2"></i> Some branches are truncated. Increase the tree depth to see more levels.
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- JavaScript Libraries -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/orgchart/3.1.1/js/jquery.orgchart.min.js"></script>

  <script>
    // Function to change tree depth
    function changeDepth(depth) {
      window.location.href = 'admin_user_tree_viewer.php?id=<?php echo $userId; ?>&depth=' + depth;
    }

    $(document).ready(function() {
      // Prepare data for OrgChart
      const treeData = <?php echo json_encode($treeData); ?>;

      if (!treeData.error) {
        // Format the root node
        const rootNode = {
          id: treeData.root.id,
          name: treeData.root.full_name,
          title: 'Root User',
          email: treeData.root.email,
          referralCode: treeData.root.referral_code,
          balance: treeData.root.balance,
          children: formatChildren(treeData.tree)
        };

        // Initialize the org chart
        $('#tree-chart').orgchart({
          data: rootNode,
          nodeContent: 'title',
          direction: 'b2t',
          nodeTemplate: function(data) {
            let levelClass = '';
            if (data.level) {
              levelClass = 'level-' + data.level;
            }

            return `
                            <div class="title ${levelClass}">${data.name}</div>
                            <div class="content ${levelClass}">
                                <div><small>${data.email || ''}</small></div>
                                <div><small>Code: ${data.referralCode || ''}</small></div>
                                <div><small>Balance: $${formatNumber(data.balance) || '0.00'}</small></div>
                                <div class="mt-1">
                                    <a href="admin_user_tree_viewer.php?id=${data.id}" class="btn btn-sm btn-primary">View</a>
                                </div>
                            </div>
                        `;
          }
        });

        // Expand/Collapse buttons
        $('#expandAll').on('click', function() {
          $('#tree-chart').find('.node').removeClass('collapsed');
          $('#tree-chart').find('.nodes').css('display', '');
          $('#tree-chart').find('.lines').css('display', '');
        });

        $('#collapseAll').on('click', function() {
          $('#tree-chart').find('.node:not(:first)').addClass('collapsed');
          $('#tree-chart').find('.nodes').css('display', 'none');
          $('#tree-chart').find('.lines').css('display', 'none');
        });

        // Zoom controls
        let zoom = 1;
        const zoomStep = 0.1;

        $('#zoomIn').on('click', function() {
          zoom += zoomStep;
          applyZoom();
        });

        $('#zoomOut').on('click', function() {
          zoom = Math.max(0.5, zoom - zoomStep);
          applyZoom();
        });

        $('#resetZoom').on('click', function() {
          zoom = 1;
          applyZoom();
        });

        function applyZoom() {
          $('#tree-chart').css('transform', `scale(${zoom})`);
        }
      }

      // Helper function to format children nodes
      function formatChildren(children) {
        if (!children || children.length === 0) {
          return [];
        }

        return children.map(child => {
          return {
            id: child.id,
            name: child.name,
            title: 'Level ' + child.level,
            email: child.email,
            referralCode: child.referral_code,
            balance: child.balance,
            level: child.level,
            children: formatChildren(child.children),
            hasMore: child.has_more
          };
        });
      }

      // Helper function to format numbers
      function formatNumber(number) {
        return parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
      }
    });
  </script>
</body>

</html>