<?php
// admin/download_template.php
//define('IN_ADMIN', true);
require_once 'includes/common.php';

// 清除所有输出缓冲区
while (ob_get_level()) {
    ob_end_clean();
}

// 设置JSON数据
$example = [
    'version' => '1.0',
    'description' => '题库导入模板',
    'question_banks' => [
        [
            'name' => '示例题库',
            'description' => '示例题库的模板',
            'is_active' => 1,
            'questions' => [
                [
                    'type' => 1, // 1: 单选, 2: 多选, 3: 判断
                    'stem' => 'HTTP协议默认使用的端口号是？',
                    'options' => ['A. 21', 'B. 25', 'C. 80', 'D. 443'],
                    'answer' => 'C',
                    'analysis' => 'HTTP默认端口80，HTTPS默认端口443'
                ],
                [
                    'type' => 2,
                    'stem' => '下列哪些属于网络层协议？',
                    'options' => ['A. IP', 'B. TCP', 'C. ICMP', 'D. ARP'],
                    'answer' => 'ACD',
                    'analysis' => 'TCP是传输层协议'
                ],
                [
                    'type' => 3,
                    'stem' => 'TCP协议是面向连接的。',
                    'options' => ['对', '错'],
                    'answer' => '对',
                    'analysis' => 'TCP是面向连接的可靠传输协议'
                ]
            ]
        ]
    ]
];

// 设置HTTP头
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="question_bank_template_'.date('Ymd').'.json"');
header('Content-Length: ' . strlen(json_encode($example, JSON_UNESCAPED_UNICODE)));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('Pragma: public');

// 输出JSON
echo json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>