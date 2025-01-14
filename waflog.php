<?php
date_default_timezone_set('Asia/Shanghai'); // 设置为中国时区
// 配置 SQLite 数据库文件
$db = new SQLite3('../plugins/adminer/adminer/wafdb/1pwaf.db');

// 获取当前页码和汇总时间范围
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$range = isset($_GET['range']) ? intval($_GET['range']) : 1; // 默认汇总 1 天

// 每页显示的记录数
$perPage = 20;

// 表格展示数据
$totalRecords = $db->querySingle("SELECT COUNT(*) FROM waf_stat");
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;
$tableQuery = "SELECT day, req_count, attack_count, count4xx, count5xx FROM waf_stat ORDER BY day DESC LIMIT $perPage OFFSET $offset";
$tableResults = $db->query($tableQuery);

// 汇总数据（独立于表格展示）
if ($range == 1) {
    $latestDayQuery = "SELECT day FROM waf_stat ORDER BY day DESC LIMIT 1";
    $latestDay = $db->querySingle($latestDayQuery);
    $summaryQuery = "
        SELECT 
            SUM(req_count) AS total_req_count,
            SUM(attack_count) AS total_attack_count,
            SUM(count4xx) AS total_count4xx,
            SUM(count5xx) AS total_count5xx
        FROM waf_stat
        WHERE day = '$latestDay'
    ";
} else {
    $summaryQuery = "
        SELECT 
            SUM(req_count) AS total_req_count,
            SUM(attack_count) AS total_attack_count,
            SUM(count4xx) AS total_count4xx,
            SUM(count5xx) AS total_count5xx
        FROM waf_stat
        WHERE day >= DATE('now', '-$range days')
    ";
}
$summary = $db->querySingle($summaryQuery, true);
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/footer.css">
    <title>WAF日志</title>
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
    }

    .summary {
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 10px 0;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .summary div {
        text-align: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        display: block;
        overflow-x: auto;
        table-layout: fixed;
    }

    th,
    td {
        width: 30%;
        white-space: nowrap;
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
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
    }

    .pagination a.active {
        font-weight: bold;
        color: #fff;
        background-color: #007BFF;
        border-color: #007BFF;
    }

    form {
        text-align: center;
        margin-bottom: 20px;
    }

    select {
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
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

        <h1>WAF日志</h1>

        <!-- 汇总数据选择框 -->
        <form method="GET" action="">
            <label for="range">汇总时间范围：</label>
            <select name="range" id="range" onchange="this.form.submit()">
                <option value="1" <?= $range == 1 ? 'selected' : '' ?>>1 天</option>
                <option value="7" <?= $range == 7 ? 'selected' : '' ?>>7 天</option>
                <option value="15" <?= $range == 15 ? 'selected' : '' ?>>15 天</option>
                <option value="30" <?= $range == 30 ? 'selected' : '' ?>>30 天</option>
            </select>
        </form>

        <!-- 汇总数据展示 -->
        <div class="summary">
            <div>总请求数：<?= $summary['total_req_count'] ?? 0 ?></div>
            <div>总攻击数：<?= $summary['total_attack_count'] ?? 0 ?></div>
            <div>4xx 状态码数：<?= $summary['total_count4xx'] ?? 0 ?></div>
            <div>5xx 状态码数：<?= $summary['total_count5xx'] ?? 0 ?></div>
        </div>

        <!-- 数据表格展示 -->
        <table>
            <thead>
                <tr>
                    <th>日期</th>
                    <th>请求数</th>
                    <th>攻击数</th>
                    <th>4xx 状态码数</th>
                    <th>5xx 状态码数</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $tableResults->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td><?= $row['day'] ?></td>
                    <td><?= $row['req_count'] ?></td>
                    <td><?= $row['attack_count'] ?></td>
                    <td><?= $row['count4xx'] ?></td>
                    <td><?= $row['count5xx'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- 分页功能 -->
        <div class="pagination">
            <!-- 首页链接 -->
            <?php if ($page > 1): ?>
            <a href="?page=1&range=<?= $range ?>">首页</a>
            <?php endif; ?>

            <!-- 显示当前页附近 3 个页码 -->
            <?php
    $startPage = max(1, $page - 3); // 起始页码
    $endPage = min($totalPages, $page + 3); // 结束页码
    for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?page=<?= $i ?>&range=<?= $range ?>" class="<?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <!-- 尾页链接 -->
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $totalPages ?>&range=<?= $range ?>">尾页</a>
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
</body>

</html>