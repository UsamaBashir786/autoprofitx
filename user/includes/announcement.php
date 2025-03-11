<?php
// Function to fetch announcements
function fetchAnnouncements($conn)
{
  $announcements = [];

  // User Registrations
  $reg_query = "
        SELECT 
            id, full_name, 'registration' as type, 
            CONCAT(full_name, ' joined') as message, 
            registration_date as activity_date,
            NULL as amount
        FROM users
        ORDER BY registration_date DESC
        LIMIT 10
    ";
  $reg_result = mysqli_query($conn, $reg_query);
  while ($row = mysqli_fetch_assoc($reg_result)) {
    $announcements[] = $row;
  }

  // Deposits
  $deposit_query = "
        SELECT 
            d.id, u.full_name, 'deposit' as type,
            CONCAT(u.full_name, ' deposited $', FORMAT(d.amount, 0)) as message,
            d.created_at as activity_date,
            d.amount
        FROM deposits d
        JOIN users u ON d.user_id = u.id
        WHERE d.status = 'approved'
        ORDER BY d.created_at DESC
        LIMIT 10
    ";
  $deposit_result = mysqli_query($conn, $deposit_query);
  while ($row = mysqli_fetch_assoc($deposit_result)) {
    $announcements[] = $row;
  }

  // Investments
  $invest_query = "
        SELECT 
            i.id, u.full_name, 'investment' as type,
            CONCAT(u.full_name, ' invested $', FORMAT(i.amount, 0)) as message,
            i.created_at as activity_date,
            i.amount
        FROM investments i
        JOIN users u ON i.user_id = u.id
        ORDER BY i.created_at DESC
        LIMIT 10
    ";
  $invest_result = mysqli_query($conn, $invest_query);
  while ($row = mysqli_fetch_assoc($invest_result)) {
    $announcements[] = $row;
  }

  // Sort announcements by date
  usort($announcements, function ($a, $b) {
    return strtotime($b['activity_date']) - strtotime($a['activity_date']);
  });

  // Limit to top 10
  return array_slice($announcements, 0, 10);
}

// Fetch announcements
$announcements = fetchAnnouncements($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Micro Announcements</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --registration-color: rgba(16, 185, 129, 0.45);
      --deposit-color: rgba(59, 130, 246, 0.45);
      --investment-color: rgba(139, 92, 246, 0.45);
      --referral-color: rgba(245, 158, 11, 0.45);
      --withdrawal-color: rgba(239, 68, 68, 0.45);
      --leaderboard-color: rgba(99, 102, 241, 0.45);
      --shadow-color: rgba(0, 0, 0, 0.12);
      --text-color: #ffffff;
      --border-radius: 6px;
      --transition-speed: 0.25s;
    }

    @keyframes slideIn {
      0% {
        transform: translateX(110%);
        opacity: 0;
      }
      100% {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes slideOut {
      0% {
        transform: translateX(0);
        opacity: 1;
      }
      100% {
        transform: translateX(110%);
        opacity: 0;
      }
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
    }

    #announcement-container {
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 9999;
      width: 260px;
      max-width: calc(100vw - 20px);
    }

    .announcement-box {
      display: flex;
      align-items: center;
      padding: 8px 10px;
      border-radius: var(--border-radius);
      background-color: #2c3e50;
      box-shadow: 0 2px 5px var(--shadow-color);
      margin-bottom: 5px;
      overflow: hidden;
      backdrop-filter: blur(5px);
      transition: all var(--transition-speed) ease;
      opacity: 0;
      transform: translateX(110%);
      max-width: 100%;
    }

    .announcement-box.show {
      animation: slideIn 0.3s forwards;
    }

    .announcement-box.hide {
      animation: slideOut 0.3s forwards;
    }

    .announcement-icon {
      width: 26px;
      height: 26px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      margin-right: 10px;
      background-color: rgba(255, 255, 255, 0.2);
      flex-shrink: 0;
    }

    .announcement-icon i {
      font-size: 12px;
      color: var(--text-color);
    }

    .announcement-content {
      flex-grow: 1;
      min-width: 0;
    }

    .announcement-title {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-color);
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .announcement-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .announcement-time {
      font-size: 10px;
      color: rgba(255, 255, 255, 0.7);
    }

    .announcement-amount {
      font-size: 10px;
      font-weight: 600;
      border-radius: 10px;
      padding: 1px 5px;
      background-color: rgba(255, 255, 255, 0.15);
      max-width: 70px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Type-specific styles */
    .registration {
      background: linear-gradient(135deg, var(--registration-color), rgba(16, 185, 129, 0.7));
      border-left: 3px solid rgb(16, 185, 129);
    }

    .deposit {
      background: linear-gradient(135deg, var(--deposit-color), rgba(59, 130, 246, 0.7));
      border-left: 3px solid rgb(59, 130, 246);
    }

    .investment {
      background: linear-gradient(135deg, var(--investment-color), rgba(139, 92, 246, 0.7));
      border-left: 3px solid rgb(139, 92, 246);
    }

    .referral {
      background: linear-gradient(135deg, var(--referral-color), rgba(245, 158, 11, 0.7));
      border-left: 3px solid rgb(245, 158, 11);
    }

    .withdrawal {
      background: linear-gradient(135deg, var(--withdrawal-color), rgba(239, 68, 68, 0.7));
      border-left: 3px solid rgb(239, 68, 68);
    }

    .leaderboard {
      background: linear-gradient(135deg, var(--leaderboard-color), rgba(99, 102, 241, 0.7));
      border-left: 3px solid rgb(99, 102, 241);
    }

    /* For extra small screens */
    @media (max-width: 600px) {
      #announcement-container {
        width: 180px;
        top: 5px;
        right: 5px;
      }
      
      .announcement-box {
        padding: 6px 8px;
        margin-bottom: 4px;
        border-radius: 5px;
      }
      
      .announcement-icon {
        width: 20px;
        height: 20px;
        margin-right: 6px;
      }
      
      .announcement-icon i {
        font-size: 10px;
      }
      
      .announcement-title {
        font-size: 10px;
        margin-bottom: 1px;
      }
      
      .announcement-time, 
      .announcement-amount {
        font-size: 8px;
      }
      
      .announcement-amount {
        padding: 1px 4px;
        max-width: 50px;
      }
    }
    
    /* For super small screens */
    @media (max-width: 320px) {
      #announcement-container {
        width: 150px;
      }
      
      .announcement-icon {
        width: 18px;
        height: 18px;
        margin-right: 5px;
      }
      
      .announcement-title {
        max-width: 110px;
      }
    }
  </style>
