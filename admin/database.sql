-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin BOOLEAN DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- 题库表
CREATE TABLE IF NOT EXISTS question_banks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 题目表
CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_id INTEGER NOT NULL,
    type INTEGER NOT NULL, -- 1: 单选， 2: 多选， 3: 判断
    stem TEXT NOT NULL,
    options_json TEXT NOT NULL,
    answer TEXT NOT NULL,
    analysis TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_id) REFERENCES question_banks (id)
);

-- 用户答题记录表
CREATE TABLE IF NOT EXISTS answer_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    bank_id INTEGER NOT NULL,
    user_answer TEXT,
    is_correct BOOLEAN,
    mode INTEGER NOT NULL, -- 1: 练习， 2: 测验
    time_spent INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (question_id) REFERENCES questions (id),
    FOREIGN KEY (bank_id) REFERENCES question_banks (id)
);

-- 测验会话表（记录整场测验）
CREATE TABLE IF NOT EXISTS quiz_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    bank_id INTEGER NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME,
    time_limit INTEGER DEFAULT 1800, -- 30分钟=1800秒
    total_questions INTEGER DEFAULT 20,
    correct_count INTEGER DEFAULT 0,
    status INTEGER DEFAULT 1, -- 1: 进行中， 2: 已完成， 3: 超时
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (bank_id) REFERENCES question_banks (id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_records_user_question ON answer_records (user_id, question_id, mode);
CREATE INDEX IF NOT EXISTS idx_records_created ON answer_records (user_id, bank_id, created_at);
CREATE INDEX IF NOT EXISTS idx_questions_bank ON questions (bank_id, type);
CREATE INDEX IF NOT EXISTS idx_quiz_sessions_user ON quiz_sessions (user_id, status);