<?php
if (!defined('ABSPATH')) {
    exit;
}

$stats = NexlifyDesk_Reports::get_dashboard_stats();
?>

<div class="nexlifydesk-reports-dashboard">
    <div class="nexlifydesk-header">
        <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Plugin asset image, not media library image ?>
        <img src="<?php echo esc_url(NEXLIFYDESK_PLUGIN_URL . 'assets/images/nexlifydesk-logo.png'); ?>" 
             alt="<?php esc_attr_e('NexlifyDesk', 'nexlifydesk'); ?>" 
             class="nexlifydesk-logo-img">
        <h2 style="margin-top: 10px;"><?php esc_html_e('NexlifyDesk Reports', 'nexlifydesk'); ?></h2>
    </div>

    <!-- Key Metrics Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-value"><?php echo esc_html($stats['total_tickets']); ?></div>
            <div class="metric-label"><?php esc_html_e('Total Tickets', 'nexlifydesk'); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo esc_html($stats['active_tickets']); ?></div>
            <div class="metric-label"><?php esc_html_e('Active Tickets', 'nexlifydesk'); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo esc_html($stats['closed_tickets']); ?></div>
            <div class="metric-label"><?php esc_html_e('Closed Tickets', 'nexlifydesk'); ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo esc_html($stats['avg_response_time']); ?></div>
            <div class="metric-label"><?php esc_html_e('Avg Response Time', 'nexlifydesk'); ?></div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <!-- Status Distribution Chart -->
        <div class="chart-container">
            <h3><?php esc_html_e('Ticket Status Distribution', 'nexlifydesk'); ?></h3>
            <div class="chart-wrapper">
                <canvas id="statusChart" width="250" height="250"></canvas>
                <div class="chart-legend">
                    <?php foreach ($stats['status_breakdown'] as $status => $count) : ?>
                        <div class="legend-item">
                            <span class="legend-color status-<?php echo esc_attr($status); ?>"></span>
                            <span><?php echo esc_html(ucfirst($status)); ?>: <?php echo esc_html($count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Priority Distribution Chart -->
        <div class="chart-container">
            <h3><?php esc_html_e('Priority Distribution', 'nexlifydesk'); ?></h3>
            <div class="chart-wrapper">
                <canvas id="priorityChart" width="250" height="250"></canvas>
                <div class="chart-legend">
                    <?php foreach ($stats['priority_breakdown'] as $priority => $count) : ?>
                        <div class="legend-item">
                            <span class="legend-color priority-<?php echo esc_attr($priority); ?>"></span>
                            <span><?php echo esc_html(ucfirst($priority)); ?>: <?php echo esc_html($count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Monthly Tickets Chart -->
        <div class="chart-container full-width">
            <h3><?php esc_html_e('Tickets This Month', 'nexlifydesk'); ?></h3>
            <div class="chart-wrapper">
                <canvas id="monthlyChart" width="800" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- Agent Performance Table -->
    <div class="agent-performance">
        <h3><?php esc_html_e('Agent Performance', 'nexlifydesk'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Agent', 'nexlifydesk'); ?></th>
                    <th><?php esc_html_e('Assigned Tickets', 'nexlifydesk'); ?></th>
                    <th><?php esc_html_e('Closed Tickets', 'nexlifydesk'); ?></th>
                    <th><?php esc_html_e('Response Rate', 'nexlifydesk'); ?></th>
                    <th><?php esc_html_e('Avg Response Time', 'nexlifydesk'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['agent_performance'] as $agent) : ?>
                    <tr>
                        <td><?php echo esc_html($agent['name']); ?></td>
                        <td><?php echo esc_html($agent['assigned']); ?></td>
                        <td><?php echo esc_html($agent['closed']); ?></td>
                        <td><?php echo esc_html($agent['response_rate']); ?>%</td>
                        <td><?php echo esc_html($agent['avg_response_time']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity">
        <h3><?php esc_html_e('Recent Activity', 'nexlifydesk'); ?></h3>
        <div class="activity-list">
            <?php foreach ($stats['recent_activity'] as $activity) : ?>
                <div class="activity-item">
                    <div class="activity-icon status-<?php echo esc_attr($activity['type']); ?>"></div>
                    <div class="activity-content">
                        <div class="activity-text"><?php echo wp_kses_post($activity['message']); ?></div>
                        <div class="activity-time"><?php echo esc_html($activity['time']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = <?php echo wp_json_encode(array_values($stats['status_breakdown'])); ?>;
    const statusLabels = <?php echo wp_json_encode(array_keys($stats['status_breakdown'])); ?>;
    
    drawPieChart(statusCtx, statusData, statusLabels, [
        '#3498db', '#e74c3c', '#f39c12', '#95a5a6'
    ]);

    // Priority Chart
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    const priorityData = <?php echo wp_json_encode(array_values($stats['priority_breakdown'])); ?>;
    const priorityLabels = <?php echo wp_json_encode(array_keys($stats['priority_breakdown'])); ?>;
    
    drawPieChart(priorityCtx, priorityData, priorityLabels, [
        '#27ae60', '#f39c12', '#e67e22', '#e74c3c'
    ]);

    // Monthly Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyData = <?php echo wp_json_encode($stats['monthly_data']); ?>;
    
    drawLineChart(monthlyCtx, monthlyData);
});

function drawPieChart(ctx, data, labels, colors) {
    const total = data.reduce((a, b) => a + b, 0);
    let currentAngle = -Math.PI / 2;
    
    const centerX = ctx.canvas.width / 2;
    const centerY = ctx.canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 20;
    
    data.forEach((value, index) => {
        const sliceAngle = (value / total) * 2 * Math.PI;
        
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
        ctx.closePath();
        ctx.fillStyle = colors[index] || '#999';
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        currentAngle += sliceAngle;
    });
}

function drawLineChart(ctx, data) {
    const padding = 40;
    const chartWidth = ctx.canvas.width - padding * 2;
    const chartHeight = ctx.canvas.height - padding * 2;
    
    // Clear canvas
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    
    // Draw grid
    ctx.strokeStyle = '#e0e0e0';
    ctx.lineWidth = 1;
    
    // Vertical grid lines
    for (let i = 0; i <= 30; i += 5) {
        const x = padding + (i / 30) * chartWidth;
        ctx.beginPath();
        ctx.moveTo(x, padding);
        ctx.lineTo(x, padding + chartHeight);
        ctx.stroke();
    }
    
    // Draw data
    if (data.length > 0) {
        ctx.strokeStyle = '#3498db';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        data.forEach((point, index) => {
            const x = padding + (index / (data.length - 1)) * chartWidth;
            const y = padding + chartHeight - (point.tickets / Math.max(...data.map(d => d.tickets)) * chartHeight);
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        
        ctx.stroke();
        
        // Draw points
        ctx.fillStyle = '#3498db';
        data.forEach((point, index) => {
            const x = padding + (index / (data.length - 1)) * chartWidth;
            const y = padding + chartHeight - (point.tickets / Math.max(...data.map(d => d.tickets)) * chartHeight);
            
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, 2 * Math.PI);
            ctx.fill();
        });
    }
}
</script>