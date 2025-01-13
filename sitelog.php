<?php
// 定义站点目录路径
define('BASE_DIR', '../plugins/adminer/adminer/wafdb/sites');

// 获取站点目录列表
function getSites() {
    $dirs = glob(BASE_DIR . '/*', GLOB_ONLYDIR);
    return array_map('basename', $dirs);
}

// 查询 SQLite 数据库
function queryDatabase($dbPath, $query, $params = []) {
    $db = new SQLite3($dbPath);
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $result = $stmt->execute();
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }
    $db->close();
    return $data;
}

// 初始化数据
$sites = getSites();
$selectedSite = $_GET['site'] ?? ($sites[0] ?? '');
$dateRange = $_GET['date_range'] ?? 'today';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';

switch ($dateRange) {
    case 'yesterday':
        $startDate = $endDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'last7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case 'last30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        break;
    case 'custom':
        $startDate = $customStartDate ?: date('Y-m-d');
        $endDate = $customEndDate ?: date('Y-m-d');
        break;
    default:
        $startDate = $endDate = date('Y-m-d');
}

// 数据库路径
$statDbPath = BASE_DIR . "/$selectedSite/site_stat.db";
$logsDbPath = BASE_DIR . "/$selectedSite/site_req_logs.db";

// 查询统计数据
$statQuery = ($startDate === $endDate)
    ? "SELECT hour AS time, pv, spider, ip, uv, req, count4xx, count5xx FROM site_stat WHERE day = :day"
    : "SELECT day AS time, SUM(pv) AS pv, SUM(spider) AS spider, SUM(ip) AS ip, SUM(uv) AS uv, SUM(req) AS req, SUM(count4xx) AS count4xx, SUM(count5xx) AS count5xx FROM site_stat WHERE day BETWEEN :start AND :end GROUP BY day";
$statData = queryDatabase($statDbPath, $statQuery, [
    ':day' => $startDate,
    ':start' => $startDate,
    ':end' => $endDate,
]);

// 计算聚合数据
$aggregatedData = [
    'pv' => array_sum(array_column($statData, 'pv')),
    'spider' => array_sum(array_column($statData, 'spider')),
    'ip' => array_sum(array_column($statData, 'ip')),
    'uv' => array_sum(array_column($statData, 'uv')),
    'req' => array_sum(array_column($statData, 'req')),
    'count4xx' => array_sum(array_column($statData, 'count4xx')),
    'count5xx' => array_sum(array_column($statData, 'count5xx')),
];

// 查询统计数据
$statQuery = ($startDate === $endDate)
    ? "SELECT hour AS time, pv, spider, ip, uv, req, count4xx, count5xx FROM site_stat WHERE day = :day"
    : "SELECT day AS time, SUM(pv) AS pv, SUM(spider) AS spider, SUM(ip) AS ip, SUM(uv) AS uv, SUM(req) AS req, SUM(count4xx) AS count4xx, SUM(count5xx) AS count5xx FROM site_stat WHERE day BETWEEN :start AND :end GROUP BY day";
$statData = queryDatabase($statDbPath, $statQuery, [
    ':day' => $startDate,
    ':start' => $startDate,
    ':end' => $endDate,
]);

// TOP 15 查询
$topCountries = queryDatabase($logsDbPath, "SELECT ip_country_zh AS name, COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end GROUP BY ip_country_zh ORDER BY count DESC LIMIT 15", [
    ':start' => $startDate,
    ':end' => $endDate,
]);

$topIPs = queryDatabase($logsDbPath, "SELECT ip AS name, COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end GROUP BY ip ORDER BY count DESC LIMIT 15", [
    ':start' => $startDate,
    ':end' => $endDate,
]);

$topDevices = queryDatabase($logsDbPath, "SELECT device AS name, COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end GROUP BY device ORDER BY count DESC LIMIT 15", [
    ':start' => $startDate,
    ':end' => $endDate,
]);

$topURIs = queryDatabase($logsDbPath, "SELECT uri AS name, COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end GROUP BY uri ORDER BY count DESC LIMIT 15", [
    ':start' => $startDate,
    ':end' => $endDate,
]);

$topHost = queryDatabase($logsDbPath, "SELECT host AS name, COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end GROUP BY host ORDER BY count DESC LIMIT 15", [
    ':start' => $startDate,
    ':end' => $endDate,
]);

$topOs = queryDatabase($logsDbPath, "SELECT os AS name, COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end GROUP BY os ORDER BY count DESC LIMIT 15", [
    ':start' => $startDate,
    ':end' => $endDate,
]);

