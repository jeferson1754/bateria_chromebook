<?php
require 'conexion.php';

/**
 * Convierte un número de minutos a un formato de "Xh Ymin" o "Ymin".
 * @param int $totalMinutes El total de minutos a formatear.
 * @return string El tiempo formateado.
 */
function formatMinutesToHoursAndMinutes(int $totalMinutes): string
{
    if ($totalMinutes < 60) {
        return $totalMinutes . 'min';
    } else {
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        return $hours . 'h ' . $minutes . 'min';
    }
}


$registros = [];
$promedio_porcentaje_actual = 0;
$total_minutos_carga = 0;
$min_porcentaje = 100;
$max_porcentaje = 0;

$fechas_chart = [];
$porcentajes_chart = [];

$promedio_minutos_por_dia = [];
$dias_chart = [];
$minutos_promedio_chart = [];

try {
    $sql = "SELECT fecha_registro, porcentaje_actual, minutos_carga FROM registros_bateria ORDER BY fecha_registro ASC";
    $stmt = $pdo->query($sql);
    $registros = $stmt->fetchAll();

    if (!empty($registros)) {
        $suma_porcentaje_actual = 0;
        foreach ($registros as $registro) {
            $suma_porcentaje_actual += $registro['porcentaje_actual'];
            $total_minutos_carga += $registro['minutos_carga'];

            $min_porcentaje = min($min_porcentaje, $registro['porcentaje_actual']);
            $max_porcentaje = max($max_porcentaje, $registro['porcentaje_actual']);

            $fechas_chart[] = date('d/m H:i', strtotime($registro['fecha_registro']));
            $porcentajes_chart[] = $registro['porcentaje_actual'];
        }
        $promedio_porcentaje_actual = round($suma_porcentaje_actual / count($registros), 2);
    }

    // Calcular el promedio de minutos de carga por día
    $sql_daily_avg = "SELECT DATE(fecha_registro) as fecha, AVG(minutos_carga) as promedio_carga 
                      FROM registros_bateria 
                      GROUP BY DATE(fecha_registro) 
                      ORDER BY fecha ASC";
    $stmt_daily_avg = $pdo->query($sql_daily_avg);
    $promedio_minutos_por_dia = $stmt_daily_avg->fetchAll();

    foreach ($promedio_minutos_por_dia as $daily_avg) {
        $dias_chart[] = date('d/m/Y', strtotime($daily_avg['fecha']));
        $minutos_promedio_chart[] = round($daily_avg['promedio_carga'], 2);
    }
} catch (PDOException $e) {
    // En un entorno de producción, esto debería ser loggeado y no mostrado al usuario
    echo "<div class=\'alert alert-error\'>Error al cargar datos del dashboard: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestor de Batería</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container-main {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 30px;
            margin-bottom: 25px;
        }

        .header-title {
            color: #333;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }

        .header-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f0f4ff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-label {
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #667eea;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .nav-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }

        .nav-link:hover {
            background: #764ba2;
        }
    </style>
</head>

<body>
    <div class="container-main">
        <!-- Cabecera del Dashboard -->
        <header class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-100 pb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-2xl">📊</span>

                    <h1 class="bg-gradient-to-r from-white via-indigo-100 to-indigo-200 bg-clip-text text-2xl font-extrabold tracking-tight text-transparent sm:text-3xl">
                        Dashboard de Batería
                    </h1>
                </div>
                <p class="text-sm font-medium text-black-500">
                    Análisis y tendencias de carga de tu Chromebook
                </p>
            </div>

            <!-- Enlace de regreso estilizado como botón secundario -->
            <div>
                <a href="index.php" class="group inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-all duration-200 hover:bg-gray-50 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <!-- Icono de flecha unificado en color índigo -->
                    <svg class="h-4 w-4 text-indigo-500 transition-transform duration-200 group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver al Registro de Carga</span>
                </a>
            </div>
        </header>

        <!-- Sección de Estadísticas Clave -->
        <div class="mb-8 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <!-- Icono de tendencia en color índigo -->
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Estadísticas Clave
            </h2>

            <!-- Grid adaptable -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">

                <!-- Tarjeta 1: Registros Totales -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/40 p-4 transition-all hover:shadow-md hover:bg-white">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Registros Totales</p>
                    <div class="flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-gray-900"><?php echo count($registros ?? []); ?></span>
                        <!-- Icono de documento/registro -->
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 2: % Actual Promedio -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/40 p-4 transition-all hover:shadow-md hover:bg-white">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">% Actual Promedio</p>
                    <div class="flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-indigo-600"><?php echo $promedio_porcentaje_actual; ?>%</span>
                        <!-- Icono de calculadora -->
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 11h.01M9 11h.01M12 7h.01M15 11h.01M4 19V5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 3: Total Minutos Carga -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/40 p-4 transition-all hover:shadow-md hover:bg-white">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Total Minutos Carga</p>
                    <div class="flex items-baseline justify-between">
                        <span class="text-xl font-bold text-gray-900 leading-7">
                            <?php
                            if (function_exists('formatMinutesToHoursAndMinutes')) {
                                echo formatMinutesToHoursAndMinutes($total_minutos_carga);
                            } else {
                                echo $total_minutos_carga . ' min';
                            }
                            ?>
                        </span>
                        <!-- Icono de reloj -->
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 4: % Mínimo Registrado -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/40 p-4 transition-all hover:shadow-md hover:bg-white">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Porcentaje Mínimo</p>
                    <div class="flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-red-600">
                            <?php echo count($registros ?? []) > 0 ? $min_porcentaje . '%' : 'N/A'; ?>
                        </span>
                        <!-- Icono de tendencia a la baja -->
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 5: % Máximo Registrado -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/40 p-4 transition-all hover:shadow-md hover:bg-white">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Porcentaje Máximo</p>
                    <div class="flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-green-600">
                            <?php echo count($registros ?? []) > 0 ? $max_porcentaje . '%' : 'N/A'; ?>
                        </span>
                        <!-- Icono de tendencia al alza -->
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                </div>

            </div>
        </div>
        <div class="card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Evolución del Porcentaje de Batería</h2>
            <div class="chart-container" style="position: relative; height:300px;">
                <canvas id="batteryChart"></canvas>
            </div>
        </div>

        <div class="card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Promedio de Minutos de Carga por Día</h2>
            <?php if (!empty($promedio_minutos_por_dia)): ?>
                <div class="table-container mb-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table class="w-full border-collapse text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium">Día</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right">Promedio de Carga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 border-t border-gray-100">
                            <?php foreach ($promedio_minutos_por_dia as $daily_avg): ?>
                                <tr class="hover:bg-gray-50/75 transition-colors duration-150">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <?php echo date('d/m/Y', strtotime($daily_avg['fecha'])); ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-gray-700">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <?php
                                            if (function_exists('formatMinutesToHoursAndMinutes')) {
                                                echo formatMinutesToHoursAndMinutes(round($daily_avg['promedio_carga']));
                                            } else {
                                                echo round($daily_avg['promedio_carga']) . ' min';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="dailyChargeChart"></canvas>
                </div>
            <?php else: ?>
                <div class="no-records">
                    No hay datos suficientes para calcular el promedio de carga por día.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 1. Gráfico de Evolución de Batería (Línea)
        const ctx = document.getElementById('batteryChart').getContext('2d');
        const batteryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($fechas_chart ?? []); ?>,
                datasets: [{
                    label: 'Porcentaje Actual de Batería',
                    data: <?php echo json_encode($porcentajes_chart ?? []); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Porcentaje de Batería (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Fecha y Hora'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // 2. Gráfico de Promedio de Minutos de Carga por Día (Barras)
        const dailyChargeCtx = document.getElementById('dailyChargeChart');
        if (dailyChargeCtx) {
            const dailyChargeChart = new Chart(dailyChargeCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($dias_chart ?? []); ?>,
                    datasets: [{
                        label: 'Promedio de Minutos de Carga',
                        data: <?php echo json_encode($minutos_promedio_chart ?? []); ?>,
                        backgroundColor: 'rgba(118, 75, 162, 0.7)',
                        borderColor: 'rgba(118, 75, 162, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Minutos de Carga Promedio'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Día'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const hours = Math.floor(value / 60);
                                    const minutes = Math.round(value % 60);
                                    if (hours > 0) {
                                        return 'Promedio: ' + hours + 'h ' + minutes + 'min';
                                    } else {
                                        return 'Promedio: ' + minutes + 'min';
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>