<style>
  .chart-container {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
  }

  .chart-card {
    background: #111111;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid #222;
  }

  .chart-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
    border-color: #f59e0b;
  }
</style>
<!-- Charts Section -->
<section class="py-16">
  <div class="container mx-auto px-4">
    <div class="text-center mb-12">
      <h2 class="text-3xl font-bold mb-4">Investment Performance</h2>
      <p class="text-gray-400 max-w-xl mx-auto">
        See how our investment plans deliver consistent returns over time
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      <!-- Growth Chart -->
      <div class="chart-card">
        <div class="chart-container">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 400" width="400" height="200">
            <!-- Background -->
            <rect width="800" height="400" fill="#111111" rx="10" ry="10" />

            <!-- Subtle grid -->
            <g stroke="#222222" stroke-width="1">
              <line x1="100" y1="50" x2="100" y2="350" />
              <line x1="200" y1="50" x2="200" y2="350" />
              <line x1="300" y1="50" x2="300" y2="350" />
              <line x1="400" y1="50" x2="400" y2="350" />
              <line x1="500" y1="50" x2="500" y2="350" />
              <line x1="600" y1="50" x2="600" y2="350" />
              <line x1="700" y1="50" x2="700" y2="350" />

              <line x1="100" y1="50" x2="700" y2="50" />
              <line x1="100" y1="100" x2="700" y2="100" />
              <line x1="100" y1="150" x2="700" y2="150" />
              <line x1="100" y1="200" x2="700" y2="200" />
              <line x1="100" y1="250" x2="700" y2="250" />
              <line x1="100" y1="300" x2="700" y2="300" />
              <line x1="100" y1="350" x2="700" y2="350" />
            </g>

            <!-- Chart title -->
            <text x="400" y="30" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="white" text-anchor="middle">Investment Growth</text>

            <!-- Y-axis labels -->
            <text x="90" y="350" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹0k</text>
            <text x="90" y="250" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹20k</text>
            <text x="90" y="150" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹40k</text>
            <text x="90" y="50" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹60k</text>

            <!-- X-axis labels -->
            <text x="100" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W1</text>
            <text x="200" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W2</text>
            <text x="300" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W3</text>
            <text x="400" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W4</text>
            <text x="500" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W5</text>
            <text x="600" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W6</text>
            <text x="700" y="370" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">W7</text>

            <!-- Premium Plan Line (20% growth) -->
            <path d="M100,330 L200,306 L300,282 L400,258 L500,234 L600,210 L700,186" stroke="#f59e0b" stroke-width="3" fill="none" />

            <!-- Data points for Premium -->
            <circle cx="100" cy="330" r="6" fill="#f59e0b" />
            <circle cx="200" cy="306" r="6" fill="#f59e0b" />
            <circle cx="300" cy="282" r="6" fill="#f59e0b" />
            <circle cx="400" cy="258" r="6" fill="#f59e0b" />
            <circle cx="500" cy="234" r="6" fill="#f59e0b" />
            <circle cx="600" cy="210" r="6" fill="#f59e0b" />
            <circle cx="700" cy="186" r="6" fill="#f59e0b" />

            <!-- Gradient below line -->
            <defs>
              <linearGradient id="premiumGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.5" />
                <stop offset="100%" stop-color="#f59e0b" stop-opacity="0" />
              </linearGradient>
            </defs>
            <path d="M100,330 L200,306 L300,282 L400,258 L500,234 L600,210 L700,186 L700,350 L100,350 Z" fill="url(#premiumGradient)" />

            <!-- Legend -->
            <rect x="580" y="70" width="15" height="15" fill="#f59e0b" rx="2" ry="2" />
            <text x="605" y="83" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="start">20% Returns</text>

            <!-- Annotations -->
            <path d="M700,186 L730,150" stroke="#ffffff" stroke-width="1" stroke-dasharray="2,2" />
            <rect x="650" y="100" width="140" height="50" rx="10" ry="10" fill="#222222" />
            <text x="720" y="125" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="middle">Final: ₹42,000</text>
            <text x="720" y="145" font-family="Arial, sans-serif" font-size="12" fill="#f59e0b" text-anchor="middle">+20% ROI</text>
          </svg>
        </div>
        <div class="p-4">
          <h3 class="text-lg font-bold mb-2">Growth Projection</h3>
          <p class="text-sm text-gray-400">Weekly growth of investment with consistent 20% returns</p>
        </div>
      </div>

      <!-- Comparison Chart -->
      <div class="chart-card">
        <div class="chart-container">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 400" width="400" height="200">
            <!-- Background -->
            <rect width="800" height="400" fill="#111111" rx="10" ry="10" />

            <!-- Chart title -->
            <text x="400" y="30" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="white" text-anchor="middle">Plan Comparison</text>

            <!-- Bar chart group -->
            <g transform="translate(100, 80)">
              <!-- Y-axis -->
              <line x1="0" y1="0" x2="0" y2="250" stroke="#333333" stroke-width="2" />

              <!-- X-axis -->
              <line x1="0" y1="250" x2="600" y2="250" stroke="#333333" stroke-width="2" />

              <!-- Y-axis labels -->
              <text x="-10" y="250" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹0k</text>
              <text x="-10" y="150" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹20k</text>
              <text x="-10" y="50" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="end">₹40k</text>

              <!-- Horizontal grid lines -->
              <line x1="0" y1="150" x2="600" y2="150" stroke="#222222" stroke-width="1" />
              <line x1="0" y1="50" x2="600" y2="50" stroke="#222222" stroke-width="1" />

              <!-- Basic Plan -->
              <g transform="translate(100, 0)">
                <rect x="-30" y="200" width="60" height="50" fill="#3B82F6" rx="3" ry="3" />
                <rect x="20" y="188" width="60" height="62" fill="#f59e0b" rx="3" ry="3" />
                <text x="0" y="275" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="middle">Basic</text>
                <text x="0" y="295" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">₹3,000</text>
              </g>

              <!-- Premium Plan -->
              <g transform="translate(300, 0)">
                <rect x="-30" y="150" width="60" height="100" fill="#3B82F6" rx="3" ry="3" />
                <rect x="20" y="130" width="60" height="120" fill="#f59e0b" rx="3" ry="3" />
                <text x="0" y="275" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="middle">Premium</text>
                <text x="0" y="295" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">₹10,000</text>
              </g>

              <!-- Professional Plan -->
              <g transform="translate(500, 0)">
                <rect x="-30" y="100" width="60" height="150" fill="#3B82F6" rx="3" ry="3" />
                <rect x="20" y="70" width="60" height="180" fill="#f59e0b" rx="3" ry="3" />
                <text x="0" y="275" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="middle">Pro</text>
                <text x="0" y="295" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="middle">₹20,000</text>
              </g>

              <!-- Legend -->
              <g transform="translate(450, -45)">
                <rect x="0" y="0" width="15" height="15" fill="#3B82F6" rx="2" ry="2" />
                <text x="25" y="12" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="start">Initial</text>

                <rect x="0" y="25" width="15" height="15" fill="#f59e0b" rx="2" ry="2" />
                <text x="25" y="37" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="start">After 1 Month</text>
              </g>
            </g>
          </svg>
        </div>
        <div class="p-4">
          <h3 class="text-lg font-bold mb-2">Plan Comparison</h3>
          <p class="text-sm text-gray-400">Investment growth across our three premium plans</p>
        </div>
      </div>

      <!-- User Distribution Chart -->
      <div class="chart-card">
        <div class="chart-container">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 400" width="400" height="200">
            <!-- Background -->
            <rect width="800" height="400" fill="#111111" rx="10" ry="10" />

            <!-- Chart title -->
            <text x="400" y="30" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="white" text-anchor="middle">User Distribution</text>

            <!-- Pie Chart -->
            <g transform="translate(250, 200)">
              <!-- Pie slices -->
              <path d="M0,0 L0,-150 A150,150 0 0,1 129.9,75 z" fill="#f59e0b" />
              <path d="M0,0 L129.9,75 A150,150 0 0,1 -129.9,75 z" fill="#3B82F6" />
              <path d="M0,0 L-129.9,75 A150,150 0 0,1 0,-150 z" fill="#10B981" />

              <!-- Inner circle for donut effect -->
              <circle cx="0" cy="0" r="70" fill="#111111" />

              <!-- Center text -->
              <text x="0" y="0" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle">20%</text>
              <text x="0" y="30" font-family="Arial, sans-serif" font-size="16" fill="#f59e0b" text-anchor="middle" dominant-baseline="middle">Returns</text>
            </g>

            <!-- Legend -->
            <g transform="translate(500, 120)">
              <rect x="0" y="0" width="20" height="20" fill="#10B981" rx="3" ry="3" />
              <text x="30" y="15" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="start">Basic (20%)</text>

              <rect x="0" y="50" width="20" height="20" fill="#3B82F6" rx="3" ry="3" />
              <text x="30" y="65" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="start">Premium (45%)</text>

              <rect x="0" y="100" width="20" height="20" fill="#f59e0b" rx="3" ry="3" />
              <text x="30" y="115" font-family="Arial, sans-serif" font-size="14" fill="white" text-anchor="start">Pro (35%)</text>
            </g>

            <!-- Stats Section -->
            <g transform="translate(500, 250)">
              <rect x="0" y="0" width="200" height="80" fill="#222222" rx="10" ry="10" />
              <text x="100" y="25" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="white" text-anchor="middle">Statistics</text>

              <text x="10" y="50" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="start">Users:</text>
              <text x="190" y="50" font-family="Arial, sans-serif" font-size="12" fill="white" text-anchor="end">25,000+</text>

              <text x="10" y="70" font-family="Arial, sans-serif" font-size="12" fill="#cccccc" text-anchor="start">Profit:</text>
              <text x="190" y="70" font-family="Arial, sans-serif" font-size="12" fill="#f59e0b" text-anchor="end">₹14M+</text>
            </g>
          </svg>
        </div>
        <div class="p-4">
          <h3 class="text-lg font-bold mb-2">User Distribution</h3>
          <p class="text-sm text-gray-400">Plan popularity and platform performance metrics</p>
        </div>
      </div>
    </div>
  </div>
</section>