// 查询日志数据

// 获取筛选条件
$filterColumn = $_GET['filter_column'] ?? '';
$filterValue = $_GET['filter_value'] ?? '';

$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$filterCondition = '';
$params = [
    ':start' => $startDate,
    ':end' => $endDate,
];

if ($filterColumn && $filterValue) {
    $filterCondition = "AND $filterColumn = :filterValue";
    $params[':filterValue'] = $filterValue;
}

$logParams = $params;
$logParams[':limit'] = $pageSize;
$logParams[':offset'] = $offset;

$logQuery = "SELECT datetime(localtime, '+8 hours') AS localtime, ip, ip_country_zh, ip_province_zh, user_agent, method, host, request_uri, status_code, referer, spider, request_time, os, browser, device 
             FROM site_req_logs 
             WHERE day BETWEEN :start AND :end $filterCondition 
             ORDER BY localtime DESC 
             LIMIT :limit OFFSET :offset";
$logData = queryDatabase($logsDbPath, $logQuery, $logParams);


// 获取日志总数
$totalLogs = queryDatabase($logsDbPath, "SELECT COUNT(*) AS count FROM site_req_logs WHERE day BETWEEN :start AND :end $filterCondition", $params)[0]['count'];
$totalPages = ceil($totalLogs / $pageSize);

// 确定页码范围
$startPage = max(1, $page - 3);
$endPage = min($totalPages, $page + 3);

$paginationUrl = "?site=$selectedSite&date_range=$dateRange&start_date=$startDate&end_date=$endDate";
if ($filterColumn && $filterValue) {
    $paginationUrl .= "&filter_column=$filterColumn&filter_value=" . urlencode($filterValue);
}
?>

<!DOCTYPE html>
<html lang="zh">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站点统计</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/footer.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 10px;
        box-sizing: border-box;
        max-width: 100%;
        background-color: #f4f4f9;
    }

    h1 {
        text-align: center;
        color: #333;
        margin-bottom: 20px;
    }
    
    a{
        text-decoration:none;
        font-weight: bold;
    }

    .chart-container {
        margin-bottom: 20px;
    }

    .top-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        /* 设置每个 div 之间的间距 */
        justify-content: space-between;
    }

    .top15-container {
        flex: 1 1 calc(50% - 20px);
        /* 在宽度足够时，每个 div 占 50% 宽度，并且减去 gap */
        background-color: #f0f0f0;
        padding: 10px;
        border: 1px solid #ddd;
        box-sizing: border-box;
        min-width: 200px;
        /* 每个 div 的最小宽度 */
        margin-bottom: 10px;
    }

    /* 当屏幕宽度小于 600px 时，调整每个 div 占满一行 */
    @media (max-width: 600px) {
        .box {
            flex: 1 1 100%;
            /* 每个 div 占 100% 宽度 */
        }
    }

    .top15-list {
        max-width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        display: block;
        border-collapse: collapse;
        margin-bottom: 20px;
        overflow-x: auto;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        table-layout: fixed;
    }

    th,
    td {
        width: 100%;
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
        white-space: nowrap;
    }

    th {
        background-color: #007BFF;
        color: #fff;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .pagination {
        display: block;
        text-align: center;
        margin-top: 20px;
    }

    .pagination a {
        margin: 0 5px;
        text-decoration: none;
        color: #007BFF;
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: #fff;
    }

    .pagination a:hover {
        background-color: #007BFF;
        color: #fff;
    }

    .pagination a.active {
        font-weight: bold;
        color: #fff;
        background-color: #007BFF;
        border-color: #007BFF;
        border-color: #007BFF;
    }
    </style>
</head>

