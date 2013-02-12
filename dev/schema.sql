
CREATE TABLE sessions
(
    id INTEGER PRIMARY KEY,
    session_id TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    startdate TEXT NOT NULL
);
CREATE TABLE sessions_answers
(
    id INTEGER PRIMARY KEY,
    session_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    date_start TEXT NOT NULL,
    date_end TEXT,
    answer TEXT,
    correct TEXT NOT NULL
);