</head>
<body>

  <div id="announcement-container"></div>

  <script>
    // PHP-generated announcements
    const announcements = <?php echo json_encode($announcements); ?>;
    
    // DOM Element
    const announcementContainer = document.getElementById('announcement-container');
    
    // Icons for each announcement type
    const announcementIcons = {
      'registration': '<i class="fas fa-user-plus"></i>',
      'deposit': '<i class="fas fa-wallet"></i>',
      'investment': '<i class="fas fa-chart-line"></i>',
      'referral': '<i class="fas fa-handshake"></i>',
      'withdrawal': '<i class="fas fa-money-bill-wave"></i>',
      'leaderboard': '<i class="fas fa-trophy"></i>'
    };
    
    // Format relative time in shortest possible format
    function getRelativeTime(dateString) {
      const now = new Date();
      const date = new Date(dateString);
      const diffInSeconds = Math.floor((now - date) / 1000);
      
      if (diffInSeconds < 60) {
        return 'now';
      } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m`;
      } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h`;
      } else {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days}d`;
      }
    }
    
    // Format amount with compact notation
    function formatAmount(amount) {
      if (!amount) return '';
      
      // Simplify large numbers
      amount = parseInt(amount);
      if (amount >= 1000000) {
        return `$${(amount / 1000000).toFixed(1)}M`;
      } else if (amount >= 1000) {
        return `$${(amount / 1000).toFixed(1)}K`;
      } else {
        return `$${amount}`;
      }
    }
    
    // Extract first name or username from full name
    function getShortName(fullName) {
      if (!fullName) return '';
      
      // Get first name or username
      const parts = fullName.split(' ');
      if (parts.length > 0) {
        // If name is very long, truncate it
        let firstName = parts[0];
        if (firstName.length > 10) {
          firstName = firstName.substring(0, 8) + '...';
        }
        return firstName;
      }
      return fullName;
    }
    
    // Create announcement element with abbreviated content
    function createAnnouncementElement(announcement) {
      const element = document.createElement('div');
      element.className = `announcement-box ${announcement.type}`;
      
      // Get type-specific icon or default
      const icon = announcementIcons[announcement.type] || '<i class="fas fa-bell"></i>';
      
      // Format time
      const time = getRelativeTime(announcement.activity_date);
      
      // Format amount if present
      const amountHtml = announcement.amount 
        ? `<span class="announcement-amount">${formatAmount(announcement.amount)}</span>` 
        : '';
      
      // Shorten message for small screens
      let shortMessage = announcement.message;
      
      // If it's a deposit/investment message, make it shorter
      if (announcement.type === 'deposit' || announcement.type === 'investment') {
        const action = announcement.type === 'deposit' ? 'dep' : 'inv';
        const shortName = getShortName(announcement.full_name);
        shortMessage = `${shortName} ${action} ${formatAmount(announcement.amount)}`;
      } else if (announcement.type === 'registration') {
        const shortName = getShortName(announcement.full_name);
        shortMessage = `${shortName} joined`;
      }
      
      element.innerHTML = `
        <div class="announcement-icon">${icon}</div>
        <div class="announcement-content">
          <div class="announcement-title">${shortMessage}</div>
          <div class="announcement-meta">
            <div class="announcement-time">${time}</div>
            ${announcement.type === 'registration' ? '' : amountHtml}
          </div>
        </div>
      `;
      
      return element;
    }
    
    // Show a single announcement
    async function showAnnouncement(announcement) {
      return new Promise(resolve => {
        // Create and add the announcement element
        const element = createAnnouncementElement(announcement);
        announcementContainer.appendChild(element);
        
        // Animation delay for DOM to update
        setTimeout(() => {
          element.classList.add('show');
        }, 50);
        
        // Display duration (shorter for mobile)
        setTimeout(() => {
          element.classList.remove('show');
          element.classList.add('hide');
          
          // Remove after animation completes
          setTimeout(() => {
            element.remove();
            resolve();
          }, 300);
        }, 3000);
      });
    }
    
    // Run announcements sequence
    async function runAnnouncementSequence() {
      if (announcements.length === 0) return;
      
      while (true) {
        for (const announcement of announcements) {
          await showAnnouncement(announcement);
          // Shorter pause between announcements
          await new Promise(resolve => setTimeout(resolve, 500));
        }
      }
    }
    
    // Special detection for very small screens to make even more compact
    function adjustForScreenSize() {
      if (window.innerWidth <= 320) {
        // Add extra class for super small screens
        document.getElementById('announcement-container').classList.add('super-compact');
      }
    }
    
    // Start when page loads
    window.addEventListener('load', () => {
      adjustForScreenSize();
      runAnnouncementSequence();
    });
    
    // Adjust if window resizes
    window.addEventListener('resize', adjustForScreenSize);
  </script>
</body>
</html>