<body>
    <div class="container">

        <!-- 顶部导航栏 -->
        <nav class="navbar">
            <a href="#" class="navbar-brand">1Panel统计</a>
            <ul class="navbar-links">
                <a href="sitelog.php">站点统计</a>
                <a href="waflog.php">WAF日志</a>
                <a href="wafdetail.php">攻击日志</a>
            </ul>
        </nav>

        <h1 class="mb-4">站点统计</h1>

        <!-- 日期和站点选择 -->
        <form method="GET" class="row g-3 mb-4">
            <!-- 日期选择表单 -->
            <div class="col-md-3">
                <label for="site" class="form-label">站点选择</label>
                <select name="site" id="site" class="form-select">
                    <?php foreach ($sites as $site): ?>
                    <option value="<?= htmlspecialchars($site) ?>" <?= $site === $selectedSite ? 'selected' : '' ?>>
                        <?= htmlspecialchars($site) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_range" class="form-label">日期范围</label>
                <select name="date_range" id="date_range" class="form-select">
                    <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>今天</option>
                    <option value="yesterday" <?= $dateRange === 'yesterday' ? 'selected' : '' ?>>昨天</option>
                    <option value="last7days" <?= $dateRange === 'last7days' ? 'selected' : '' ?>>最近7天</option>
                    <option value="last30days" <?= $dateRange === 'last30days' ? 'selected' : '' ?>>最近30天</option>
                    <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>自定义</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="start_date" class="form-label">开始日期</label>
                <input type="date" name="start_date" id="start_date" class="form-control"
                    value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">结束日期</label>
                <input type="date" name="end_date" id="end_date" class="form-control"
                    value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary" onclick="clearFilters()">查询</button>
            </div>
        </form>

        <!-- 统计项：Y轴数据各项的聚合 -->
        <div class="row mb-4">
            <div class="col-md-12">
                <ul class="list-group d-flex flex-row flex-wrap p-0 justify-content-center">
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>请求数</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['req'] ?></span>
                    </li>    
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>页面访问量 (PV)</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['pv'] ?></span>
                    </li>                    
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>独立访客数量 (UV)</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['uv'] ?></span>
                    </li>
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>独立 IP 数量</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['ip'] ?></span>
                    </li>
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>响应4xx</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['count4xx'] ?></span>
                    </li>
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>响应5xx</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['count5xx'] ?></span>
                    </li>                    
                    <li class="list-group-item d-flex flex-column align-items-center m-2"
                        style="flex: 1 1 calc(25% - 10px); min-width: 120px;">
                        <strong>蜘蛛访问量</strong>
                        <span class="badge bg-secondary"><?= $aggregatedData['spider'] ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 折线统计图 -->
        <div class="chart-container">
            <canvas id="statChart"></canvas>
        </div>

        <div class="top-container">

            <!-- IP 来源 TOP 15 -->
            <div class="top15-container">
                <h3>IP 来源 TOP 15</h3>
                <ul class="list-group top15-list">
                    <?php foreach ($topIPs as $ip): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="sitelog.php?site=<?= $selectedSite ?>&date_range=<?= $dateRange ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter_column=ip&filter_value=<?= urlencode($ip['name']) ?>">
                            <?= htmlspecialchars($ip['name']) ?>
                        </a>
                        <span class="badge bg-primary rounded-pill"><?= $ip['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- URL 来源 TOP 15 -->
            <div class="top15-container">
                <h3>URL 来源 TOP 15</h3>
                <ul class="list-group top15-list">
                    <?php foreach ($topURIs as $uri): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($uri['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= $uri['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- 国家来源 TOP 15 -->
            <div class="top15-container">
                <h3>国家来源 TOP 15</h3>
                <ul class="list-group top15-list">
                    <?php foreach ($topCountries as $country): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="sitelog.php?site=<?= $selectedSite ?>&date_range=<?= $dateRange ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter_column=ip_country_zh&filter_value=<?= urlencode($country['name']) ?>">
                            <?= htmlspecialchars($country['name']) ?>
                        </a>
                        <span class="badge bg-primary rounded-pill"><?= $country['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- 设备来源 TOP 15 -->
            <div class="top15-container">
                <h3>设备来源 TOP 15</h3>
                <ul class="list-group top15-list">
                    <?php foreach ($topDevices as $device): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="sitelog.php?site=<?= $selectedSite ?>&date_range=<?= $dateRange ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter_column=device&filter_value=<?= urlencode($device['name']) ?>">
                            <?= htmlspecialchars($device['name']) ?>
                        </a>
                        <span class="badge bg-primary rounded-pill"><?= $device['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- 域名来源 TOP 15 -->
            <div class="top15-container">
                <h3>域名来源 TOP 15</h3>
                <ul class="list-group top15-list">
                    <?php foreach ($topHost as $host): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="sitelog.php?site=<?= $selectedSite ?>&date_range=<?= $dateRange ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter_column=host&filter_value=<?= urlencode($host['name']) ?>">
                            <?= htmlspecialchars($host['name']) ?>
                        </a>
                        <span class="badge bg-primary rounded-pill"><?= $host['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- OS来源 TOP 15 -->
            <div class="top15-container">
                <h3>OS来源 TOP 15</h3>
                <ul class="list-group top15-list">
                    <?php foreach ($topOs as $os): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="sitelog.php?site=<?= $selectedSite ?>&date_range=<?= $dateRange ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter_column=os&filter_value=<?= urlencode($os['name']) ?>">
                            <?= htmlspecialchars($os['name']) ?>
                        </a>
                        <span class="badge bg-primary rounded-pill"><?= $os['count'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>

        <!-- 日志表格 -->
        <h3 class="mt-4">日志数据</h3>
        <table>
            <thead>
                <tr>
                    <th>时间</th>
                    <th>IP</th>
                    <th>国家</th>
                    <th>省份</th>
                    <th>请求方法</th>
                    <th>请求域名</th>
                    <th>请求 URL</th>
                    <th>状态码</th>
                    <th>请求时间</th>
                    <th>操作系统</th>
                    <th>浏览器</th>
                    <th>设备</th>
                    <th>蜘蛛</th>
                    <th>Referer</th>
                    <th>浏览器agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logData as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['localtime']) ?></td>
                    <td><?= htmlspecialchars($log['ip']) ?></td>
                    <td><?= htmlspecialchars($log['ip_country_zh']) ?></td>
                    <td><?= htmlspecialchars($log['ip_province_zh']) ?></td>
                    <td><?= htmlspecialchars($log['method']) ?></td>
                    <td><?= htmlspecialchars($log['host']) ?></td>
                    <td><?= htmlspecialchars($log['request_uri']) ?></td>
                    <td><?= htmlspecialchars($log['status_code']) ?></td>
                    <td><?= htmlspecialchars($log['request_time']) ?></td>
                    <td><?= htmlspecialchars($log['os']) ?></td>
                    <td><?= htmlspecialchars($log['browser']) ?></td>
                    <td><?= htmlspecialchars($log['device']) ?></td>
                    <td><?= htmlspecialchars($log['spider']) ?></td>
                    <td><?= htmlspecialchars($log['referer']) ?></td>
                    <td><?= htmlspecialchars($log['user_agent']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 分页 -->
        <div class="pagination">
            <?php if ($startPage > 1): ?>
                <a href="<?= $paginationUrl ?>&page=1">首页</a>
            <?php endif; ?>
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?= $paginationUrl ?>&page=<?= $i ?>" <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($endPage < $totalPages): ?>
            <a href="<?= $paginationUrl ?>&page=<?= $totalPages ?>">尾页</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer部分 -->
    <footer>
        <div class="footer-content">
            <p><strong>HaosApp</strong></p>
            <p><a href="mailto:haosapp@6688.dedyn.io">haosapp@6688.dedyn.io</a></p>
            <p>&copy; 2025 HaosApp. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
    function clearFilters() {
        const url = new URL(window.location.href);
        url.searchParams.delete('filter_column');
        url.searchParams.delete('filter_value');
        window.location.href = url.toString();
    }
    
    // 生成统计图
    const ctx = document.getElementById('statChart').getContext('2d');
    const chartData = {
        labels: <?= json_encode(array_column($statData, 'time')) ?> ,
        datasets: [
            {
                label: '页面访问量 (PV)',
                data: <?= json_encode(array_column($statData, 'pv')) ?> ,
                borderColor: 'rgba(75, 192, 192, 1)',
                fill: false,
        },
        {
                label: '蜘蛛访问量',
                data: <?= json_encode(array_column($statData, 'spider')) ?> ,
                borderColor: 'rgba(153, 102, 255, 1)',
                fill: false,
        },
        {
                label: '独立 IP 数量',
                data: <?= json_encode(array_column($statData, 'ip')) ?> ,
                borderColor: 'rgba(255, 159, 64, 1)',
                fill: false,
        },
        {
                label: '独立访客数量 (UV)',
                data: <?= json_encode(array_column($statData, 'uv')) ?> ,
                borderColor: 'rgba(255, 39, 15, 1)',
                fill: false,
        },
        {
                label: '请求数',
                data: <?= json_encode(array_column($statData, 'req')) ?> ,
                borderColor: 'rgba(130, 99, 132, 1)',
                fill: false,
        },
        {
                label: '响应4xx',
                data: <?= json_encode(array_column($statData, 'count4xx')) ?> ,
                borderColor: 'rgba(150, 99, 65, 1)',
                fill: false,
        },
        {
                label: '响应5xx',
                data: <?= json_encode(array_column($statData, 'count5xx')) ?> ,
                borderColor: 'rgba(100, 99, 132, 1)',
                fill: false,
        }
    ]
    };

    const config = {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: '时间',
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: '数量'
                    }
                }
            }
        }
    };

    new Chart(ctx, config);
    </script>
</body>

</html>