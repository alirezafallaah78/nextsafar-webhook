<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Widget_Revenue_Chart extends NextSafar_Widget_Base {

    public function __construct() {
        parent::__construct(
            'revenue_chart',
            'Revenue Overview',
            'Monthly revenue for the past 12 months',
            'half'
        );
    }

    protected function get_data() {
        $months = array();
        $revenue = array();
        $bookings = array();

        for ( $i = 11; $i >= 0; $i-- ) {
            $timestamp = strtotime( "-{$i} months" );
            $year = date( 'Y', $timestamp );
            $month = date( 'n', $timestamp );

            $months[] = date( 'M Y', $timestamp );

            // فعلاً placeholder - بعداً با داده واقعی جایگزین می‌شود
            $revenue[]  = rand( 5000, 50000 );
            $bookings[] = rand( 10, 100 );
        }

        return array(
            'labels'   => $months,
            'revenue'  => $revenue,
            'bookings' => $bookings,
        );
    }

    protected function render_content( $data ) {
        ?>
        <div class="nextsafar-chart-container">
            <canvas id="nextsafar-revenue-chart" height="300"></canvas>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('nextsafar-revenue-chart');
                if (!ctx) return;

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo wp_json_encode( $data['labels'] ); ?>,
                        datasets: [
                            {
                                label: 'Revenue ($)',
                                data: <?php echo wp_json_encode( $data['revenue'] ); ?>,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                tension: 0.4,
                                fill: true,
                                yAxisID: 'y',
                            },
                            {
                                label: 'Bookings',
                                data: <?php echo wp_json_encode( $data['bookings'] ); ?>,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: false,
                                yAxisID: 'y1',
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                padding: 12,
                                cornerRadius: 8,
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                grid: {
                                    color: 'rgba(229, 231, 235, 0.5)',
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false,
                                },
                            },
                            x: {
                                grid: {
                                    display: false,
                                },
                            }
                        }
                    }
                });
            });
        </script>
        <?php
    }
}