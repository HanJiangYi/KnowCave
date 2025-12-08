<?php
// includes/header.php
// 公共头部文件，用于所有页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? '知识洞天 KnowCave'); ?></title>
    
    <!-- 本地Bootstrap CSS -->
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 本地SweetAlert2 CSS -->
    <link href="/assets/css/sweetalert2.min.css" rel="stylesheet">
    
    <!-- 本地自定义样式 -->
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <!-- 页面特定的CSS -->
    <?php echo $pageStyles ?? ''; ?>
	
	<!-- 本地Bootstrap JS -->
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- 本地SweetAlert2 JS -->
    <script src="/assets/js/sweetalert2.min.js"></script>
</head>
<body <?php echo isset($bodyClass) ? 'class="' . $bodyClass . '"' : ''; ?>>