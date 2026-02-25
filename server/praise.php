<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 数据库配置 
$db_host = '127.0.0.1';
$db_name = 'your_database_name'; // 替换为你的数据库名
$db_user = 'your_username';      // 替换为你的数据库用户名
$db_pass = 'your_password';      // 替换为你的数据库密码

// 系统运行配置 
// 【风控总开关】 1 = 持续循环模式（按CD一直派单）， 2 = 每日打卡模式（干满定额强制下班）
$WORK_MODE = 2; 

// 额度控制
$MAX_RECEIVE_PER_DAY = 30; // 每天最多被多少个陌生人赞（防清赞）
$MAX_SEND_PER_DAY = 30;    // 模式2 每天最多去赞多少人（防冻结）

// 派单节奏
$FETCH_LIMIT = 15;         // 每次派发单量（建议10~15）
$COOLDOWN = 1800;          // 正常批次间隔：1800秒 (半小时)
$DONE_COOLDOWN = 43200;    // 模式2 完成打卡后的超长休息：12小时
$LIKE_COUNT_PER_USER = 50; // 每次对着名片点 50 下

function getRealIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$client_ip = getRealIp();

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    exit(json_encode(['code' => '500', 'message' => 'db error', 'cooldown' => 60000]));
}

$uin = isset($_GET['uin']) ? trim($_GET['uin']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

$today = date('Y-m-d');
$now_time = time(); 
$now_db_time = date('Y-m-d H:i:s'); 

// 校验 uin 格式
if (empty($uin) || !preg_match('/^[1-9][0-9]{4,11}$/', $uin)) {
    exit(json_encode(['code' => '400', 'cooldown' => 600000]));
}

// IP 频率风控：10分钟内同一个 IP 下最多允许 4 个不同 uin 请求
$ipCheckStmt = $pdo->prepare("SELECT COUNT(DISTINCT uin) as uin_count FROM praise_users WHERE ip = ? AND update_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$ipCheckStmt->execute([$client_ip]);
if ($ipCheckStmt->fetch()['uin_count'] > 4) {
    exit(json_encode(['code' => '429', 'cooldown' => 3600000])); 
}

// 初始化用户
$pdo->prepare("INSERT IGNORE INTO praise_users (uin, update_time, ip, status, today_pushed_count, last_push_date) VALUES (?, '2000-01-01 00:00:00', ?, 0, 0, ?)")->execute([$uin, $client_ip, $today]);

$checkStmt = $pdo->prepare("SELECT status, update_time FROM praise_users WHERE uin = ?");
$checkStmt->execute([$uin]);
$currentUser = $checkStmt->fetch();

// 黑名单拦截
if ($currentUser['status'] == 1) {
    exit(json_encode(['code' => '403', 'cooldown' => 86400000])); 
}

if ($type === 'read') {
    // 优先拦截：常规冷却时间检查 (已在队列中)
    if ($now_time - strtotime($currentUser['update_time']) < $COOLDOWN) {
        exit(json_encode(['code' => '429', 'data' => [], 'cooldown' => ($COOLDOWN * 1000)]));
    }

    try {
        $pdo->beginTransaction(); 

        // 查询今天已经赞过的人
        $logStmt = $pdo->prepare("SELECT target_uin FROM praise_logs WHERE provider_uin = ? AND push_date = ?");
        $logStmt->execute([$uin, $today]);
        $already_pushed = $logStmt->fetchAll(PDO::FETCH_COLUMN);
        $already_sent_count = count($already_pushed);

        // 模式2：检查是否完成今日打卡
        if ($WORK_MODE == 2 && $already_sent_count >= $MAX_SEND_PER_DAY) {
            $pdo->rollBack();
            exit(json_encode([
                'code' => '429', 
                'message' => 'done_for_today',
                'data' => [], 
                'cooldown' => ($DONE_COOLDOWN * 1000)
            ]));
        }

        // 动态计算本次派发数量（防止最后一波超出每日上限）
        $current_fetch_limit = $FETCH_LIMIT;
        if ($WORK_MODE == 2) {
            $current_fetch_limit = min($FETCH_LIMIT, $MAX_SEND_PER_DAY - $already_sent_count);
        }

        $normal_data = [];
        $exclude_uins = array_unique(array_merge([$uin], $already_pushed)); // 排除自己和已经赞过的人

        // 从互赞池中随机抽取用户
        if ($current_fetch_limit > 0 && !empty($exclude_uins)) {
            $ex_placeholders = implode(',', array_fill(0, count($exclude_uins), '?'));
            $sql = "SELECT u.uin AS user 
                    FROM praise_users u
                    WHERE u.uin NOT IN ($ex_placeholders)
                    AND u.status = 0 
                    AND (u.last_push_date != ? OR u.today_pushed_count < ?)
                    ORDER BY u.update_time ASC 
                    LIMIT 200 FOR UPDATE"; 
                    
            $stmt = $pdo->prepare($sql);
            $idx = 1;
            foreach ($exclude_uins as $ex) {
                $stmt->bindValue($idx++, $ex, PDO::PARAM_STR);
            }
            $stmt->bindValue($idx++, $today, PDO::PARAM_STR);
            $stmt->bindValue($idx++, $MAX_RECEIVE_PER_DAY, PDO::PARAM_INT);
            $stmt->execute();
            
            $candidate_pool = $stmt->fetchAll();
            if (!empty($candidate_pool)) {
                shuffle($candidate_pool); 
                $normal_data = array_slice($candidate_pool, 0, $current_fetch_limit);
            }
        }

        $all_to_update = $normal_data;
        
        // 更新被赞用户的被赞次数，并写入日志
        if (!empty($all_to_update)) {
            $uidsToUpdate = array_column($all_to_update, 'user');
            $up_placeholders = implode(',', array_fill(0, count($uidsToUpdate), '?'));
            
            $updateSql = "UPDATE praise_users 
                          SET today_pushed_count = CASE WHEN last_push_date = ? THEN today_pushed_count + 1 ELSE 1 END,
                              last_push_date = ? 
                          WHERE uin IN ($up_placeholders)";
            $pdo->prepare($updateSql)->execute(array_merge([$today, $today], $uidsToUpdate));

            $logSql = "INSERT IGNORE INTO praise_logs (provider_uin, target_uin, push_date) VALUES ";
            $logValues = [];
            $logParams = [];
            foreach ($uidsToUpdate as $target) {
                $logValues[] = "(?, ?, ?)";
                array_push($logParams, $uin, $target, $today);
            }
            $pdo->prepare($logSql . implode(', ', $logValues))->execute($logParams);
        }

        // 更新请求者的状态
        $pdo->prepare("UPDATE praise_users SET update_time = ?, ip = ? WHERE uin = ?")->execute([$now_db_time, $client_ip, $uin]);
        
        $pdo->commit(); 

        // 返回下发数据
        if (count($all_to_update) > 0) {
            echo json_encode(['code' => '200', 'data' => $all_to_update, 'like_count' => $LIKE_COUNT_PER_USER, 'cooldown' => ($COOLDOWN * 1000), 'user_delay' => 15000]);
        } else {
            echo json_encode(['code' => '200', 'data' => [], 'cooldown' => ($COOLDOWN * 1000)]);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['code' => '500', 'cooldown' => 60000]);
    }
    
} elseif ($type === 'update') {
    // 仅更新在线时间
    $pdo->prepare("UPDATE praise_users SET update_time = ?, ip = ? WHERE uin = ?")->execute([$now_db_time, $client_ip, $uin]);
    echo json_encode(['code' => '200']);
} else {
    echo json_encode(['code' => '400', 'message' => 'bad request', 'cooldown' => 60000]);
}
?>
