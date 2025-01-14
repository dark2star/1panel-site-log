<?php
date_default_timezone_set('Asia/Shanghai'); // 设置为中国时区
// 配置 SQLite 数据库文件
$db = new SQLite3('../plugins/adminer/adminer/wafdb/req_log.db');

// 获取当前页码
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 获取日期范围
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// 获取站点和站点域名筛选条件
$selectedWebsite = isset($_GET['website_key']) ? $_GET['website_key'] : '';
$selectedHost = isset($_GET['host']) ? $_GET['host'] : '';

// 如果未提供开始日期，则默认查询所有数据
$startDateCondition = $startDate ? "DATE(datetime(localtime, '+8 hours')) >= '$startDate'" : '1=1';
$endDateCondition = "DATE(datetime(localtime, '+8 hours')) <= '$endDate'";

// 站点和站点域名条件
$websiteCondition = $selectedWebsite && $selectedWebsite !== 'all' ? "AND website_key = '$selectedWebsite'" : '';
$hostCondition = $selectedHost && $selectedHost !== 'all' ? "AND host = '$selectedHost'" : '';

// 每页显示的记录数
$perPage = 20;

// 表格数据查询
$offset = ($page - 1) * $perPage;
$tableQuery = "
    SELECT 
        datetime(localtime, '+8 hours') AS 请求时间,
        ip AS IP地址,
        ip_country_zh AS 国家,
        ip_province_zh AS 省份,
        method AS 请求方法,
        website_key AS 站点,
        host AS 站点域名,
        uri AS 请求路径,
        user_agent AS 浏览器Agent,
        exec_rule AS 攻击类型,
        rule_type AS 触发规则类型,
        match_value AS 匹配值,
        blocking_time AS 锁定时间,
        action AS 动作,
        is_block AS 是否拦截
    FROM req_logs
    WHERE $startDateCondition AND $endDateCondition $websiteCondition $hostCondition
    ORDER BY time DESC
    LIMIT $perPage OFFSET $offset
";
$tableResults = $db->query($tableQuery);

// 汇总数据查询
$summaryQuery = "
    SELECT 
        COUNT(*) AS total_requests,
        SUM(is_block) AS total_blocks
    FROM req_logs
    WHERE $startDateCondition AND $endDateCondition $websiteCondition $hostCondition
";
$summary = $db->querySingle($summaryQuery, true);

// 计算总记录数和分页
$totalRecordsQuery = "
    SELECT COUNT(*) FROM req_logs
    WHERE $startDateCondition AND $endDateCondition $websiteCondition $hostCondition
";
$totalRecords = $db->querySingle($totalRecordsQuery);
$totalPages = ceil($totalRecords / $perPage);

// 确定页码范围
$startPage = max(1, $page - 3);
$endPage = min($totalPages, $page + 3);

// 获取站点和站点域名的聚合结果
$websiteOptions = $db->query("SELECT DISTINCT website_key FROM req_logs");
$hostOptions = $db->query("SELECT DISTINCT host FROM req_logs");
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>攻击日志</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/footer.css">
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
        font-size: 16px;
        color: #444;
    }

    form {
        text-align: center;
        margin-bottom: 20px;
    }

    input[type="date"] {
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
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
        text-align: center;
        margin-top: 20px;
        display: block;
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

        <h1>攻击日志</h1>

        <!-- 日期选择表单 -->
        <form method="GET" action="" class="row g-3 mb-4">
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
            
            <div class="col-md-3">
                <label for="website_key" class="form-label">站点</label>
                <select name="website_key" id="website_key" class="form-select">
                    <option value="all">全部</option>
                    <?php while ($row = $websiteOptions->fetchArray(SQLITE3_ASSOC)): ?>
                    <option value="<?= htmlspecialchars($row['website_key']) ?>"
                        <?= $selectedWebsite === $row['website_key'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['website_key']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="host" class="form-label">站点域名</label>
                <select name="host" id="host" class="form-select">
                    <option value="all">全部</option>
                    <?php while ($row = $hostOptions->fetchArray(SQLITE3_ASSOC)): ?>
                    <option value="<?= htmlspecialchars($row['host']) ?>"
                        <?= $selectedHost === $row['host'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['host']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary">查询</button>
            </div>
        </form>

        <!-- 汇总数据展示 -->
        <div class="summary">
            <div>总请求数：<?= $summary['total_requests'] ?? 0 ?></div>
            <div>总拦截数：<?= $summary['total_blocks'] ?? 0 ?></div>
        </div>

        <!-- 数据表格展示 -->
        <table>
            <thead>
                <tr>
                    <th>请求时间</th>
                    <th>IP地址</th>
                    <th>国家</th>
                    <th>省份</th>
                    <th>请求方法</th>
                    <th>站点</th>
                    <th>站点域名</th>
                    <th>请求路径</th>
                    <th>浏览器Agent</th>
                    <th>攻击类型</th>
                    <th>触发规则类型</th>
                    <th>匹配值</th>
                    <th>锁定时间</th>
                    <th>动作</th>
                    <th>是否拦截</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $tableResults->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td><?= $row['请求时间'] ?></td>
                    <td><?= $row['IP地址'] ?></td>
                    <td><?= $row['国家'] ?></td>
                    <td><?= $row['省份'] ?></td>
                    <td><?= $row['请求方法'] ?></td>
                    <td><?= $row['站点'] ?></td>
                    <td><?= $row['站点域名'] ?></td>
                    <td><?= $row['请求路径'] ?></td>
                    <td><?= $row['浏览器Agent'] ?></td>
                    <td><?= $row['攻击类型'] ?></td>
                    <td><?= $row['触发规则类型'] ?></td>
                    <td><?= $row['匹配值'] ?></td>
                    <td><?= $row['锁定时间'] ?></td>
                    <td><span class="badge bg-primary rounded-pill"><?= $row['动作'] == 'deny' ? '禁止' : '允许' ?></span>
                    </td>
                    <td><span class="badge bg-primary rounded-pill"><?= $row['是否拦截'] == 1 ? '是' : '否' ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- 分页功能 -->
        <div class="pagination">
            <?php if ($startPage > 1): ?>
            <a href="?page=1&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>&website_key=<?= htmlspecialchars($selectedWebsite) ?>&host=<?= htmlspecialchars($selectedHost) ?>">首页</a>
            <?php endif; ?>
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?page=<?= $i ?>&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>&website_key=<?= htmlspecialchars($selectedWebsite) ?>&host=<?= htmlspecialchars($selectedHost) ?>"
                class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($endPage < $totalPages): ?>
            <a href="?page=<?= $totalPages ?>&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>&website_key=<?= htmlspecialchars($selectedWebsite) ?>&host=<?= htmlspecialchars($selectedHost) ?>">尾页</a>
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