<?php
// check_resources.php
echo "<!DOCTYPE html><html><head><title>资源检查</title><style>body{font-family:Arial;padding:20px;}pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style></head><body>";
echo "<h3>检查本地资源文件</h3>";
echo "<pre>";

$required_files = [
    'assets/css/bootstrap.min.css' => 'Bootstrap CSS',
    'assets/js/bootstrap.bundle.min.js' => 'Bootstrap JS',
    'assets/css/sweetalert2.min.css' => 'SweetAlert2 CSS',
    'assets/js/sweetalert2.min.js' => 'SweetAlert2 JS',
    'assets/css/style.css' => '自定义样式',
    'includes/header.php' => '公共头部',
    'includes/footer.php' => '公共尾部'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $description: $file (".round($size/1024,2)." KB)\n";
    } else {
        echo "❌ $description: $file <span style='color:red'>缺失！</span>\n";
    }
}

echo "</pre>";

// 测试页面加载
echo "<h3>测试页面加载</h3>";
$test_pages = ['pages/login.php', 'pages/dashboard.php', 'pages/quiz.php'];
foreach ($test_pages as $page) {
    if (file_exists($page)) {
        echo "✅ $page 存在<br>";
    } else {
        echo "❌ $page 不存在<br>";
    }
}

echo "</body></html>";
?>