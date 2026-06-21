<?php 
if ( ! defined( 'ABSPATH' ) ) exit; 

function arms_reports_tab() { 
    // Mock Data Engine for KPIs and Analytics Matrix
    $analytics_data = [
        'patients' => [
            'new_vs_returning' => ['new' => 420, 'returning' => 860, 'ratio' => '32.8% / 67.2%'],
            'diagnosis' => [
                ['name' => 'Chronic Hypertension', 'count' => 340, 'percentage' => 40],
                ['name' => 'Type 2 Diabetes', 'count' => 255, 'percentage' => 30],
                ['name' => 'Acute Respiratory', 'count' => 170, 'percentage' => 20],
                ['name' => 'Other Diagnoses', 'count' => 85, 'percentage' => 10],
            ]
        ],
        'admission' => [
            'bed_occupancy' => '78.5%',
            'avg_stay' => '5.4 Days',
            'discharge_rate' => '92.1%',
            'occupancy_trend' => [45, 58, 62, 74, 78, 82, 785] // Last 7 days out of 100 max
        ],
        'physio' => [
            'recovery_rate' => '88.4%',
            'session_completion' => '94.2%',
            'treatment_success' => '91.0%',
        ],
        'financial' => [
            'net_profit' => '$142,350',
            'revenue_trend' => '+14.6% MoM',
            'expenses' => [
                ['category' => 'Medical Supplies', 'amount' => '$45,200', 'width' => '50%'],
                ['category' => 'Staff Salaries', 'amount' => '$32,100', 'width' => '35%'],
                ['category' => 'Utility & Admin', 'amount' => '$13,500', 'width' => '15%']
            ]
        ],
        'inventory' => [
            'stock_usage' => 'Optimized (12% reduction in waste)',
            'cost_analysis' => '$18,400 Saved This Quarter',
            'expiry_alerts' => [
                ['item' => 'Amoxicillin 500mg', 'days' => '14 Days Left', 'status' => 'critical'],
                ['item' => 'Sterile Gauze Packs', 'days' => '28 Days Left', 'status' => 'warning'],
            ]
        ],
        'kpi' => [
            'growth_index' => '+22.4%',
            'dept_performance' => 'Outpatient Care (96% Efficiency Score)',
        ]
    ];
    ?>
    <style>
        /* Modern Glassmorphic Neo-Bento Design System */
        .arms-analytics-container {
            --arms-bg-dark: #0f1115;
            --arms-card-bg: rgba(22, 26, 33, 0.8);
            --arms-border-color: rgba(255, 255, 255, 0.08);
            --arms-accent-glow: rgba(0, 210, 255, 0.15);
            --arms-text-main: #f3f4f6;
            --arms-text-muted: #9ca3af;
            --arms-cyan: #00d2ff;
            --arms-emerald: #05f3a2;
            --arms-rose: #ff4a76;
            --arms-amber: #ffb800;
            
            background: var(--arms-bg-dark);
            color: var(--arms-text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 30px;
            border-radius: 16px;
            margin-top: 20px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        /* Top Bar & Engine Architecture Details */
        .arms-analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--arms-border-color);
            padding-bottom: 24px;
            margin-bottom: 30px;
        }
        .arms-header-left h2 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0 0 6px 0;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .arms-header-left p {
            margin: 0;
            font-size: 14px;
            color: var(--arms-text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .arms-engine-badge {
            background: linear-gradient(135deg, rgba(0, 210, 255, 0.1), rgba(5, 243, 162, 0.1));
            border: 1px solid rgba(0, 210, 255, 0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            color: var(--arms-cyan);
            letter-spacing: 0.5px;
        }

        /* Navigation Systems */
        .arms-analytics-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .arms-tab-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--arms-border-color);
            color: var(--arms-text-muted);
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .arms-tab-btn:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
        }
        .arms-tab-btn.arms-active {
            background: #fff;
            color: #000;
            border-color: #fff;
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        /* Neo-Bento Architectural Grid Layouts */
        .arms-bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            display: none;
        }
        .arms-bento-grid.arms-active {
            display: grid;
        }

        /* Responsive Breakdowns */
        .arms-col-4 { grid-column: span 4; }
        .arms-col-5 { grid-column: span 5; }
        .arms-col-6 { grid-column: span 6; }
        .arms-col-7 { grid-column: span 7; }
        .arms-col-8 { grid-column: span 8; }
        .arms-col-12 { grid-column: span 12; }

        /* Bento Panel Glassmorphism Elements */
        .arms-bento-card {
            background: var(--arms-card-bg);
            border: 1px solid var(--arms-border-color);
            border-radius: 14px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .arms-bento-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }
        .arms-card-title {
            font-size: 14px;
            color: var(--arms-text-muted);
            margin: 0 0 16px 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .arms-big-stat {
            font-size: 38px;
            font-weight: 800;
            line-height: 1;
            color: #fff;
            letter-spacing: -1px;
            margin-bottom: 8px;
        }
        .arms-stat-desc {
            font-size: 13px;
            color: var(--arms-text-muted);
            margin: 0;
        }

        /* Procedural CSS Chart Architectures */
        .arms-progress-track {
            background: rgba(255, 255, 255, 0.05);
            height: 6px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 12px;
        }
        .arms-progress-fill {
            height: 100%;
            border-radius: 10px;
            background: var(--arms-cyan);
        }
        .arms-data-list {
            margin-top: 10px;
        }
        .arms-data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .arms-data-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .arms-row-label {
            font-size: 14px;
            color: var(--arms-text-muted);
        }
        .arms-row-value {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        /* SVG Micro Sparkline Framework */
        .arms-sparkline-container {
            margin-top: 20px;
            padding: 10px 0;
        }
        .arms-sparkline {
            width: 100%;
            height: 60px;
            stroke: var(--arms-emerald);
            stroke-width: 2.5;
            fill: none;
            stroke-linecap: round;
        }

        /* Distribution Bars for Inventory and Diagnosis Matrix */
        .arms-dist-bar {
            display: flex;
            height: 24px;
            border-radius: 6px;
            overflow: hidden;
            margin: 20px 0;
            background: rgba(255,255,255,0.05);
        }
        .arms-dist-seg {
            height: 100%;
            transition: width 0.3s ease;
        }

        /* Interactive Alerts UI */
        .arms-alert-pill {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .arms-alert-pill.critical {
            background: rgba(255, 74, 118, 0.1);
            border: 1px solid rgba(255, 74, 118, 0.2);
            color: var(--arms-rose);
        }
        .arms-alert-pill.warning {
            background: rgba(255, 184, 0, 0.1);
            border: 1px solid rgba(255, 184, 0, 0.2);
            color: var(--arms-amber);
        }

        /* Legend Indicators */
        .arms-legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        .arms-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--arms-text-muted);
        }
        .arms-legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        @media (max-width: 991px) {
            .arms-col-4, .arms-col-5, .arms-col-6, .arms-col-7, .arms-col-8 {
                grid-column: span 12;
            }
        }
    </style>

    <div class="arms-analytics-container">
        <!-- Top Engine Bar Header -->
        <div class="arms-analytics-header">
            <div class="arms-header-left">
                <h2>Management Decision System</h2>
                <p>Real-Time Cross-Department Diagnostics</p>
            </div>
            <div class="arms-engine-badge">
                ANALYTICS ENGINE v4.2 // ONLINE
            </div>
        </div>

        <!-- Global Navigation Core Tabs -->
        <div class="arms-analytics-tabs">
            <button class="arms-tab-btn arms-active" onclick="armsSwitchTab(event, 'arms-overview-tab')">📊 Core Performance KPIs</button>
            <button class="arms-tab-btn" onclick="armsSwitchTab(event, 'arms-patients-tab')">👥 Patient & Admission Matrix</button>
            <button class="arms-tab-btn" onclick="armsSwitchTab(event, 'arms-clinical-tab')">💪 Therapy & Inventory Logs</button>
            <button class="arms-tab-btn" onclick="armsSwitchTab(event, 'arms-financial-tab')">💰 Financial Breakdown</button>
        </div>

        <!-- TAB 1: PERFORMANCE ANALYTICS & KEY MANAGEMENT KPIS -->
        <div id="arms-overview-tab" class="arms-bento-grid arms-active">
            <div class="arms-bento-card arms-col-6">
                <div class="arms-card-title">📈 System Growth Tracker</div>
                <div class="arms-big-stat"><?php echo esc_html($analytics_data['kpi']['growth_index']); ?></div>
                <p class="arms-stat-desc">Compounded capacity and intake volume optimization index compared to last fiscal cycle.</p>
                <div class="arms-progress-track">
                    <div class="arms-progress-fill" style="width: 76.4%; background: var(--arms-emerald);"></div>
                </div>
            </div>

            <div class="arms-bento-card arms-col-6">
                <div class="arms-card-title">🏛️ Department Performance Target</div>
                <div class="arms-big-stat" style="font-size:24px; margin-top:10px; margin-bottom:14px; color: var(--arms-cyan);">
                    <?php echo esc_html($analytics_data['kpi']['dept_performance']); ?>
                </div>
                <p class="arms-stat-desc">Top processing department verified by patient throughput metrics and internal SLA completion rates.</p>
            </div>

            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">🏨 Bed Occupancy</div>
                <div class="arms-big-stat"><?php echo esc_html($analytics_data['admission']['bed_occupancy']); ?></div>
                <div class="arms-progress-track">
                    <div class="arms-progress-fill" style="width: 78.5%;"></div>
                </div>
            </div>

            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">💪 Physiotherapy Success</div>
                <div class="arms-big-stat" style="color: var(--arms-emerald);"><?php echo esc_html($analytics_data['physio']['treatment_success']); ?></div>
                <div class="arms-progress-track">
                    <div class="arms-progress-fill" style="width: 91%; background: var(--arms-emerald);"></div>
                </div>
            </div>

            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">💰 Net Profit Margin Trend</div>
                <div class="arms-big-stat"><?php echo esc_html($analytics_data['financial']['net_profit']); ?></div>
                <p class="arms-stat-desc" style="color: var(--arms-emerald); font-weight:700;"><?php echo esc_html($analytics_data['financial']['revenue_trend']); ?></p>
            </div>
        </div>

        <!-- TAB 2: PATIENT & ADMISSION LOGS -->
        <div id="arms-patients-tab" class="arms-bento-grid">
            <div class="arms-bento-card arms-col-5">
                <div class="arms-card-title">👥 Patient Lifecycle Distribution</div>
                <div class="arms-data-list">
                    <div class="arms-data-row">
                        <span class="arms-row-label">New Registrations</span>
                        <span class="arms-row-value"><?php echo esc_html($analytics_data['patients']['new_vs_returning']['new']); ?></span>
                    </div>
                    <div class="arms-data-row">
                        <span class="arms-row-label">Returning Profiles</span>
                        <span class="arms-row-value"><?php echo esc_html($analytics_data['patients']['new_vs_returning']['returning']); ?></span>
                    </div>
                    <div class="arms-data-row">
                        <span class="arms-row-label">Ratio Analysis</span>
                        <span class="arms-row-value" style="color: var(--arms-cyan);"><?php echo esc_html($analytics_data['patients']['new_vs_returning']['ratio']); ?></span>
                    </div>
                </div>
            </div>

            <div class="arms-bento-card arms-col-7">
                <div class="arms-card-title">📊 Primary Diagnostic Classification</div>
                <div class="arms-dist-bar">
                    <?php 
                    $colors = ['#00d2ff', '#05f3a2', '#ffb800', '#ff4a76'];
                    foreach ($analytics_data['patients']['diagnosis'] as $index => $diag) {
                        echo '<div class="arms-dist-seg" style="width:' . esc_attr($diag['percentage']) . '%; background:' . esc_attr($colors[$index]) . ';" title="' . esc_attr($diag['name']) . '"></div>';
                    }
                    ?>
                </div>
                <div class="arms-legend">
                    <?php foreach ($analytics_data['patients']['diagnosis'] as $index => $diag) : ?>
                        <div class="arms-legend-item">
                            <span class="arms-legend-dot" style="background: <?php echo esc_attr($colors[$index]); ?>;"></span>
                            <span><?php echo esc_html($diag['name']); ?> (<?php echo esc_html($diag['percentage']); ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">⏱️ Average Stay Duration</div>
                <div class="arms-big-stat" style="color: #fff;"><?php echo esc_html($analytics_data['admission']['avg_stay']); ?></div>
                <p class="arms-stat-desc">Mean duration recorded across general admission beds inside current billing cycle.</p>
            </div>

            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">🚪 Fluid Discharge Rate</div>
                <div class="arms-big-stat" style="color: var(--arms-emerald);"><?php echo esc_html($analytics_data['admission']['discharge_rate']); ?></div>
                <p class="arms-stat-desc">Patient clearance optimization index matching internal safety protocols.</p>
            </div>

            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">📈 Occupancy Loading Trend</div>
                <div class="arms-sparkline-container">
                    <svg class="arms-sparkline" viewBox="0 0 140 40">
                        <path d="M0,30 Q20,10 40,25 T80,5 T120,18 T140,8" />
                    </svg>
                </div>
                <p class="arms-stat-desc">Dynamic 7-day vector mapping out clinical bed requests.</p>
            </div>
        </div>

        <!-- TAB 3: PHYSIOTHERAPY & INVENTORY MATRIX -->
        <div id="arms-clinical-tab" class="arms-bento-grid">
            <div class="arms-bento-card arms-col-6">
                <div class="arms-card-title">💪 Physiotherapy Metrics System</div>
                <div class="arms-data-list">
                    <div class="arms-data-row">
                        <span class="arms-row-label">Functional Recovery Rate</span>
                        <span class="arms-row-value" style="color: var(--arms-emerald);"><?php echo esc_html($analytics_data['physio']['recovery_rate']); ?></span>
                    </div>
                    <div class="arms-data-row">
                        <span class="arms-row-label">Target Session Completion</span>
                        <span class="arms-row-value"><?php echo esc_html($analytics_data['physio']['session_completion']); ?></span>
                    </div>
                    <div class="arms-data-row">
                        <span class="arms-row-label">Clinical Treatment Success</span>
                        <span class="arms-row-value" style="color: var(--arms-cyan);"><?php echo esc_html($analytics_data['physio']['treatment_success']); ?></span>
                    </div>
                </div>
            </div>

            <div class="arms-bento-card arms-col-6">
                <div class="arms-card-title">📦 Supply Chain & Material Audits</div>
                <div class="arms-data-list">
                    <div class="arms-data-row">
                        <span class="arms-row-label">Stock Usage Allocation</span>
                        <span class="arms-row-value"><?php echo esc_html($analytics_data['inventory']['stock_usage']); ?></span>
                    </div>
                    <div class="arms-data-row">
                        <span class="arms-row-label">Strategic Cost Mitigation</span>
                        <span class="arms-row-value" style="color: var(--arms-emerald);"><?php echo esc_html($analytics_data['inventory']['cost_analysis']); ?></span>
                    </div>
                </div>
            </div>

            <div class="arms-bento-card arms-col-12">
                <div class="arms-card-title" style="color: var(--arms-rose);">⚠️ Critical Batch Expiry Watchlist</div>
                <div style="margin-top: 15px;">
                    <?php foreach ($analytics_data['inventory']['expiry_alerts'] as $alert) : ?>
                        <div class="arms-alert-pill <?php echo esc_attr($alert['status']); ?>">
                            <span>📦 Item SKU: <?php echo esc_html($alert['item']); ?></span>
                            <span><?php echo esc_html($alert['days']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB 4: FINANCIAL ACCOUNTING MODULE -->
        <div id="arms-financial-tab" class="arms-bento-grid">
            <div class="arms-bento-card arms-col-4">
                <div class="arms-card-title">💰 Balance Statement Net</div>
                <div class="arms-big-stat" style="color: var(--arms-emerald);"><?php echo esc_html($analytics_data['financial']['net_profit']); ?></div>
                <p class="arms-stat-desc">Operational net margins calculated after direct overhead deductions.</p>
            </div>

            <div class="arms-bento-card arms-col-8">
                <div class="arms-card-title">📊 Operational Overhead Breakdown</div>
                <div style="margin-top: 24px;">
                    <div style="display: flex; height: 12px; border-radius: 30px; overflow: hidden; background: rgba(255,255,255,0.05);">
                        <div style="width: 50%; background: #ff4a76;"></div>
                        <div style="width: 35%; background: #ffb800;"></div>
                        <div style="width: 15%; background: #00d2ff;"></div>
                    </div>
                    <div class="arms-data-list" style="margin-top: 20px;">
                        <?php 
                        $fin_colors = ['var(--arms-rose)', 'var(--arms-amber)', 'var(--arms-cyan)'];
                        foreach ($analytics_data['financial']['expenses'] as $idx => $exp) : ?>
                            <div class="arms-data-row">
                                <span class="arms-row-label" style="display:flex; align-items:center; gap:8px;">
                                    <span style="width:8px; height:8px; border-radius:50%; background:<?php echo $fin_colors[$idx]; ?>;"></span>
                                    <?php echo esc_html($exp['category']); ?>
                                </span>
                                <span class="arms-row-value"><?php echo esc_html($exp['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Vanilla Engine Navigation Switching Logic -->
    <script>
        function armsSwitchTab(event, tabId) {
            const container = event.target.closest('.arms-analytics-container');
            
            // Isolate lookups strictly to this workspace block instance
            const contentPanels = container.querySelectorAll('.arms-bento-grid');
            const tabButtons = container.querySelectorAll('.arms-tab-btn');
            
            contentPanels.forEach(panel => panel.classList.remove('arms-active'));
            tabButtons.forEach(btn => btn.classList.remove('arms-active'));
            
            container.querySelector(`#${tabId}`).classList.add('arms-active');
            event.target.classList.add('arms-active');
        }
    </script>
    <?php
}