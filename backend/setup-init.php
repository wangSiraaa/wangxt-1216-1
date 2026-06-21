<?php
/**
 * 系统初始化脚本：创建目录、sqlite数据库、生成APP_KEY
 * 用法：php backend/setup-init.php
 */

$baseDir = __DIR__;

echo "=== Food Recall System 初始化 ===\n\n";

// 1. 创建目录结构
$dirs = [
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/logs',
    'storage/debugbar',
    'bootstrap/cache',
];

echo "[1/5] 创建目录结构...\n";
foreach ($dirs as $d) {
    $path = $baseDir . '/' . $d;
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
        echo "  ✓ 已创建: {$d}\n";
    } else {
        echo "  ○ 已存在: {$d}\n";
    }
    @chmod($path, 0777);
}

// 2. 创建.gitkeep到空目录
$gitkeeps = [
    'storage/app/public/.gitkeep',
    'storage/framework/sessions/.gitkeep',
    'storage/framework/views/.gitkeep',
    'storage/framework/cache/.gitkeep',
    'storage/framework/cache/data/.gitkeep',
    'storage/logs/.gitkeep',
    'tests/.gitkeep',
];

echo "\n[2/5] 创建空目录占位文件(.gitkeep)...\n";
foreach ($gitkeeps as $f) {
    $path = $baseDir . '/' . $f;
    if (!file_exists($path)) {
        @file_put_contents($path, '');
        echo "  ✓ 已创建: {$f}\n";
    }
}

// 3. 创建SQLite数据库文件
echo "\n[3/5] 创建SQLite数据库文件...\n";
$dbPath = $baseDir . '/database/database.sqlite';
if (!file_exists($dbPath)) {
    @file_put_contents($dbPath, '');
    @chmod($dbPath, 0777);
    echo "  ✓ 已创建: database/database.sqlite (" . number_format(filesize($dbPath)) . " bytes)\n";
} else {
    echo "  ○ 已存在: database/database.sqlite (" . number_format(filesize($dbPath)) . " bytes)\n";
}

// 4. 生成.env文件和APP_KEY（如果尚未存在有效key）
echo "\n[4/5] 检查.env文件...\n";
$envPath = $baseDir . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (strpos($envContent, 'APP_KEY=base64:') === false) {
        // 生成APP_KEY：随机32字节 -> base64
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        $envContent = preg_replace('/^APP_KEY=(.*)$/m', 'APP_KEY=' . $appKey, $envContent);
        file_put_contents($envPath, $envContent);
        echo "  ✓ 已生成全新APP_KEY\n";
    } else {
        echo "  ○ APP_KEY已存在，无需重新生成\n";
    }
} else {
    echo "  ✗ .env文件不存在，请先创建.env文件\n";
    exit(1);
}

// 5. 输出下一步操作指引
echo "\n[5/5] 初始化完成！下一步操作：\n\n";
echo "  # 安装PHP依赖\n";
echo "  cd {$baseDir} && composer install\n\n";
echo "  # 执行数据库迁移（创建表结构）\n";
echo "  php artisan migrate\n\n";
echo "  # 填充测试数据\n";
echo "  php artisan db:seed\n\n";
echo "  # 执行核心业务流程回归测试（5个场景）\n";
echo "  php artisan test --filter=CoreBusinessFlowTest --stop-on-failure\n\n";
echo "  # 启动Laravel后端\n";
echo "  php artisan serve --host=127.0.0.1 --port=8000\n\n";

echo "=== 初始化完成 ✓ ===\n";
