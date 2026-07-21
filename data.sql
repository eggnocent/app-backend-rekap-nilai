CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE users (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    name        VARCHAR(150),
    email       VARCHAR(191),
    password_hash VARCHAR(255),
    role        VARCHAR(30),
    identifier  VARCHAR(50),
    phone       VARCHAR(30),
    avatar_path VARCHAR(255),
    is_active   BOOLEAN,
    last_login_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (created_by) REFERENCES users (id) DEFERRABLE INITIALLY DEFERRED,
    FOREIGN KEY (updated_by) REFERENCES users (id) DEFERRABLE INITIALLY DEFERRED
);

CREATE TABLE academic_terms (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    name        VARCHAR(100),
    academic_year VARCHAR(9),
    semester    VARCHAR(20),
    start_date  DATE,
    end_date    DATE,
    is_active   BOOLEAN,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE student_profiles (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    user_id     UUID,
    nim         VARCHAR(30),
    faculty     VARCHAR(150),
    major       VARCHAR(150),
    entry_year  SMALLINT,
    current_semester SMALLINT,
    total_credits_target SMALLINT,
    status      VARCHAR(30),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE lecturer_profiles (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    user_id     UUID,
    nidn        VARCHAR(30),
    faculty     VARCHAR(150),
    major       VARCHAR(150),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE courses (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    code        VARCHAR(30),
    name        VARCHAR(200),
    credits     SMALLINT,
    recommended_semester SMALLINT,
    lecturer_id UUID,
    status      VARCHAR(30),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (lecturer_id) REFERENCES lecturer_profiles (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE classes (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    term_id     UUID,
    course_id   UUID,
    lecturer_id UUID,
    code        VARCHAR(40),
    capacity    SMALLINT,
    status      VARCHAR(30),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (term_id) REFERENCES academic_terms (id),
    FOREIGN KEY (course_id) REFERENCES courses (id),
    FOREIGN KEY (lecturer_id) REFERENCES lecturer_profiles (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE class_schedules (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    class_id    UUID,
    day_of_week SMALLINT,
    start_time  TIME,
    end_time    TIME,
    room        VARCHAR(100),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (class_id) REFERENCES classes (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE enrollments (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    student_id  UUID,
    class_id    UUID,
    status      VARCHAR(30),
    enrolled_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (student_id) REFERENCES student_profiles (id),
    FOREIGN KEY (class_id) REFERENCES classes (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE grades (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    enrollment_id UUID,
    assignment_score NUMERIC(5,2),
    midterm_score NUMERIC(5,2),
    final_exam_score NUMERIC(5,2),
    final_score NUMERIC(5,2),
    letter_grade VARCHAR(2),
    status      VARCHAR(30),
    submitted_at TIMESTAMPTZ,
    verified_at TIMESTAMPTZ,
    published_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (enrollment_id) REFERENCES enrollments (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE grade_status_history (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    grade_id    UUID,
    from_status VARCHAR(30),
    to_status   VARCHAR(30),
    note        TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (grade_id) REFERENCES grades (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE attendance_meetings (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    class_id    UUID,
    meeting_date DATE,
    topic       VARCHAR(255),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (class_id) REFERENCES classes (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE attendance_records (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    meeting_id  UUID,
    enrollment_id UUID,
    status      VARCHAR(30),
    recorded_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (meeting_id) REFERENCES attendance_meetings (id),
    FOREIGN KEY (enrollment_id) REFERENCES enrollments (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE academic_events (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    term_id     UUID,
    title       VARCHAR(255),
    description TEXT,
    starts_at   TIMESTAMPTZ,
    ends_at     TIMESTAMPTZ,
    location    VARCHAR(150),
    audience    VARCHAR(30),
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (term_id) REFERENCES academic_terms (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE activity_log (
    id              UUID        DEFAULT gen_random_uuid()       NOT NULL,
    user_id         UUID                                  NOT NULL,
    activity_type   BIGINT                                NOT NULL,
    activity        VARCHAR(255)                          NOT NULL,
    activity_string TEXT                                  NOT NULL,
    role            VARCHAR(30)                           NOT NULL,
    created_at      TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by      UUID                                  NOT NULL,
    updated_at      TIMESTAMPTZ,
    updated_by      UUID,
    email           VARCHAR(255),
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE auth_sessions (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    user_id     UUID                                  NOT NULL,
    token_hash  VARCHAR(64)                           NOT NULL,
    expires_at  TIMESTAMPTZ                           NOT NULL,
    revoked_at  TIMESTAMPTZ,
    last_used_at TIMESTAMPTZ,
    ip_address  VARCHAR(64),
    user_agent  TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

CREATE TABLE password_reset_tokens (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    user_id     UUID                                  NOT NULL,
    token_hash  VARCHAR(64)                           NOT NULL,
    expires_at  TIMESTAMPTZ                           NOT NULL,
    used_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

BEGIN;

INSERT INTO users (id, name, email, password_hash, role, identifier, phone, is_active, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000001', 'Administrator NilaiKu', 'admin@nilaiku.test', crypt('Password123!', gen_salt('bf', 12)), 'admin', 'ADMIN-001', '081200000001', TRUE, '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000002', 'Dr. Anggit Prabowo', 'dosen@nilaiku.test', crypt('Password123!', gen_salt('bf', 12)), 'lecturer', '0512048601', '081200000002', TRUE, '00000000-0000-4000-8000-000000000002'),
    ('00000000-0000-4000-8000-000000000003', 'Budi Santoso', 'budi.santoso@students.kampus.ac.id', crypt('Password123!', gen_salt('bf', 12)), 'student', '23.11.5231', '081200000003', TRUE, '00000000-0000-4000-8000-000000000003');

INSERT INTO lecturer_profiles (id, user_id, nidn, faculty, major, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000012', '00000000-0000-4000-8000-000000000002', '0512048601', 'Fakultas Ilmu Komputer', 'Informatika', '00000000-0000-4000-8000-000000000001');

INSERT INTO student_profiles (id, user_id, nim, faculty, major, entry_year, current_semester, total_credits_target, status, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000011', '00000000-0000-4000-8000-000000000003', '23.11.5231', 'Fakultas Ilmu Komputer', 'Informatika', 2023, 6, 144, 'active', '00000000-0000-4000-8000-000000000001');

INSERT INTO academic_terms (id, name, academic_year, semester, start_date, end_date, is_active, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000021', '2025/2026 Genap', '2025/2026', 'Genap', '2026-01-20', '2026-06-30', TRUE, '00000000-0000-4000-8000-000000000001');

INSERT INTO courses (id, code, name, credits, recommended_semester, lecturer_id, status, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000031', 'IF3201', 'Pemrograman Web Lanjut', 4, 6, '00000000-0000-4000-8000-000000000012', 'Aktif', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000032', 'IF3202', 'Kecerdasan Buatan', 3, 6, '00000000-0000-4000-8000-000000000012', 'Aktif', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000033', 'IF3203', 'Metodologi Penelitian', 2, 6, '00000000-0000-4000-8000-000000000012', 'Aktif', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000034', 'IF2202', 'Rekayasa Perangkat Lunak', 3, 4, '00000000-0000-4000-8000-000000000012', 'Aktif', '00000000-0000-4000-8000-000000000001');

INSERT INTO classes (id, term_id, course_id, lecturer_id, code, capacity, status, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000041', '00000000-0000-4000-8000-000000000021', '00000000-0000-4000-8000-000000000031', '00000000-0000-4000-8000-000000000012', 'IF3201-A', 40, 'Aktif', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000042', '00000000-0000-4000-8000-000000000021', '00000000-0000-4000-8000-000000000032', '00000000-0000-4000-8000-000000000012', 'IF3202-A', 40, 'Aktif', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000043', '00000000-0000-4000-8000-000000000021', '00000000-0000-4000-8000-000000000033', '00000000-0000-4000-8000-000000000012', 'IF3203-A', 30, 'Aktif', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000044', '00000000-0000-4000-8000-000000000021', '00000000-0000-4000-8000-000000000034', '00000000-0000-4000-8000-000000000012', 'IF2202-A', 35, 'Aktif', '00000000-0000-4000-8000-000000000001');

INSERT INTO class_schedules (id, class_id, day_of_week, start_time, end_time, room, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000051', '00000000-0000-4000-8000-000000000041', 1, '09:00', '10:40', 'Lab 5.3', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000052', '00000000-0000-4000-8000-000000000042', 2, '10:30', '12:10', 'R. 1.4.2', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000053', '00000000-0000-4000-8000-000000000043', 4, '13:00', '14:40', 'R. 2.1.4', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000054', '00000000-0000-4000-8000-000000000044', 5, '08:00', '09:40', 'R. 3.2.1', '00000000-0000-4000-8000-000000000001');

INSERT INTO enrollments (id, student_id, class_id, status, enrolled_at, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000061', '00000000-0000-4000-8000-000000000011', '00000000-0000-4000-8000-000000000041', 'Terdaftar', '2026-07-01 03:00:00+00', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000062', '00000000-0000-4000-8000-000000000011', '00000000-0000-4000-8000-000000000042', 'Terdaftar', '2026-07-01 03:00:00+00', '00000000-0000-4000-8000-000000000001');

INSERT INTO grades (id, enrollment_id, assignment_score, midterm_score, final_exam_score, final_score, letter_grade, status, submitted_at, verified_at, published_at, created_by, updated_by)
VALUES
    ('00000000-0000-4000-8000-000000000071', '00000000-0000-4000-8000-000000000061', 88, 82, 90, 87.00, 'A', 'published', '2026-07-10 03:00:00+00', '2026-07-11 03:00:00+00', '2026-07-12 03:00:00+00', '00000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000072', '00000000-0000-4000-8000-000000000062', 75, 70, 80, 75.50, 'B', 'draft', NULL, NULL, NULL, '00000000-0000-4000-8000-000000000002', NULL);

INSERT INTO grade_status_history (id, grade_id, from_status, to_status, note, created_at, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000081', '00000000-0000-4000-8000-000000000071', NULL, 'draft', NULL, '2026-07-09 03:00:00+00', '00000000-0000-4000-8000-000000000002'),
    ('00000000-0000-4000-8000-000000000082', '00000000-0000-4000-8000-000000000071', 'draft', 'submitted', NULL, '2026-07-10 03:00:00+00', '00000000-0000-4000-8000-000000000002'),
    ('00000000-0000-4000-8000-000000000083', '00000000-0000-4000-8000-000000000071', 'submitted', 'verified', NULL, '2026-07-11 03:00:00+00', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000084', '00000000-0000-4000-8000-000000000071', 'verified', 'published', NULL, '2026-07-12 03:00:00+00', '00000000-0000-4000-8000-000000000001'),
    ('00000000-0000-4000-8000-000000000085', '00000000-0000-4000-8000-000000000072', NULL, 'draft', NULL, '2026-07-09 03:00:00+00', '00000000-0000-4000-8000-000000000002');

INSERT INTO attendance_meetings (id, class_id, meeting_date, topic, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000091', '00000000-0000-4000-8000-000000000041', '2026-04-13', 'Pengenalan materi dan kontrak kuliah', '00000000-0000-4000-8000-000000000002');

INSERT INTO attendance_records (id, meeting_id, enrollment_id, status, recorded_at, created_by)
VALUES
    ('00000000-0000-4000-8000-000000000101', '00000000-0000-4000-8000-000000000091', '00000000-0000-4000-8000-000000000061', 'Hadir', '2026-04-13 03:00:00+00', '00000000-0000-4000-8000-000000000002');

COMMIT;
